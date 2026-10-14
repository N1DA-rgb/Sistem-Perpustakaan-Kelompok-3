<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$id = trim($_GET['id'] ?? '');
$error = '';

if ($id === '') {
    header("Location: borrow.php");
    exit;
}

// 1. Deteksi Struktur Tabel & Kolom LOANS
$loanTable = 'Loans';
try { $rawLoanCols = $pdo->query("SHOW COLUMNS FROM Loans")->fetchAll(PDO::FETCH_ASSOC); } 
catch (Throwable $e) { $loanTable = 'loans'; try { $rawLoanCols = $pdo->query("SHOW COLUMNS FROM loans")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $ex) { $rawLoanCols = []; } }
$loanMap = []; foreach ($rawLoanCols as $c) { $loanMap[strtolower($c['Field'])] = $c['Field']; }
$colLoanId   = $loanMap['loanid']     ?? $loanMap['loan_id']   ?? $loanMap['id'] ?? 'LoanID';
$colMemberId = $loanMap['memberid']   ?? $loanMap['member_id'] ?? 'MemberID';
$colIsbn     = $loanMap['isbn']       ?? 'ISBN';
$colStatus   = $loanMap['status']     ?? 'Status';
$colDate     = $loanMap['borrowdate'] ?? $loanMap['borrow_date'] ?? $loanMap['loandate'] ?? 'BorrowDate';

// 2. Ambil Transaksi Spesifik
$stmt = $pdo->prepare("SELECT * FROM {$loanTable} WHERE {$colLoanId} = ?");
$stmt->execute([$id]);
$loan = $stmt->fetch();

if (!$loan) {
    die("Data peminjaman tidak ditemukan!");
}

$lId        = $loan[$colLoanId]   ?? $id;
$mId        = $loan[$colMemberId] ?? '';
$bIsbn      = $loan[$colIsbn]     ?? '';
$borrowDate = $loan[$colDate]     ?? '-';
$curStatus  = $loan[$colStatus]   ?? 'Borrowed';

// 3. Nama Anggota
$memberTable = 'Members';
try { $rawMemCols = $pdo->query("SHOW COLUMNS FROM Members")->fetchAll(PDO::FETCH_ASSOC); } 
catch (Throwable $e) { $memberTable = 'members'; try { $rawMemCols = $pdo->query("SHOW COLUMNS FROM members")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $ex) { $rawMemCols = []; } }
$memMap = []; foreach ($rawMemCols as $c) { $memMap[strtolower($c['Field'])] = $c['Field']; }
$colMemIdInMem   = $memMap['memberid'] ?? $memMap['member_id'] ?? $memMap['id'] ?? 'MemberID';
$colMemNameInMem = $memMap['name']     ?? $memMap['nama']      ?? 'Name';

$memberName = $mId;
if ($mId !== '') {
    $stmtM = $pdo->prepare("SELECT {$colMemNameInMem} FROM {$memberTable} WHERE {$colMemIdInMem} = ?");
    $stmtM->execute([$mId]);
    if ($mM = $stmtM->fetch()) { $memberName = $mM[$colMemNameInMem] ?? $mId; }
}

// 4. Judul Buku
$bookTable = 'Books';
try { $rawBookCols = $pdo->query("SHOW COLUMNS FROM Books")->fetchAll(PDO::FETCH_ASSOC); } 
catch (Throwable $e) { $bookTable = 'books'; try { $rawBookCols = $pdo->query("SHOW COLUMNS FROM books")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $ex) { $rawBookCols = []; } }
$bookMap = []; foreach ($rawBookCols as $c) { $bookMap[strtolower($c['Field'])] = $c['Field']; }
$colIsbnInBook  = $bookMap['isbn']     ?? 'ISBN';
$colTitleInBook = $bookMap['title']    ?? $bookMap['judul'] ?? 'Title';
$colQtyInBook   = $bookMap['quantity'] ?? $bookMap['stok']  ?? 'Quantity';

