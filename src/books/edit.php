<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';

$id    = trim($_GET['id'] ?? '');
$error = '';

if ($id === '') {
    header("Location: list.php");
    exit;
}

// 1. Deteksi tabel
$bookTable = 'Books';
try {
    $rawCols = $pdo->query("SHOW COLUMNS FROM Books")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $bookTable = 'books';
    try {
        $rawCols = $pdo->query("SHOW COLUMNS FROM books")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) { $rawCols = []; }
}

// GARANSI: Buat kolom publisher jika belum ada
try { $pdo->exec("ALTER TABLE {$bookTable} ADD COLUMN publisher VARCHAR(255) NULL"); } catch (Throwable $e) {}

try {
    $rawCols = $pdo->query("SHOW COLUMNS FROM {$bookTable}")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { $rawCols = []; }

$colMap = [];
foreach ($rawCols as $c) { $colMap[strtolower($c['Field'])] = $c['Field']; }

$colIsbn      = $colMap['isbn']      ?? $colMap['id'] ?? 'ISBN';
$colTitle     = $colMap['title']     ?? $colMap['judul'] ?? 'Title';
$colAuthor    = $colMap['author']    ?? $colMap['pengarang'] ?? $colMap['penulis'] ?? 'Author';
$colPublisher = $colMap['publisher'] ?? $colMap['penerbit']  ?? 'publisher';
$colQty       = $colMap['quantity']  ?? $colMap['stok']      ?? $colMap['qty'] ?? 'Quantity';

// 2. Ambil data buku
$stmt = $pdo->prepare("SELECT * FROM {$bookTable} WHERE {$colIsbn} = ?");
$stmt->execute([$id]);
$book = $stmt->fetch();

if (!$book) {
    header("Location: list.php");
    exit;
}

// 3. Proses Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = trim($_POST['title'] ?? '');
    $author    = trim($_POST['author'] ?? '');
    $publisher = trim($_POST['publisher'] ?? '');
    $qty       = (int)($_POST['quantity'] ?? 0);

    if ($title === '') {
        $error = 'Judul Buku tidak boleh kosong!';
    } else {
        try {
            $sqlUpdate = "UPDATE {$bookTable} SET {$colTitle} = ?, {$colAuthor} = ?, {$colPublisher} = ?, {$colQty} = ? WHERE {$colIsbn} = ?";
            $pdo->prepare($sqlUpdate)->execute([$title, $author, $publisher, $qty, $id]);

            header("Location: list.php");
            exit;
        } catch (Throwable $e) {
            $error = 'Gagal memperbarui data: ' . $e->getMessage();
        }
    }
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// 4. Panggil View
$viewPath = __DIR__ . '/../views/books/edit.view.php';
if (!file_exists($viewPath)) { $viewPath = __DIR__ . '/edit.view.php'; }
require_once $viewPath;