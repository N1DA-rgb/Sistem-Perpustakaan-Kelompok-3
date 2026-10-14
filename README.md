# 📚 Sistem Informasi Perpustakaan Digital
# Kelompok 3
Aplikasi Manajemen Perpustakaan berbasis web dengan arsitektur MVC sederhana, mendukung pengelolaan katalog buku, data anggota, dan transaksi peminjaman. Dikembangkan menggunakan PHP dan MariaDB di dalam lingkungan kontainer Docker.

---

## 🛠️ Teknologi & Stack (Tech Stack)

* **Language**: PHP 8.x (`declare(strict_types=1)`)
* **Database**: MariaDB / MySQL
* **Containerization**: Docker & Docker Compose
* **Version Control**: Git & GitHub

---

## 📁 Struktur Direktori Proyek

```text
.
├── config/             # Konfigurasi koneksi database (db.php)
├── members/            # Controller & logika backend modul Anggota (list.php, edit.php, dll)
├── books/              # Controller & logika backend modul Buku
├── loans/              # Controller & logika backend modul Peminjaman
├── views/              # Template tampilan / UI (list.view.php, edit.view.php, dll)
├── assets/             # File statis (CSS, JS, Gambar)
├── docker-compose.yml  # Konfigurasi kontainer Docker
├── index.php           # Landing Page / Katalog Utama
└── README.md           # Dokumentasi Proyek
```

---

## 🚀 Panduan Instalasi & Menjalankan Aplikasi

1. **Clone Repository**
   ```bash
   git clone <URL_REPOSITORY_GIT>
   cd <NAMA_FOLDER_PROJECT>
   ```

2. **Jalankan Docker Container**
   ```bash
   docker-compose up -d
   ```

3. **Akses Aplikasi**
   Buka browser dan navigasi ke: `http://localhost:8080`

---

## 👥 Dokumentasi Kontribusi Tim (Role Documentation)

---

### 👑 1. Project Manager (PM)

> *Diisi oleh Project Manager*

#### 📌 Responsibilitas & Lingkup Kerja
* [ ] *Jelaskan garis besar perencanaan proyek*
* [ ] *Jelaskan pembagian tugas tim*
* [ ] *Jelaskan garis waktu (timeline) / milestone pengerjaan*

#### 🎯 Target & Status Milestone
* [ ] Milestone 1: Setup Repository & Docker Environment `(Selesai)`
* [ ] Milestone 2: Integrasi Backend & Frontend `(Selesai)`
* [ ] Milestone 3: Final Testing & Demo `(Selesai)`

---

### ⚙️ 2. Backend Developer

> *Diimplementasikan dan Didokumentasikan oleh Backend Developer*

#### 📌 Ringkasan Implementasi
Backend dikembangkan menggunakan PHP native berorientasi *clean code* dengan menerapkan pola **Separation of Concerns (MVC)**. Backend dirancang khusus untuk memiliki resiliensi tinggi terhadap perbedaan lingkungan *development* antar anggota tim, keamanan data, dan toleransi terhadap Docker berbasis Linux.

#### 🛠️ Fitur Utama & Keunggulan Teknis Backend

1. **Arsitektur MVC Sederhana & Strict Typing**
   * Memisahkan logika aplikasi (*Controller* di folder modul, misal `members/list.php`) dan antarmuka (*View* di folder `views/`).
   * Menegaskan *type safety* menggunakan `declare(strict_types=1);` di baris pertama controller.
   * Menerapkan *Fallback Path Checking* dengan `file_exists()` untuk pemanggilan `db.php` dan file view agar terhindar dari *Fatal Error* atau *White Screen of Death* (WSOD).

2. **Toleransi Lingkungan Docker Linux (Case-Sensitivity & Dynamic Table Detection)**
   * **Case-Sensitive Table Handling**: Mengantisipasi sifat OS Linux di Docker dengan mendeteksi variasi nama tabel secara otomatis (`Members` vs `members`).
   * **Dynamic Schema Mapping**: Membaca metadata struktur database menggunakan kueri `SHOW COLUMNS`. Backend secara dinamis memetakan nama kolom yang berbeda antar laptop tim (seperti `member_id`, `MemberID`, `id`, `name`, `nama`, `phone`, `telepon`, `email`).

3. **Integritas Data (Soft Delete & Auto-Migration)**
   * **Soft Delete Logic**: Penghapusan data anggota tidak menggunakan `HARD DELETE`, melainkan memanfaatkan kolom `is_deleted = 0`. Hal ini penting untuk menjaga integritas data riwayat transaksi peminjaman di masa lalu.
   * **Auto-Migration**: Backend dilengkapi skrip otomatis `ALTER TABLE` untuk menambahkan kolom `is_deleted` secara mandiri jika kolom tersebut belum tersedia di database lokal.

4. **Keamanan (SQL Injection & XSS Protection)**
   * **Anti SQL Injection**: Seluruh fitur pencarian (*search*) dan filter kueri memanfaatkan **PDO Prepared Statements** dengan *parameter binding*.
   * **Anti XSS (Cross-Site Scripting)**: Menyiapkan *helper function* sanitasi `e()` berbasis `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` pada layer visual.

5. **Standardisasi Routing & Navigasi Antar Modul**
   * Menstandarkan penulisan *path* relatif (`../index.php`, `../loans/`, dll) untuk menghubungkan Katalog Utama, Kelola Buku, Kelola Anggota, dan Peminjaman sehingga bebas dari error *404 Not Found*.

---

### 🎨 3. Frontend Developer

> *Diisi oleh Frontend Developer*

#### 📌 Responsibilitas & Lingkup Kerja
* [ ] *Jelaskan konsep rancangan UI/UX (Layout, Skema Warna, Tipografi)*
* [ ] *Jelaskan komponen HTML & CSS yang dibuat*
* [ ] *Jelaskan kepatuhan terhadap desain responsif (Responsive Web Design)*

#### 🎨 Desain & Antarmuka
* [ ] **Katalog Utama (`index.php`)**: *[Penjelasan singkat]*
* [ ] **Tabel Data & Form**: *[Penjelasan singkat]*

---

### 🧪 4. Quality Assurance (QA)

> *Diisi oleh Quality Assurance (QA)*

#### 📌 Responsibilitas & Lingkup Kerja
* [ ] *Jelaskan skenario pengujian (Test Cases)*
* [ ] *Jelaskan kriteria kelayakan/keberhasilan sistem (Acceptance Criteria)*

#### 📋 Laporan Pengujian (Test Report Summary)

| No | Modul | Skenario Pengujian | Hasil yang Diharapkan | Status |
|---|---|---|---|---|
| 1 | Navigation | Klik Kembali ke Katalog Utama | Beralih ke `index.php` tanpa 404 | `[ Pass / Fail ]` |
| 2 | Members | Pencarian data anggota | Menampilkan data sesuai keyword | `[ Pass / Fail ]` |
| 3 | Members | Hapus data anggota | Data tersembunyi (Soft Delete) | `[ Pass / Fail ]` |
| 4 | Security | Input karakter XSS/SQLi | Karakter tersanitasi aman | `[ Pass / Fail ]` |

---

## 🤝 Alur Kerja Git (Git Workflow)

1. **Buat Branch Fitur Baru**
   ```bash
   git checkout -b feature/<nama-fitur>
   ```
2. **Commit Perubahan**
   ```bash
   git add .
   git commit -m "feat: menambah logika dynamic schema pada backend"
   ```
3. **Push & Buat Pull Request (PR)**
   ```bash
   git push origin feature/<nama-fitur>
   ```