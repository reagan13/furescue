<?php

namespace App\Services;

use App\Database;
use App\Entity\RescueCase;
use App\Http\DomainException;
use App\Http\Response;
use App\Repositories\CaseRepository;
use App\Repositories\ReportRepository;
use PDO;

class CaseWorkflowException extends DomainException
{
}

class CaseWorkflowService
{
    private CaseRepository $cases;
    private ReportRepository $reports;
    private NotificationService $notifications;
    private CaseProofUpload $uploads;

    public function __construct(private PDO $pdo)
    {
        $this->cases = new CaseRepository($pdo);
        $this->reports = new ReportRepository($pdo);
        $this->notifications = new NotificationService($pdo);
        $this->uploads = new CaseProofUpload();
    }

    public function accept(string $caseId, array $user): array
    {
        $case = $this->requireCase($caseId);
        $this->requireAssignedRescuer($case, $user);
        if ($case->status() !== 'assigned') {
            throw new CaseWorkflowException('INVALID_STATUS', 'Case must be assigned before it can be accepted', 422);
        }

        $this->cases->update($case->id(), ['status' => 'in_progress']);
        $this->logActivity($case->id(), 'accepted', 'Rescuer accepted the assignment', $user['id'], $user['role']);
        $this->notifyAdmins('case_accepted', 'A rescuer accepted a case and started the rescue.', $case->id());

        return ['case' => $this->cases->find($case->id())->toArray()];
    }

    public function decline(string $caseId, array $user): array
    {
        $case = $this->requireCase($caseId);
        $this->requireAssignedRescuer($case, $user);
        if ($case->status() !== 'assigned') {
            throw new CaseWorkflowException('INVALID_STATUS', 'Case must be assigned before it can be declined', 422);
        }

        $this->cases->update($case->id(), [
            'status' => 'open',
            'assigned_rescuer_id' => null,
        ]);
        $this->logActivity($case->id(), 'declined', 'Rescuer declined the assignment', $user['id'], $user['role']);
        $this->notifyAdmins('case_declined', 'A rescuer declined a case assignment.', $case->id());

        return ['case' => $this->cases->find($case->id())->toArray()];
    }

    public function addProof(string $caseId, array $user, array $files): array
    {
        $case = $this->requireCase($caseId);
        if (($user['role'] ?? null) !== 'admin') {
            $this->requireAssignedRescuer($case, $user);
        }
        if ($case->status() !== 'in_progress') {
            throw new CaseWorkflowException('INVALID_STATUS', 'Proof can only be added while the case is in progress', 422);
        }
        if ($files === []) {
            throw new CaseWorkflowException('VALIDATION_ERROR', 'At least one proof file is required.', 400);
        }
        if (count($files) > CaseProofUpload::MAX_FILES) {
            throw new CaseWorkflowException(
                'VALIDATION_ERROR',
                'Up to ' . CaseProofUpload::MAX_FILES . ' files can be attached at once.',
                400
            );
        }

        $urls = [];
        foreach ($files as $file) {
            try {
                $urls[] = $this->uploads->store($file);
            } catch (\InvalidArgumentException $e) {
                throw new CaseWorkflowException('VALIDATION_ERROR', $e->getMessage(), 400);
            } catch (\RuntimeException $e) {
                error_log('[SERVER_ERROR] ' . $e);
                throw new CaseWorkflowException('SERVER_ERROR', Response::SERVER_ERROR_MESSAGE, 500);
            }
        }

        $photos = array_values(array_unique(array_merge($this->photoList($case), $urls)));
        $this->cases->update($case->id(), [
            'resolution_photos' => json_encode($photos, JSON_UNESCAPED_SLASHES),
        ]);
        $this->logActivity($case->id(), 'proof_added', 'Resolution proof photo added', $user['id'], $user['role']);
        $this->notifyAdmins('case_proof_added', 'Rescue proof was added to a case.', $case->id());

        return ['proof' => $photos, 'case' => $this->cases->find($case->id())->toArray()];
    }

    public function resolve(string $caseId, array $user): array
    {
        $case = $this->requireCase($caseId);
        if (($user['role'] ?? null) !== 'admin') {
            throw new CaseWorkflowException('FORBIDDEN', 'Only an admin can resolve a case', 403);
        }
        if ($case->status() !== 'in_progress') {
            throw new CaseWorkflowException('INVALID_STATUS', 'Case must be in progress before it can be resolved', 422);
        }
        if (count($this->photoList($case)) < 1) {
            throw new CaseWorkflowException('PROOF_REQUIRED', 'At least one proof photo is required before resolve', 422);
        }
        if (count($this->reportPhotoList($case)) < 1) {
            throw new CaseWorkflowException('ATTACHMENTS_REQUIRED', 'At least one attached file is required before resolve', 422);
        }

        $this->cases->update($case->id(), ['status' => 'resolved']);
        $this->logActivity($case->id(), 'resolved', 'Status set to resolved', $user['id'], $user['role']);

        $report = $this->reports->find($case->reportId());
        if ($report) {
            $this->reports->update($report->id(), ['status' => 'verified']);
            $this->notifications->notify(
                $report->residentId(),
                'case_resolved',
                'Your reported case has been resolved.',
                'case',
                $case->id()
            );
        }

        return ['case' => $this->cases->find($case->id())->toArray()];
    }

    public function rejectStatusPatch(string $status): void
    {
        $hint = match ($status) {
            'in_progress' => 'Use POST /api/v1/cases/{id}/accept to start a rescue.',
            'resolved' => 'Use POST /api/v1/cases/{id}/resolve after proof is on file.',
            'assigned' => 'Use POST /api/v1/cases/{id}/assign to assign a rescuer.',
            default => 'Case status cannot be changed via PATCH.',
        };
        throw new CaseWorkflowException('WORKFLOW_REQUIRED', $hint, 409);
    }

    private function requireCase(string $id): RescueCase
    {
        $case = $this->cases->find($id);
        if (!$case) {
            throw new CaseWorkflowException('NOT_FOUND', 'Case not found', 404);
        }
        return $case;
    }

    private function requireAssignedRescuer(RescueCase $case, array $user): void
    {
        if (($user['id'] ?? null) !== $case->assignedRescuerId()) {
            throw new CaseWorkflowException('FORBIDDEN', 'Not your case', 403);
        }
    }

    private function photoList(RescueCase $case): array
    {
        return $this->urlList($case->resolutionPhotos());
    }

    private function reportPhotoList(RescueCase $case): array
    {
        $report = $this->reports->find((string) $case->reportId());
        if (!$report) {
            return [];
        }
        return $this->urlList($report->photoUrls());
    }

    private function urlList(mixed $value): array
    {
        $existing = is_array($value) ? $value : json_decode((string) $value, true);
        if (!is_array($existing)) {
            return [];
        }
        return array_values(array_filter($existing, static fn($u) => is_string($u) && $u !== ''));
    }

    private function notifyAdmins(string $type, string $message, string $caseId): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM users WHERE role = ?');
        $stmt->execute(['admin']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
            $this->notifications->notify((string) $uid, $type, $message, 'case', $caseId);
        }
    }

    private function logActivity(string $caseId, string $action, ?string $notes, string $actorId, string $actorRole): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO case_activity_log (id, case_id, actor_id, actor_role, action, notes) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([Database::uuidV4(), $caseId, $actorId, $actorRole, $action, $notes]);
    }
}
