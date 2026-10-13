<?php
declare(strict_types=1);

// Memuat koneksi database PDO dari src/config/db.php
require_once __DIR__ . '/config/db.php';

// Mengambil data buku yang stoknya > 0
$books = $pdo->query('SELECT Title, Author, Genre, Quantity FROM Books WHERE Quantity > 0 ORDER BY Title')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pustaka Digital - Katalog</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #2a2a2a; }
        h1 { color: #14756e; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
        .card { border: 1px solid #ddd; border-left: 6px solid #14756e; padding: 1rem; border-radius: 6px; }
        .stok { color: #6a3b68; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Katalog Pustaka Digital</h1>
    <div class="grid">
        <?php foreach ($books as $b): ?>
            <article class="card">
                <h3><?= htmlspecialchars($b['Title']) ?></h3>
                <p><?= htmlspecialchars($b['Author']) ?> | <?= htmlspecialchars($b['Genre'] ?? '-') ?></p>
                <p class="stok">Tersedia: <?= (int) $b['Quantity'] ?> eksemplar</p>
            </article>
        <?php endforeach; ?>
    </div>
</body>
</html>
