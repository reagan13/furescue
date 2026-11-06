<?php

namespace App\Services;

use PDO;

/**
 * Who may start a thread about a report, case, or adoption.
 *
 * A resident messages the assigned rescuer and an admin. A rescuer messages
 * that resident and an admin. An admin messages the applicant and the
 * assigned rescuer. Once a case exists, the shared thread is the case.
 */
class MessageAudience
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{id?:string,role?:string} $user
     * @return array{receiver:?array{id:string,full_name:string,role:string},topics:list<array<string,mixed>>}
     */
    public function compose(array $user): array
    {
        $role = (string) ($user['role'] ?? '');
        $me = (string) ($user['id'] ?? '');
        $topics = match ($role) {
            'rescuer' => $this->rescuerTopics($me),
            'admin' => $this->adminTopics($me),
            default => $this->residentTopics($me),
        };
        $receiver = null;
        foreach ($topics as $topic) {
            $first = $topic['receivers'][0] ?? null;
            if (is_array($first)) {
                $receiver = $first;
                break;
            }
        }

        return ['receiver' => $receiver, 'topics' => $topics];
    }

    public function allows(array $user, string $type, string $relatedId, string $receiverId): bool
    {
        $senderId = (string) ($user['id'] ?? '');
        if ($senderId === '' || $senderId === $receiverId) {
            return false;
        }
        foreach ($this->parties($type, $relatedId) as $party) {
            if (($party['id'] ?? '') !== $receiverId) {
                continue;
            }
            return $this->senderIsParty($user, $type, $relatedId);
        }

        return false;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function residentTopics(string $me): array
    {
        $admins = $this->admins($me);
        $topics = [];
        $stmt = $this->pdo->prepare(
            "SELECT r.id, r.animal_description, c.id AS case_id, c.assigned_rescuer_id, u.full_name AS rescuer_name
             FROM reports r
             LEFT JOIN cases c ON c.report_id = r.id
             LEFT JOIN users u ON u.id = c.assigned_rescuer_id AND u.account_status = 'active'
             WHERE r.resident_id = ?
             ORDER BY r.created_at DESC
             LIMIT 50"
        );
        $stmt->execute([$me]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $receivers = [];
            $rescuerId = (string) ($row['assigned_rescuer_id'] ?? '');
            if ($rescuerId !== '' && $rescuerId !== $me) {
                $receivers[] = $this->person($rescuerId, (string) ($row['rescuer_name'] ?? 'Rescuer'), 'rescuer');
            }
            $receivers = $this->mergePeople($receivers, $admins);
            if ($receivers === []) {
                continue;
            }
            $label = trim((string) ($row['animal_description'] ?? ''));
            $caseId = (string) ($row['case_id'] ?? '');
            $topics[] = [
                'related_type' => $caseId !== '' ? 'case' : 'report',
                'related_id' => $caseId !== '' ? $caseId : (string) $row['id'],
                'label' => 'Report · ' . ($label !== '' ? mb_substr($label, 0, 80) : 'your report'),
                'receivers' => $receivers,
            ];
        }

        $adopt = $this->pdo->prepare(
            "SELECT a.id, an.name
             FROM adoptions a
             LEFT JOIN animals an ON an.id = a.animal_id
             WHERE a.applicant_id = ? AND a.status <> 'cancelled'
             ORDER BY a.created_at DESC
             LIMIT 50"
        );
        $adopt->execute([$me]);
        foreach ($adopt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($admins === []) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $topics[] = [
                'related_type' => 'adoption',
                'related_id' => (string) $row['id'],
                'label' => 'Adoption · ' . ($name !== '' ? $name : 'your application'),
                'receivers' => $admins,
            ];
        }

        return $topics;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function rescuerTopics(string $me): array
    {
        $admins = $this->admins($me);
        $stmt = $this->pdo->prepare(
            "SELECT c.id, r.animal_description, r.resident_id, u.full_name AS resident_name
             FROM cases c
             JOIN reports r ON r.id = c.report_id
             LEFT JOIN users u ON u.id = r.resident_id
             WHERE c.assigned_rescuer_id = ?
             ORDER BY c.created_at DESC
             LIMIT 50"
        );
        $stmt->execute([$me]);
        $topics = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $receivers = [];
            $residentId = (string) ($row['resident_id'] ?? '');
            if ($residentId !== '' && $residentId !== $me) {
                $receivers[] = $this->person($residentId, (string) ($row['resident_name'] ?? 'Resident'), 'resident');
            }
            $receivers = $this->mergePeople($receivers, $admins);
            if ($receivers === []) {
                continue;
            }
            $label = trim((string) ($row['animal_description'] ?? ''));
            $topics[] = [
                'related_type' => 'case',
                'related_id' => (string) $row['id'],
                'label' => 'Case · ' . ($label !== '' ? mb_substr($label, 0, 80) : 'assigned to you'),
                'receivers' => $receivers,
            ];
        }

        return $topics;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function adminTopics(string $me): array
    {
        $topics = [];
        $cases = $this->pdo->prepare(
            "SELECT c.id, c.assigned_rescuer_id, u.full_name AS rescuer_name, r.animal_description, r.resident_id, ru.full_name AS resident_name
             FROM cases c
             JOIN reports r ON r.id = c.report_id
             LEFT JOIN users u ON u.id = c.assigned_rescuer_id AND u.account_status = 'active'
             LEFT JOIN users ru ON ru.id = r.resident_id
             ORDER BY c.updated_at DESC
             LIMIT 50"
        );
        $cases->execute();
        foreach ($cases->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $receivers = [];
            $rescuerId = (string) ($row['assigned_rescuer_id'] ?? '');
            if ($rescuerId !== '' && $rescuerId !== $me) {
                $receivers[] = $this->person($rescuerId, (string) ($row['rescuer_name'] ?? 'Rescuer'), 'rescuer');
            }
            $residentId = (string) ($row['resident_id'] ?? '');
            if ($residentId !== '' && $residentId !== $me) {
                $receivers[] = $this->person($residentId, (string) ($row['resident_name'] ?? 'Resident'), 'resident');
            }
            if ($receivers === []) {
                continue;
            }
            $label = trim((string) ($row['animal_description'] ?? ''));
            $topics[] = [
                'related_type' => 'case',
                'related_id' => (string) $row['id'],
                'label' => 'Case · ' . ($label !== '' ? mb_substr($label, 0, 80) : 'rescue case'),
                'receivers' => $receivers,
            ];
        }

        $adopt = $this->pdo->query(
            "SELECT a.id, a.applicant_id, u.full_name AS applicant_name, an.name AS animal_name
             FROM adoptions a
             LEFT JOIN users u ON u.id = a.applicant_id
             LEFT JOIN animals an ON an.id = a.animal_id
             WHERE a.status <> 'cancelled'
             ORDER BY a.created_at DESC
             LIMIT 50"
        );
        foreach ($adopt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $applicantId = (string) ($row['applicant_id'] ?? '');
            if ($applicantId === '' || $applicantId === $me) {
                continue;
            }
            $who = trim((string) ($row['applicant_name'] ?? ''));
            $animal = trim((string) ($row['animal_name'] ?? ''));
            $topics[] = [
                'related_type' => 'adoption',
                'related_id' => (string) $row['id'],
                'label' => 'Adoption · ' . ($who !== '' ? $who : 'Applicant') . ($animal !== '' ? ' · ' . $animal : ''),
                'receivers' => [$this->person($applicantId, $who !== '' ? $who : 'Applicant', 'resident')],
            ];
        }

        return $topics;
    }

    /**
     * @return list<array{id:string,full_name:string,role:string}>
     */
    private function parties(string $type, string $relatedId): array
    {
        if ($type === 'adoption') {
            $stmt = $this->pdo->prepare(
                "SELECT a.applicant_id, u.full_name
                 FROM adoptions a
                 LEFT JOIN users u ON u.id = a.applicant_id
                 WHERE a.id = ?"
            );
            $stmt->execute([$relatedId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return [];
            }
            $people = [];
            $applicantId = (string) ($row['applicant_id'] ?? '');
            if ($applicantId !== '') {
                $people[] = $this->person($applicantId, (string) ($row['full_name'] ?? 'Applicant'), 'resident');
            }

            return $this->mergePeople($people, $this->admins(''));
        }

        if ($type === 'case') {
            $stmt = $this->pdo->prepare(
                "SELECT c.assigned_rescuer_id, ru.full_name AS rescuer_name, r.resident_id, u.full_name AS resident_name
                 FROM cases c
                 JOIN reports r ON r.id = c.report_id
                 LEFT JOIN users ru ON ru.id = c.assigned_rescuer_id AND ru.account_status = 'active'
                 LEFT JOIN users u ON u.id = r.resident_id
                 WHERE c.id = ?"
            );
            $stmt->execute([$relatedId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return [];
            }
            $people = [];
            $residentId = (string) ($row['resident_id'] ?? '');
            if ($residentId !== '') {
                $people[] = $this->person($residentId, (string) ($row['resident_name'] ?? 'Resident'), 'resident');
            }
            $rescuerId = (string) ($row['assigned_rescuer_id'] ?? '');
            if ($rescuerId !== '') {
                $people[] = $this->person($rescuerId, (string) ($row['rescuer_name'] ?? 'Rescuer'), 'rescuer');
            }

            return $this->mergePeople($people, $this->admins(''));
        }

        $stmt = $this->pdo->prepare(
            "SELECT r.resident_id, u.full_name AS resident_name, c.assigned_rescuer_id, ru.full_name AS rescuer_name
             FROM reports r
             LEFT JOIN users u ON u.id = r.resident_id
             LEFT JOIN cases c ON c.report_id = r.id
             LEFT JOIN users ru ON ru.id = c.assigned_rescuer_id AND ru.account_status = 'active'
             WHERE r.id = ?"
        );
        $stmt->execute([$relatedId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [];
        }
        $people = [];
        $residentId = (string) ($row['resident_id'] ?? '');
        if ($residentId !== '') {
            $people[] = $this->person($residentId, (string) ($row['resident_name'] ?? 'Resident'), 'resident');
        }
        $rescuerId = (string) ($row['assigned_rescuer_id'] ?? '');
        if ($rescuerId !== '') {
            $people[] = $this->person($rescuerId, (string) ($row['rescuer_name'] ?? 'Rescuer'), 'rescuer');
        }

        return $this->mergePeople($people, $this->admins(''));
    }

    private function senderIsParty(array $user, string $type, string $relatedId): bool
    {
        $senderId = (string) ($user['id'] ?? '');
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }
        foreach ($this->parties($type, $relatedId) as $party) {
            if (($party['id'] ?? '') === $senderId && ($party['role'] ?? '') !== 'admin') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{id:string,full_name:string,role:string}>
     */
    private function admins(string $exceptUserId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, full_name FROM users
             WHERE role = 'admin' AND account_status = 'active' AND id <> ?
             ORDER BY created_at ASC, id ASC"
        );
        $stmt->execute([$exceptUserId]);
        $people = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $people[] = $this->person((string) $row['id'], (string) ($row['full_name'] ?? 'City Veterinary Office'), 'admin');
        }

        return $people;
    }

    /**
     * @param list<array{id:string,full_name:string,role:string}> $left
     * @param list<array{id:string,full_name:string,role:string}> $right
     * @return list<array{id:string,full_name:string,role:string}>
     */
    private function mergePeople(array $left, array $right): array
    {
        $seen = [];
        $out = [];
        foreach (array_merge($left, $right) as $person) {
            $id = $person['id'];
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $person;
        }

        return $out;
    }

    /**
     * @return array{id:string,full_name:string,role:string}
     */
    private function person(string $id, string $name, string $role): array
    {
        $name = trim($name);

        return [
            'id' => $id,
            'full_name' => $name !== '' ? $name : 'City Veterinary Office',
            'role' => $role,
        ];
    }
}
