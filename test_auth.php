<?php
require_once __DIR__ . '/api/_bootstrap.php';

echo "Testing Database Connection and Auto-migration...\n";
try {
    $pdo = db();
    echo "SUCCESS: Connected to database.\n";
    
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'reg_no'");
    $column = $stmt->fetch();
    if ($column) {
        echo "SUCCESS: 'reg_no' column exists in 'users' table. Type: " . $column['Type'] . "\n";
    } else {
        echo "FAIL: 'reg_no' column does not exist.\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nTesting Registration Number Validation & Auto-creation via simulated POST request...\n";

function simulateStudentAccess($regNo) {
    $pdo = db();
    $regNoNormalized = strtoupper(str_replace(' ', '', $regNo));

    if (!preg_match('/^[A-Z0-9\-]+\/[A-Z0-9\-]+\/[A-Z0-9\-]+(\/[A-Z0-9\-]+)?$/i', $regNoNormalized)) {
        return ['success' => false, 'message' => 'Invalid format'];
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE reg_no = :reg_no AND role = "student" LIMIT 1');
    $stmt->execute([':reg_no' => $regNoNormalized]);
    $userRow = $stmt->fetch();

    if (!$userRow) {
        // Auto-create student
        $emailSafeReg = strtolower(str_replace('/', '.', $regNoNormalized));
        $email = $emailSafeReg . '@student.kiut.ac.tz';
        $name = "Student (" . $regNoNormalized . ")";
        $password = hash('sha256', bin2hex(random_bytes(16)));

        $parts = explode('/', $regNoNormalized);
        $courseCode = $parts[0];
        $department = 'General Studies';
        if (in_array($courseCode, ['BIT', 'BCO', 'BCS'])) {
            $department = 'Computer Science';
        }

        $insert = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, reg_no, department, is_active, created_at)
             VALUES (:name, :email, :password, "student", :reg_no, :department, 1, NOW())'
        );
        $insert->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $password,
            ':reg_no' => $regNoNormalized,
            ':department' => $department,
        ]);

        $userId = $pdo->lastInsertId();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $userRow = $stmt->fetch();
    }

    return ['success' => true, 'user' => $userRow];
}

$tests = [
    'KIUT/2024/1001' => 'Existing or default Student',
    'BIT/24567/2301/DT' => 'New student format 1',
    'KU/BIT/1234/18' => 'New student format 2',
    'INVALID_REG_NO' => 'Invalid format'
];

foreach ($tests as $regNo => $desc) {
    echo "Testing: $regNo ($desc)... ";
    $res = simulateStudentAccess($regNo);
    if ($desc === 'Invalid format') {
        if (!$res['success']) {
            echo "SUCCESS: Correctly rejected.\n";
        } else {
            echo "FAIL: Accepted invalid format.\n";
        }
    } else {
        if ($res['success'] && $res['user']['reg_no'] === strtoupper($regNo)) {
            echo "SUCCESS: Authorized user ID: " . $res['user']['id'] . ", Name: " . $res['user']['name'] . "\n";
        } else {
            echo "FAIL: " . ($res['message'] ?? 'Unknown error') . "\n";
        }
    }
}
