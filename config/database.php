<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_NAME = 'eventrix';
const DB_USER = 'root';
const DB_PASS = 'Lutherking@2003';
const DB_CHARSET = 'utf8mb4';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Auto migration to add reg_no column to users table if it doesn't exist
    try {
        $check = $pdo->query("SHOW COLUMNS FROM users LIKE 'reg_no'");
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN reg_no VARCHAR(100) DEFAULT NULL UNIQUE AFTER role");
            // Also update Amina Juma (student@campus.edu) in DB if they exist to have KIUT/2024/1001
            $pdo->exec("UPDATE users SET reg_no = 'KIUT/2024/1001' WHERE email = 'student@campus.edu' AND role = 'student'");
        }
    } catch (Throwable $e) {
        // Silently ignore migration errors
    }

    return $pdo;
}
