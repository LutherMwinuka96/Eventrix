<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$action = request_action();
$method = request_method();
$pdo = db();

try {
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        respond(['success' => false, 'message' => 'Access denied'], 403);
    }

    if ($action === 'getpending' && $method === 'GET') {
        $stmt = $pdo->query(
            'SELECT e.*,
                    (
                        SELECT COUNT(*)
                        FROM event_registrations r
                        WHERE r.event_id = e.id
                    ) AS participant_count
             FROM events e
             WHERE e.status = "pending"
             ORDER BY e.created_at DESC, e.id DESC'
        );

        respond(['success' => true, 'message' => 'Pending events loaded', 'data' => $stmt->fetchAll()]);
    }

    if ($action === 'approve' && $method === 'POST') {
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Invalid event id'], 422);
        }

        $stmt = $pdo->prepare('UPDATE events SET status = "approved", updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);

        create_notification_record(null, 'organizer', 'Event approved', 'Your event was approved by admin.', 'success', 'approve_event', ['event_id' => $id]);

        respond(['success' => true, 'message' => 'Event approved']);
    }

    if ($action === 'reject' && $method === 'POST') {
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Invalid event id'], 422);
        }

        $data = request_data();
        $reason = trim((string)($data['reason'] ?? 'No reason provided'));

        $stmt = $pdo->prepare('UPDATE events SET status = "rejected", updated_at = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);

        create_notification_record(null, 'organizer', 'Event rejected', $reason, 'warning', 'reject_event', ['event_id' => $id]);

        respond(['success' => true, 'message' => 'Event rejected']);
    }

    if ($action === 'bulkapprove' && $method === 'POST') {
        $data = request_data();
        $eventIds = array_map('intval', $data['eventIds'] ?? []);
        foreach ($eventIds as $eventId) {
            if ($eventId > 0) {
                $pdo->prepare('UPDATE events SET status = "approved", updated_at = NOW() WHERE id = :id')->execute([':id' => $eventId]);
            }
        }
        respond(['success' => true, 'message' => 'Events approved']);
    }

    if ($action === 'bulkreject' && $method === 'POST') {
        $data = request_data();
        $eventIds = array_map('intval', $data['eventIds'] ?? []);
        $reason = trim((string)($data['reason'] ?? 'No reason provided'));
        foreach ($eventIds as $eventId) {
            if ($eventId > 0) {
                $pdo->prepare('UPDATE events SET status = "rejected", updated_at = NOW() WHERE id = :id')->execute([':id' => $eventId]);
            }
        }
        create_notification_record(null, 'organizer', 'Events rejected', $reason, 'warning', 'bulk_reject', ['event_ids' => $eventIds]);
        respond(['success' => true, 'message' => 'Events rejected']);
    }

    respond(['success' => false, 'message' => 'Unsupported action'], 400);
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Approvals error', 'error' => $e->getMessage()], 500);
}
