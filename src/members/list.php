<?php
declare(strict_types=1);

// Menampilkan pesan error jika terjadi masalah (Bukan Halaman Putih)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

// Safe-check lokasi file db.php
$dbPath = __DIR__ . '/../config/db.php';
if (!file_exists($dbPath)) {
    $dbPath = __DIR__ . '/../src/config/db.php';
}
require_once $dbPath;

$flashError = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

$search = trim($_GET['search'] ?? '');

// 1. Deteksi Struktur Tabel & Kolom (Logika Asli Kamu)
$memberTable = 'Members';
try {
    $rawCols = $pdo->query("SHOW COLUMNS FROM Members")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $memberTable = 'members';
    try {
        $rawCols = $pdo->query("SHOW COLUMNS FROM members")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        $rawCols = [];
    }
}

$colMap = [];
foreach ($rawCols as $c) { $colMap[strtolower($c['Field'])] = $c['Field']; }

$colId    = $colMap['memberid'] ?? $colMap['member_id'] ?? $colMap['id'] ?? 'MemberID';
$colName  = $colMap['name']     ?? $colMap['nama']      ?? $colMap['nama_anggota'] ?? 'Name';
$colEmail = $colMap['email']    ?? null;
$colPhone = $colMap['phone']    ?? $colMap['telepon']   ?? null;

if (!isset($colMap['is_deleted'])) {
    try { $pdo->exec("ALTER TABLE {$memberTable} ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}
}

// 2. Kueri Read & Search Hanya untuk Data Aktif (is_deleted = 0) (Logika Asli Kamu)
$members = [];
try {
    $baseCondition = "(is_deleted = 0 OR is_deleted IS NULL)";
    
    if ($search !== '') {
        $whereClauses = ["{$colId} LIKE ?", "{$colName} LIKE ?"];
        $params = ["%{$search}%", "%{$search}%"];

        if ($colEmail) { $whereClauses[] = "{$colEmail} LIKE ?"; $params[] = "%{$search}%"; }
        if ($colPhone) { $whereClauses[] = "{$colPhone} LIKE ?"; $params[] = "%{$search}%"; }

        $sql = "SELECT * FROM {$memberTable} WHERE {$baseCondition} AND (" . implode(' OR ', $whereClauses) . ") ORDER BY {$colId} DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll();
    } else {
        $members = $pdo->query("SELECT * FROM {$memberTable} WHERE {$baseCondition} ORDER BY {$colId} DESC")->fetchAll();
    }
} catch (Throwable $e) {
        $members = [];
}

if (!function_exists('e')) {
    function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
}

// 3. Panggil File Tampilan View
$viewPath = __DIR__ . '/../views/members/list.view.php';
if (!file_exists($viewPath)) {
    $viewPath = __DIR__ . '/list.view.php';
}
require_once $viewPath;