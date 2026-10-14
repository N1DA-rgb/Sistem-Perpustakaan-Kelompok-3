<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';

$id = trim($_GET['id'] ?? '');

if ($id === '') {
    header("Location: list.php");
    exit;
}

// 1. Deteksi nama tabel dan kolom Members
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
$colId = $colMap['memberid'] ?? $colMap['member_id'] ?? $colMap['id'] ?? 'MemberID';

// 2. Deteksi nama tabel dan kolom Loans
$loanTable = 'Loans';
try {
    $rawLoanCols = $pdo->query("SHOW COLUMNS FROM Loans")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $loanTable = 'loans';
    try {
        $rawLoanCols = $pdo->query("SHOW COLUMNS FROM loans")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        $rawLoanCols = [];
    }
}

$loanMap = [];
foreach ($rawLoanCols as $c) { $loanMap[strtolower($c['Field'])] = $c['Field']; }
$colLoanMemberId = $loanMap['memberid'] ?? $loanMap['member_id'] ?? 'MemberID';
$colLoanStatus   = $loanMap['status']   ?? 'Status';

// 3. Validasi: Cek apakah anggota masih meminjam buku (status belum Returned)
try {
    $sqlCheck = "SELECT COUNT(*) FROM {$loanTable} 
                 WHERE {$colLoanMemberId} = ? 
                 AND LOWER({$colLoanStatus}) NOT IN ('returned', 'dikembalikan', 'kembali')";
    $stmtCheck = $pdo->prepare($sqlCheck);
    $stmtCheck->execute([$id]);
    $activeLoansCount = (int)$stmtCheck->fetchColumn();

    if ($activeLoansCount > 0) {
        $_SESSION['flash_error'] = "Gagal menghapus! Anggota ini masih memiliki {$activeLoansCount} transaksi peminjaman aktif yang belum dikembalikan.";
        header("Location: list.php");
        exit;
    }
} catch (Throwable $e) {
    // Lanjutkan jika tabel Loans belum ada
}

// 4. Pastikan kolom is_deleted ada di tabel Members
if (!isset($colMap['is_deleted'])) {
    try {
        $pdo->exec("ALTER TABLE {$memberTable} ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0");
    } catch (Throwable $e) {}
}

// 5. Eksekusi Soft Delete jika aman
try {
    $stmt = $pdo->prepare("UPDATE {$memberTable} SET is_deleted = 1 WHERE {$colId} = ?");
    $stmt->execute([$id]);
} catch (Throwable $e) {
    $_SESSION['flash_error'] = 'Gagal menghapus anggota: ' . $e->getMessage();
}

header("Location: list.php");
exit;