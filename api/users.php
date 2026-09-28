<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$action = request_action();
$method = request_method();
$pdo = db();

try {
    $user = require_auth();

    if ($action === 'getall' && $method === 'GET') {
        if ($user['role'] !== 'admin') {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $stmt = $pdo->query(
            'SELECT id, name, email, role, reg_no, department, theme, language, compact_view, two_factor_auth, is_active, created_at, last_login_at
             FROM users
             ORDER BY created_at DESC, id DESC'
        );

        $users = array_map(static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role'],
                'reg_no' => $row['reg_no'],
                'idNumber' => $row['reg_no'],
                'regNumber' => $row['reg_no'],
                'department' => $row['department'],
                'theme' => $row['theme'],
                'language' => $row['language'],
                'compactView' => (bool)$row['compact_view'],
                'twoFactorAuth' => (bool)$row['two_factor_auth'],
                'isActive' => (bool)$row['is_active'],
                'createdAt' => $row['created_at'],
                'lastLoginAt' => $row['last_login_at'],
            ];
        }, $stmt->fetchAll());

        respond(['success' => true, 'message' => 'Users loaded', 'data' => $users]);
    }

    if ($action === 'update' && in_array($method, ['POST', 'PUT'], true)) {
        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Invalid user id'], 422);
        }

        if ($user['role'] !== 'admin' && $user['id'] !== $id) {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $data = request_data();
        $fields = [
            'name' => trim((string)($data['name'] ?? '')),
            'email' => strtolower(trim((string)($data['email'] ?? ''))),
            'role' => isset($data['role']) ? normalize_role($data['role']) : null,
            'department' => trim((string)($data['department'] ?? '')),
            'theme' => trim((string)($data['theme'] ?? '')),
            'language' => trim((string)($data['language'] ?? '')),
            'compact_view' => isset($data['compactView']) ? (int)(bool)$data['compactView'] : null,
            'two_factor_auth' => isset($data['twoFactorAuth']) ? (int)(bool)$data['twoFactorAuth'] : null,
        ];

        $sets = [];
        $params = [':id' => $id];
        foreach ($fields as $column => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $sets[] = "$column = :$column";
            $params[":$column"] = $value;
        }

        if (!$sets) {
            respond(['success' => false, 'message' => 'No fields to update'], 422);
        }

        $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);

        respond(['success' => true, 'message' => 'User updated successfully']);
    }

    if ($action === 'togglestatus' && $method === 'POST') {
        if ($user['role'] !== 'admin') {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Invalid user id'], 422);
        }

        $stmt = $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = :id');
        $stmt->execute([':id' => $id]);

        respond(['success' => true, 'message' => 'User status updated']);
    }

    if ($action === 'create' && $method === 'POST') {
        if ($user['role'] !== 'admin') {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $data = request_data();
        $name = trim((string)($data['name'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $role = normalize_role($data['role'] ?? 'student');
        $department = trim((string)($data['department'] ?? ''));
        $regNo = normalize_reg_no(trim((string)($data['regNo'] ?? $data['idNumber'] ?? $data['reg_no'] ?? '')));
        $isActive = array_key_exists('isActive', $data) ? (int)(bool)$data['isActive'] : 1;

        if ($name === '' || $email === '') {
            respond(['success' => false, 'message' => 'Name and email are required'], 422);
        }

        if ($role === 'student') {
            if ($regNo === '') {
                respond(['success' => false, 'message' => 'Registration number is required for student accounts'], 422);
            }
            if (!is_valid_reg_no($regNo)) {
                respond(['success' => false, 'message' => 'Invalid registration number. Use format BCS/23456/2301/DT'], 422);
            }
            if ($password === '') {
                $password = bin2hex(random_bytes(16));
            }
        } elseif ($password === '') {
            respond(['success' => false, 'message' => 'Password is required'], 422);
        }

        if (!in_array($role, ['student', 'organizer', 'admin'], true)) {
            respond(['success' => false, 'message' => 'Invalid role'], 422);
        }

        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email AND role = :role LIMIT 1');
        $exists->execute([':email' => $email, ':role' => $role]);
        if ($exists->fetch()) {
            respond(['success' => false, 'message' => 'Email already registered for this role'], 409);
        }

        if ($regNo !== '') {
            $regCheck = $pdo->prepare('SELECT id FROM users WHERE reg_no = :reg_no LIMIT 1');
            $regCheck->execute([':reg_no' => $regNo]);
            if ($regCheck->fetch()) {
                respond(['success' => false, 'message' => 'Registration number already exists'], 409);
            }
        }

        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, reg_no, department, is_active, created_at)
             VALUES (:name, :email, :password, :role, :reg_no, :department, :is_active, NOW())'
        );
        $insert->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => hash_secret($password),
            ':role' => $role,
            ':reg_no' => $regNo !== '' ? $regNo : null,
            ':department' => $department !== '' ? $department : null,
            ':is_active' => $isActive,
        ]);

        respond([
            'success' => true,
            'message' => 'User created successfully',
            'data' => ['id' => (int)$pdo->lastInsertId()],
        ], 201);
    }

    if ($action === 'approveorganizer' && $method === 'POST') {
        if ($user['role'] !== 'admin') {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $data = request_data();
        $id = (int)($_GET['id'] ?? ($data['id'] ?? 0));
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Invalid user id'], 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND role = "organizer" LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            respond(['success' => false, 'message' => 'Organizer not found'], 404);
        }
        if ((int)$row['is_active'] === 1) {
            respond(['success' => true, 'message' => 'Organizer is already approved']);
        }

        $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = :id')->execute([':id' => $id]);

        create_notification_record(
            $id,
            null,
            'Organizer account approved',
            'Your organizer account has been approved. You can now sign in and create events.',
            'success',
            'organizer_approved',
            ['email' => $row['email']]
        );

        respond(['success' => true, 'message' => 'Organizer approved successfully']);
    }

    if ($action === 'rejectorganizer' && $method === 'POST') {
        if ($user['role'] !== 'admin') {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $data = request_data();
        $id = (int)($_GET['id'] ?? ($data['id'] ?? 0));
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Invalid user id'], 422);
        }
        if ($id === (int)$user['id']) {
            respond(['success' => false, 'message' => 'Invalid operation'], 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id AND role = "organizer" AND is_active = 0 LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            respond(['success' => false, 'message' => 'Pending organizer not found'], 404);
        }

        $pdo->prepare('DELETE FROM event_registrations WHERE user_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);

        respond(['success' => true, 'message' => 'Organizer registration rejected and removed']);
    }

    if ($action === 'bulkstudents' && $method === 'POST') {
        if ($user['role'] !== 'admin') {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $data = request_data();
        $students = is_array($data['students'] ?? null) ? $data['students'] : [];
        $range = is_array($data['range'] ?? null) ? $data['range'] : null;

        if ($range) {
            $course = strtoupper(trim((string)($range['course'] ?? '')));
            $from = (int)($range['from'] ?? 0);
            $to = (int)($range['to'] ?? 0);
            $year = trim((string)($range['year'] ?? ''));
            $faculty = trim((string)($range['faculty'] ?? ''));
            $department = trim((string)($range['department'] ?? ''));

            if ($course === '' || $from <= 0 || $to <= 0 || $from > $to) {
                respond(['success' => false, 'message' => 'Invalid range values'], 422);
            }
            if (($to - $from) > 500) {
                respond(['success' => false, 'message' => 'Maximum 500 students per range'], 422);
            }
            if (!in_array($course, valid_course_codes(), true)) {
                respond(['success' => false, 'message' => 'Invalid course code'], 422);
            }
            if (!in_array($year, valid_intake_years(), true)) {
                respond(['success' => false, 'message' => 'Invalid intake year. Use 2301, 2401, 2501, or 2601'], 422);
            }

            if ($department === '') {
                $department = department_from_course($course);
            }

            for ($n = $from; $n <= $to; $n++) {
                $regNo = build_reg_no($course, $n, $year);
                $students[] = [
                    'reg_no' => $regNo,
                    'name' => default_student_name($regNo),
                    'email' => email_from_reg_no($regNo),
                    'faculty' => $faculty,
                    'department' => $department,
                ];
            }
        }

        if (!$students) {
            respond(['success' => false, 'message' => 'No students to register'], 422);
        }

        if (count($students) > 500) {
            respond(['success' => false, 'message' => 'Maximum 500 students per upload'], 422);
        }

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        $createdRows = [];

        $pdo->beginTransaction();
        try {
            foreach ($students as $index => $row) {
                if (!is_array($row)) {
                    $failed++;
                    $errors[] = ['row' => $index + 1, 'message' => 'Invalid row data'];
                    continue;
                }

                $result = insert_student_user($pdo, $row);
                if (!empty($result['ok'])) {
                    $created++;
                    $createdRows[] = [
                        'id' => $result['id'],
                        'reg_no' => $result['reg_no'],
                        'name' => $result['name'],
                        'email' => $result['email'],
                        'department' => $result['department'],
                    ];
                    continue;
                }

                if (!empty($result['skipped'])) {
                    $skipped++;
                    $errors[] = ['reg_no' => $result['reg_no'] ?? '', 'message' => $result['message'] ?? 'Skipped'];
                    continue;
                }

                $failed++;
                $errors[] = ['reg_no' => $result['reg_no'] ?? '', 'message' => $result['message'] ?? 'Failed'];
            }
            $pdo->commit();
        } catch (Throwable $bulkError) {
            $pdo->rollBack();
            throw $bulkError;
        }

        respond([
            'success' => true,
            'message' => sprintf('Bulk registration complete: %d created, %d skipped, %d failed', $created, $skipped, $failed),
            'data' => [
                'created' => $created,
                'skipped' => $skipped,
                'failed' => $failed,
                'errors' => array_slice($errors, 0, 50),
                'students' => array_slice($createdRows, 0, 100),
            ],
        ]);
    }

    if ($action === 'delete' && in_array($method, ['DELETE', 'POST'], true)) {
        if ($user['role'] !== 'admin') {
            respond(['success' => false, 'message' => 'Access denied'], 403);
        }

        $id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Invalid user id'], 422);
        }

        if ($id === (int)$user['id']) {
            respond(['success' => false, 'message' => 'You cannot delete your own account'], 422);
        }

        $check = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $check->execute([':id' => $id]);
        if (!$check->fetch()) {
            respond(['success' => false, 'message' => 'User not found'], 404);
        }

        $pdo->prepare('DELETE FROM event_registrations WHERE user_id = :id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);

        respond(['success' => true, 'message' => 'User deleted successfully']);
    }

    respond(['success' => false, 'message' => 'Unsupported action'], 400);
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Users error', 'error' => $e->getMessage()], 500);
}
