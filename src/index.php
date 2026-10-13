
<?php

require_once __DIR__ . '/config/db.php';

$databaseStatus = 'Terhubung ke database LibraryDB';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pustaka Digital</title>
</head>
<body>
    <h1>Pustaka Digital</h1>
    <p>Sistem Perpustakaan Berbasis Web</p>

    <h2>Status Koneksi</h2>
    <p><?= htmlspecialchars($databaseStatus, ENT_QUOTES, 'UTF-8') ?></p>
</body>
</html>