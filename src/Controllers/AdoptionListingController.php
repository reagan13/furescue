<?php

namespace App\Controllers;

use App\Http\DomainException;
use App\Http\Request;
use App\Http\Response;
use App\Services\AdoptionEligibilityService;
use App\Services\ListingPhotoUpload;
use App\Services\ResidentListingService;
use PDO;

class AdoptionListingController extends AbstractController
{
    private AdoptionEligibilityService $eligibility;
    private ResidentListingService $listings;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->eligibility = new AdoptionEligibilityService($pdo);
        $this->listings = new ResidentListingService($pdo);
    }

    public function create(Request $req): void
    {
        $animalId = trim((string) ($req->body['animal_id'] ?? ''));
        if ($animalId !== '') {
            $this->createFromExisting($req, $animalId);
            return;
        }
        $this->createFromProfile($req);
    }

    public function index(Request $req): void
    {
        if ($this->rejectBadQuery($req, [
            'status' => ['pending_review', 'approved', 'rejected'],
        ])) {
            return;
        }
        $where = [];
        $params = [];
        if (!in_array('adoptions.read', $req->permissions, true)) {
            $where[] = 'l.posted_by = ?';
            $params[] = $req->user['id'];
        }
        if (!empty($req->query['status'])) {
            $where[] = 'l.status = ?';
            $params[] = $req->query['status'];
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $page = $this->page($req);
        $perPage = $this->perPage($req);

        $totalStmt = $this->pdo->prepare("SELECT COUNT(*) FROM adoption_listings l {$whereSql}");
        $totalStmt->execute($params);
        $total = (int) $totalStmt->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT l.*, a.name AS animal_name, a.species, u.full_name AS poster_name
             FROM adoption_listings l
             LEFT JOIN animals a ON a.id = l.animal_id
             LEFT JOIN users u ON u.id = l.posted_by
             {$whereSql}
             ORDER BY l.created_at DESC
             LIMIT " . (int) $perPage . " OFFSET " . (int) (($page - 1) * $perPage)
        );
        $stmt->execute($params);
        Response::paginated($stmt->fetchAll(\PDO::FETCH_ASSOC), $this->meta($page, $perPage, $total));
    }

    public function show(Request $req): void
    {
        $listing = $this->repo('adoption_listings')->find($req->params['id']);
        if (!$listing) {
            Response::error('NOT_FOUND', 'Listing not found', 404);
            return;
        }
        $canReadAll = in_array('adoptions.read', $req->permissions, true);
        $isOwner = ($req->user['id'] ?? null) === ($listing['posted_by'] ?? null);
        if (!$canReadAll && !$isOwner) {
            Response::error('FORBIDDEN', 'Not your listing', 403);
            return;
        }
        Response::success(['listing' => $listing]);
    }

    public function review(Request $req, string $decision): void
    {
        $v = new \App\Validation\Validator($req->body);
        if ($decision === 'rejected') {
            $v->required('review_notes')->string('review_notes', 500);
        }
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $repo = $this->repo('adoption_listings');
        $listing = $repo->find($req->params['id']);
        if (!$listing) {
            Response::error('NOT_FOUND', 'Listing not found', 404);
            return;
        }
        if ($decision === 'approved' && !$this->eligibility->isEligible($listing['animal_id'])) {
            $this->rejectNotHealthReady();
            return;
        }
        $repo->update($listing['id'], [
            'status' => $decision === 'approved' ? 'approved' : 'rejected',
            'reviewed_by' => $req->user['id'],
            'review_notes' => $req->body['review_notes'] ?? null,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        if ($decision === 'approved') {
            $this->repo('animals')->update($listing['animal_id'], ['adoption_status' => 'available']);
        }

        $notif = new \App\Services\NotificationService($this->pdo);
        $notif->notify($listing['posted_by'], 'listing_' . $decision, 'Your adoption listing was ' . $decision . '.', 'adoption_listing', $listing['id']);
        Response::success(['listing' => $repo->find($listing['id'])]);
    }

    private function createFromExisting(Request $req, string $animalId): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('animal_id')->uuid('animal_id');
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        try {
            $created = $this->listings->createFromExisting((string) $req->user['id'], $animalId);
        } catch (DomainException $e) {
            Response::error($e->errorCode(), $e->getMessage(), $e->httpStatus());
            return;
        } catch (\RuntimeException $e) {
            Response::serverError($e);
            return;
        }
        $id = (string) ($created['listing']['id'] ?? '');
        if ($id !== '') {
            $this->notifyRole('admin', 'listing_submitted', 'A new adoption listing needs review.', 'adoption_listing', $id);
        }
        Response::success($created, 201);
    }

    private function createFromProfile(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('name')->string('name', 100)
            ->required('species')->in('species', ['dog', 'cat'])
            ->required('sex')->in('sex', ['male', 'female'])
            ->required('description')->string('description', 2000)
            ->optional('breed_type')->in('breed_type', ['aspin', 'puspin'])
            ->optional('age_estimate')->string('age_estimate', 50)
            ->optional('color_markings')->string('color_markings', 500)
            ->optional('photo_urls')->stringOrStringList('photo_urls', 4000);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $files = $req->files('photos');
        $urls = ListingPhotoUpload::normalizeUrlList($req->body['photo_urls'] ?? null);
        if ($files === [] && $urls === []) {
            Response::error('VALIDATION_ERROR', 'At least one profile photo is required.', 400);
            return;
        }
        try {
            $created = $this->listings->createFromProfile((string) $req->user['id'], $req->body, $files);
        } catch (\InvalidArgumentException $e) {
            $detail = $e->getMessage();
            Response::error('VALIDATION_ERROR', $detail, 400);
            return;
        } catch (DomainException $e) {
            Response::error($e->errorCode(), $e->getMessage(), $e->httpStatus());
            return;
        } catch (\RuntimeException $e) {
            Response::serverError($e);
            return;
        }
        $id = (string) ($created['listing']['id'] ?? '');
        if ($id !== '') {
            $this->notifyRole('admin', 'listing_submitted', 'A new adoption listing needs review.', 'adoption_listing', $id);
        }
        Response::success($created, 201);
    }

    private function rejectNotHealthReady(): void
    {
        Response::error(
            AdoptionEligibilityService::ERROR_CODE,
            AdoptionEligibilityService::ERROR_MESSAGE,
            409
        );
    }
}
