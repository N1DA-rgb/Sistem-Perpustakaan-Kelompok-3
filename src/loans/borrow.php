<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

// Fitur reset/bersihkan semua transaksi & log
if (isset($_GET['reset_all']) && $_GET['reset_all'] === 'true') {
    try { $pdo->exec("TRUNCATE TABLE Loans"); } catch (Throwable $e) { try { $pdo->exec("TRUNCATE TABLE loans"); } catch (Throwable $ex) {} }
    try { $pdo->exec("TRUNCATE TABLE LoanLogs"); } catch (Throwable $e) { try { $pdo->exec("TRUNCATE TABLE loan_logs"); } catch (Throwable $ex) {} }
    header("Location: borrow.php");
    exit;
}

$error = ''; $success = '';

// Auto-create tabel audit log jika belum ada
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS LoanLogs (LogID INT AUTO_INCREMENT PRIMARY KEY, LoanID INT NOT NULL, OldStatus VARCHAR(50) NULL, NewStatus VARCHAR(50) NOT NULL, Action VARCHAR(50) NOT NULL, ChangedAt DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;");
} catch (Throwable $e) {}

// 1. Map Members (Ambil SEMUA untuk mapping riwayat, tapi filter data aktif untuk dropdown)
$memberTable = 'Members';
try { $rawMemCols = $pdo->query("SHOW COLUMNS FROM Members")->fetchAll(PDO::FETCH_ASSOC); } 
catch (Throwable $e) { $memberTable = 'members'; try { $rawMemCols = $pdo->query("SHOW COLUMNS FROM members")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $ex) { $rawMemCols = []; } }
$memMap = []; foreach ($rawMemCols as $c) { $memMap[strtolower($c['Field'])] = $c['Field']; }
$colMemIdInMem   = $memMap['memberid'] ?? $memMap['member_id'] ?? $memMap['id'] ?? 'MemberID';
$colMemNameInMem = $memMap['name']     ?? $memMap['nama']      ?? 'Name';

$membersMap = []; $membersList = [];
try {
    $rawM = $pdo->query("SELECT * FROM {$memberTable} ORDER BY 1 ASC")->fetchAll();
    foreach ($rawM as $m) {
        $mId = $m[$colMemIdInMem] ?? ''; $mName = $m[$colMemNameInMem] ?? $mId;
        $isDel = (int)($m['is_deleted'] ?? 0);
        if ($mId !== '') { 
            $membersMap[(string)$mId] = (string)$mName; 
            if ($isDel === 0) {
                $membersList[] = ['id' => $mId, 'name' => $mName]; 
            }
        }
    }
} catch (Throwable $e) {}

// 2. Map Books (Ambil SEMUA untuk mapping riwayat, tapi filter data aktif untuk dropdown)
$bookTable = 'Books';
try { $rawBookCols = $pdo->query("SHOW COLUMNS FROM Books")->fetchAll(PDO::FETCH_ASSOC); } 
catch (Throwable $e) { $bookTable = 'books'; try { $rawBookCols = $pdo->query("SHOW COLUMNS FROM books")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $ex) { $rawBookCols = []; } }
$bookMap = []; foreach ($rawBookCols as $c) { $bookMap[strtolower($c['Field'])] = $c['Field']; }
$colIsbnInBook  = $bookMap['isbn']     ?? 'ISBN';
$colTitleInBook = $bookMap['title']    ?? $bookMap['judul'] ?? 'Title';
$colQtyInBook   = $bookMap['quantity'] ?? $bookMap['stok']  ?? 'Quantity';

$booksMap = []; $booksList = [];
try {
    $rawB = $pdo->query("SELECT * FROM {$bookTable} ORDER BY 1 ASC")->fetchAll();
    foreach ($rawB as $b) {
        $bIsbn = $b[$colIsbnInBook] ?? ''; $bTitle = $b[$colTitleInBook] ?? $bIsbn; $bQty = (int)($b[$colQtyInBook] ?? 0);
        $isDel = (int)($b['is_deleted'] ?? 0);
        if ($bIsbn !== '') { 
            $booksMap[(string)$bIsbn] = (string)$bTitle; 
            if ($bQty > 0 && $isDel === 0) { 
                $booksList[] = ['isbn' => $bIsbn, 'title' => $bTitle, 'qty' => $bQty]; 
            } 
        }
    }
} catch (Throwable $e) {}

// 3. Map Loans
$loanTable = 'Loans';
try { $rawLoanCols = $pdo->query("SHOW COLUMNS FROM Loans")->fetchAll(PDO::FETCH_ASSOC); } 
catch (Throwable $e) { $loanTable = 'loans'; try { $rawLoanCols = $pdo->query("SHOW COLUMNS FROM loans")->fetchAll(PDO::FETCH_ASSOC); } catch (Throwable $ex) { $rawLoanCols = []; } }
$loanMap = []; foreach ($rawLoanCols as $c) { $loanMap[strtolower($c['Field'])] = $c['Field']; }
$colLoanId   = $loanMap['loanid']     ?? $loanMap['loan_id']   ?? $loanMap['id'] ?? 'LoanID';
$colMemberId = $loanMap['memberid']   ?? $loanMap['member_id'] ?? 'MemberID';
$colIsbn     = $loanMap['isbn']       ?? 'ISBN';
$colStatus   = $loanMap['status']     ?? 'Status';
$colDate     = $loanMap['borrowdate'] ?? $loanMap['borrow_date'] ?? $loanMap['loandate'] ?? 'BorrowDate';

