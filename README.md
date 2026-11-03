# StockFlow — Inventory & Production Management System

Portfolio dummy project berupa web application untuk simulasi pengelolaan persediaan dan pemantauan rencana produksi pada perusahaan manufaktur.

> **Catatan:** Ini adalah project fiktif untuk portofolio. Tidak berafiliasi, tidak menggunakan data internal, dan bukan aplikasi resmi PT HM Sampoerna.

## Fitur
- Login dan session authentication
- Dashboard ringkasan inventory, low-stock alert, dan produksi aktif
- Inventory management (tambah dan hapus item)
- Stock transaction (stock in / stock out) dengan validasi stok dan database transaction
- Production order dan pembaruan status
- Laporan transaksi dan ringkasan kategori
- Print report melalui browser

## Teknologi
PHP 8+, MySQL, HTML, CSS, JavaScript (browser print), PDO.

## Cara menjalankan di XAMPP
1. Ekstrak folder `inventory_system` ke `C:\xampp\htdocs\`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`.
4. Pilih menu **Import**, unggah file `database.sql`, lalu klik **Import**.
5. Buka `http://localhost/inventory_system/login.php`.
6. Login menggunakan:
   - Username: `admin`
   - Password: `admin123`

## Struktur folder
```text
inventory_system/
├── assets/style.css
├── config/database.php
├── includes/auth.php
├── includes/header.php
├── includes/footer.php
├── database.sql
├── login.php
├── index.php
├── inventory.php
├── transactions.php
├── production.php
├── reports.php
├── logout.php
└── README.md
```

## Ide pengembangan berikutnya
- Edit item dan pencarian/filter inventory
- Role admin dan staff
- Pagination dan export CSV/PDF
- Relasi pemakaian bahan baku dengan production order
- Audit log dan CSRF protection

## Catatan keamanan
Akun demo dan konfigurasi database ditujukan untuk lingkungan lokal. Ganti password demo, gunakan environment variables, tambahkan CSRF protection, dan lakukan validasi/otorisasi lebih lanjut sebelum deployment publik.
