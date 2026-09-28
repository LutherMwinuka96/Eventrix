<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $pdo = db();

    $dbOk = true;
    $dbMessage = 'Database connection successful';
    $counts = [
        'users' => 0,
        'events' => 0,
        'notifications' => 0,
    ];

    foreach (['users', 'events', 'notifications'] as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) AS total FROM {$table}");
        $counts[$table] = (int)($stmt->fetch()['total'] ?? 0);
    }

    respond([
        'success' => true,
        'message' => 'Backend is healthy',
        'data' => [
            'dbOk' => $dbOk,
            'dbMessage' => $dbMessage,
            'time' => date('c'),
            'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'counts' => $counts,
        ],
    ]);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => 'Backend health check failed',
        'error' => $e->getMessage(),
        'data' => [
            'dbOk' => false,
            'time' => date('c'),
        ],
    ], 500);
}