$bookTitle = $bIsbn;
if ($bIsbn !== '') {
    $stmtB = $pdo->prepare("SELECT {$colTitleInBook} FROM {$bookTable} WHERE {$colIsbnInBook} = ?");
    $stmtB->execute([$bIsbn]);
    if ($bB = $stmtB->fetch()) { $bookTitle = $bB[$colTitleInBook] ?? $bIsbn; }
}

// 5. Proses Update Status & Catat Audit Log
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = trim($_POST['status'] ?? '');

    if ($newStatus === '') {
        $error = 'Status wajib dipilih!';
    } else {
        try {
            $pdo->beginTransaction();

            $isOldReturned = in_array(strtolower((string)$curStatus), ['dikembalikan', 'returned', 'kembali']);
            $isNewReturned = in_array(strtolower($newStatus), ['dikembalikan', 'returned', 'kembali']);

            // Penyesuaian stok buku
            if (!$isOldReturned && $isNewReturned) {
                $stmtStock = $pdo->prepare("UPDATE {$bookTable} SET {$colQtyInBook} = {$colQtyInBook} + 1 WHERE {$colIsbnInBook} = ?");
                $stmtStock->execute([$bIsbn]);
            } elseif ($isOldReturned && !$isNewReturned) {
                $stmtStock = $pdo->prepare("UPDATE {$bookTable} SET {$colQtyInBook} = {$colQtyInBook} - 1 WHERE {$colIsbnInBook} = ?");
                $stmtStock->execute([$bIsbn]);
            }

            // Update status transaksi
            $stmtUpdate = $pdo->prepare("UPDATE {$loanTable} SET {$colStatus} = ? WHERE {$colLoanId} = ?");
            $stmtUpdate->execute([$newStatus, $lId]);

            // Insert ke Audit Log jika status berubah
            if (strtolower((string)$curStatus) !== strtolower($newStatus)) {
                try {
                    $stmtLog = $pdo->prepare("INSERT INTO LoanLogs (LoanID, OldStatus, NewStatus, Action, ChangedAt) VALUES (?, ?, ?, 'UBAH_STATUS', NOW())");
                    $stmtLog->execute([$lId, $curStatus, $newStatus]);
                } catch (Throwable $ex) {
                    try {
                        $stmtLog = $pdo->prepare("INSERT INTO loan_logs (loan_id, old_status, new_status, action, changed_at) VALUES (?, ?, ?, 'UBAH_STATUS', NOW())");
                        $stmtLog->execute([$lId, $curStatus, $newStatus]);
                    } catch (Throwable $eLog) {}
                }
            }

            $pdo->commit();
            header("Location: borrow.php");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Gagal mengubah status: ' . $e->getMessage();
        }
    }
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Edit Status Peminjaman</title>
</head>
<body>
    <h1>Edit Status Peminjaman #<?= e((string)$lId) ?></h1>
    <p><a href="borrow.php">&laquo; Kembali ke Daftar Peminjaman</a></p>

    <?php if ($error): ?><p style="color: red;"><?= e($error) ?></p><?php endif; ?>

    <table border="1" cellpadding="8" cellspacing="0" style="margin-bottom: 15px;">
        <tr>
            <th>Anggota</th>
            <td><?= e((string)$memberName) ?></td>
        </tr>
        <tr>
            <th>Judul Buku</th>
            <td><?= e((string)$bookTitle) ?></td>
        </tr>
        <tr>
            <th>Tanggal Pinjam</th>
            <td><?= e((string)$borrowDate) ?></td>
        </tr>
        <tr>
            <th>Status Saat Ini</th>
            <td><b><?= e((string)$curStatus) ?></b></td>
        </tr>
    </table>

    <form method="post">
        <p>
            <label>Pilih Status Baru:<br>
                <select name="status" required>
                    <option value="Borrowed" <?= in_array(strtolower((string)$curStatus), ['borrowed', 'dipinjam']) ? 'selected' : '' ?>>Borrowed / Dipinjam</option>
                    <option value="Returned" <?= in_array(strtolower((string)$curStatus), ['returned', 'dikembalikan']) ? 'selected' : '' ?>>Returned / Dikembalikan</option>
                </select>
            </label>
        </p>
        <button type="submit">Update Status</button>
    </form>
</body>
</html>