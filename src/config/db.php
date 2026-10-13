
<?php

$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: 'LibraryDB';
$user = getenv('DB_USER') ?: 'libraryuser';
$password = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    exit('Koneksi database gagal. Periksa konfigurasi aplikasi.');
}