# RasaRaya PHP + MySQL

Aplikasi toko untuk pembeli dan penjual. Admin dapat memantau akun dan menyetujui pendaftaran penjual. Produk menggunakan foto JPG, PNG, atau WebP.

## Persyaratan

- PHP 8.1 atau lebih baru, dengan ekstensi `pdo_mysql` dan `fileinfo`
- MySQL atau MariaDB

## Menjalankan dengan XAMPP

1. Nyalakan MySQL dari XAMPP Control Panel.
2. Dari PowerShell pada folder proyek, buat database dan impor skema:

   ```powershell
   & C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS rasaraya_php CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   Get-Content .\schema.sql | & C:\xampp\mysql\bin\mysql.exe -u root -D rasaraya_php
   ```

3. Jalankan aplikasi pada port khusus agar tidak bercampur dengan Apache atau server lama:

   ```powershell
   $env:DB_HOST = "127.0.0.1"
   $env:DB_PORT = "3306"
   $env:DB_NAME = "rasaraya_php"
   $env:DB_USER = "root"
   $env:DB_PASS = ""
   php -S 0.0.0.0:8002 -t C:\xampp\htdocs\php-app
   ```

4. Buka `http://localhost:8002`.

Untuk akses dari perangkat lain dalam jaringan lokal, gunakan `http://<IPv4-komputer>:8002`.

## Database lama

Skema di atas tidak menghapus data lama. Jika tabel dari versi sebelumnya sudah ada, periksa kolom terlebih dahulu dengan `SHOW COLUMNS FROM users;` dan `SHOW COLUMNS FROM products;`. Jalankan perintah berikut **satu per satu hanya untuk kolom yang belum ada**:

```sql
ALTER TABLE users ADD COLUMN full_name VARCHAR(120) NOT NULL DEFAULT '' AFTER id;
ALTER TABLE users ADD COLUMN whatsapp VARCHAR(20) NOT NULL DEFAULT '' AFTER full_name;
ALTER TABLE users ADD COLUMN business_name VARCHAR(120) NOT NULL DEFAULT '' AFTER whatsapp;
ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL DEFAULT NULL AFTER stock;
```

Pastikan semua tabel tambahan dari `schema.sql` juga tersedia sebelum menjalankan aplikasi.

## Pasang ke shared hosting/cPanel

1. Pilih paket hosting yang mendukung PHP 8.1+, ekstensi `pdo_mysql` dan `fileinfo`, MariaDB/MySQL, unggah file PHP, serta SSL. Untuk toko kecil, paket shared-hosting Small umumnya cukup untuk mulai; bandingkan batas penyimpanan, backup, dan biaya perpanjangan sebelum membeli.
2. Tambahkan domain atau subdomain di panel hosting, aktifkan sertifikat SSL, lalu pastikan situs dibuka melalui `https://`.
3. Di cPanel, buat database MySQL/MariaDB dan pengguna database dengan kata sandi kuat. Beri pengguna itu hak akses hanya ke database aplikasi.
4. Unggah file proyek ke document root domain. Pertahankan `.htaccess`, folder `assets`, dan `uploads`. Jangan mengganti server publik dengan PHP Development Server.
5. Salin `config.example.php` menjadi `config.local.php`, lalu isi host, nama database, pengguna, kata sandi, dan `APP_URL` domain Anda. File lokal ini diabaikan Git dan diblokir dari akses web oleh `.htaccess`; jangan mengirim kata sandi melalui chat.
6. Di phpMyAdmin, pilih database aplikasi yang telah dibuat, lalu impor `schema.sql`. Berkas ini hanya membuat tabel, sehingga cocok untuk hosting yang melarang perintah `CREATE DATABASE`.
7. Pastikan PHP dapat menulis ke folder `uploads/products` (biasanya izin folder `755`). Jika folder belum ada, aplikasi akan membuatnya saat foto produk pertama diunggah.
8. Buat atau tetapkan akun admin, lalu uji registrasi, unggah foto, pesanan, dan email reset. SMTP/sendmail harus diatur tersendiri untuk fitur reset kata sandi.

Untuk rekomendasi awal di Indonesia, halaman resmi [Rumahweb Shared Hosting](https://www.rumahweb.com/hosting-murah/) saat ini mencantumkan paket Small dengan MariaDB dan SSL; harga promosi, masa kontrak, batas resource, dan harga perpanjangan dapat berubah. Periksa detail terbaru langsung di situs penyedia sebelum membeli. Domain tidak wajib dibeli bila paket menyediakan subdomain, tetapi domain sendiri lebih mudah dibagikan.

## Menjadikan akun admin

Daftar sebagai pembeli, lalu tetapkan admin tepercaya melalui phpMyAdmin atau MySQL CLI:

```sql
UPDATE users
SET role = 'ADMIN_PENJUAL'
WHERE email = 'email-admin@example.com';
```

Masuk sebagai **Penjual / Admin**. Admin dapat menyetujui akun penjual dan memantau seluruh akun. Pembeli aktif langsung; pendaftaran penjual menunggu persetujuan.

## Pengelolaan produk dan pesanan

- Foto produk maksimal 5 MB; tipe file diverifikasi berdasarkan MIME, bukan hanya nama file.
- Penjual dapat mengelola produk, pesanan, stok, biaya, dan laporan.
- Pembeli dapat memasukkan produk ke keranjang, membuat pesanan, menghubungi toko lewat WhatsApp, dan melihat statusnya.
- Reset kata sandi dikirim melalui fungsi `mail()` PHP. Atur SMTP/sendmail dan environment `MAIL_FROM` sebelum digunakan.
- Jangan menghapus tabel atau database jika ingin mempertahankan data aplikasi.

Untuk server publik, gunakan akun database khusus dengan izin minimum, aktifkan HTTPS, dan jangan menyimpan kredensial database di file yang dipublikasikan.
