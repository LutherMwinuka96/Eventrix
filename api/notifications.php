<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$action = request_action();
$method = request_method();
$pdo = db();
$user = current_user();

try {
    if ($action === 'getall' && $method === 'GET') {
        require_auth();

        $stmt = $pdo->prepare(
            'SELECT n.*, u.name AS target_user_name, u.role AS target_user_role
             FROM notifications n
             LEFT JOIN users u ON u.id = n.target_user_id
             WHERE n.target_user_id IS NULL
                OR n.target_user_id = :user_id
                OR n.target_role = :role
             ORDER BY n.created_at DESC, n.id DESC'
        );
        $stmt->execute([
            ':user_id' => $user['id'],
            ':role' => $user['role'],
        ]);

        $rows = $stmt->fetchAll();
        $notifications = array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'targetUserId' => $row['target_user_id'] !== null ? (int)$row['target_user_id'] : null,
                'targetRole' => $row['target_role'],
                'title' => $row['title'],
                'message' => $row['message'],
                'type' => $row['type'],
                'actionType' => $row['action_type'],
                'meta' => $row['meta_json'] ? json_decode($row['meta_json'], true) : new stdClass(),
                'read' => $row['read_at'] !== null,
                'timestamp' => $row['created_at'],
            ];
        }, $rows);

        respond([
            'success' => true,
            'message' => 'Notifications loaded',
            'data' => $notifications,
        ]);
    }

    if ($action === 'create' && $method === 'POST') {
        $user = require_auth();
        $data = request_data();

        $targetUserId = isset($data['targetUserId']) && $data['targetUserId'] !== '' ? (int)$data['targetUserId'] : null;
        $targetRole = isset($data['targetRole']) && $data['targetRole'] !== '' ? normalize_role((string)$data['targetRole']) : null;
        $title = trim((string)($data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));
        $type = normalize_role((string)($data['type'] ?? 'info'));
        $actionType = trim((string)($data['actionType'] ?? 'manual'));
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        if ($title === '' || $message === '') {
            respond(['success' => false, 'message' => 'Title and message are required'], 422);
        }

        $created = create_notification_record(
            $targetUserId,
            $targetRole,
            $title,
            $message,
            in_array($type, ['info', 'success', 'warning', 'error'], true) ? $type : 'info',
            $actionType ?: 'manual',
            $meta
        );

        respond([
            'success' => true,
            'message' => 'Notification created successfully',
            'data' => $created,
        ], 201);
    }

    if ($action === 'markread' && $method === 'POST') {
        $user = require_auth();
        $data = request_data();
        $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Invalid notification id'], 422);
        }

        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET read_at = NOW()
             WHERE id = :id
               AND (target_user_id IS NULL OR target_user_id = :user_id OR target_role = :role)'
        );
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $user['id'],
            ':role' => $user['role'],
        ]);

        respond([
            'success' => true,
            'message' => 'Notification marked as read',
        ]);
    }

    if ($action === 'delete' && in_array($method, ['POST', 'DELETE'], true)) {
        $user = require_auth();
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Invalid notification id'], 422);
        }

        $stmt = $pdo->prepare(
            'DELETE FROM notifications
             WHERE id = :id
               AND (target_user_id IS NULL OR target_user_id = :user_id OR target_role = :role)'
        );
        $stmt->execute([
            ':id' => $id,
            ':user_id' => $user['id'],
            ':role' => $user['role'],
        ]);

        respond([
            'success' => true,
            'message' => 'Notification deleted',
        ]);
    }

    respond([
        'success' => false,
        'message' => 'Unsupported action',
    ], 400);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => 'Notification error',
        'error' => $e->getMessage(),
    ], 500);
}
