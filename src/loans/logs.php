<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

// Handler untuk membersihkan log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    try {
        $pdo->exec("TRUNCATE TABLE LoanLogs");
    } catch (Throwable $e) {
        try { $pdo->exec("TRUNCATE TABLE loan_logs"); } catch (Throwable $ex) {}
    }
    header("Location: logs.php");
    exit;
}

// 1. Auto-create Tabel Audit Log jika belum ada
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS LoanLogs (
            LogID INT AUTO_INCREMENT PRIMARY KEY,
            LoanID INT NOT NULL,
            OldStatus VARCHAR(50) NULL,
            NewStatus VARCHAR(50) NOT NULL,
            Action VARCHAR(50) NOT NULL,
            ChangedAt DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB;
    ");
} catch (Throwable $e) {
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS loan_logs (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                loan_id INT NOT NULL,
                old_status VARCHAR(50) NULL,
                new_status VARCHAR(50) NOT NULL,
                action VARCHAR(50) NOT NULL,
                changed_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;
        ");
    } catch (Throwable $ex) {}
}

// 2. Ambil Mapping Data Transaksi Loans (ID => MemberID & ISBN)
$loansMap = [];
try { $rawL = $pdo->query("SELECT * FROM Loans")->fetchAll(); } 
catch (Throwable $e) { try { $rawL = $pdo->query("SELECT * FROM loans")->fetchAll(); } catch (Throwable $ex) { $rawL = []; } }

foreach ($rawL as $l) {
    $lId   = $l['LoanID']   ?? $l['loan_id']   ?? $l['id'] ?? '';
    $mId   = $l['MemberID'] ?? $l['member_id'] ?? '';
    $bIsbn = $l['ISBN']     ?? $l['isbn']      ?? '';
    if ($lId !== '') {
        $loansMap[(string)$lId] = ['member_id' => (string)$mId, 'isbn' => (string)$bIsbn];
    }
}

// 3. Ambil Mapping Members (NIM => Nama)
$membersMap = [];
try { $rawM = $pdo->query("SELECT * FROM Members")->fetchAll(); } 
catch (Throwable $e) { try { $rawM = $pdo->query("SELECT * FROM members")->fetchAll(); } catch (Throwable $ex) { $rawM = []; } }

foreach ($rawM as $m) {
    $mId   = $m['MemberID'] ?? $m['member_id'] ?? $m['id'] ?? '';
    $mName = $m['Name']     ?? $m['name']      ?? $m['nama'] ?? $mId;
    if ($mId !== '') { $membersMap[(string)$mId] = (string)$mName; }
}

// 4. Ambil Mapping Books (ISBN => Judul)
$booksMap = [];
try { $rawB = $pdo->query("SELECT * FROM Books")->fetchAll(); } 
catch (Throwable $e) { try { $rawB = $pdo->query("SELECT * FROM books")->fetchAll(); } catch (Throwable $ex) { $rawB = []; } }

foreach ($rawB as $b) {
    $bIsbn  = $b['ISBN']  ?? $b['isbn']  ?? '';
    $bTitle = $b['Title'] ?? $b['title'] ?? $b['judul'] ?? $bIsbn;
    if ($bIsbn !== '') { $booksMap[(string)$bIsbn] = (string)$bTitle; }
}

// 5. Ambil Seluruh Baris Audit Log
$logs = [];
try { $logs = $pdo->query("SELECT * FROM LoanLogs ORDER BY LogID DESC")->fetchAll(); } 
catch (Throwable $e) { 
    try { $logs = $pdo->query("SELECT * FROM loan_logs ORDER BY log_id DESC")->fetchAll(); } 
    catch (Throwable $ex) { $logs = []; } 
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Audit Log - Riwayat Transaksi</title>
</head>
<body>
    <h1>Audit Log (Riwayat Aktivitas Peminjaman)</h1>
    <form method="post" style="margin: 15px 0;" onsubmit="return confirm('Yakin ingin menghapus seluruh riwayat log?');">
        <button type="submit" name="clear_logs" style="background-color: #d9534f; color: white; border: none; padding: 8px 16px; cursor: pointer; border-radius: 4px;">
          🗑️ Bersihkan Semua Log
        </button>
    </form>
    <p>
        <a href="borrow.php">&laquo; Kembali ke Transaksi Peminjaman</a>
    </p>

    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Log ID</th>
                <th>Loan ID</th>
                <th>Anggota</th>
                <th>Buku</th>
                <th>Status Lama</th>
                <th>Status Baru</th>
                <th>Aksi</th>
                <th>Waktu Perubahan</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
            <tr>
                <td colspan="8">
                    Belum ada catatan aktivitas. Silakan coba Tambah Peminjaman, atau Edit Status di halaman Peminjaman terlebih dahulu untuk melihat catatan log baru!
                </td>
            </tr>
            <?php else: ?>
                <?php foreach ($logs as $log): ?>
                <?php 
                    $logId   = $log['LogID']      ?? $log['log_id']     ?? '';
                    $loanId  = $log['LoanID']     ?? $log['loan_id']    ?? '';
                    $oldStat = $log['OldStatus']  ?? $log['old_status'] ?? '-';
                    $newStat = $log['NewStatus']  ?? $log['new_status'] ?? '-';
                    $action  = $log['Action']     ?? $log['action']     ?? '-';
                    $time    = $log['ChangedAt']  ?? $log['changed_at'] ?? '-';

                    // Mapping pencarian Anggota & Judul Buku dari $loanId
                    $mId    = $loansMap[(string)$loanId]['member_id'] ?? '';
                    $bIsbn  = $loansMap[(string)$loanId]['isbn']      ?? '';

                    $memberName = $membersMap[(string)$mId] ?? ($mId ?: '-');
                    $bookTitle  = $booksMap[(string)$bIsbn] ?? ($bIsbn ?: '-');
                ?>
                <tr>
                    <td><?= e((string)$logId) ?></td>
                    <td>#<?= e((string)$loanId) ?></td>
                    <td><?= e((string)$memberName) ?></td>
                    <td><?= e((string)$bookTitle) ?></td>
                    <td><?= e((string)($oldStat ?: '-')) ?></td>
                    <td><b><?= e((string)$newStat) ?></b></td>
                    <td><code><?= e((string)$action) ?></code></td>
                    <td><?= e((string)$time) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>