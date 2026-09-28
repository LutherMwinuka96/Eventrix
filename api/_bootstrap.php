<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function cors_origin(): ?string
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === '') {
        return null;
    }

    if ($origin === 'null') {
        return 'null';
    }

    if (preg_match('#^https?://(localhost|127\\.0\\.0\\.1)(:\\d+)?$#i', $origin)) {
        return $origin;
    }

    return null;
}

$corsOrigin = cors_origin();
if ($corsOrigin !== null) {
    header('Access-Control-Allow-Origin: ' . $corsOrigin);
    header('Vary: Origin');
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, Accept, Origin, Authorization');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function request_action(): string
{
    return strtolower((string)($_GET['action'] ?? $_POST['action'] ?? ''));
}

function request_data(): array
{
    $body = file_get_contents('php://input');
    $decoded = json_decode($body ?: '', true);
    if (is_array($decoded)) {
        return $decoded;
    }

    return array_merge($_GET ?? [], $_POST ?? []);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        respond([
            'success' => false,
            'message' => 'Authentication required',
        ], 401);
    }

    return $user;
}

function normalize_role(?string $role): string
{
    return strtolower(trim((string)$role));
}

function normalize_reg_no(string $regNo): string
{
    return strtoupper(str_replace(' ', '', trim($regNo)));
}

function valid_course_codes(): array
{
    return [
        'LLB', 'BCS', 'BIT', 'BBA', 'BMS', 'BMLS', 'DCM', 'CIT', 'DBA',
        'BCO', 'BPH', 'BCM', 'BPL', 'BNS', 'BED', 'MED', 'PHAR', 'NUR', 'LAW',
    ];
}

function valid_intake_years(): array
{
    return ['2301', '2401', '2501', '2601'];
}

function parse_reg_no(string $regNo): ?array
{
    $regNo = normalize_reg_no($regNo);
    if (!preg_match('#^([A-Z]{2,5})/(\d+)/(2[3-6]01)/(DT)$#', $regNo, $matches)) {
        return null;
    }

    return [
        'reg_no' => $regNo,
        'course' => $matches[1],
        'number' => $matches[2],
        'year' => $matches[3],
        'suffix' => $matches[4],
    ];
}

function is_valid_reg_no(string $regNo): bool
{
    $parsed = parse_reg_no($regNo);
    if ($parsed === null) {
        return false;
    }

    return in_array($parsed['course'], valid_course_codes(), true)
        && in_array($parsed['year'], valid_intake_years(), true);
}

function build_reg_no(string $course, int|string $number, string $year): string
{
    return strtoupper(trim($course)) . '/' . $number . '/' . $year . '/DT';
}

function email_from_reg_no(string $regNo): string
{
    return strtolower(str_replace('/', '.', normalize_reg_no($regNo))) . '@student.kiut.ac.tz';
}

function default_student_name(string $regNo): string
{
    return 'Student (' . normalize_reg_no($regNo) . ')';
}

function department_from_course(string $course, string $fallback = 'General Studies'): string
{
    $map = [
        'BCS' => 'Computer Science',
        'BIT' => 'Information Technology',
        'CIT' => 'Information Technology',
        'BCO' => 'Computer Science',
        'BBA' => 'Business Administration',
        'DBA' => 'Business Administration',
        'DCM' => 'Business Administration',
        'BCM' => 'Business Administration',
        'BMS' => 'Biomedical Sciences',
        'BMLS' => 'Medical Laboratory',
        'BPH' => 'Pharmacy',
        'PHAR' => 'Pharmacy',
        'NUR' => 'Nursing',
        'LLB' => 'Law',
        'LAW' => 'Law',
        'BED' => 'Education',
        'MED' => 'Medicine',
        'BPL' => 'Pharmacy',
        'BNS' => 'Nursing',
    ];

    $course = strtoupper(trim($course));
    return $map[$course] ?? $fallback;
}

