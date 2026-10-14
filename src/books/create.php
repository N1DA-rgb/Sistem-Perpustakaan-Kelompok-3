<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../config/db.php';

$error = '';

// 1. Deteksi tabel Books / books secara dinamis
$bookTable = 'Books';
try {
    $rawCols = $pdo->query("SHOW COLUMNS FROM Books")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $bookTable = 'books';
    try {
        $rawCols = $pdo->query("SHOW COLUMNS FROM books")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) { $rawCols = []; }
}

// GARANSI: Paksa buat kolom publisher & is_deleted di DB jika belum ada
try { $pdo->exec("ALTER TABLE {$bookTable} ADD COLUMN publisher VARCHAR(255) NULL"); } catch (Throwable $e) {}
try { $pdo->exec("ALTER TABLE {$bookTable} ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable $e) {}

// Refresh ulang peta kolom
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

// 2. Proses Simpan / Restore Buku
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isbn      = trim($_POST['isbn'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    $author    = trim($_POST['author'] ?? '');
    $publisher = trim($_POST['publisher'] ?? '');
    $qty       = (int)($_POST['quantity'] ?? 1);

    if ($isbn === '' || $title === '') {
        $error = 'ISBN dan Judul Buku wajib diisi!';
    } else {
        try {
            $stmtCheck = $pdo->prepare("SELECT * FROM {$bookTable} WHERE {$colIsbn} = ?");
            $stmtCheck->execute([$isbn]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $isDel = (int)($existing['is_deleted'] ?? 0);
                if ($isDel === 1) {
                    $sqlRestore = "UPDATE {$bookTable} SET {$colTitle} = ?, {$colAuthor} = ?, {$colPublisher} = ?, {$colQty} = ?, is_deleted = 0 WHERE {$colIsbn} = ?";
                    $pdo->prepare($sqlRestore)->execute([$title, $author, $publisher, $qty, $isbn]);

                    header("Location: list.php");
                    exit;
                } else {
                    $error = "Gagal! Buku dengan ISBN '{$isbn}' sudah terdaftar dan masih aktif.";
                }
            } else {
                $sqlInsert = "INSERT INTO {$bookTable} ({$colIsbn}, {$colTitle}, {$colAuthor}, {$colPublisher}, {$colQty}) VALUES (?, ?, ?, ?, ?)";
                $pdo->prepare($sqlInsert)->execute([$isbn, $title, $author, $publisher, $qty]);

                header("Location: list.php");
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Gagal menyimpan data: ' . $e->getMessage();
        }
    }
}

function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

// 3. Panggil File View
$viewPath = __DIR__ . '/../views/books/create.view.php';
if (!file_exists($viewPath)) { $viewPath = __DIR__ . '/create.view.php'; }
require_once $viewPath;