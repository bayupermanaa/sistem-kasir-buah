# Checklist QA Klien

## Akses dan UI
- [ ] Login Owner dan Cashier, logout, session timeout.
- [ ] Role dan menu sidebar sesuai hak akses.
- [ ] Uji desktop, tablet, mobile, empty state, pagination, format Rupiah/tanggal.
- [ ] Uji gambar produk dan `public/storage`.

## Operasional
- [ ] CRUD kategori, satuan, produk, status aktif, dan delete aman.
- [ ] Stok aman/menipis/habis dan minimum stok.
- [ ] Pembelian stok tunai dan kredit.
- [ ] Stock adjustment dan Stock Opname dengan quantity desimal.
- [ ] Kasir 0,100/0,500/0,700 kg, manual gram, edit item duplikat.
- [ ] Tunai, Uang Pas, uang kurang, QRIS, idempotency, stok berkurang.
- [ ] Detail dan cetak struk thermal 58/80 mm.
- [ ] Riwayat transaksi, filter cashier, tanggal, metode, status.

## Modul Keuangan
- [ ] Supplier, utang, pembayaran sebagian, pelunasan, saldo outstanding.
- [ ] Pengeluaran tunai/transfer/QRIS.
- [ ] Aset dan perlindungan edit/hapus aset berjurnal.
- [ ] Saldo awal disepakati, diposting satu kali, dan cutover disetujui.
- [ ] Neraca seimbang; Laba Rugi HPP lengkap; Arus Kas terekonsiliasi.
- [ ] Penjualan, pembelian, pengeluaran, aset, utang tidak dihitung ganda.

## Ketahanan
- [ ] Uji dua cashier/concurrent checkout pada MySQL.
- [ ] Uji double submit dan refresh setelah pembayaran.
- [ ] Uji isolasi outlet dengan data legacy.
- [ ] Uji backup/restore database dan storage.
