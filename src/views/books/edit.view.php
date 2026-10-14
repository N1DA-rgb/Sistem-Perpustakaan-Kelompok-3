<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Edit Buku</title></head>
<body>
    <h1>Edit Data Buku</h1>
    <p><a href="list.php">&laquo; Kembali ke Daftar Buku</a></p>

    <?php if (!empty($error)): ?>
        <p style="color: red; font-weight: bold;"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <p><label>ISBN / ID Buku:<br><input type="text" value="<?= e((string)($book[$colIsbn] ?? '')) ?>" disabled></label></p>
        <p><label>Judul Buku:<br><input type="text" name="title" value="<?= e((string)($book[$colTitle] ?? '')) ?>" required></label></p>
        <p><label>Pengarang / Penulis:<br><input type="text" name="author" value="<?= e((string)($book[$colAuthor] ?? $book['author'] ?? '')) ?>"></label></p>
        <p><label>Penerbit:<br><input type="text" name="publisher" value="<?= e((string)($book[$colPublisher] ?? $book['publisher'] ?? $book['penerbit'] ?? '')) ?>"></label></p>
        <p><label>Jumlah Stok:<br><input type="number" name="quantity" value="<?= (int)($book[$colQty] ?? $book['quantity'] ?? 0) ?>" min="0" required></label></p>
        <button type="submit">Update Buku</button>
    </form>
</body>
</html>