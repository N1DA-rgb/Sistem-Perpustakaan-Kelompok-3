<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';

$id = trim($_GET['id'] ?? '');
$error = '';

if ($id === '') {
    header("Location: list.php");
    exit;
}

$tableName = 'Members';
try {
    $rawCols = $pdo->query("SHOW COLUMNS FROM Members")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tableName = 'members';
    $rawCols = $pdo->query("SHOW COLUMNS FROM members")->fetchAll(PDO::FETCH_ASSOC);
}

$colMap = [];
foreach ($rawCols as $c) {
    $colMap[strtolower($c['Field'])] = $c['Field'];
}

$colId    = $colMap['memberid'] ?? $colMap['member_id'] ?? $colMap['id'] ?? 'MemberID';
$colName  = $colMap['name']     ?? $colMap['nama']      ?? 'Name';
$colEmail = $colMap['email']    ?? null;
$colPhone = $colMap['phone']    ?? $colMap['telepon']   ?? null;

$stmt = $pdo->prepare("SELECT * FROM {$tableName} WHERE {$colId} = ?");
$stmt->execute([$id]);
$member = $stmt->fetch();

if (!$member) {
    die("Data Anggota tidak ditemukan!");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($name === '') {
        $error = 'Nama Anggota tidak boleh kosong!';
    } else {
        try {
            $setParts = ["{$colName} = ?"];
            $params   = [$name];

            if ($colEmail) {
                $setParts[] = "{$colEmail} = ?";
                $params[]   = $email;
            }
            if ($colPhone) {
                $setParts[] = "{$colPhone} = ?";
                $params[]   = $phone;
            }

            $params[] = $id;

            $sql = "UPDATE {$tableName} SET " . implode(', ', $setParts) . " WHERE {$colId} = ?";
            $stmtUpdate = $pdo->prepare($sql);
            $stmtUpdate->execute($params);

            header("Location: list.php");
            exit;
        } catch (Throwable $e) {
            $error = 'Gagal mengupdate anggota: ' . $e->getMessage();
        }
    }
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Edit Anggota</title></head>
<body>
    <h1>Edit Data Anggota</h1>
    <p><a href="list.php">&laquo; Kembali</a></p>
    <?php if ($error): ?><p style="color: red;"><?= e($error) ?></p><?php endif; ?>

    <form method="post">
        <p><label>ID Anggota (Readonly):<br><input type="text" value="<?= e((string)$id) ?>" readonly></label></p>
        <p><label>Nama Lengkap:<br><input type="text" name="name" value="<?= e((string)($member[$colName] ?? '')) ?>" required></label></p>
        <?php if ($colEmail): ?>
        <p><label>Email:<br><input type="email" name="email" value="<?= e((string)($member[$colEmail] ?? '')) ?>"></label></p>
        <?php endif; ?>
        <?php if ($colPhone): ?>
        <p><label>No. Telepon:<br><input type="text" name="phone" value="<?= e((string)($member[$colPhone] ?? '')) ?>"></label></p>
        <?php endif; ?>
        <button type="submit">Update Anggota</button>
    </form>
</body>
</html>