<?php
declare(strict_types=1);

// Aktifkan laporan error sementara untuk mendeteksi masalah
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

// 1. Cari lokasi file koneksi database secara fleksibel
$dbPath = __DIR__ . '/config/db.php';
if (!file_exists($dbPath)) {
    $dbPath = __DIR__ . '/src/config/db.php';
}

if (!file_exists($dbPath)) {
    die("<h3 style='color:red;'>Error: File db.php tidak ditemukan di /config/db.php atau /src/config/db.php!</h3>");
}

require_once $dbPath;

// 2. Deteksi nama tabel Books / books secara dinamis
$bookTable = 'Books';
try {
    $rawCols = $pdo->query("SHOW COLUMNS FROM Books")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $bookTable = 'books';
    try {
        $rawCols = $pdo->query("SHOW COLUMNS FROM books")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) { 
        $rawCols = []; 
    }
}

// 3. Garansi kolom is_deleted ada di DB
try { $pdo->exec("ALTER TABLE {$bookTable} ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}

// 4. Mapping nama kolom dinamis
$colMap = [];
foreach ($rawCols as $c) { $colMap[strtolower($c['Field'])] = $c['Field']; }

$colTitle  = $colMap['title']     ?? $colMap['judul']     ?? 'Title';
$colAuthor = $colMap['author']    ?? $colMap['pengarang'] ?? $colMap['penulis'] ?? 'Author';
$colGenre  = $colMap['genre']     ?? $colMap['kategori']  ?? $colMap['publisher'] ?? $colMap['penerbit'] ?? null;
$colQty    = $colMap['quantity']  ?? $colMap['stok']      ?? $colMap['qty'] ?? 'Quantity';

// 5. Query data buku (Mencakup is_deleted = 0 ATAU NULL)
$sql = "SELECT * FROM {$bookTable} WHERE (is_deleted = 0 OR is_deleted IS NULL)";
$rawBooks = [];
try {
    $rawBooks = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    try {
        $rawBooks = $pdo->query("SELECT * FROM {$bookTable}")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {
        $rawBooks = [];
    }
}

// 6. Normalisasi data agar pas dengan variabel HTML Frontend ($b['Title'], $b['Author'], dll)
$books = [];
foreach ($rawBooks as $row) {
    $q = (int)($row[$colQty] ?? $row['Quantity'] ?? $row['stok'] ?? 1);
    
    // Tampilkan buku yang stoknya di atas 0
    if ($q > 0) {
        $books[] = [
            'Title'    => (string)($row[$colTitle]  ?? $row['Title'] ?? $row['judul'] ?? 'Tanpa Judul'),
            'Author'   => (string)($row[$colAuthor] ?? $row['Author'] ?? $row['pengarang'] ?? '-'),
            'Genre'    => (string)($colGenre ? ($row[$colGenre] ?? '-') : '-'),
            'Quantity' => $q
        ];
    }
}
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
  
    <body>
    <!-- 1. BARIS h1 LAMA DIGANTI DENGAN BLOK DIV HEADER INI -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h1 style="margin: 0;">Katalog Pustaka Digital</h1>
        <div>
            <a href="books/list.php" style="background-color: #14756e; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-weight: bold; margin-right: 8px;">Kelola Buku</a>
            <a href="members/list.php" style="background-color: #4a5568; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; font-weight: bold;">Kelola Anggota</a>
        </div>
    </div>

    <!-- 2. DIV GRID DIBIARKAN TETAP ADA DI BAWAHNYA (JANGAN DIHAPUS) -->
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