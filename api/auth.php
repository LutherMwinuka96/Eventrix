<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

$action = request_action();
$method = request_method();
$pdo = db();

try {
    if ($action === 'me' && $method === 'GET') {
        $user = current_user();
        if (!$user) {
            respond(['success' => false, 'message' => 'Not logged in'], 401);
        }

        respond([
            'success' => true,
            'message' => 'Current user loaded',
            'data' => ['user' => $user],
        ]);
    }

    if ($action === 'student_access' && $method === 'POST') {
        $data = request_data();
        $regNo = trim((string)($data['reg_no'] ?? ''));

        if ($regNo === '') {
            respond(['success' => false, 'message' => 'Registration number is required'], 422);
        }

        $regNoNormalized = normalize_reg_no($regNo);

        if (!is_valid_reg_no($regNoNormalized)) {
            respond(['success' => false, 'message' => 'Invalid registration number format. Example: BCS/23456/2301/DT'], 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE reg_no = :reg_no AND role = "student" LIMIT 1');
        $stmt->execute([':reg_no' => $regNoNormalized]);
        $userRow = $stmt->fetch();

        if (!$userRow) {
            respond([
                'success' => false,
                'message' => 'Registration number not found. Students cannot self-register — contact campus admin to be added.',
            ], 404);
        }

        if (!(int)$userRow['is_active']) {
            respond(['success' => false, 'message' => 'Account is inactive'], 403);
        }

        session_regenerate_id(true);
        $sessionUser = push_session_user($userRow);

        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([
            ':id' => $sessionUser['id'],
        ]);

        $notification = create_notification_record(
            $sessionUser['id'],
            null,
            'Login successful',
            sprintf('Welcome to KIUT-Eventrix Campus, %s.', $sessionUser['name']),
            'success',
            'login',
            ['reg_no' => $sessionUser['regNumber'], 'role' => $sessionUser['role']]
        );

        respond([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $sessionUser,
                'token' => session_id(),
                'notification' => $notification,
            ],
        ]);
    }

    if ($action === 'login' && $method === 'POST') {
        $data = request_data();
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $role = normalize_role($data['role'] ?? '');

        if ($email === '' || $password === '') {
            respond(['success' => false, 'message' => 'Email and password are required'], 422);
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email ORDER BY id ASC');
        $stmt->execute([
            ':email' => $email,
        ]);
        $candidates = $stmt->fetchAll();

        $userRow = null;
        foreach ($candidates as $candidate) {
            if ($role && $candidate['role'] !== $role) {
                continue;
            }
            if (verify_secret($password, (string)$candidate['password'])) {
                $userRow = $candidate;
                break;
            }
        }

        if (!$userRow) {
            foreach ($candidates as $candidate) {
                if (verify_secret($password, (string)$candidate['password'])) {
                    $userRow = $candidate;
                    break;
                }
            }
        }

        if (!$userRow) {
            respond(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        if ($userRow['role'] === 'student') {
            respond([
                'success' => false,
                'message' => 'Students must access the system using their registration number only',
            ], 403);
        }

        if (!(int)$userRow['is_active']) {
            $message = 'Account is inactive. Contact campus admin.';
            if (($userRow['role'] ?? '') === 'organizer') {
                $message = 'Your organizer account is pending admin approval. Please wait until an administrator approves your registration.';
            }
            respond(['success' => false, 'message' => $message], 403);
        }

        session_regenerate_id(true);
        $sessionUser = push_session_user($userRow);

        $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([
            ':id' => $sessionUser['id'],
        ]);

        $notification = create_notification_record(
            $sessionUser['id'],
            null,
            'Login successful',
            sprintf('Welcome back, %s.', $sessionUser['name']),
            'success',
            'login',
            ['email' => $sessionUser['email'], 'role' => $sessionUser['role']]
        );

        respond([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $sessionUser,
                'token' => session_id(),
                'notification' => $notification,
            ],
        ]);
    }

    if ($action === 'register' && $method === 'POST') {
        $data = request_data();
        $name = trim((string)($data['name'] ?? ''));
        $email = strtolower(trim((string)($data['email'] ?? '')));
        $password = (string)($data['password'] ?? '');
        $role = normalize_role($data['role'] ?? 'student');
        $department = trim((string)($data['department'] ?? ''));

        if ($name === '' || $email === '' || $password === '') {
            respond(['success' => false, 'message' => 'Name, email, and password are required'], 422);
        }

        if ($role === 'student') {
            respond([
                'success' => false,
                'message' => 'Students cannot self-register. Use your registration number on the sign-in page',
            ], 403);
        }

        if ($role === 'admin') {
            respond([
                'success' => false,
                'message' => 'Admin accounts use default credentials and cannot be self-registered',
            ], 403);
        }

        if ($role !== 'organizer') {
            respond(['success' => false, 'message' => 'Only organizers can create new accounts'], 422);
        }

        $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email AND role = :role LIMIT 1');
        $exists->execute([
            ':email' => $email,
            ':role' => $role,
        ]);
        if ($exists->fetch()) {
            respond(['success' => false, 'message' => 'Email already registered for this role'], 409);
        }

        $hashed = hash_secret($password);
        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, department, is_active, created_at)
             VALUES (:name, :email, :password, :role, :department, 0, NOW())'
        );
        $insert->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $hashed,
            ':role' => $role,
            ':department' => $department ?: null,
        ]);

        $userId = (int)$pdo->lastInsertId();

        create_notification_record(
            null,
            'admin',
            'New organizer registration',
            sprintf('%s (%s) registered and awaits your approval.', $name, $email),
            'info',
            'organizer_pending',
            ['userId' => $userId, 'email' => $email, 'name' => $name]
        );

        create_notification_record(
            $userId,
            null,
            'Registration submitted',
            'Your organizer account was created and is pending admin approval. You can sign in after approval.',
            'info',
            'register',
            ['email' => $email, 'pendingApproval' => true]
        );

        respond([
            'success' => true,
            'message' => 'Registration submitted. Your account is pending admin approval.',
            'data' => [
                'pendingApproval' => true,
                'email' => $email,
                'userId' => $userId,
            ],
        ], 201);
    }

    if ($action === 'logout' && $method === 'POST') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
        }
        session_destroy();

        respond([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    respond([
        'success' => false,
        'message' => 'Unsupported action',
    ], 400);
} catch (Throwable $e) {
    respond([
        'success' => false,
        'message' => 'Authentication error',
        'error' => $e->getMessage(),
    ], 500);
}
