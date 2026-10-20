# Checklist Deployment

## Sebelum Deploy
- [ ] Backup database dan `storage/app/public` telah diverifikasi.
- [ ] Persetujuan klien untuk saldo awal, tanggal cutover, dan mapping akun.
- [ ] `APP_ENV=production` dan `APP_DEBUG=false`.
- [ ] `APP_KEY` dibuat/tersedia pada secret manager, bukan repository.
- [ ] Credential database, mail, storage, dan API disimpan sebagai secret environment.
- [ ] HTTPS dan domain production aktif.

## Server
- [ ] PHP extension, Composer, Node build, dan MySQL sesuai kebutuhan.
- [ ] Permission `storage` dan `bootstrap/cache` benar.
- [ ] `public/storage` dibuat dengan `php artisan storage:link`.
- [ ] Jalankan `composer install --no-dev --optimize-autoloader` sesuai prosedur.
- [ ] Jalankan build asset dan verifikasi manifest.
- [ ] Cache config/route/view hanya setelah environment benar.
- [ ] Queue/scheduler tidak digunakan saat ini kecuali nanti ditambahkan.

## Setelah Deploy
- [ ] Login Owner/Cashier.
- [ ] Cek dashboard, kasir, stok, struk, laporan, dan akses 403.
- [ ] Cek log tanpa credential atau password.
- [ ] Siapkan restore plan dan rollback aplikasi.
- [ ] Jangan menjalankan `migrate:fresh` atau `db:wipe`.