function insert_student_user(PDO $pdo, array $row): array
{
    $regNo = normalize_reg_no((string)($row['reg_no'] ?? ''));
    $name = trim((string)($row['name'] ?? ''));
    $email = strtolower(trim((string)($row['email'] ?? '')));
    $department = trim((string)($row['department'] ?? ''));

    if (!is_valid_reg_no($regNo)) {
        return ['ok' => false, 'reg_no' => $regNo, 'message' => 'Invalid registration number format'];
    }

    if ($name === '') {
        $name = default_student_name($regNo);
    }
    if ($email === '') {
        $email = email_from_reg_no($regNo);
    }
    if ($department === '') {
        $parsed = parse_reg_no($regNo);
        $department = department_from_course($parsed['course'] ?? '');
    }

    $regCheck = $pdo->prepare('SELECT id FROM users WHERE reg_no = :reg_no LIMIT 1');
    $regCheck->execute([':reg_no' => $regNo]);
    if ($regCheck->fetch()) {
        return ['ok' => false, 'reg_no' => $regNo, 'message' => 'Registration number already exists', 'skipped' => true];
    }

    $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email AND role = "student" LIMIT 1');
    $emailCheck->execute([':email' => $email]);
    if ($emailCheck->fetch()) {
        return ['ok' => false, 'reg_no' => $regNo, 'message' => 'Email already exists for a student', 'skipped' => true];
    }

    $password = hash_secret(bin2hex(random_bytes(16)));
    $insert = $pdo->prepare(
        'INSERT INTO users (name, email, password, role, reg_no, department, is_active, created_at)
         VALUES (:name, :email, :password, "student", :reg_no, :department, 1, NOW())'
    );
    $insert->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $password,
        ':reg_no' => $regNo,
        ':department' => $department,
    ]);

    return [
        'ok' => true,
        'reg_no' => $regNo,
        'id' => (int)$pdo->lastInsertId(),
        'name' => $name,
        'email' => $email,
        'department' => $department,
    ];
}

function verify_secret(string $plain, string $stored): bool
{
    $info = password_get_info($stored);
    if (($info['algo'] ?? 0) !== 0) {
        return password_verify($plain, $stored);
    }

    return hash('sha256', $plain) === $stored || $plain === $stored;
}

function hash_secret(string $plain): string
{
    return hash('sha256', $plain);
}

function create_notification_record(
    ?int $targetUserId,
    ?string $targetRole,
    string $title,
    string $message,
    string $type = 'info',
    string $actionType = 'manual',
    array $meta = []
): array {
    $pdo = db();
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (target_user_id, target_role, title, message, type, action_type, meta_json, created_at)
         VALUES (:target_user_id, :target_role, :title, :message, :type, :action_type, :meta_json, NOW())'
    );
    $stmt->execute([
        ':target_user_id' => $targetUserId,
        ':target_role' => $targetRole,
        ':title' => $title,
        ':message' => $message,
        ':type' => $type,
        ':action_type' => $actionType,
        ':meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    return [
        'id' => (int)$pdo->lastInsertId(),
        'target_user_id' => $targetUserId,
        'target_role' => $targetRole,
        'title' => $title,
        'message' => $message,
        'type' => $type,
        'action_type' => $actionType,
        'meta' => $meta,
        'read_at' => null,
        'created_at' => date('c'),
    ];
}

function push_session_user(array $userRow): array
{
    $sessionUser = [
        'id' => (int)$userRow['id'],
        'name' => $userRow['name'],
        'email' => $userRow['email'],
        'role' => $userRow['role'],
        'department' => $userRow['department'] ?? '',
        'theme' => $userRow['theme'] ?? 'light',
        'language' => $userRow['language'] ?? 'en',
        'compactView' => (bool)($userRow['compact_view'] ?? 0),
        'twoFactorAuth' => (bool)($userRow['two_factor_auth'] ?? 0),
        'idNumber' => $userRow['reg_no'] ?? '',
        'regNumber' => $userRow['reg_no'] ?? '',
    ];

    $_SESSION['user'] = $sessionUser;
    $_SESSION['user_id'] = $sessionUser['id'];
    return $sessionUser;
}
