<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    header("Location: list.php");
    exit;
}

// 1. Deteksi nama tabel dan kolom Books
$bookTable = 'Books';
try {
    $rawCols = $pdo->query("SHOW COLUMNS FROM Books")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $bookTable = 'books';
    try {
        $rawCols = $pdo->query("SHOW COLUMNS FROM books")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        $rawCols = [];
    }
}

$colMap = [];
foreach ($rawCols as $c) { $colMap[strtolower($c['Field'])] = $c['Field']; }
$colIsbn = $colMap['isbn'] ?? 'ISBN';

// 2. Pastikan kolom is_deleted ada di tabel Books
if (!isset($colMap['is_deleted'])) {
    try {
        $pdo->exec("ALTER TABLE {$bookTable} ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $e) {}
}

// 3. Eksekusi SOFT DELETE (Ubah flag is_deleted = 1)
try {
    $stmt = $pdo->prepare("UPDATE {$bookTable} SET is_deleted = 1 WHERE {$colIsbn} = ?");
    $stmt->execute([$id]);
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Gagal menghapus buku: ' . $e->getMessage();
}

header("Location: list.php");
exit;