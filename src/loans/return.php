<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loanId = filter_var($_POST['loan_id'] ?? '', FILTER_VALIDATE_INT);

    if (!$loanId) {
        $error = 'ID Peminjaman tidak valid!';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare("SELECT * FROM Loans WHERE LoanID = ? OR loan_id = ? FOR UPDATE");
            $stmtCheck->execute([$loanId, $loanId]);
            $loan = $stmtCheck->fetch();

            if (!$loan) {
                throw new Exception("Data peminjaman tidak ditemukan!");
            }

            $stmtReturn = $pdo->prepare("UPDATE Loans SET Status = 'Dikembalikan' WHERE LoanID = ? OR loan_id = ?");
            $stmtReturn->execute([$loanId, $loanId]);

            $isbn = $loan['ISBN'] ?? $loan['isbn'] ?? '';
            $stmtUpdate = $pdo->prepare("UPDATE Books SET Quantity = Quantity + 1 WHERE ISBN = ? OR isbn = ?");
            $stmtUpdate->execute([$isbn, $isbn]);

            $pdo->commit();
            $success = 'Buku berhasil dikembalikan dan stok bertambah!';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Gagal memproses pengembalian: ' . $e->getMessage();
        }
    }
}

try {
    $activeLoans = $pdo->query("
        SELECT l.*, m.Name AS MemberName, b.Title AS BookTitle
        FROM Loans l
        LEFT JOIN Books b ON l.ISBN = b.ISBN
        LEFT JOIN Members m ON l.MemberID = m.MemberID
        WHERE l.Status IN ('Dipinjam', 'Borrowed', 'dipinjam', 'borrowed')
        ORDER BY 1 DESC
    ")->fetchAll();
} catch (Throwable $e) {
    $activeLoans = $pdo->query("SELECT * FROM Loans WHERE Status IN ('Dipinjam', 'Borrowed', 'dipinjam', 'borrowed') ORDER BY 1 DESC")->fetchAll();
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Pengembalian Buku</title></head>
<body>
    <h1>Transaksi Pengembalian Buku</h1>
    <p>
        <a href="borrow.php">Peminjaman Buku</a> | 
        <a href="../books/list.php">Kelola Buku</a>
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
                <?php foreach ($activeLoans as $loan): ?>
                <?php $lId = $loan['LoanID'] ?? $loan['loan_id'] ?? '-'; ?>
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