// 4. Proses Tambah Peminjaman Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = trim($_POST['member_id'] ?? '');
    $isbn     = trim($_POST['isbn'] ?? '');
    $status   = trim($_POST['status'] ?? 'Borrowed');

    if (!$memberId || !$isbn) {
        $error = 'ID Anggota dan Buku wajib dipilih!';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtStock = $pdo->prepare("UPDATE {$bookTable} SET {$colQtyInBook} = {$colQtyInBook} - 1 WHERE {$colIsbnInBook} = ? AND {$colQtyInBook} > 0");
            $stmtStock->execute([$isbn]);

            if ($stmtStock->rowCount() === 0) {
                throw new Exception("Stok buku habis atau tidak ditemukan!");
            }

            $sqlInsert = "INSERT INTO {$loanTable} ({$colMemberId}, {$colIsbn}, {$colDate}, {$colStatus}) VALUES (?, ?, NOW(), ?)";
            $stmtLoan  = $pdo->prepare($sqlInsert);
            $stmtLoan->execute([$memberId, $isbn, $status]);

            $newLoanId = (int)$pdo->lastInsertId();

            // Insert ke Audit Log
            try {
                $stmtLog = $pdo->prepare("INSERT INTO LoanLogs (LoanID, OldStatus, NewStatus, Action, ChangedAt) VALUES (?, NULL, ?, 'PEMINJAMAN_BARU', NOW())");
                $stmtLog->execute([$newLoanId, $status]);
            } catch (Throwable $ex) {
                try {
                    $stmtLog = $pdo->prepare("INSERT INTO loan_logs (loan_id, old_status, new_status, action, changed_at) VALUES (?, NULL, ?, 'PEMINJAMAN_BARU', NOW())");
                    $stmtLog->execute([$newLoanId, $status]);
                } catch (Throwable $eLog) {}
            }

            $pdo->commit();
            header("Location: borrow.php");
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Gagal memproses peminjaman: ' . $e->getMessage();
        }
    }
}

// 5. Ambil Daftar Transaksi Peminjaman
try { $loans = $pdo->query("SELECT * FROM {$loanTable} ORDER BY {$colLoanId} DESC")->fetchAll(); } 
catch (Throwable $e) { $loans = []; }

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Peminjaman Buku</title></head>
<body>
    <h1>Transaksi Peminjaman Buku</h1>
    <p>
        <a href="../books/list.php">Kelola Buku</a> | 
        <a href="return.php">Pengembalian</a> |
        <a href="logs.php" style="font-weight: bold; color: green;">[Audit Log / Riwayat Transaksi]</a>
    </p>

    <?php if ($error): ?><p style="color: red;"><?= e($error) ?></p><?php endif; ?>

    <h2>Form Peminjaman</h2>
    <form method="post">
        <p>
            <label>Pilih Anggota:<br>
                <select name="member_id" required>
                    <option value="">-- Pilih Anggota --</option>
                    <?php foreach ($membersList as $m): ?>
                        <option value="<?= e((string)$m['id']) ?>"><?= e((string)$m['name']) ?> (<?= e((string)$m['id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <p>
            <label>Pilih Buku:<br>
                <select name="isbn" required>
                    <option value="">-- Pilih Buku --</option>
                    <?php foreach ($booksList as $b): ?>
                        <option value="<?= e((string)$b['isbn']) ?>"><?= e((string)$b['title']) ?> (Stok: <?= $b['qty'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <p>
            <label>Status Initial:<br>
                <select name="status">
                    <option value="Borrowed">Borrowed</option>
                    <option value="Dipinjam">Dipinjam</option>
                </select>
            </label>
        </p>
        <button type="submit">Pinjam Buku</button>
    </form>

    <hr>

    <h2>Daftar Peminjaman Aktif</h2>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>ID Pinjam</th>
                <th>Anggota</th>
                <th>Buku</th>
                <th>Tanggal Pinjam</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($loans)): ?>
            <tr><td colspan="6">Belum ada peminjaman aktif.</td></tr>
            <?php else: ?>
                <?php foreach ($loans as $loan): ?>
                <?php 
                    $lId    = $loan[$colLoanId]   ?? '';
                    $mId    = $loan[$colMemberId] ?? '';
                    $bIsbn  = $loan[$colIsbn]     ?? '';
                    $date   = $loan[$colDate]     ?? '-';
                    $status = $loan[$colStatus]   ?? '-';

                    $memberName = $membersMap[(string)$mId] ?? $mId;
                    $bookTitle  = $booksMap[(string)$bIsbn] ?? $bIsbn;
                ?>
                <tr>
                    <td><?= e((string)$lId) ?></td>
                    <td><?= e((string)$memberName) ?></td>
                    <td><?= e((string)$bookTitle) ?></td>
                    <td><?= e((string)$date) ?></td>
                    <td><?= e((string)$status) ?></td>
                    <td>
                        <a href="edit_status.php?id=<?= urlencode((string)$lId) ?>">Edit Status</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>