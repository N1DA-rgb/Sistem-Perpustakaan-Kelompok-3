<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$error = '';$success = '';

// 1. Deteksi Kolom & Perbaikan Struktur Tabel secara Dinamis
$loanIdCol = 'LoanID';
$statusCol = 'Status';$isbnColLoan = 'ISBN';
$returnDateCol = '';$hasReturnDate = false;

$isbnColBook = 'ISBN';$qtyColBook = 'Quantity';

try {
    $colsLoansStmt =$pdo->query("SHOW FULL COLUMNS FROM Loans");
    $colsLoans =$colsLoansStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($colsLoans as$col) {
        $field =$col['Field'];
        if (strcasecmp($field, 'LoanID') === 0 OR strcasecmp($field, 'loan_id') === 0) {
            $loanIdCol =$field;
        }
        if (strcasecmp($field, 'Status') === 0 OR strcasecmp($field, 'status') === 0) {
            $statusCol =$field;
        }
        if (strcasecmp($field, 'ISBN') === 0 OR strcasecmp($field, 'isbn') === 0) {
            $isbnColLoan =$field;
        }
        if (strcasecmp($field, 'ReturnDate') === 0 OR strcasecmp($field, 'return_date') === 0 OR strcasecmp($field, 'tgl_kembali') === 0) {
            $returnDateCol =$field;
            $hasReturnDate = true;
        }
    }

    try {
        $pdo->exec("ALTER TABLE Loans MODIFY `$statusCol` VARCHAR(50) DEFAULT 'Dipinjam'");
    } catch (Throwable $e) {
        // Abaikan jika tidak ada izin ALTER
    }

    $colsBooksStmt =$pdo->query("SHOW FULL COLUMNS FROM Books");
    $colsBooks =$colsBooksStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($colsBooks as$col) {
        $field =$col['Field'];
        if (strcasecmp($field, 'ISBN') === 0 OR strcasecmp($field, 'isbn') === 0) {
            $isbnColBook =$field;
        }
        if (strcasecmp($field, 'Quantity') === 0 OR strcasecmp($field, 'quantity') === 0 OR strcasecmp($field, 'stok') === 0) {
            $qtyColBook =$field;
        }
    }
} catch (Throwable $e) {
    // Fallback
}

// 2. Proses Pengembalian Buku
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loanId = filter_var($_POST['loan_id'] ?? '', FILTER_VALIDATE_INT);

    if (!$loanId) {$error = 'ID Peminjaman tidak valid!';
    } else {
        try {
            $pdo->beginTransaction();

            $stmtCheck =$pdo->prepare("SELECT * FROM Loans WHERE `$loanIdCol` = ? FOR UPDATE");
            $stmtCheck->execute([$loanId]);
            $loan =$stmtCheck->fetch();

            if (!$loan) {
                throw new Exception("Data peminjaman tidak ditemukan!");
            }

            $statusOptions = ['Dikembalikan', 'Kembali', 'Returned', 'Selesai', '0'];$updated = false;
            $lastException = null;

            foreach ($statusOptions as$statusVal) {
                try {
                    if ($hasReturnDate) {
                        $stmtReturn =$pdo->prepare("UPDATE Loans SET `$statusCol` = ?, `$returnDateCol` = CURDATE() WHERE `$loanIdCol` = ?");
                        $stmtReturn->execute([$statusVal,$loanId]);
                    } else {
                        $stmtReturn =$pdo->prepare("UPDATE Loans SET `$statusCol` = ? WHERE `$loanIdCol` = ?");
                        $stmtReturn->execute([$statusVal,$loanId]);
                    }
                    $updated = true;
                    break;
                } catch (Throwable $ex) {
                    $lastException =$ex;
                    // Jika gagal saat mengupdate ReturnDate, coba update statusnya saja
                    if ($hasReturnDate) {
                        try {
                            $stmtReturn =$pdo->prepare("UPDATE Loans SET `$statusCol` = ? WHERE `$loanIdCol` = ?");
                            $stmtReturn->execute([$statusVal, $loanId]);$updated = true;
                            break;
                        } catch (Throwable $ex2) {
                            $lastException =$ex2;
                        }
                    }
                }
            }

            if (!$updated) {$msg = $lastException ? $lastException->getMessage() : 'Error DB tidak diketahui';
                throw new Exception("Gagal update status: " . $msg);
            }

            $isbn = $loan[$isbnColLoan] ?? $loan['ISBN'] ?? $loan['isbn'] ?? '';
            if ($isbn !== '') {
                $stmtUpdate =$pdo->prepare("UPDATE Books SET `$qtyColBook` = `$qtyColBook` + 1 WHERE `$isbnColBook` = ?");
                $stmtUpdate->execute([$isbn]);
            }

            $pdo->commit();$success = 'Buku berhasil dikembalikan dan stok bertambah!';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {$pdo->rollBack();
            }
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
        WHERE l.`$statusCol` IN ('Dipinjam', 'Borrowed', 'dipinjam', 'borrowed', '1')
        ORDER BY l.`$loanIdCol` DESC
    ");
    $activeLoans =$stmtActive->fetchAll();
} catch (Throwable $e) {
    try {
        $stmtActive =$pdo->query("SELECT * FROM Loans WHERE `$statusCol` IN ('Dipinjam', 'Borrowed', 'dipinjam', 'borrowed', '1') ORDER BY `$loanIdCol` DESC");
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
        <a href="borrow.php">Peminjaman Buku</a> &bull; 
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