# 🔐 WireGuard Middleman Portal

WireGuard Middleman Portal adalah aplikasi web internal sederhana yang dirancang untuk menjembatani komunikasi antara Admin IT dan User dalam pertukaran Public Key WireGuard. Aplikasi ini memudahkan dokumentasi user yang terhubung ke MikroTik sekaligus memberikan akses cepat bagi user untuk menyalin informasi koneksi server.

## ✨ Fitur Utama
- **📌 Pinned Message Area**: Menampilkan informasi server (Public Key MikroTik, Endpoint, dan Allowed IP) yang dapat disalin dengan satu klik.
- **📝 Form Registrasi User**: Memudahkan user mendaftarkan perangkat mereka dengan menginput nama, IP Tunnel yang diinginkan, dan Public Key perangkat.
- **👥 Antrean Publik**: Menampilkan daftar pendaftar terbaru beserta status aktivasinya.
- **⚙️ Dashboard Admin**: Area khusus untuk mengelola informasi server dan memverifikasi pendaftar (Activate/Pending/Delete).
- **🌓 Dark Mode**: Dukungan tema gelap untuk kenyamanan visual.
- **🛡️ Security Hardened**: Dilengkapi dengan proteksi CSRF, password hashing (bcrypt), penanganan sesi yang aman, dan validasi input.

## 🛠️ Teknologi
- **Bahasa**: PHP Native (tanpa framework)
- **Database**: SQLite (file `.db`)
- **UI/UX**: Bootstrap 5 (CDN) & Vanilla JavaScript
- **Font**: Inter (Google Fonts)

## 📋 Prasyarat
- PHP >= 7.4
- Ekstensi PHP: `pdo_sqlite`, `sqlite3`
- Web Server (Apache/Nginx) atau menggunakan PHP Built-in Server

## 🚀 Instalasi & Menjalankan

### Cara Cepat (PHP Built-in Server)
1. Clone repositori ini atau download source codenya.
2. Buka terminal/command prompt di dalam folder project.
3. Jalankan perintah:
   ```bash
   php -S localhost:8000
   ```
4. Buka browser dan akses `http://localhost:8000`.

### Menggunakan Apache (XAMPP/Laragon)
1. Pindahkan folder project ke direktori `htdocs` atau `www`.
2. Pastikan ekstensi `pdo_sqlite` sudah aktif di `php.ini`.
3. Akses folder melalui browser (contoh: `http://localhost/wireguard-portal`).

## 🔑 Akun Default Admin
- **Username**: `admin`
- **Password**: `admin123`

> [!IMPORTANT]
> Sangat disarankan untuk segera mengubah kredensial admin pada database jika aplikasi digunakan dalam lingkungan produksi.

## 📂 Struktur Folder
```
wireguard-portal/
├── admin.php         — Dashboard pengelolaan admin
├── index.php         — Halaman utama untuk user publik
├── login.php         — Halaman login administrator
├── logout.php        — Mengakhiri sesi administrator
├── db_config.php     — Konfigurasi database & fungsi helper global
├── assets/
│   └── style.css     — File desain CSS (termasuk variabel Dark Mode)
└── data/
    ├── .htaccess     — Melindungi file database dari akses URL langsung
    ├── index.php     — File fallback keamanan
    └── *.db          — File database SQLite (dibuat otomatis)
```

## 📖 Cara Penggunaan

### Untuk User
1. Buka halaman utama.
2. Salin **Public Key MikroTik** dan **Endpoint** ke dalam konfigurasi WireGuard Anda.
3. Isi **Form Registrasi** dengan detail perangkat Anda dan klik "Daftar Sekarang".
4. Tunggu Admin IT mengonfirmasi pendaftaran Anda.

### Untuk Admin
1. Akses halaman `/login.php` dan masuk menggunakan akun admin.
2. Gunakan **Settings Server** untuk mengubah informasi yang tampil di halaman user.
3. Pada **User Management**, klik tombol **Activate** untuk menyetujui pendaftar baru.
4. Gunakan fitur pencarian jika daftar user sudah terlalu banyak.

## 🔒 Keamanan
- **CSRF Protection**: Mencegah serangan pemalsuan permintaan lintas situs.
- **Password Hashing**: Menggunakan `password_hash()` standar industri.
- **SQL Injection**: Seluruh query database menggunakan PDO Prepared Statements.
- **XSS Protection**: Output data user selalu dibersihkan (escaped).
- **Database Isolation**: File `.db` diletakkan di subfolder terproteksi.

## 📄 Lisensi
Project ini bersifat open-source di bawah lisensi MIT.
