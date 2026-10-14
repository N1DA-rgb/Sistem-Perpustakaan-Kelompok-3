<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Buku</title>
    <style>
        table { border-collapse: collapse; width: 100%; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 8px 12px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Daftar Buku Perpustakaan</h1>

        <p style="margin-bottom: 1.5rem;">
            <a href="../index.php" style="margin-right: 15px; font-weight: bold; color: #14756e; text-decoration: none;">&laquo; Kembali ke Katalog Utama</a> | 
            <a href="create.php" style="margin-left: 15px; margin-right: 15px; font-weight: bold; color: #14756e;">+ Tambah Buku Baru</a> | 
            <a href="../loans/borrow.php" style="margin-left: 15px; font-weight: bold; color: #14756e;">Peminjaman Buku</a>
        </p>

    <table>
        <thead>
            <tr>
                <th>ISBN / ID</th>
                <th>Judul Buku</th>
                <th>Pengarang</th>
                <th>Penerbit</th>
                <th>Stok</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($books)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">Belum ada data buku.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($books as $b): ?>
                    <tr>
                        <td><?= e($b['isbn']) ?></td>
                        <td><?= e($b['title']) ?></td>
                        <td><?= e($b['author'] !== '' ? $b['author'] : '-') ?></td>
                        <td><?= e($b['publisher'] !== '' ? $b['publisher'] : '-') ?></td>
                        <td><?= $b['quantity'] ?></td>
                        <td>
                            <a href="edit.php?id=<?= urlencode($b['isbn']) ?>">Edit</a> | 
                            <a href="delete.php?id=<?= urlencode($b['isbn']) ?>" onclick="return confirm('Yakin ingin menghapus buku ini?')">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>