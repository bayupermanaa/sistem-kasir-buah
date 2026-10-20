# Panduan Instalasi

## Prasyarat
- PHP XAMPP dengan extension Laravel yang diperlukan.
- Composer, Node.js/npm, dan MySQL.
- Database kosong atau backup clone yang disetujui untuk instalasi baru.

## Konfigurasi
1. Salin `.env.example` menjadi `.env`.
2. Isi `APP_URL`, koneksi database, filesystem, session, dan mail sesuai environment.
3. Jalankan `php artisan key:generate` hanya pada instalasi baru.
4. Jangan menyalin `.env` atau credential ke repository/dokumentasi.

## Instalasi Aman
```text
composer install
npm install
php artisan storage:link
php artisan migrate
npm run build
```

Gunakan `php artisan migrate` hanya setelah backup dan persetujuan environment. Jangan gunakan `migrate:fresh` atau `db:wipe` pada database berisi data.

## Menjalankan
```text
php artisan serve
npm run dev
```

Untuk XAMPP, arahkan virtual host/document root ke folder `public` dan pastikan `public/storage` menunjuk ke `storage/app/public`.

## Backup
Backup database dan folder `storage/app/public` sebelum upgrade. Simpan backup di luar repository dan uji restore pada environment terpisah.
