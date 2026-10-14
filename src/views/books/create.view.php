<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>Tambah Buku</title></head>
<body>
    <h1>Tambah Buku Baru</h1>
    <p><a href="list.php">&laquo; Kembali ke Daftar Buku</a></p>

    <?php if ($error): ?>
        <p style="color: red; font-weight: bold;"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <p><label>ISBN / ID Buku:<br><input type="text" name="isbn" required></label></p>
        <p><label>Judul Buku:<br><input type="text" name="title" required></label></p>
        <?php if ($colAuthor): ?>
            <p><label>Pengarang / Penulis:<br><input type="text" name="author"></label></p>
        <?php endif; ?>
        <?php if ($colPublisher): ?>
            <p><label>Penerbit:<br><input type="text" name="publisher"></label></p>
        <?php endif; ?>
        <?php if ($colQty): ?>
            <p><label>Jumlah Stok:<br><input type="number" name="quantity" value="1" min="0" required></label></p>
        <?php endif; ?>
        <button type="submit">Simpan Buku</button>
    </form>
</body>
</html>