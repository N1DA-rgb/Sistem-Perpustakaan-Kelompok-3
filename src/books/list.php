<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';

// 1. Deteksi nama tabel Books / books secara dinamis
$bookTable = 'Books';
try {
    $rawCols = $pdo->query("SHOW COLUMNS FROM Books")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $bookTable = 'books';
    try {
        $rawCols = $pdo->query("SHOW COLUMNS FROM books")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) { $rawCols = []; }
}

// GARANSI 100%: Buat kolom publisher & is_deleted jika belum ada
try { $pdo->exec("ALTER TABLE {$bookTable} ADD COLUMN publisher VARCHAR(255) NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE {$bookTable} ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}

// Refresh peta kolom database
try {
    $rawCols = $pdo->query("SHOW COLUMNS FROM {$bookTable}")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $rawCols = []; }

$colMap = [];
foreach ($rawCols as $c) { $colMap[strtolower($c['Field'])] = $c['Field']; }

$colIsbn      = $colMap['isbn']      ?? $colMap['id'] ?? 'ISBN';
$colTitle     = $colMap['title']     ?? $colMap['judul'] ?? 'Title';
$colAuthor    = $colMap['author']    ?? $colMap['pengarang'] ?? $colMap['penulis'] ?? 'Author';
$colPublisher = $colMap['publisher'] ?? $colMap['penerbit']  ?? $colMap['penerbit_buku'] ?? 'publisher';
$colQty       = $colMap['quantity']  ?? $colMap['stok']      ?? $colMap['qty'] ?? 'Quantity';

// 2. Ambil data semua buku yang aktif
$stmt = $pdo->query("SELECT * FROM {$bookTable} WHERE is_deleted = 0 ORDER BY {$colTitle} ASC");
$rawBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// NORMALISASI DATA: Backend menyerap & merapikan variabel agar UI dev tinggal panggil dengan mudah
$books = [];
foreach ($rawBooks as $row) {
    // Ambil isi penerbit dari berbagai variasi nama kolom jika ada
    $pubValue = $row[$colPublisher] ?? $row['publisher'] ?? $row['Publisher'] ?? $row['penerbit'] ?? '';

    $books[] = [
        'isbn'      => (string)($row[$colIsbn] ?? ''),
        'title'     => (string)($row[$colTitle] ?? ''),
        'author'    => (string)($row[$colAuthor] ?? $row['author'] ?? $row['pengarang'] ?? ''),
        'publisher' => (string)$pubValue,
        'quantity'  => (int)($row[$colQty] ?? $row['quantity'] ?? 0)
    ];
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 3. Panggil File View Tampilan ---
$viewPath = __DIR__ . '/../views/books/list.view.php';

// Jika tidak ada di folder views/books/, cek di folder yang sama (books/)
if (!file_exists($viewPath)) {
    $viewPath = __DIR__ . '/list.view.php';
}

// Jika di kedua folder tetap tidak ada, tampilkan pesan petunjuk
if (!file_exists($viewPath)) {
    die("
        <div style='font-family:sans-serif; padding:20px; background:#fff3cd; color:#856404; border:1px solid #ffeeba; border-radius:5px;'>
            <h2>⚠️ File View Tidak Ditemukan!</h2>
            <p>PHP mencari file <code>list.view.php</code> tetapi tidak menemukannya.</p>
            <p><strong>Langkah Perbaikan:</strong> Pastikan kamu sudah membuat file bernama <code>list.view.php</code> dan ditaruh di salah satu lokasi ini:</p>
            <ul>
                <li><code>/var/www/html/views/books/list.view.php</code></li>
                <li><code>/var/www/html/books/list.view.php</code></li>
            </ul>
        </div>
    ");
}

require_once $viewPath;