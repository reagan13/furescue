<?php

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\AnimalRepository;
use App\Services\AdoptionEligibilityService;
use PDO;

class AnimalController extends AbstractController
{
    private AnimalRepository $animals;
    private AdoptionEligibilityService $eligibility;
    private ?bool $hasCaseIdColumn = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->animals = new AnimalRepository($pdo);
        $this->eligibility = new AdoptionEligibilityService($pdo);
    }

    public function index(Request $req): void
    {
        if ($this->rejectBadQuery($req, [
            'species' => ['dog', 'cat'],
            'breed_type' => ['aspin', 'puspin'],
            'sex' => ['male', 'female'],
            'adoption_status' => ['not_listed', 'available', 'pending', 'adopted'],
            'source' => ['rescued_case', 'resident_listing'],
        ])) {
            return;
        }
        if (!empty($req->query['case_id'])) {
            $idV = new \App\Validation\Validator($req->query);
            $idV->optional('case_id')->uuid('case_id');
            if (!$idV->passes()) {
                Response::error('VALIDATION_ERROR', $idV->firstError(), 400);
                return;
            }
        }
        $where = "WHERE deleted_at IS NULL";
        $params = [];
        foreach (['species', 'breed_type', 'sex', 'adoption_status', 'source', 'case_id'] as $f) {
            if (!empty($req->query[$f])) {
                $where .= " AND {$f} = ?";
                $params[] = $req->query[$f];
            }
        }
        if (!empty($req->query['q'])) {
            $where .= " AND name LIKE ?";
            $params[] = '%' . trim((string) $req->query['q']) . '%';
        }
        if (($req->query['mine'] ?? '') === '1') {
            $where .= " AND created_by = ?";
            $params[] = $req->user['id'];
        }
        $page = $this->page($req);
        $perPage = $this->perPage($req);
        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM animals {$where}");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            "SELECT id,name,species,breed_type,sex,age_estimate,birth_date,color_markings,photo_urls,model_3d_url,photo_360_set,adoption_status,source,case_id,created_at
             FROM animals {$where} ORDER BY created_at DESC LIMIT " . (int) $perPage . " OFFSET " . (int) $offset
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        Response::paginated($items, $this->meta($page, $perPage, $total));
    }

    public function show(Request $req): void
    {
        $animal = $this->animals->findActive($req->params['id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        $data = $animal->toArray();
        $data['medical'] = $this->repo('animal_medical_records')->findBy('animal_id', $animal->id());
        $data['field_status'] = $this->repo('animal_field_status')->all(['animal_id' => $animal->id()], 'logged_at', 'DESC');
        Response::success(['animal' => $data]);
    }

    public function create(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('species')->in('species', ['dog', 'cat'])
            ->required('breed_type')->in('breed_type', ['aspin', 'puspin'])
            ->required('sex')->in('sex', ['male', 'female'])
            ->optional('name')->string('name', 100)
            ->optional('adoption_status')->in('adoption_status', ['not_listed', 'available', 'pending', 'adopted'])
            ->optional('source')->in('source', ['rescued_case', 'resident_listing'])
            ->optional('description')->string('description', 2000)
            ->optional('case_id')->uuid('case_id');
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }

        $fromCase = $this->optionalCaseId($req->body);
        $id = Database::uuidV4();
        $adoptionStatus = $req->body['adoption_status'] ?? 'not_listed';
        if ($fromCase !== null && $adoptionStatus !== 'available') {
            $adoptionStatus = 'not_listed';
        }

        if ($adoptionStatus === 'available' && !$this->eligibility->isEligible($id)) {
            $this->rejectNotHealthReady();
            return;
        }

        $data = [
            'id' => $id,
            'species' => $req->body['species'],
            'breed_type' => $req->body['breed_type'],
            'sex' => $req->body['sex'],
            'name' => $req->body['name'] ?? null,
            'age_estimate' => $req->body['age_estimate'] ?? null,
            'birth_date' => $req->body['birth_date'] ?? null,
            'color_markings' => $req->body['color_markings'] ?? null,
            'description' => $req->body['description'] ?? null,
            'photo_urls' => isset($req->body['photo_urls'])
                ? json_encode($req->body['photo_urls'])
                : ($fromCase !== null ? $this->reportPhotoUrlsForCase($fromCase) : null),
            'model_3d_url' => $req->body['model_3d_url'] ?? null,
            'photo_360_set' => isset($req->body['photo_360_set']) ? json_encode($req->body['photo_360_set']) : null,
            'adoption_status' => $adoptionStatus,
            'source' => $fromCase !== null ? 'rescued_case' : ($req->body['source'] ?? 'rescued_case'),
            'created_by' => $req->user['id'],
        ];
        if ($fromCase !== null && $this->hasCaseIdColumn()) {
            $data['case_id'] = $fromCase;
        }
        $this->animals->create($data);
        if ($fromCase !== null) {
            $this->attachCaseId($id, $fromCase);
        }
        $created = $this->animals->find($id);
        Response::success(['animal' => $created->toArray()], 201);
    }

    public function update(Request $req): void
    {
        $animal = $this->animals->findActive($req->params['id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        $allowed = ['name', 'age_estimate', 'birth_date', 'color_markings', 'description', 'photo_urls', 'model_3d_url', 'photo_360_set', 'adoption_status', 'case_id', 'species', 'sex'];
        $data = [];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $req->body)) {
                $val = $req->body[$f];
                if (in_array($f, ['photo_urls', 'photo_360_set'], true) && is_array($val)) {
                    $val = json_encode($val);
                }
                $data[$f] = $val;
            }
        }
        $v = new \App\Validation\Validator($req->body);
        if (array_key_exists('name', $req->body)) {
            $v->optional('name')->string('name', 100);
        }
        if (array_key_exists('description', $req->body)) {
            $v->optional('description')->string('description', 2000);
        }
        if (array_key_exists('age_estimate', $req->body)) {
            $v->optional('age_estimate')->string('age_estimate', 50);
        }
        if (array_key_exists('birth_date', $req->body)) {
            $v->optional('birth_date')->string('birth_date', 32);
        }
        if (array_key_exists('color_markings', $req->body)) {
            $v->optional('color_markings')->string('color_markings', 500);
        }
        if (array_key_exists('adoption_status', $req->body)) {
            $v->optional('adoption_status')->in('adoption_status', ['not_listed', 'available', 'pending', 'adopted']);
        }
        if (array_key_exists('species', $req->body)) {
            $v->optional('species')->in('species', ['dog', 'cat']);
        }
        if (array_key_exists('sex', $req->body)) {
            $v->optional('sex')->in('sex', ['male', 'female']);
        }
        if (array_key_exists('case_id', $req->body) && $req->body['case_id'] !== null && $req->body['case_id'] !== '') {
            $v->optional('case_id')->uuid('case_id');
        }
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $caseId = array_key_exists('case_id', $data) ? $data['case_id'] : null;
        unset($data['case_id']);
        if (empty($data) && ($caseId === null || $caseId === '')) {
            Response::error('VALIDATION_ERROR', 'No updatable fields', 400);
            return;
        }
        if (($data['adoption_status'] ?? null) === 'available' && !$this->eligibility->isEligible($animal->id())) {
            $this->rejectNotHealthReady();
            return;
        }
        if (!empty($data)) {
            $this->animals->update($animal->id(), $data);
        }
        if ($caseId !== null && $caseId !== '') {
            $this->attachCaseId($animal->id(), (string) $caseId);
        }
        $updated = $this->animals->find($animal->id());
        Response::success(['animal' => $updated->toArray()]);
    }

    public function delete(Request $req): void
    {
        $animal = $this->animals->findActive($req->params['id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        $this->animals->softDelete($animal->id());
        Response::success(['animal' => $animal->toArray()]);
    }

    public function logFieldStatus(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('rescue_status')->in('rescue_status', ['rescued', 'not_rescued'])
            ->required('health_status')->in('health_status', ['healthy', 'not_healthy'])
            ->optional('case_id')->uuid('case_id');
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $animal = $this->animals->findActive($req->params['id']);
        if (!$animal) {
            Response::error('NOT_FOUND', 'Animal not found', 404);
            return;
        }
        $id = $this->repo('animal_field_status')->create([
            'id' => Database::uuidV4(),
            'animal_id' => $animal->id(),
            'case_id' => $req->body['case_id'] ?? null,
            'rescue_status' => $req->body['rescue_status'],
            'health_status' => $req->body['health_status'],
            'logged_by' => $req->user['id'],
        ]);
        Response::success(['field_status' => $this->repo('animal_field_status')->find($id)], 201);
    }

    public function fieldStatusHistory(Request $req): void
    {
        $rows = $this->repo('animal_field_status')->all(['animal_id' => $req->params['id']], 'logged_at', 'DESC');
        Response::success(['field_status' => $rows]);
    }

    private function rejectNotHealthReady(): void
    {
        Response::error(
            AdoptionEligibilityService::ERROR_CODE,
            AdoptionEligibilityService::ERROR_MESSAGE,
            409
        );
    }

    private function reportPhotoUrlsForCase(string $caseId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.photo_urls FROM cases c INNER JOIN reports r ON r.id = c.report_id WHERE c.id = ? LIMIT 1'
        );
        $stmt->execute([$caseId]);
        $raw = $stmt->fetchColumn();
        if (!is_string($raw)) {
            return null;
        }
        $trim = trim($raw);
        if ($trim === '' || $trim === 'null' || $trim === '[]') {
            return null;
        }
        return $raw;
    }

    private function optionalCaseId(array $body): ?string
    {
        if (!array_key_exists('case_id', $body) || $body['case_id'] === null || $body['case_id'] === '') {
            return null;
        }
        return (string) $body['case_id'];
    }

    private function hasCaseIdColumn(): bool
    {
        if ($this->hasCaseIdColumn !== null) {
            return $this->hasCaseIdColumn;
        }
        try {
            $this->pdo->query('SELECT case_id FROM animals LIMIT 0');
            $this->hasCaseIdColumn = true;
        } catch (\PDOException $e) {
            $this->hasCaseIdColumn = false;
        }
        return $this->hasCaseIdColumn;
    }

    private function attachCaseId(string $animalId, string $caseId): void
    {
        if (!$this->hasCaseIdColumn()) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('UPDATE animals SET case_id = ? WHERE id = ?');
            $stmt->execute([$caseId, $animalId]);
        } catch (\PDOException $e) {
            // Unique/FK failures are owned by Workstream E's migration; do not block create.
        }
    }
}
