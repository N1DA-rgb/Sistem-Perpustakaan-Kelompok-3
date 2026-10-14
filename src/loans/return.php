<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$error = '';$success = '';

// 1. Deteksi Nama Kolom Tabel Loans & Books Secara Dinamis
try {
    $colsLoansStmt =$pdo->query("SHOW COLUMNS FROM Loans");
    $colsLoans =$colsLoansStmt->fetchAll(PDO::FETCH_COLUMN);

    $loanIdCol = in_array('LoanID',$colsLoans) ? 'LoanID' : (in_array('loan_id', $colsLoans) ? 'loan_id' :$colsLoans[0]);
    $statusCol = in_array('Status',$colsLoans) ? 'Status' : (in_array('status', $colsLoans) ? 'status' : 'Status');$isbnColLoan = in_array('ISBN', $colsLoans) ? 'ISBN' : (in_array('isbn',$colsLoans) ? 'isbn' : 'ISBN');

    $colsBooksStmt =$pdo->query("SHOW COLUMNS FROM Books");
    $colsBooks = $colsBooksStmt->fetchAll(PDO::FETCH_COLUMN);$isbnColBook = in_array('ISBN', $colsBooks) ? 'ISBN' : (in_array('isbn',$colsBooks) ? 'isbn' : 'ISBN');
    $qtyColBook = in_array('Quantity',$colsBooks) ? 'Quantity' : (in_array('quantity', $colsBooks) ? 'quantity' : (in_array('stok',$colsBooks) ? 'stok' : 'Quantity'));
} catch (Exception $e) {
    $error = 'Gagal membaca struktur database: ' .$e->getMessage();
}

// 2. Proses Pengembalian Buku
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $loanId = filter_var($_POST['loan_id'] ?? '', FILTER_VALIDATE_INT);

    if (!$loanId) {$error = 'ID Peminjaman tidak valid!';
    } else {
        try {
            $pdo->beginTransaction();

            // Ambil data peminjaman pakai nama kolom dinamis
            $stmtCheck =$pdo->prepare("SELECT * FROM Loans WHERE `$loanIdCol` = ? FOR UPDATE");
            $stmtCheck->execute([$loanId]);
            $loan =$stmtCheck->fetch();

            if (!$loan) {
                throw new Exception("Data peminjaman tidak ditemukan!");
            }

            // Update status peminjaman
            $stmtReturn =$pdo->prepare("UPDATE Loans SET `$statusCol` = 'Dikembalikan' WHERE `$loanIdCol` = ?");
            $stmtReturn->execute([$loanId]);

            // Restock jumlah buku
            $isbn = $loan[$isbnColLoan] ?? $loan['ISBN'] ?? $loan['isbn'] ?? '';
            if ($isbn !== '') {
                $stmtUpdate =$pdo->prepare("UPDATE Books SET `$qtyColBook` = `$qtyColBook` + 1 WHERE `$isbnColBook` = ?");
                $stmtUpdate->execute([$isbn]);
            }

            $pdo->commit();$success = 'Buku berhasil dikembalikan dan stok bertambah!';
        } catch (Exception $e) {
            if ($pdo->inTransaction())$pdo->rollBack();
            $error = 'Gagal memproses pengembalian: ' .$e->getMessage();
        }
    }
}

// 3. Ambil Daftar Buku Yang Harus Dikembalikan
$activeLoans = [];
try {
    $stmtActive =$pdo->query("
        SELECT l.*, m.Name AS MemberName, b.Title AS BookTitle
        FROM Loans l
        LEFT JOIN Books b ON l.`$isbnColLoan` = b.`$isbnColBook`
        LEFT JOIN Members m ON l.MemberID = m.MemberID
        WHERE l.`$statusCol` IN ('Dipinjam', 'Borrowed', 'dipinjam', 'borrowed')
        ORDER BY l.`$loanIdCol` DESC
    ");
    $activeLoans =$stmtActive->fetchAll();
} catch (Throwable $e) {
    try {
        $stmtActive =$pdo->query("SELECT * FROM Loans WHERE `$statusCol` IN ('Dipinjam', 'Borrowed', 'dipinjam', 'borrowed') ORDER BY `$loanIdCol` DESC");
        $activeLoans =$stmtActive->fetchAll();
    } catch (Throwable $ex) {$activeLoans = [];
    }
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pengembalian Buku</title>
</head>
<body>
    <h1>Transaksi Pengembalian Buku</h1>
    <p>
        <a href="borrow.php">Peminjaman Buku</a> | 
        <a href="../books/list.php">Kelola Buku</a> | 
        <a href="../index.php">Katalog Utama</a>
    </p>

    <?php if ($error): ?><p style="color: red;"><?= e($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p style="color: green;"><?= e($success) ?></p><?php endif; ?>

    <h2>Daftar Buku Yang Harus Dikembalikan</h2>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>ID Pinjam</th>
                <th>Anggota</th>
                <th>Judul Buku</th>
                <th>Tanggal Pinjam</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($activeLoans)): ?>
            <tr><td colspan="5">Tidak ada peminjaman aktif yang perlu dikembalikan.</td></tr>
            <?php else: ?>
                <?php foreach ($activeLoans as$loan): ?>
                <?php $lId = $loan[$loanIdCol] ?? $loan['LoanID'] ?? $loan['loan_id'] ?? '-'; ?>
                <tr>
                    <td><?= e((string)$lId) ?></td>
                    <td><?= e((string)($loan['MemberName'] ?? $loan['MemberID'] ?? $loan['member_id'] ?? '-')) ?></td>
                    <td><?= e((string)($loan['BookTitle'] ?? $loan['ISBN'] ?? $loan['isbn'] ?? '-')) ?></td>
                    <td><?= e((string)($loan['BorrowDate'] ?? $loan['borrow_date'] ?? '-')) ?></td>
                    <td>
                        <form method="post" onsubmit="return confirm('Proses pengembalian buku ini?');">
                            <input type="hidden" name="loan_id" value="<?= e((string)$lId) ?>">
                            <button type="submit">Kembalikan Buku</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>