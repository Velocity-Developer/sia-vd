# Changelog

Semua perubahan penting dicatat di sini. Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/).
Mulai 1.0.0 repo memakai [Semantic Versioning](https://semver.org/lang/id/); riwayat sebelum 1.0.0 dikelompokkan per tanggal.

## [Belum dirilis]

### Diubah
- README dilengkapi **Alur pemakaian dari awal** (persiapan, siklus per semester, akhir studi) dan langkah membuat
  dosen pertama saat onboarding (fakultas/prodi butuh dekan/kaprodi, sedangkan form dosen butuh prodi).

## [1.0.0] - 2026-09-30

Rilis pertama bernomor versi. Isinya seluruh riwayat di bawah (per tanggal sejak 2026-08-31) ditambah perubahan berikut.

### Ditambahkan
- Tombol **Tambah** di menu Jadwal Kelas, Materi, Tugas, dan Quiz (admin dan dosen) dengan isian **Kelas Kuliah**
  (kelas tahun akademik aktif, bukan TA/Skripsi; dosen hanya kelas yang diampunya). Rute `{admin,dosen}.{jadwal,materi,tugas,quiz}.{create,store}`.
- Edit dan Hapus langsung dari daftar menu: Hapus materi; Edit dan Hapus tugas serta quiz. Sesudahnya kembali ke menu.
- Dosen bisa menambah, mengubah, dan menghapus jadwal mingguan kelas yang diampunya (tahun akademik aktif, cek bentrok tetap).

### Diubah
- Form jadwal pindah ke `Kelas/JadwalForm` dan dipakai bersama admin dan dosen.

### Dihapus
- Menu Perpustakaan beserta sub-menu Katalog Buku, Pinjaman Aktif, dan Riwayat Pinjaman (masih placeholder), rute
  `/mahasiswa/perpustakaan*`, dan izin `mahasiswa.perpustakaan` (dibuang dari semua role lewat migrasi).

## 2026-09-30

### Ditambahkan
- **Feature flag per klien** (`config/client.php`, `App\Feature::aktif`): `kelola_role` dan `keuangan`, masing-masing
  dengan `FEATURE_*` (status) dan `LOCK_*` (kunci developer). Bawaan semua mati.
- **Override fitur di database**: tabel `pengaturan_fitur` dan `log_pengaturan_fitur`. Urutan status: tidak terdaftar
  = mati, `locked` = ikut config, lalu override database, lalu bawaan config. Override dibaca satu query dan di-cache.
- **Panel developer** `/dev` (hanya bila `DEV_PANEL=true`, khusus role developer, dengan konfirmasi sandi):
  - Fitur Klien: toggle flag, kembali ke bawaan, riwayat perubahan; flag terkunci disabled dan ditolak 403;
    perubahan yang melanggar dependensi ditolak; override dan log disimpan dalam satu transaksi.
  - Kelola Role: developer menyusun role dan hak akses, terlepas dari flag `kelola_role`; admin memakai role itu
    saat menambah user.
- Role `developer` dan perintah `php artisan sia:developer <username> [--cabut]`. Role ini tidak tampil dan tidak
  bisa diberikan dari aplikasi.
- Aturan SKS khusus mata kuliah TA/Skripsi: TA diambil tiap semester sampai dinilai (status *Berlanjut*), syarat
  minimal SKS lulus, tagihan per SKS mengikuti SKS TA, kelas TA tanpa dosen/jadwal/konten.

### Diubah
- Keuangan mati: izin tagihan & info biaya disembunyikan, rute 404, KRS tidak dikunci tagihan, remidi dan ujian
  susulan tanpa syarat bayar. Bukti bayar cuti, pendadaran, dan wisuda tetap wajib.
- Kelola Role mati: menu dan rute Kelola Role admin 404; izin `admin.roles` tetap dimiliki Admin agar tetap bisa
  memberi role ke user.
- Ujian susulan tidak dijadikan flag; selalu tersedia.

### Diperbaiki
- Saat keuangan mati, admin tetap bisa membuka kunci daftar remidi walau ada tagihan remidi lama.
- Catatan "tagihan sudah lunas" di PDF daftar hadir remidi/susulan hanya muncul saat keuangan aktif.

## 2026-09-29

### Ditambahkan
- Pengajuan cuti dan aktif kembali mahasiswa; mahasiswa Lulus/Cuti tetap bisa masuk tanpa tagihan dan KRS.
- Status dosen Aktif/Nonaktif; akun tidak aktif ditolak saat masuk dan sesinya diakhiri.
- Unduh transkrip dan KRS (PDF) dengan tanda tangan PA/Kaprodi/mahasiswa.
- Zona waktu institusi (WIB/WITA/WIT) di Pengaturan Sistem.
- Beranda baru untuk admin (ringkasan + tindak lanjut sesuai hak akses), dosen, dan mahasiswa.

### Diubah
- Standar tampilan seragam untuk seluruh halaman (`docs/standar-ui.md`).
- Bagian Detail Kelas Kuliah menjadi accordion; `/dashboard` menjadi pengalih ke beranda peran.

## 2026-09-28

### Ditambahkan
- Masuk dengan NIM/NIDN/username, verifikasi email, dan konfirmasi kata sandi untuk halaman sensitif.
- KRS: prasyarat mata kuliah, tawaran mata kuliah tertunda/perlu diulang, semester dihitung dari angkatan.
- Bukti bayar tagihan semester dan terbitkan ulang yang aman.
- Jadwal ulang per pertemuan dengan alasan dan riwayat.

### Diperbaiki
- Menu admin tidak bisa diberikan ke role mahasiswa; urutan tanggal tahun akademik dan skala nilai.

## 2026-09-27

### Ditambahkan
- Ujian susulan UTS/UAS: pengajuan, tagihan & bukti bayar, jadwal untuk pemohon lunas, pengingat beranda.
- Tugas akhir: pengajuan TA/Skripsi, pendaftaran & penjadwalan pendadaran, penilaian, surat, periode dan
  pendaftaran wisuda, SKL.
- Dokumentasi alur proses bisnis dan flowchart sistem (`docs/`).

## 2026-09-25

### Ditambahkan
- Jadwal ujian UTS/UAS oleh admin dengan mode tatap muka, online unggah berkas, dan online soal; kartu ujian PDF;
  nilai ujian skala 0–100 dan rilis setelah ujian selesai.
- Finalisasi nilai per kelas dan batas input nilai.
- Remidi: daftar calon per kelas, tagihan dan bukti bayar, jadwal ujian remidi, huruf akhir dan finalisasi remidi.

### Diubah
- Optimasi presensi (PDF, halaman mahasiswa, layar dosen, beranda) dan logo kop PDF.

## 2026-09-24

### Ditambahkan
- Presensi: pertemuan, presensi dosen, presensi mandiri mahasiswa (QR/PIN), izin/sakit, syarat kehadiran ujian.
- Akun karyawan; Pengaturan Profil dipisah dari Pengaturan Sistem; pilihan tata letak halaman masuk.

### Diperbaiki
- Checkbox memakai `v-model` agar nilainya tersimpan; 8 bug hasil review presensi.

## 2026-09-23

### Ditambahkan
- Keuangan: jenis biaya, tarif, tagihan semester, status pembayaran, kunci KRS sampai lunas, penjelasan asal angka
  tagihan untuk mahasiswa.
- Halaman masuk dan alur atur ulang kata sandi; Pengaturan Email (SMTP).
- Menu tersendiri untuk jadwal, materi, tugas, dan quiz; data demo sesuai aturan sistem.

### Diperbaiki
- XSS pada berkas unggahan, rute yang selalu 500, kerentanan dependensi npm.
- Kunci attempt quiz dan data halaman yang terlalu besar; tampilan tabel dan filter di layar HP.

## 2026-09-22

### Ditambahkan
- Kelola Role dan Pengaturan Institusi.
- Aturan KRS, nilai, koreksi esai, dan Pengaturan Akademik (skala nilai, batas SKS).

### Diperbaiki
- Keamanan unggahan, batas waktu quiz di server, zona waktu, bentrok jadwal.
- Hak akses role, seeder aman, berkas kuliah privat, integritas data, dan performa halaman.

## 2026-08-31 – 2026-09-21

### Ditambahkan
- Fondasi aplikasi: manajemen user (admin, dosen, mahasiswa), fakultas, program studi, mata kuliah, ruang,
  tahun akademik dan periode KRS.
- Kelas kuliah, jadwal, materi, tugas, quiz dan soal; penilaian tugas dan quiz.
- KRS mahasiswa dan persetujuan dosen, jadwal kuliah mahasiswa, info kuliah, pindah kelas.
- KHS, ekspor KHS, dan transkrip nilai.

[Belum dirilis]: https://github.com/Velocity-Developer/sia-vd/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/Velocity-Developer/sia-vd/releases/tag/v1.0.0
