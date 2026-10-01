# Changelog

Semua perubahan penting dicatat di sini. Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/).
Mulai 1.0.0 repo memakai [Semantic Versioning](https://semver.org/lang/id/); riwayat sebelum 1.0.0 dikelompokkan per tanggal.

## [Belum dirilis]

### Ditambahkan
- **Formulir PMB publik** `/pmb/daftar` (tabel `cmb`): tanpa pilihan tahun pendaftaran, pendaftar otomatis masuk ke
  periode yang sedang dibuka (Atur Periode PMB menolak dua periode dibuka dengan tanggal bertumpuk). Isian disamakan
  dengan pmb.stikesyapika.ac.id (data diri, NIK,
  kewarganegaraan, alamat + kecamatan berkode Feeder, transportasi, jenis tinggal/masuk, KPS, pembiayaan, kelas,
  program studi, status baru/pindahan beserta asal sekolah atau asal PT, agen, info). Formulir hanya terbuka selama ada
  periode di **Atur Periode PMB** yang `is_open` dan hari ini di antara tanggal buka–tutup; kuota mengikuti kapasitas,
  NIK unik per periode, nomor pendaftaran `<kode periode>-0001`. Captcha formulir dinyalakan terpisah lewat sakelar
  baru **Tampilkan captcha di form pendaftaran PMB** di Pengaturan Sistem → reCAPTCHA (kolom `aktif_pmb`, kunci sama). Halaman
  masuk punya tombol **Link Pendaftaran Mahasiswa Baru**. Tabel baru `wilayah_kecamatan` (7.608 kecamatan Feeder,
  diisi migrasi) dan agama berkode Feeder (1–6, 99) ditambahkan ke master Agama bila belum ada.
- Menu **PMB → Data Pendaftar** (izin `admin.pendaftar-pmb`): daftar + filter periode/status, detail, isi **nilai**
  (0–100) dan **status pendaftaran** (Lulus/Ditolak; kosong = Menunggu), hapus pendaftar. Periode yang sudah punya
  pendaftar tidak bisa dihapus.
- **Atur Periode PMB** (menu PMB → Konfigurasi; tabel `pengaturan_pmb`, izin `admin.periode-pmb`): kode unik, tahun angkatan,
  tanggal buka/tutup pendaftaran, tanggal USM mulai/selesai, tanggal her-registrasi, nilai minimal (0–100), kapasitas,
  biaya pendaftaran, tanggal pembayaran mulai/selesai, dan status dibuka (`is_open`). Seksi sidebar bisa diberi
  `tetap: true` agar tidak diratakan walau isinya satu menu.
- Master **Agama**, **Provinsi**, dan **Kota/Kabupaten** (tabel `agamas`, `provinsis`, `kotas`, masing-masing dengan
  `kode` unik) beserta CRUD di menu Master Akademik (izin `admin.agama`, `admin.provinsi`, `admin.kota`). Kota/kabupaten
  milik satu provinsi (nama unik per provinsi, filter per provinsi); provinsi yang masih punya kota tidak bisa dihapus.
- Master **Badan Hukum** (tabel `badan_hukum`, satu baris; izin `admin.badan-hukum`): halaman edit ala Pengaturan
  Institusi tanpa tambah data, ID tampil terkunci di atas. Isian nama, tanggal berdiri, nomor/tanggal akta terakhir,
  nomor/tanggal pengesahan, alamat jalan, provinsi, kota/kabupaten (harus di provinsi terpilih), kode pos, telepon,
  faximili, email, website. Provinsi/kota yang dipakai badan hukum tidak bisa dihapus.

- Isian baru **Program Studi**: gelar akademik, singkatan gelar, SKS lulus (baru disimpan, belum dipakai perhitungan),
  status prodi (Aktif/Pembinaan/Alih Bentuk/Alih Kelola/Tutup), nomor kaprodi, operator, nomor operator, nomor/tanggal/
  tanggal berakhir SK Dikti, alamat, provinsi, kota/kabupaten, kode pos, telepon, faximili, email, website.
- **Foto** di data mahasiswa, dosen, dan karyawan (kolom `foto` di `mahasiswa_profiles`, `dosen_profiles`,
  `admin_profiles`): unggah/ganti/hapus di form pengguna (jpg/jpeg/png/webp, maks. 2 MB), tampil di halaman detail.
  Berkas disimpan di disk privat dan dibuka lewat `/berkas/foto/{user}` (pemilik akun atau pemegang izin kelola jenis
  pengguna itu); foto lama terhapus saat diganti atau akun dihapus.

### Diubah
- Tab **Institusi** di Pengaturan Sistem pindah ke **Master Akademik → Perguruan Tinggi** (`/admin/perguruan-tinggi`,
  izin tetap `admin.institusi`, kini bernama "Perguruan Tinggi"); alamat lama dialihkan. Isian baru: badan hukum,
  nomor/tanggal akta terakhir, nomor/tanggal pengesahan, akreditasi, alamat lain, provinsi, kota/kabupaten, kode pos,
  faximili.
- Susunan menu admin: menu baru **Master** berisi **Master Tabel** (Badan Hukum, Perguruan Tinggi, Fakultas, Program
  Studi, Agama, Provinsi, Kota/Kabupaten), **Data Dosen**, dan **Data Mahasiswa** (pindah dari Pengguna & Akses).
  "Master Akademik" menjadi **Akademik** (Tahun Akademik, Mata Kuliah, Ruang). Sidebar kini mendukung seksi di dalam
  seksi (satu tingkat), termasuk submenu melayang saat sidebar diciutkan.

## [1.1.0] - 2026-10-01

### Ditambahkan
- **Verifikasi KRS** (sakelar `verifikasi_krs_aktif` di Pengaturan Akademik, bawaan mati): KRS yang disimpan mahasiswa
  berstatus *Diajukan* lalu disetujui admin atau dikembalikan untuk revisi dengan catatan. Menu Administrasi →
  Verifikasi KRS (izin `admin.verifikasi-krs`) dengan rincian dan setujui massal. Isian **Akhir Masa Revisi KRS** di
  Tahun Akademik; sesudahnya semua KRS terkunci, yang masih diajukan tetap bisa disetujui. Kartu ujian menunggu KRS
  disetujui dan PDF KRS ditandai "BELUM DISETUJUI". Saat sakelar mati, KRS yang disimpan langsung final seperti dulu.
- **Feature flag per klien** baru (bawaan nyala): `materi`, `tugas`, `quiz`, `ujian_online`, `presensi_qr`,
  `pindah_kelas`, `ujian_susulan` (`FEATURE_*` di `.env`). Fitur yang mati menyembunyikan menu, izin, dan rutenya (404).
- Tab **reCAPTCHA** di Pengaturan Sistem (izin `admin.pengaturan-recaptcha`): site key & secret key Google reCAPTCHA v2.
  Bila aktif, halaman masuk menampilkan kotak centang "Saya bukan robot" yang diverifikasi di server. Captcha uji wajib
  lolos saat menyalakan atau mengganti kunci.
- **Template Email** di Pengaturan Sistem > Email: admin bisa mengubah subjek, sapaan, isi, teks tombol, dan penutup
  surel atur ulang kata sandi, verifikasi email, dan surel uji, dengan variabel (`{nama}`, `{tautan}`, …), pratinjau,
  dan tombol Kembalikan Bawaan.
- Tab **Maintenance** di Pengaturan Sistem (izin `admin.pengaturan-maintenance`): menutup sementara sistem untuk dosen
  dan/atau mahasiswa dengan pesan dan perkiraan selesai. Admin/karyawan tetap bisa masuk dan melihat spanduk pengingat.

### Diubah
- **Buka kunci KRS** oleh admin kini mengembalikan KRS ke status *perlu revisi* dengan catatan dan tanggal
  "Dibuka sampai" opsional (wajib setelah masa KRS/revisi berakhir), jadi bisa dipakai di luar periode KRS.
- Saat **syarat kehadiran ujian** aktif, kartu ujian (UTS/UAS/susulan) tidak bisa dicetak selama ada mata kuliah yang
  kehadirannya di bawah batas tanpa dispensasi. Sebelumnya kartu tetap tercetak dengan tanda "Tidak memenuhi".
- README dilengkapi **Alur pemakaian dari awal** (persiapan, siklus per semester, akhir studi).
- Isian **Program Studi** pada form dosen kini opsional, sehingga instalasi baru bisa membuat dosen calon
  dekan/kaprodi sebelum fakultas dan program studi ada (sebelumnya buntu tanpa tinker).

### Diperbaiki
- Admin tidak bisa membuka kunci KRS saat fitur keuangan mati (tombolnya hanya ada di menu Tagihan yang ikut 404);
  kini juga tersedia di Detail Mahasiswa.
- Simpanan pertama pengaturan singleton (mis. Maintenance) hilang di MySQL karena id tidak auto-increment.

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

[Belum dirilis]: https://github.com/Velocity-Developer/sia-vd/compare/v1.1.0...HEAD
[1.1.0]: https://github.com/Velocity-Developer/sia-vd/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/Velocity-Developer/sia-vd/releases/tag/v1.0.0
