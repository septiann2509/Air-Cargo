<?php
// config/database.php
// Konfigurasi Database MySQL & Session Handler

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Database {
    private static $host = 'localhost';
    private static $db_name = 'air_cargo_db';
    private static $username = 'root';
    private static $password = '';
    private static $conn = null;

    public static function getConnection() {
        if (self::$conn === null) {
            try {
                $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$db_name . ";charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];
                self::$conn = new PDO($dsn, self::$username, self::$password, $options);
            } catch (PDOException $e) {
                die(json_encode([
                    'success' => false,
                    'message' => 'Database connection failed: ' . $e->getMessage()
                ]));
            }
        }
        return self::$conn;
    }
}

function getDb() {
    return Database::getConnection();
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    if (isset($_SESSION['full_name']) && in_array($_SESSION['full_name'], ['Tim Konsultan Kelompok 1', 'CargoPinnacle Consulting'])) {
        $_SESSION['full_name'] = 'PT Aerospace Consultant';
        $_SESSION['organization'] = 'PT Aerospace Consultant Advisory';
    }
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'guest',
        'full_name' => $_SESSION['full_name'] ?? 'PT Aerospace Consultant',
        'role' => $_SESSION['role'] ?? 'consultant',
        'organization' => $_SESSION['organization'] ?? 'PT Aerospace Consultant Advisory'
    ];
}

function requireAuth() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}
