Pengujian Pustaka Digital

Dokumentasi kasus uji fungsional untuk pengujian sistem perpustakaan berbasis web.

| ID Uji | Fitur | Langkah Pengujian | Hasil yang Diharapkan | Status |
| :--- | :--- | :--- | :--- | :--- |
| TC-01 | **Katalog Publik** | Buka `http://localhost:8080/` | Halaman memuat daftar buku dalam tata letak kartu (*grid*), dan hanya buku dengan stok > 0 yang tampil. | Lolos |
| TC-02 | **Tambah Buku** | Buka `/books/create.php`, isi data buku baru dengan lengkap, klik tombol Simpan | Data tersimpan, halaman dialihkan kembali ke `/books/list.php`, dan buku baru muncul di tabel. | Lolos |
| TC-03 | **Validasi ISBN** | Masukkan data buku dengan nomor ISBN yang sudah ada di basis data | Sistem menolak penyimpanan dan memunculkan notifikasi "ISBN sudah terdaftar". | Lolos |
| TC-04 | **Daftar Anggota** | Buka `/members/list.php` | Menampilkan tabel seluruh anggota perpustakaan yang terdaftar beserta email dan nomor telepon. | Lolos |
| TC-05 | **Peminjaman Buku** | Buka `/loans/borrow.php`, pilih ID anggota dan ISBN buku yang tersedia | Transaksi tercatat di tabel riwayat peminjaman dan jumlah stok buku di tabel Books otomatis berkurang 1. | Lolos |
| TC-06 | **Peminjaman Stok Kosong** | Coba lakukan peminjaman buku yang memiliki jumlah stok 0 | Sistem menolak peminjaman dengan notifikasi error stok habis dan stok tidak bernilai negatif. | Lolos |
| TC-07 | **Pengembalian Buku** | Buka `/loans/return.php`, proses transaksi pengembalian untuk buku yang sedang dipinjam | Kolom ReturnDate terisi tanggal hari ini dan jumlah stok buku otomatis bertambah 1 kembali. | Lolos |