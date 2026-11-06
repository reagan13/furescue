<?php

namespace App\Services;

use App\Database;
use PDO;

class NotificationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function notify(
        string $userId,
        string $type,
        string $message,
        ?string $relatedType = null,
        ?string $relatedId = null
    ): string {
        $id = Database::uuidV4();
        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (id, user_id, type, message, related_type, related_id, is_read, created_at)
             VALUES (?, ?, ?, ?, ?, ?, FALSE, NOW())"
        );
        $stmt->execute([$id, $userId, $type, $message, $relatedType, $relatedId]);
        return $id;
    }

    public function unreadCount(string $userId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public function broadcast(string $message, string $type = 'admin_announcement', array $targets = ['role:admin'], ?string $excludeUserId = null): int
    {
        $recipients = [];
        foreach ($targets as $target) {
            if (str_starts_with($target, 'role:')) {
                $role = substr($target, 5);
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE role = ? AND account_status = 'active'");
                $stmt->execute([$role]);
                $recipients = array_merge($recipients, $stmt->fetchAll(PDO::FETCH_COLUMN));
            } elseif ($target === 'all') {
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE account_status = 'active'");
                $stmt->execute();
                $recipients = array_merge($recipients, $stmt->fetchAll(PDO::FETCH_COLUMN));
            } elseif (str_starts_with($target, 'user:')) {
                $id = substr($target, 5);
                if (preg_match('/^[0-9a-fA-F-]{36}$/', $id)) {
                    $recipients[] = $id;
                }
            }
        }
        $recipients = array_unique(array_values(array_filter($recipients)));
        if ($excludeUserId !== null) {
            $recipients = array_values(array_filter($recipients, fn($id) => $id !== $excludeUserId));
        }
        if (empty($recipients)) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO notifications (id, user_id, type, message, related_type, related_id, is_read, created_at)
             VALUES (?, ?, ?, ?, NULL, NULL, FALSE, NOW())"
        );
        $written = 0;
        foreach ($recipients as $userId) {
            $id = Database::uuidV4();
            $stmt->execute([$id, $userId, $type, $message]);
            $written++;
        }
        return $written;
    }

    public function dbNow(): string
    {
        return (string) $this->pdo->query('SELECT NOW()')->fetchColumn();
    }

    /**
     * Unread notifications for a user created at or after $since (DB datetime string).
     * Rows are ordered oldest first so SSE consumers can advance their cursor.
     *
     * @return array<int, array<string, mixed>>
     */
    public function latestSince(string $userId, string $since, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, user_id, type, message, related_type, related_id, is_read, created_at
             FROM notifications
             WHERE user_id = ? AND is_read = FALSE AND created_at >= ?
             ORDER BY created_at ASC
             LIMIT " . max(1, (int) $limit)
        );
        $stmt->execute([$userId, $since]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Broadcasts are one row per recipient; collapse them into one entry per message.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentBroadcasts(int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT MIN(id) AS id, message, type, COUNT(*) AS recipients, MIN(created_at) AS created_at
             FROM notifications
             WHERE type = 'admin_announcement'
             GROUP BY message, type
             ORDER BY created_at DESC
             LIMIT " . max(1, (int) $limit)
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
