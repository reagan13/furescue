<?php

namespace App\Controllers;

use App\Database;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\CaseRepository;
use App\Repositories\ReportRepository;
use App\Services\DedupService;
use App\Services\GeoService;
use App\Services\NotificationService;
use PDO;

class ReportController extends AbstractController
{
    private const MEDIA_EXT = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];
    private const MAX_MEDIA_BYTES = 10 * 1024 * 1024;
    private const MAX_MEDIA_FILES = 8;

    private DedupService $dedup;
    private GeoService $geo;
    private ReportRepository $reports;
    private CaseRepository $cases;

    public function __construct(PDO $pdo, DedupService $dedup, GeoService $geo)
    {
        parent::__construct($pdo);
        $this->dedup = $dedup;
        $this->geo = $geo;
        $this->reports = new ReportRepository($pdo);
        $this->cases = new CaseRepository($pdo);
    }

    public function create(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('animal_description')->string('animal_description', 2000)
            ->required('latitude')->latitude('latitude')
            ->required('longitude')->longitude('longitude')
            ->optional('address_text')->string('address_text', 500)
            ->optional('photo_urls')->stringOrStringList('photo_urls', 4000)
            ->optional('species')->in('species', ['dog', 'cat']);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }

        $lat = (float) $req->body['latitude'];
        $lng = (float) $req->body['longitude'];

        if (!$this->geo->inMatiBounds($lat, $lng)) {
            Response::error('OUT_OF_BOUNDS', 'Location is outside Mati City', 422);
            return;
        }

        $photoUrls = is_array($req->body['photo_urls'] ?? null)
            ? json_encode($req->body['photo_urls'])
            : ($req->body['photo_urls'] ?? null);

        $contentHash = DedupService::contentHash($req->body['animal_description'], $lat, $lng);
        $duplicateId = $this->dedup->findDuplicate($contentHash, $lat, $lng);

        $validationStatus = $duplicateId ? 'flagged_duplicate' : 'validated';

        $row = [
            'id' => Database::uuidV4(),
            'resident_id' => $req->user['id'],
            'animal_description' => $req->body['animal_description'],
            'photo_urls' => $photoUrls,
            'latitude' => $lat,
            'longitude' => $lng,
            'address_text' => $req->body['address_text'] ?? null,
            'content_hash' => $contentHash,
            'duplicate_of_report_id' => $duplicateId,
            'validation_status' => $validationStatus,
            'status' => 'pending_verification',
        ];
        if (!empty($req->body['species'])) {
            $row['species'] = $req->body['species'];
        }
        $id = $this->reports->create($row);

        $this->notifyRole('admin', 'report_submitted', 'A new animal report was submitted.', 'report', $id);

        $report = $this->reports->find($id);
        Response::success(['report' => $report->toArray()], 201);
    }

    public function index(Request $req): void
    {
        if ($this->rejectBadQuery($req, [
            'status' => ['pending_verification', 'verified', 'dismissed'],
            'validation_status' => ['pending', 'flagged_duplicate', 'validated'],
        ])) {
            return;
        }
        $filters = [];
        if (!in_array('reports.read', $req->permissions, true)) {
            $filters['resident_id'] = $req->user['id'];
        }
        foreach (['status','validation_status'] as $f) {
            if (!empty($req->query[$f])) {
                $filters[$f] = $req->query[$f];
            }
        }
        $result = $this->reports->paginate($this->page($req), $this->perPage($req), $filters);
        Response::paginated(
            array_map(fn($r) => $r->toArray(), $result['items']),
            $this->meta($result['page'], $result['per_page'], $result['total'])
        );
    }

    public function mine(Request $req): void
    {
        if ($this->rejectBadQuery($req)) {
            return;
        }
        $result = $this->reports->paginate($this->page($req), $this->perPage($req), ['resident_id' => $req->user['id']]);
        Response::paginated(
            array_map(fn($r) => $r->toArray(), $result['items']),
            $this->meta($result['page'], $result['per_page'], $result['total'])
        );
    }

    public function show(Request $req): void
    {
        $report = $this->reports->find($req->params['id']);
        if (!$report) {
            Response::error('NOT_FOUND', 'Report not found', 404);
            return;
        }
        if (!$this->canReadReport($req, $report)) {
            Response::error('FORBIDDEN', 'Not your report', 403);
            return;
        }
        Response::success(['report' => $report->toArray()]);
    }

    public function verify(Request $req): void
    {
        $this->setReportStatus($req, 'verified', true);
    }

    public function dismiss(Request $req): void
    {
        $v = new \App\Validation\Validator($req->body);
        $v->required('dismiss_reason')->string('dismiss_reason', 500);
        if (!$v->passes()) {
            Response::error('VALIDATION_ERROR', $v->firstError(), 400);
            return;
        }
        $report = $this->reports->find($req->params['id']);
        if (!$report) {
            Response::error('NOT_FOUND', 'Report not found', 404);
            return;
        }
        $this->reports->update($report->id(), [
            'status' => 'dismissed',
            'dismiss_reason' => $req->body['dismiss_reason'],
            'verified_by' => $req->user['id'],
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $notif = new NotificationService($this->pdo);
        $notif->notify($report->residentId(), 'report_dismissed', 'Your report was dismissed.', 'report', $report->id());
        Response::success(['report' => $this->reports->find($report->id())->toArray()]);
    }

    private function setReportStatus(Request $req, string $status, bool $createCase): void
    {
        $report = $this->reports->find($req->params['id']);
        if (!$report) {
            Response::error('NOT_FOUND', 'Report not found', 404);
            return;
        }
        $this->reports->update($report->id(), [
            'status' => $status,
            'validation_status' => 'validated',
            'verified_by' => $req->user['id'],
            'verified_at' => date('Y-m-d H:i:s'),
        ]);

        $caseId = null;
        if ($createCase) {
            $existing = $this->cases->findByReportId($report->id());
            if (!$existing) {
                $caseId = $this->cases->create([
                    'id' => Database::uuidV4(),
                    'report_id' => $report->id(),
                    'status' => 'open',
                ]);
            } else {
                $caseId = $existing->id();
            }
        }

        $notif = new NotificationService($this->pdo);
        $notif->notify($report->residentId(), 'report_verified', 'Your report was verified.', 'report', $report->id());
        Response::success(['report' => $this->reports->find($report->id())->toArray(), 'case_id' => $caseId]);
    }

    public function uploadMedia(Request $req): void
    {
        $report = $this->reports->find($req->params['id']);
        if (!$report) {
            Response::error('NOT_FOUND', 'Report not found', 404);
            return;
        }
        if (!$this->canReadReport($req, $report)) {
            Response::error('FORBIDDEN', 'Not your report', 403);
            return;
        }

        $files = $this->normalizeUploadedFiles($_FILES['photos'] ?? null);
        if (!$files) {
            Response::error('VALIDATION_ERROR', 'At least one photo or video file is required.', 400);
            return;
        }
        if (count($files) > self::MAX_MEDIA_FILES) {
            Response::error('VALIDATION_ERROR', 'Up to ' . self::MAX_MEDIA_FILES . ' files can be attached at once.', 400);
            return;
        }

        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                Response::error('VALIDATION_ERROR', "Upload failed for '{$file['name']}'.", 400);
                return;
            }
            if (!is_uploaded_file($file['tmp_name'])) {
                Response::error('VALIDATION_ERROR', 'Invalid upload.', 400);
                return;
            }
            if ($file['size'] > self::MAX_MEDIA_BYTES) {
                Response::error('VALIDATION_ERROR', "'{$file['name']}' exceeds the 10 MB size limit.", 400);
                return;
            }
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!array_key_exists($ext, self::MEDIA_EXT)) {
                Response::error('VALIDATION_ERROR', 'Unsupported file type. Allowed: JPG, PNG, GIF, WEBP, MP4, WEBM.', 400);
                return;
            }
            $mime = \App\Services\AnimalAssetUpload::detectMime($file);
            if (in_array($mime, [
                'application/x-msdownload',
                'application/x-dosexec',
                'application/x-executable',
                'application/x-msdos-program',
                'application/vnd.microsoft.portable-executable',
            ], true)) {
                Response::error('VALIDATION_ERROR', 'Unsupported file type. Allowed: JPG, PNG, GIF, WEBP, MP4, WEBM.', 400);
                return;
            }
        }

        $dir = dirname(__DIR__, 2) . '/public/uploads/reports/' . date('Y') . '/' . date('m');
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            Response::error('SERVER_ERROR', 'Could not create the upload directory.', 500);
            return;
        }

        $urls = [];
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $stored = Database::uuidV4() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $stored)) {
                Response::error('SERVER_ERROR', "Could not save '{$file['name']}'.", 500);
                return;
            }
            $urls[] = '/uploads/reports/' . date('Y') . '/' . date('m') . '/' . $stored;
        }

        $existing = $report->photoUrls();
        $existingUrls = [];
        if (is_string($existing) && trim($existing) !== '') {
            $decoded = json_decode($existing, true);
            if (is_array($decoded)) {
                $existingUrls = array_values(array_filter($decoded, static fn($u) => is_string($u) && $u !== ''));
            }
        }
        $merged = array_values(array_unique(array_merge($existingUrls, $urls)));
        $encoded = json_encode($merged, JSON_UNESCAPED_SLASHES);
        if ($encoded === false || strlen($encoded) > 8000) {
            foreach ($urls as $url) {
                @unlink(dirname(__DIR__, 2) . '/public' . $url);
            }
            Response::error('VALIDATION_ERROR', 'Too many files attached to this report.', 400);
            return;
        }

        $this->reports->update($report->id(), ['photo_urls' => $encoded]);
        Response::success(['photo_urls' => $merged, 'report' => $this->reports->find($report->id())->toArray()], 201);
    }

    private function normalizeUploadedFiles(mixed $entry): array
    {
        if (!$entry || !is_array($entry) || !isset($entry['name'])) {
            return [];
        }
        if (!is_array($entry['name'])) {
            return [$entry];
        }
        $out = [];
        foreach (array_keys($entry['name']) as $i) {
            if (($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => (string) ($entry['name'][$i] ?? ''),
                'type' => (string) ($entry['type'][$i] ?? ''),
                'tmp_name' => (string) ($entry['tmp_name'][$i] ?? ''),
                'error' => (int) ($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($entry['size'][$i] ?? 0),
            ];
        }
        return $out;
    }

    public function withdraw(Request $req): void
    {
        $report = $this->reports->find($req->params['id']);
        if (!$report) {
            Response::error('NOT_FOUND', 'Report not found', 404);
            return;
        }
        if ($report->residentId() !== ($req->user['id'] ?? null)) {
            Response::error('FORBIDDEN', 'Not your report', 403);
            return;
        }
        if ($report->status() !== 'pending_verification') {
            Response::error('INVALID_STATE', 'Only a report that is still waiting for verification can be withdrawn.', 409);
            return;
        }
        if ($this->cases->findByReportId($report->id())) {
            Response::error('INVALID_STATE', 'This report already has a case.', 409);
            return;
        }
        $this->pdo->prepare('DELETE FROM notifications WHERE related_type = ? AND related_id = ?')
            ->execute(['report', $report->id()]);
        $this->pdo->prepare('DELETE FROM reports WHERE id = ?')->execute([$report->id()]);
        Response::success(['withdrawn' => true]);
    }

    private function canReadReport(Request $req, \App\Entity\Report $report): bool
    {
        if (in_array('reports.read', $req->permissions, true)) {
            return true;
        }
        if ($report->residentId() === ($req->user['id'] ?? null)) {
            return true;
        }
        if (($req->user['role'] ?? '') !== 'rescuer' || $report->id() === null) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM cases WHERE report_id = ? AND assigned_rescuer_id = ? LIMIT 1'
        );
        $stmt->execute([$report->id(), $req->user['id']]);

        return (bool) $stmt->fetchColumn();
    }

    public function heatmap(Request $req): void
    {
        Response::success(['points' => $this->geo->heatmapPoints($req->query['status'] ?? null)]);
    }
}
