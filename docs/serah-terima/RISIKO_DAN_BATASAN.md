# Risiko dan Batasan

## P0 sebelum produksi
- Saldo awal belum diposting.
- Cutover accounting belum diaktifkan.
- Rekonsiliasi dengan data nyata klien belum dilakukan.

## P1
- QA visual browser dan mobile belum dilakukan dalam audit CLI.
- Concurrency checkout dan locking perlu diuji pada MySQL XAMPP/production-like.
- Printer thermal dan integrasi QRIS masih perlu validasi perangkat nyata.
- HPP produk tanpa harga beli tidak boleh dianggap nol; laporan dapat berstatus tidak lengkap.

## P2
- Beberapa komponen Breeze legacy masih memakai Tailwind, sedangkan aplikasi utama memakai Bootstrap.
- Akses shortcut Pembelian Stok dimulai dari halaman Barang & Stok karena route pembelian memerlukan parameter produk.
- Pengelolaan queue/scheduler belum menjadi kebutuhan modul saat ini.

## Data dan Accounting
Jurnal memakai double-entry dan idempotency. Sistem tidak boleh membuat jurnal historis otomatis. Saldo awal harus disetujui Owner/klien, diposting satu kali, lalu cutover ditetapkan secara eksplisit.

## Kesimpulan
Kode dan automated test kuat untuk melanjutkan QA serah-terima, tetapi sistem belum boleh disebut production-ready sebelum risiko P0, validasi keuangan, QA browser, concurrency, backup/restore, dan data nyata diselesaikan.
