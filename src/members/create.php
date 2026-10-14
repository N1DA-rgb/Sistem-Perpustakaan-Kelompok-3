<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';

// 1. Deteksi nama tabel dan kolom Members secara dinamis
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

// 2. Proses Tambah / Re-aktivasi Anggota
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = trim($_POST['member_id'] ?? $_POST['id'] ?? '');
    $name  = trim($_POST['name'] ?? $_POST['nama'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? $_POST['telepon'] ?? '');

    if ($id === '' || $name === '') {
        $error = 'ID Anggota dan Nama wajib diisi!';
    } else {
        try {
            // Cek apakah ID Anggota sudah pernah ada di database
            $stmtCheck = $pdo->prepare("SELECT * FROM {$memberTable} WHERE {$colId} = ?");
            $stmtCheck->execute([$id]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $isDel = (int)($existing['is_deleted'] ?? 0);
                if ($isDel === 1) {
                    // Jika pernah di-soft delete, aktifkan kembali (Restore) & perbarui datanya
                    $updateFields = ["{$colName} = ?", "is_deleted = 0"];
                    $updateParams = [$name];

                    if ($colEmail) { $updateFields[] = "{$colEmail} = ?"; $updateParams[] = $email; }
                    if ($colPhone) { $updateFields[] = "{$colPhone} = ?"; $updateParams[] = $phone; }
                    $updateParams[] = $id;

                    $sqlRestore = "UPDATE {$memberTable} SET " . implode(', ', $updateFields) . " WHERE {$colId} = ?";
                    $pdo->prepare($sqlRestore)->execute($updateParams);

                    header("Location: list.php");
                    exit;
                } else {
                    $error = "Gagal! ID / NIM Anggota '{$id}' sudah terdaftar dan masih aktif di sistem.";
                }
            } else {
                // Input baru
                $fields = [$colId, $colName];
                $params = [$id, $name];

                if ($colEmail && $email !== '') { $fields[] = $colEmail; $params[] = $email; }
                if ($colPhone && $phone !== '') { $fields[] = $colPhone; $params[] = $phone; }

                $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                $fieldNames   = implode(', ', $fields);

                $sqlInsert = "INSERT INTO {$memberTable} ({$fieldNames}) VALUES ({$placeholders})";
                $pdo->prepare($sqlInsert)->execute($params);

                header("Location: list.php");
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Tambah Anggota</title></head>
<body>
    <h1>Tambah Anggota Baru</h1>
    <p><a href="list.php">&laquo; Kembali ke Daftar Anggota</a></p>

    <?php if ($error): ?>
        <p style="color: red; font-weight: bold;"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <p><label>ID / NIM Anggota:<br><input type="text" name="member_id" required></label></p>
        <p><label>Nama Lengkap:<br><input type="text" name="name" required></label></p>
        <?php if ($colEmail): ?>
            <p><label>Email:<br><input type="email" name="email"></label></p>
        <?php endif; ?>
        <?php if ($colPhone): ?>
            <p><label>No. HP / Telepon:<br><input type="text" name="phone"></label></p>
        <?php endif; ?>
        <button type="submit">Simpan Anggota</button>
    </form>
</body>
</html>