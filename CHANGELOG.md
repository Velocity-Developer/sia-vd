# Changelog

Semua perubahan penting dicatat di sini. Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/).
Mulai 1.0.0 repo memakai [Semantic Versioning](https://semver.org/lang/id/); riwayat sebelum 1.0.0 dikelompokkan per tanggal.

## [Belum dirilis]

### Ditambahkan
- **Presensi Dosen, Verifikasi Presensi Dosen, dan BAP** (migrasi `2026_10_02_120000_add_presensi_dosen_to_pertemuans`:
  kolom `pertemuans.status_dosen`, `verifikasi`, `catatan_verifikasi`, `diverifikasi_oleh`, `diverifikasi_at` + tabel
  `riwayat_presensi_dosens`; izin `admin.presensi-dosen` dan `admin.verifikasi-presensi-dosen`, bawaan Admin). Presensi dosen
  tetap diambil dari pertemuan yang dibuka–ditutup dosen. Menu **Akademik → Perkuliahan → Presensi Dosen** berisi tab Daftar
  Pertemuan (koreksi admin: status Hadir/Digantikan/Tidak Hadir/Kuliah Diganti/Sakit/Izin/Alpa, dosen pengajar, jam
  masuk–keluar, topik; alasan wajib dan tercatat di riwayat; pertemuan terlewat bisa dicatat sebagai susulan lengkap dengan
  jamnya), Rekap per Dosen (terlaksana, total jam, total SKS, terlambat, ketidakhadiran per bulan/semester, CSV; bawaan hanya
  yang terverifikasi), dan Per Kelas (Laporan Kehadiran Dosen lama, kini `admin/presensi-dosen/per-kelas`; alamat lama
  dialihkan). Menu **Verifikasi Presensi Dosen**: setujui massal (jurnal kosong dilewati), tolak dengan catatan (dosen melihat
  catatannya dan pertemuan kembali menunggu setelah jurnal/presensi diperbaiki), batal verifikasi beralasan. Pertemuan yang
  disetujui terkunci bagi dosen dan admin (termasuk proses izin mahasiswa). **BAP PDF** per pertemuan dan rekap per kelas
  (tanda tangan Kaprodi, Petugas Akademik pemverifikasi, dan Dosen): dosen hanya untuk pertemuan terverifikasi, admin boleh
  mencetak draf bertanda air DRAF.
- **Set Penasehat Akademik** di menu baru **Akademik → Perkuliahan → Set Penasehat Akademik** (izin
  `admin.penasehat-akademik`, bawaan Admin; migrasi `2026_10_02_110000_add_izin_penasehat_akademik`): pilih NIM awal,
  NIM akhir (pilihan hanya mahasiswa berstatus Aktif/Pindahan) dan Pembimbing Akademik (dosen Aktif), lalu dosen wali
  semua mahasiswa Aktif/Pindahan dalam rentang NIM itu diganti sekaligus. Halaman menampilkan pratinjau mahasiswa dalam
  rentang beserta dosen wali lamanya; rentang terbalik ditolak.
- **Batas SKS per Semester per prodi** (migrasi `2026_10_02_100000_create_batas_sks_prodis_table`: tabel
  `batas_sks_prodis` (prodi, IPS minimal, maks SKS) + kolom `program_studis.maks_sks_tanpa_ips`) di menu **Akademik →
  Konfigurasi → Batas SKS per Semester** (izin `admin.batas-sks`, bawaan Admin): pilih prodi, atur tingkatan dan maks
  SKS tanpa IPS, atau hapus agar kembali ke global. `PengaturanAkademik::maksSksUntuk($ips, $prodiId)` memakai batas
  prodi lebih dulu, lalu batas global; berlaku di KRS mahasiswa, verifikasi KRS, kuota SKS tagihan, dan DemoSeeder.
  Validasi dipakai bersama dengan Batas SKS global (`App\ValidasiBatasSks`). Worker antrean kini mengosongkan cache
  `once()` tiap job (`Queue::after`), agar perubahan Bobot Nilai tidak tertahan di worker yang berjalan lama.
- **Predikat kelulusan** (migrasi `2026_10_02_090000_create_predikats_table`, tabel `predikats`: nama, bobot_minimal,
  bobot_maksimal = rentang IPK) di menu **Akademik → Konfigurasi → Predikat** (izin `admin.predikat`, bawaan Admin):
  tambah, ubah, hapus; rentang tidak boleh beririsan. Isi awal = aturan lama (Cum Laude 3,51–4,00, Sangat Memuaskan
  3,01–3,50, Memuaskan 2,76–3,00, Cukup 0,00–2,75). `Wisuda::predikat()` kini membaca tabel ini (`Predikat::untuk()`),
  null bila IPK di luar semua rentang; predikat SKL yang sudah terbit tidak berubah.
- Menu **Akademik → Konfigurasi** kini berisi Tahun Akademik, Mata Kuliah, **Mata Kuliah Prasyarat** (baru), Ruang, dan
  Bobot Nilai. Mata Kuliah Prasyarat (`/admin/mata-kuliah-prasyarat`, rute `admin.prasyarat.*`, izin `admin.prasyarat`
  bawaan Admin lewat migrasi `2026_10_02_080000_add_izin_mata_kuliah_prasyarat`, model `MataKuliahPrasyarat` di tabel
  `mata_kuliah_prasyarat` yang sudah ada) mengelola pasangan mata kuliah ↔ prasyarat: cari, filter prodi, tambah, edit,
  hapus. Aturannya sama dengan isian Prasyarat di form Mata Kuliah (tetap ada): prodi sama, semester prasyarat lebih
  kecil, tidak boleh diri sendiri atau ganda.
- **Bobot Nilai per prodi** (migrasi `2026_10_02_070000_create_bobot_nilais_table`, tabel `bobot_nilais`, unik per
  prodi + huruf): kolom sama dengan Skala Nilai (huruf, bobot, angka minimal, lulus, boleh diulang). Dikelola admin di
  menu **Akademik → Konfigurasi → Bobot Nilai** (izin `admin.bobot-nilai`, bawaan Admin); prodi yang belum diatur terisi
  awal dari Skala Nilai umum. Aturan validasinya dipakai bersama Skala Nilai (`App\ValidasiSkalaNilai`).
  Nilai KRS dihitung dengan Bobot Nilai **prodi mata kuliahnya** (`Krs::bobotNilai()/nilaiLulus()/nilaiBolehDiulang()`,
  `SkalaNilai::semua($prodiId)`); prodi tanpa Bobot Nilai tetap memakai Skala Nilai umum. Berlaku untuk IP/IPS/IPK, KHS,
  transkrip, SKS lulus, batas SKS, prasyarat & mengulang di KRS, IPK SKL wisuda, pilihan huruf dosen + batas huruf
  remidi, usulan remidi, dan konversi angka pendadaran (prodi mahasiswa). Huruf yang dipakai KRS prodi tidak bisa
  dihapus dari bobot prodi; bobot prodi tidak bisa dihapus bila hurufnya tidak ada di Skala Nilai umum; Skala Nilai
  umum hanya menjaga huruf yang dipakai prodi tanpa bobot sendiri.
- Menu **PMB** kini **Mahasiswa Baru**, dan **Data Pendaftar** menjadi **Calon Maba** (izin `admin.pendaftar-pmb` bernama
  "Calon Maba (PMB)"). Nilai dan status (Menunggu/Lulus/Ditolak) bisa diubah langsung di tabel. Aksi **Salin ke Master
  Mahasiswa** (calon maba Lulus, perlu juga izin `admin.users.mahasiswa`) membuat akun mahasiswa: username = nomor
  pendaftaran, sandi acak, lalu tautan atur sandi + verifikasi email dikirim. Profil diisi dari formulir PMB: L/P →
  Laki-laki/Perempuan, agama master (Kristen → Kristen Protestan, Katolik → Kristen Katolik), kode negara → nama negara,
  HP → no. telepon, jalan → alamat, angkatan dari periode, status **Aktif** (peserta baru) atau **Pindahan** (pindahan);
  foto, ijazah, dan transkrip disalin ke folder mahasiswa. Pendaftar yang sudah disalin tidak bisa disalin ulang, dihapus,
  atau diubah statusnya dari Lulus.
- **Biodata PDDIKTI** di Data Mahasiswa (migrasi `2026_10_02_050000_add_biodata_pmb_to_mahasiswa_profiles`): `cmb_id`,
  NIK (unik), NPWP, status perkawinan, HP wali, dusun, RT/RW, kelurahan, kecamatan Feeder, kode pos, alat transportasi,
  jenis tinggal, jenis masuk, KPS, jenis/jumlah pembiayaan, `jalur_kelas`, nilai UN, asal pindahan (PT, jenjang, prodi,
  NIM, SKS diakui), berkas ijazah/transkrip (unduh lewat `berkas.mahasiswa`: pemilik atau izin Data Mahasiswa). Tampil
  di form dan detail mahasiswa.
- Status mahasiswa baru **Pindahan**, diperlakukan sama dengan Aktif (`MahasiswaProfile::STATUS_AKTIF`, scope `aktif()`):
  bisa KRS, ditagih, bisa cuti, ikut dihitung mahasiswa aktif di dashboard, beranda dosen wali, dan ringkasan tagihan.
  Agama **Lainnya** ditambahkan ke pilihan agama mahasiswa.
- **Semester Masuk** di Data Mahasiswa (migrasi `2026_10_02_060000`, kolom `semester_masuk` + `tahun_akademik_masuk_id`):
  untuk mahasiswa pindahan, diisi admin dan dicatat pada tahun akademik yang aktif saat itu. Semester berikutnya dihitung
  dari situ (bukan dari angkatan). Paritasnya harus sesuai semester aktif (Ganjil → 1, 3, 5…; Genap → 2, 4, 6…); bila
  tidak, simpan ditolak dengan anjuran menurunkan satu semester. Mengosongkan isian kembali memakai hitungan angkatan.

- **Unggah berkas di formulir PMB**: seksi baru *Unggah Berkas* berisi **Pas Foto** (jpg/png), **Ijazah**, dan
  **Transkrip Nilai** (pdf/jpg/png), ketiganya wajib, maksimal 2 MB per berkas. Berkas disimpan di disk privat
  (`pmb/<id periode>/`, kolom `foto`, `berkas_ijazah`, `berkas_transkrip` di tabel `cmb`) dan dihapus lagi bila
  pendaftaran gagal tersimpan atau pendaftar dihapus. Detail pendaftar admin menampilkan pratinjau/tautan berkas lewat
  rute `berkas.pmb` (hanya izin `admin.pendaftar-pmb`).
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

- Isian baru **Program Studi**: gelar akademik, singkatan gelar, SKS lulus (dipakai sebagai syarat SKS pendadaran prodi),
  status prodi (Aktif/Pembinaan/Alih Bentuk/Alih Kelola/Tutup), nomor kaprodi, operator, nomor operator, nomor/tanggal/
  tanggal berakhir SK Dikti, alamat, provinsi, kota/kabupaten, kode pos, telepon, faximili, email, website.
- **Foto** di data mahasiswa, dosen, dan karyawan (kolom `foto` di `mahasiswa_profiles`, `dosen_profiles`,
  `admin_profiles`): unggah/ganti/hapus di form pengguna (jpg/jpeg/png/webp, maks. 2 MB), tampil di halaman detail.
  Berkas disimpan di disk privat dan dibuka lewat `/berkas/foto/{user}` (pemilik akun atau pemegang izin kelola jenis
  pengguna itu); foto lama terhapus saat diganti atau akun dihapus.

### Diubah
- **Syarat SKS** ikut menghitung **SKS diakui** mahasiswa pindahan (syarat ambil TA/Skripsi di KRS, syarat SKS
  pendadaran, dan SKS lulus di beranda mahasiswa; transkrip & IPK tetap hanya dari mata kuliah di sistem). Syarat SKS
  pendadaran memakai **SKS Lulus program studi** bila diisi, selain itu Pengaturan Akademik (`min_sks_pendadaran`).
- Syarat pendadaran/wisuda "Tidak ada nilai E" menjadi **"Tidak ada nilai tidak lulus"**: huruf tidak lulus mengikuti
  skala nilai prodi mata kuliahnya (Bobot Nilai), keterangan menyebut hurufnya, mis. "Statistika (E)".
- Form Data Mahasiswa: **NIM**, dosen wali, sekolah asal, NISN, email alternatif, dan data ayah/ibu (selain nama ibu)
  kini opsional; kolom `nim` boleh kosong (tetap unik bila diisi).
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
