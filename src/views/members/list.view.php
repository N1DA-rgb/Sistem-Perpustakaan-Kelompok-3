<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Data Anggota</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; margin: 2rem; color: #2a2a2a; }
        h1 { color: #14756e; margin-bottom: 1rem; }
        .nav-links { margin-bottom: 1.5rem; }
        .nav-links a { color: #14756e; font-weight: bold; text-decoration: none; }
        .nav-links a:hover { text-decoration: underline; }
        .search-form { margin-bottom: 1.5rem; display: flex; gap: 0.5rem; }
        .search-form input[type="text"] { padding: 0.4rem 0.8rem; border: 1px solid #ccc; border-radius: 4px; width: 260px; }
        .search-form button { padding: 0.4rem 1rem; background-color: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; }
        .search-form button:hover { background-color: #e0e0e0; }
        table { border-collapse: collapse; width: 100%; max-width: 900px; }
        table, th, td { border: 1px solid #ccc; }
        th { background-color: #f8f9fa; text-align: left; padding: 8px 12px; font-weight: bold; }
        td { padding: 8px 12px; }
        .action-links a { color: #0056b3; text-decoration: none; }
        .action-links a:hover { text-decoration: underline; }
        .alert-error { background-color: #f8d7da; color: #721c24; padding: 10px; margin-bottom: 15px; border-radius: 4px; }
    </style>
</head>
<body>

    <h1>Kelola Data Anggota</h1>

    <?php if (!empty($flashError)): ?>
        <div class="alert-error"><?= e($flashError) ?></div>
    <?php endif; ?>

    <!-- Navigasi mengarah ke index.php di luar folder members -->
    <div class="nav-links">
        <a href="../index.php">&laquo; Kembali ke Katalog Utama</a>
        <span style="margin: 0 10px; color: #ccc;">|</span>
        <a href="create.php">+ Tambah Anggota</a>
    </div>

    <!-- Form Pencarian -->
    <form method="get" action="list.php" class="search-form">
        <input type="text" name="search" value="<?= e($_GET['search'] ?? '') ?>" placeholder="Cari ID, Nama, Email, No HP...">
        <button type="submit">Cari</button>
    </form>

    <!-- Tabel Data Anggota -->
    <table>
        <thead>
            <tr>
                <th>ID Anggota</th>
                <th>Nama</th>
                <th>Email</th>
                <th>Telepon</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($members)): ?>
                <?php foreach ($members as $m): ?>
                    <?php 
                        // Membaca data berdasarkan logika mapping kolom yang kamu buat
                        $idVal    = $m[$colId] ?? $m['MemberID'] ?? $m['member_id'] ?? $m['id'] ?? '';
                        $nameVal  = $m[$colName] ?? $m['Name'] ?? $m['nama'] ?? '';
                        $emailVal = ($colEmail && isset($m[$colEmail])) ? $m[$colEmail] : ($m['email'] ?? $m['Email'] ?? '-');
                        $phoneVal = ($colPhone && isset($m[$colPhone])) ? $m[$colPhone] : ($m['phone'] ?? $m['telepon'] ?? '-');
                    ?>
                    <tr>
                        <td><?= e((string)$idVal) ?></td>
                        <td><?= e((string)$nameVal) ?></td>
                        <td><?= e((string)$emailVal) ?></td>
                        <td><?= e((string)$phoneVal) ?></td>
                        <td class="action-links">
                            <a href="edit.php?id=<?= urlencode((string)$idVal) ?>">Edit</a>
                            |
                            <a href="delete.php?id=<?= urlencode((string)$idVal) ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus anggota ini?')">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #666; padding: 1.5rem;">
                        Belum ada data anggota yang ditemukan.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</body>
</html>