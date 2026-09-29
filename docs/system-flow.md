# Alur Proses Bisnis SIA VD

Dokumen ini menjelaskan alur proses bisnis Sistem Informasi Akademik (SIA VD) **sesuai kode yang ada**, bukan rencana. Gambaran visualnya ada di [system-flowchart.md](system-flowchart.md).

- Disusun dari kode di cabang `main` pada commit `693048f` (27 September 2026).
- Rujukan kode ditulis sebagai `Kelas::metode` atau path berkas. Nomor baris sengaja tidak dicantumkan karena cepat berubah.
- Hal yang tidak bisa dipastikan dari kode, tampak tidak konsisten, atau masih placeholder ditandai **Perlu dikonfirmasi** dan dikumpulkan di [bagian 19](#19-perlu-dikonfirmasi).

## Daftar isi

1. [Aktor dan hak akses](#1-aktor-dan-hak-akses)
2. [Autentikasi](#2-autentikasi)
3. [Data master dan pengguna](#3-data-master-dan-pengguna)
4. [Pengaturan sistem](#4-pengaturan-sistem)
5. [Kelas kuliah dan jadwal](#5-kelas-kuliah-dan-jadwal)
6. [Keuangan semester](#6-keuangan-semester)
7. [KRS](#7-krs)
8. [Materi, tugas, dan quiz](#8-materi-tugas-dan-quiz)
9. [Presensi](#9-presensi)
10. [Ujian UTS dan UAS](#10-ujian-uts-dan-uas)
11. [Nilai akhir, kunci nilai, dan hasil studi](#11-nilai-akhir-kunci-nilai-dan-hasil-studi)
12. [Remidi](#12-remidi)
13. [Ujian susulan](#13-ujian-susulan)
14. [Tugas akhir, pendadaran, dan wisuda](#14-tugas-akhir-pendadaran-dan-wisuda)
15. [Cuti dan aktif kembali](#15-cuti-dan-aktif-kembali)
16. [Pindah kelas](#16-pindah-kelas)
17. [Fitur pendukung](#17-fitur-pendukung)
18. [Keterkaitan antarfitur dan daftar status](#18-keterkaitan-antarfitur-dan-daftar-status)
19. [Perlu dikonfirmasi](#19-perlu-dikonfirmasi)

---

## 1. Aktor dan hak akses

### 1.1 Jenis pengguna dan role

- Ada tiga jenis pengguna (`App\UserType`): `admin` (label "Admin / Karyawan"), `dosen`, dan `mahasiswa`.
- Jenis pengguna hanya menentukan tabel profil yang dipakai (`admin_profiles`, `dosen_profiles`, `mahasiswa_profiles`). Hak akses ditentukan oleh **role**.
- Setiap pengguna punya **satu role** (`users.role_id`, NOT NULL). Setiap role punya `user_type` dan sekumpulan **permission**.
  - Migrasi pengetatan (`2026_09_23_000006`) mengisi `role_id` kosong pada data lama dengan role sistem sesuai profil (`admin_profiles`, `dosen_profiles`, `mahasiswa_profiles`). Hanya pengguna tanpa profil sama sekali yang menghentikan migrasi.
- `PermissionCatalog::sync()` membuat satu role sistem per jenis (slug `admin`, `dosen`, `mahasiswa`, `is_system = true`).
  - Role sistem baru mendapat semua permission bawaan.
  - Role sistem yang sudah ada hanya ditambah permission yang baru masuk katalog, sehingga perubahan dari admin tidak tertimpa.
- Admin bisa membuat role tambahan di menu **Kelola Role**.

### 1.2 Cara izin diperiksa

- `Gate::before` meloloskan pengecekan bila `User::hasPermission($ability)` bernilai benar. Jadi setiap kunci permission langsung menjadi *ability*.
- Rute dijaga dengan middleware `can:<permission>`. Tidak ada kelas Policy.
- Frontend menerima `auth.permissions` lewat shared props dan menyembunyikan menu yang tidak diizinkan (`usePermissions().can()`, `AppSidebar.vue`).
- Rute dikelompokkan per prefix `admin/…`, `dosen/…`, dan `mahasiswa/…`. Semuanya memakai middleware `auth` dan `verified`.
- Halaman bersama (detail kelas, materi, tugas, quiz, presensi, ujian) melayani admin dan dosen sekaligus. Perannya ditentukan dari nama rute (`admin.*` atau `dosen.*`) lewat trait `Concerns\KontenKelas::peran()`.
  - Dosen hanya boleh membuka kelas yang ia ampu (`pastikanAksesKelas`, 403 bila bukan).
  - Pada daftar lintas kelas, filter dosen dipaksa ke dosen yang sedang masuk.

### 1.3 Kelompok permission

| Kelompok | Permission |
|---|---|
| Administrasi | `admin.dashboard`, `admin.info-kuliah`, `admin.tahun-akademik`, `admin.fakultas`, `admin.program-studi`, `admin.mata-kuliah`, `admin.ruang`, `admin.kelas-kuliah`, `admin.jadwal`, `admin.materi`, `admin.tugas`, `admin.quiz`, `admin.presensi`, `admin.ujian`, `admin.pindah-kelas`, `admin.pengajuan-akademik` (TA & Wisuda, Periode Wisuda) |
| Keuangan | `admin.jenis-biaya`, `admin.tagihan` (termasuk Tagihan Remidi) |
| Manajemen pengguna | `admin.users.dosen`, `admin.users.mahasiswa`, `admin.users.karyawan`, `admin.roles` |
| Pengaturan sistem | `admin.institusi`, `admin.pengaturan-email`, `admin.pengaturan-akademik`, `admin.pengaturan-tampilan` |
| Dosen | `dosen.dashboard`, `dosen.kelas-kuliah`, `dosen.jadwal`, `dosen.materi`, `dosen.tugas`, `dosen.quiz`, `dosen.presensi`, `dosen.ujian`, `dosen.mahasiswa-kelas`, `dosen.bimbingan` (Bimbingan TA) |
| Mahasiswa | `mahasiswa.dashboard`, `mahasiswa.info-kuliah`, `mahasiswa.krs`, `mahasiswa.hasil-studi`, `mahasiswa.jadwal-kuliah`, `mahasiswa.presensi`, `mahasiswa.ujian`, `mahasiswa.pindah-kelas`, `mahasiswa.tugas-akhir`, `mahasiswa.info-biaya`, `mahasiswa.perpustakaan` |

- Permission berawalan `admin.` (kolom `user_type` NULL) hanya bisa diberikan ke role **Admin / Karyawan** dan **Dosen** (dosen boleh merangkap staf, mis. kaprodi). Role Mahasiswa tidak pernah mendapatkannya (`Permission::isAvailableFor`); sambungan lama dilepas migrasi `2026_09_28_100000`, dan yang tetap tersambung diabaikan `Role::permissionKeys()`.
- Permission `dosen.*` dan `mahasiswa.*` hanya berlaku untuk role dengan jenis yang sama.

### 1.4 Aturan eskalasi hak

- `User::canAssignRole(role)`: aktor boleh memberikan sebuah role bila ia punya `admin.roles`, atau bila semua permission role itu juga ia miliki.
- `User::canManage(target)`: aktor tidak bisa mengubah atau menghapus akun yang hak aksesnya lebih tinggi.
- Pemegang terakhir `admin.roles` tidak bisa diganti role-nya atau dihapus (`User::isLastRoleManager`).
- Permission `admin.roles` tidak bisa dicabut dari role milik aktor sendiri maupun dari role sistem Admin.
- Pengguna tidak bisa menghapus akunnya sendiri.

### 1.5 Daftar rute yang dikirim ke browser (Ziggy)

`User::grupRute()` memilih grup rute Ziggy:

- `staf` untuk pengguna yang punya permission `admin.*` atau `dosen.*` (hanya role Admin/Karyawan dan Dosen);
- `mahasiswa` untuk pengguna lain yang sudah masuk;
- `umum` untuk tamu.

Karena daftar rute berbeda per grup, login dan logout memakai *full reload* (`Inertia::location`).

---

## 2. Autentikasi

### 2.1 Login

1. `/` langsung dialihkan ke halaman login.
2. Pengguna mengisi kolom **NIM / NIDN / Username**, `password`, dan opsi `remember`.
   - `User::usernameUntukMasuk()` mencari akun berurutan: `users.username`, lalu `mahasiswa_profiles.nim`, lalu `dosen_profiles.nidn` (spasi di tepi dibuang). Username didahulukan bila kebetulan sama dengan NIM/NIDN akun lain.
   - Pesan gagal: "NIM/NIDN/username atau kata sandi tidak cocok."
   - Sesudah kata sandi cocok, status profil diperiksa (`User::alasanTidakBolehMasuk`): **dosen berstatus `Nonaktif`** dan **mahasiswa berstatus di luar `Aktif`, `Lulus`, `Cuti`** (`MahasiswaProfile::STATUS_BOLEH_MASUK`: Nonaktif, Dropout, Mengundurkan Diri, Meninggal) ditolak dengan pesan yang menyebut statusnya. Mahasiswa `Lulus`/`Cuti` tetap bisa masuk untuk melihat data (KHS, transkrip, SKL), tetapi tidak ditagih dan tidak bisa mengambil KRS, sehingga tidak ada mata kuliah/jadwal kelas baru. Status tidak disebut bila kata sandi salah. Admin/karyawan tidak diperiksa.
3. Batas percobaan:
   - 5 percobaan per kombinasi `username|IP` (`LoginRequest`), lalu terkunci sementara;
   - `throttle:20,1` per IP pada rute `POST login`.
4. Bila berhasil, sesi diregenerasi lalu dialihkan ke `User::homeRoute()`, dengan urutan:
   - `<jenis>.dashboard` bila pengguna punya permission-nya;
   - dashboard lain yang ia miliki (`admin`, lalu `dosen`, lalu `mahasiswa`);
   - `/dashboard` sebagai cadangan.
5. Akun yang emailnya belum terverifikasi tetap bisa masuk, tetapi rute ber-middleware `verified` mengalihkannya ke halaman **Verifikasi Email** (lihat 2.4).
6. `AuthenticateSession` aktif. Mengganti kata sandi di pengaturan profil mengakhiri sesi di perangkat lain.
7. Middleware `PastikanAkunAktif` (grup web) memeriksa aturan status yang sama di setiap permintaan: sesi yang masih terbuka langsung diakhiri dan dialihkan ke login (muat ulang penuh) beserta pesannya begitu admin menonaktifkan dosen atau mengubah status mahasiswa.

### 2.2 Logout

`POST logout` mengakhiri sesi, menghapus sesi, mengganti token, lalu memuat ulang ke `/`.

### 2.3 Lupa dan atur ulang kata sandi

1. Pengguna mengisi **email** di halaman lupa kata sandi (`throttle:6,1`).
   - Balasan selalu sama, supaya keberadaan email tidak bisa ditebak.
2. Sistem mengirim notifikasi `AturUlangKataSandi` (bahasa Indonesia, menyebut username).
   - Tautan berlaku 60 menit. Permintaan ulang dibatasi 60 detik.
3. Di halaman atur ulang, pengguna mengisi `token`, `email`, `password`, dan konfirmasinya. Aturan kata sandi memakai `Password::defaults()`.
4. Setelah berhasil, `remember_token` diganti dan pengguna dialihkan ke login.

### 2.4 Verifikasi email dan konfirmasi kata sandi

- **Tidak ada registrasi mandiri.** Semua akun dibuat admin.
- **Verifikasi email aktif.** `User` mengimplementasikan `MustVerifyEmail`; rute `admin/…`, `dosen/…`, `mahasiswa/…`, `berkas/…`, dan Pengaturan Sistem memakai middleware `verified`. Pengaturan Profil tetap terbuka agar email yang salah bisa dibetulkan.
  - Akun yang sudah ada saat fitur ini dinyalakan ditandai terverifikasi (migrasi `2026_09_28_110000`); akun demo dari seeder juga langsung terverifikasi.
  - Akun baru buatan admin dikirimi surel `VerifikasiEmail` (bahasa Indonesia, tautan bertanda tangan 60 menit). Mengganti email (oleh admin atau pengguna sendiri) mengosongkan `email_verified_at` dan mengirim tautan ke alamat baru.
  - Kegagalan SMTP tidak menggagalkan penyimpanan (`User::kirimVerifikasiEmail()` mencatat galat ke log); pesan galat menyarankan kirim ulang.
  - Di halaman detail pengguna, admin bisa **Kirim Ulang Tautan Verifikasi** (`throttle:6,1`) atau **Tandai Terverifikasi** manual, bila boleh mengelola akun itu (`canManage`).
- **Konfirmasi kata sandi** (`password.confirm`, berlaku 3 jam) dipasang di menu **Kelola User** (dosen, mahasiswa, karyawan) dan **Kelola Role**, setelah pengecekan izin. Aksi selain GET kembali ke halaman asal sesudah konfirmasi.

### 2.5 Profil pengguna

- Menu **Pengaturan Profil** (`/settings`) berisi dua bagian: Profil dan Kata Sandi.
- **Profil:** pengguna bisa mengubah `name` dan `email` (email unik dan huruf kecil).
  - Nama **mahasiswa** tidak bisa diubah sendiri karena tercetak di KHS. Field itu diabaikan saat validasi.
  - Mengganti email mengosongkan `email_verified_at` dan mengirim tautan verifikasi baru.
- **Username** hanya bisa diubah admin.
- Tidak ada fitur hapus akun sendiri.

---

## 3. Data master dan pengguna

Semua menu di bagian ini milik admin dan memakai pencarian serta paginasi 10 baris.

### 3.1 Tahun akademik

**Field:**

- `tahun` (format `2026/2027`), `semester` (`Ganjil`/`Genap`);
- `tanggal_mulai`, `tanggal_akhir`;
- `tanggal_krs_awal`, `tanggal_krs_akhir`;
- `tanggal_cuti_awal`, `tanggal_cuti_akhir` (periode pengajuan cuti **untuk** semester ini, lihat [bagian 15](#15-cuti-dan-aktif-kembali));
- `batas_input_nilai`, `batas_bayar_remidi`, `batas_input_nilai_remidi`;
- `status` (aktif atau tidak).

**Validasi:**

- `tahun` unik per `semester`.
- `tahun` wajib berformat `YYYY/YYYY` dengan tahun kedua = tahun pertama + 1, dan `semester` hanya `Ganjil` atau `Genap`. Keduanya dipakai menghitung semester mahasiswa ([3.3](#33-pengguna-manage-user)).
- Tanggal akhir ≥ tanggal mulai. Tanggal KRS akhir ≥ tanggal KRS awal.
- Tanggal KRS akhir ≤ tanggal akhir semester. Tanggal KRS awal boleh sebelum tanggal mulai kuliah.
- Tanggal buka dan tutup pengajuan cuti diisi berdua atau dikosongkan berdua (kosong = tidak dibuka); tutup ≥ buka dan ≤ tanggal akhir semester. Buka boleh sebelum semester dimulai.
- `batas_input_nilai` ≥ `tanggal_akhir`, karena UAS paling lambat di tanggal akhir.
- `batas_bayar_remidi` harus **setelah** `tanggal_akhir`, dan setelah `batas_input_nilai` bila diisi.
- `batas_input_nilai_remidi` harus **setelah** `batas_bayar_remidi` (bila keduanya diisi).
- Hanya boleh ada **satu tahun akademik aktif**. Mengaktifkan tahun kedua ditolak ("Sudah ada tahun akademik yang aktif."). Tahun lain tidak dinonaktifkan otomatis.

**Efek samping:**

- `tanggal_mulai` **terkunci** begitu ada kelas di tahun itu yang sudah punya pertemuan; perubahannya ditolak dengan pesan jumlah kelas terdampak (form menampilkan keterangan terkunci). Pertemuan tidak pernah digeser otomatis.
- Tahun akademik yang sudah punya kelas tidak bisa dihapus.

**Urutan:** daftar dan pilihan tahun akademik di semua halaman diurutkan menurut `tanggal_mulai` (terbaru dulu), bukan teks tahun/semester.

**Periode KRS dianggap aktif** bila hari ini berada di antara `tanggal_krs_awal` dan `tanggal_krs_akhir` (inklusif). Bila salah satunya kosong, periode dianggap tertutup.

### 3.2 Fakultas, program studi, mata kuliah, ruang

| Data | Field utama | Ditolak dihapus bila |
|---|---|---|
| Fakultas | `kode_fakultas` (unik), `nama_fakultas` (unik), `dekan_id`, tanggal berdiri, kontak | masih punya program studi |
| Program studi | `fakultas_id`, `kode_prodi` (unik), `nama_prodi`, `jenjang`, akreditasi, `kaprodi`, `tahun_berdiri` | masih punya mata kuliah, mahasiswa, atau dosen |
| Mata kuliah | `kode_matkul` (unik), `nama_matkul`, `sks` 1–6, `semester` 1–14, `jenis` (Wajib/Pilihan), `prodi_id`, prasyarat | dipakai kelas kuliah |
| Ruang | `kode_ruang` (unik), `nama_ruang`, `kapasitas` 1–1000, `detail` | dipakai jadwal |

**Prasyarat mata kuliah** (tabel `mata_kuliah_prasyarat`, diisi di form mata kuliah):

- prasyarat harus mata kuliah dari **prodi yang sama** dengan **semester lebih kecil**, dan bukan mata kuliah itu sendiri. Karena itu prasyarat tidak mungkin melingkar;
- semester mata kuliah tidak bisa diubah menjadi sama atau lebih besar dari mata kuliah yang mensyaratkannya, dan prodinya tidak bisa diubah selama ia menjadi prasyarat;
- detail mata kuliah menampilkan "Harus Lulus Dulu" dan "Menjadi Prasyarat Untuk";
- menghapus mata kuliah ikut menghapus hubungan prasyaratnya.

### 3.3 Pengguna (Manage User)

Tiga menu terpisah: **Dosen**, **Mahasiswa**, dan **Karyawan** (karyawan berjenis `admin`). Masing-masing dijaga permission `admin.users.<tipe>`.

**Field umum:**

- `role_id` (harus sesuai jenis pengguna);
- `name`, `username` (unik), `email` (unik);
- kata sandi: minimal 8 karakter dan dikonfirmasi; wajib saat membuat, opsional saat mengubah;
- data diri.

**Field per jenis:**

- Karyawan: `nomor_induk`.
- Dosen: `nidn` (unik), jabatan fungsional, pendidikan, status kepegawaian, `status` (`Aktif`/`Nonaktif`, bawaan `Aktif`; nonaktif = tidak dapat masuk, lihat 2.1), `prodi_id`.
  - Dosen `Nonaktif` tidak muncul di pilihan dosen (dosen wali, dekan, kaprodi, pengampu kelas, dosen pertemuan, pembimbing, penguji) dan ditolak validasi (`DosenProfile::pilihan`/`rulePilihan`), kecuali dosen yang sudah terpilih pada data yang sedang diedit. Filter daftar (kelas, jadwal) tetap memuat semua dosen agar data lama bisa dicari.
- Mahasiswa: `nim` (unik), `angkatan` (wajib, 4 digit), `status`, `dosen_wali_id`, `prodi_id`, sekolah asal, `nisn` (10 digit, unik), `email_alternatif`, data orang tua.
  - Pilihan `status`: `Aktif`, `Nonaktif`, `Lulus`, `Dropout`, `Cuti`, `Mengundurkan Diri`, `Meninggal`. `Aktif`, `Lulus`, dan `Cuti` dapat masuk; status lain ditolak (lihat 2.1). Hanya `Aktif` yang ditagih (terbit massal maupun rincian manual admin) dan boleh mengambil KRS; halaman KRS mahasiswa lain tidak menawarkan kelas apa pun. Status `Transfer Masuk` dihapus 28 Sep 2026; mahasiswa yang berstatus itu diubah menjadi `Aktif` saat migrasi.
  - **Semester tidak disimpan**, tetapi dihitung dari angkatan (`MahasiswaProfile::semesterPada`): `(tahun pertama tahun akademik − angkatan) × 2 + (Ganjil ? 1 : 2)`. Angkatan 2024 berada di semester 1 pada 2024/2025 Ganjil dan semester 5 pada 2026/2027 Ganjil. Semester tetap bertambah selama mahasiswa cuti. Hasilnya kosong bila mahasiswa belum mulai kuliah. Detail pengguna menampilkan semester pada tahun akademik aktif.

**Hapus pengguna ditolak bila:**

- menghapus akun sendiri;
- target punya hak lebih tinggi;
- target pemegang terakhir `admin.roles`;
- dosen masih mengampu kelas, menjabat dekan atau kaprodi, atau menjadi dosen wali;
- mahasiswa sudah punya KRS, presensi, pengajuan izin, atau dispensasi.

### 3.4 Kelola role

- Field role: `name` (unik), `description`, `user_type`, dan daftar permission.
- `user_type` tidak bisa diubah untuk role sistem maupun role yang sedang dipakai.
- Permission yang tidak cocok dengan jenis role ditolak (termasuk menu `admin.*` untuk role Mahasiswa).
- Role sistem dan role yang masih dipakai pengguna tidak bisa dihapus.

---

## 4. Pengaturan sistem

- Menu **Pengaturan Sistem** berupa halaman bertab di `/pengaturan-sistem/{tab}`.
- `/pengaturan-sistem` membuka tab pertama yang diizinkan. Bila tidak punya izin satu pun, hasilnya 403.

| Tab | Permission | Isi |
|---|---|---|
| Institusi | `admin.institusi` | Nama PT (bawaan "SIA VD"), singkatan, logo (jpg/png/webp, maks 2 MB), NPSN, alamat, kontak, tahun berdiri. Dipakai di kop PDF dan tampilan. |
| Email | `admin.pengaturan-email` | Mailer `log` atau `smtp`. Kata sandi SMTP disimpan terenkripsi dan tidak pernah dikirim ke browser. Ada tombol kirim surel uji (`throttle:6,1`). Nilai di database menimpa `.env`. |
| Akademik | `admin.pengaturan-akademik` | Delapan formulir, dijelaskan di bawah tabel ini. |
| Tampilan | `admin.pengaturan-tampilan` | Nama aplikasi, favicon (png/ico/webp, SVG ditolak), halaman masuk (judul, teks, gambar, tata letak `panel`/`tengah`, sorotan fitur), sidebar bawaan (`lebar`/`ringkas`). |

**Delapan formulir di tab Akademik:**

1. **Kunci KRS oleh pembayaran**: sakelar `kunci_krs_aktif`, bawaan mati (lihat [6.4](#64-kunci-krs-oleh-pembayaran)).
2. **Batas SKS**:
   - `maks_sks_tanpa_ips`, bawaan 20;
   - tabel bertingkat IPS minimal → maks SKS, bawaan 3,00→24, 2,50→21, 2,00→18, 0,00→15;
   - wajib ada baris IPS minimal 0.
3. **Skala nilai**:
   - setiap baris berisi `huruf`, `bobot` 0–4, `angka_minimal` 0–100 (boleh kosong), `lulus`, dan `boleh_diulang`;
   - bawaan: A=4, B=3, C=2, D=1 (lulus, boleh diulang), E=0 (tidak lulus, boleh diulang); angka minimal A 80, B 70, C 60, D 50, E 0;
   - angka minimal dipakai mengonversi nilai pendadaran menjadi huruf ([14.4](#144-penilaian-dan-hasil-pendadaran)); huruf berbobot lebih tinggi wajib berangka minimal lebih tinggi;
   - wajib ada minimal satu huruf `lulus` dan satu huruf tidak lulus, dan setiap huruf tidak lulus wajib `boleh_diulang`;
   - huruf yang sudah dipakai di KRS tidak bisa dihapus.
4. **Pindah kelas**: sakelar membuka atau menutup formulir pengajuan mahasiswa, bawaan tertutup.
5. **Remidi**: `huruf_maks_remidi`, yaitu huruf tertinggi setelah remidi. Kosong berarti bebas.
   - Bila huruf itu dihapus dari skala, isian ini ikut dikosongkan.
6. **Ujian susulan**:
   - `batas_pengajuan_susulan_hari` (bawaan 3): pengajuan dibuka sampai N hari setelah tanggal ujian;
   - `batas_bayar_susulan_hari` (bawaan 3): batas bayar tiap tagihan susulan = tanggal terbit + N hari.
7. **Tugas akhir**: `min_sks_pendadaran` (bawaan 138), SKS bernilai minimal di luar TA untuk mendaftar pendadaran dan wisuda.
   **Cuti**: `maks_cuti` (bawaan 2, 0–14), jumlah semester cuti yang boleh disetujui selama studi ([bagian 15](#15-cuti-dan-aktif-kembali)).
8. **Presensi**:
   - `jumlah_pertemuan` (bawaan 16, hanya untuk kelas baru);
   - `min_kehadiran_ujian` (75%);
   - `toleransi_terlambat_menit` (15);
   - `durasi_presensi_mandiri_menit` (15);
   - `batas_pengajuan_izin_hari` (1);
   - `syarat_ujian_aktif` (bawaan mati).

---

## 5. Kelas kuliah dan jadwal

### 5.1 Kelas kuliah

- Hanya **admin** yang bisa membuat, mengubah, dan menghapus kelas. Dosen hanya melihat kelas yang ia ampu.
- **Field:**
  - `kode_kelas` (unik per tahun akademik);
  - `tahun_akademik_id`;
  - `dosen_id` (satu dosen pengampu);
  - `matkul_id`;
  - `kapasitas` 1–500;
  - `jumlah_pertemuan` 1–32. Kelas baru tanpa isian ini memakai nilai bawaan dari Pengaturan Akademik.
- Setelah kelas punya KRS, `matkul_id` dan `tahun_akademik_id` **tidak bisa diubah**.
- **Mengubah jumlah pertemuan** (`KelasKuliah::ubahJumlahPertemuan`):
  - ditolak bila pertemuan yang akan terbuang sudah berjalan atau punya presensi atau izin;
  - UAS dipindah ke pertemuan terakhir, UTS ke pertemuan tengah (n/2);
  - pertemuan baru dibuat otomatis.
- **Hapus kelas** ditolak bila sudah ada KRS, pertemuan yang berjalan, presensi, izin, atau dispensasi. Bila boleh, pertemuan dan jadwal ujiannya ikut terhapus.
- **Admin membatalkan KRS** mahasiswa dari halaman kelas hanya bila nilainya masih kosong.

### 5.2 Jadwal mingguan

- Dibuat admin per kelas. Field: `hari` (Senin–Sabtu), `jam_mulai`, `jam_akhir` (harus setelah jam mulai), `ruang_id`.
- **Pengecekan bentrok** dalam tahun akademik yang sama:
  - dengan jadwal lain kelas itu sendiri;
  - dengan ruang yang sama;
  - dengan dosen pengampu yang sama di kelas lain.
- Bentrok jadwal **mahasiswa** tidak dicek di sini. Pengecekannya dilakukan saat KRS dan saat admin menyetujui pindah kelas.
- Jadwal mingguan hanya dipakai saat **membuat** pertemuan. Menambah, mengubah, atau menghapus jadwal tidak mengubah pertemuan yang sudah ada; form jadwal menampilkan keterangan itu bila kelas sudah punya pertemuan.

### 5.3 Halaman kelas

- **Admin dan dosen** (`Kelas/KelasKuliahShow`) melihat: identitas kelas, jadwal, materi, tugas, quiz, tabel **Nilai Mahasiswa** (huruf akhir), dan bagian **Daftar Remidi** (muncul setelah nilai kelas final).
- **Mahasiswa** melihat kelas hanya bila punya KRS di kelas itu. Isinya: jadwal, materi, tugas, dan quiz biasa (lembar soal ujian tidak termasuk).
- Menu tersendiri **Jadwal**, **Materi**, **Tugas**, dan **Quiz** menampilkan data lintas kelas dengan filter tahun akademik (bawaan: tahun aktif), prodi, mata kuliah, kelas, dan dosen (khusus admin).

---

## 6. Keuangan semester

### 6.1 Jenis biaya dan tarif

- **Field jenis biaya:**
  - `kode` (unik), `nama`;
  - `cara_hitung`: `tetap` atau `per_sks`;
  - `kategori`: `semester`, `remidi`, `susulan`, `pendadaran`, `wisuda`, atau `cuti` (bawaan `semester`);
  - `aktif`, `urutan`.
- Kategori `semester` dipakai tagihan semester. Kategori `remidi` hanya dipakai tagihan remidi ([bagian 12](#12-remidi)), dan `susulan` hanya dipakai tagihan ujian susulan ([bagian 13](#13-ujian-susulan)).
- Kategori `pendadaran`, `wisuda`, dan `cuti` **tidak pernah ditagihkan**: hanya ditampilkan sebagai informasi di Biaya Kuliah, dengan cara hitung selalu `tetap` ([14.6](#146-biaya-dan-pengingat), [bagian 15](#15-cuti-dan-aktif-kembali)).
- **Tarif** dicatat per jenis biaya, per prodi, dan per angkatan (keduanya boleh kosong, artinya berlaku untuk semua).
- Tarif yang dipakai adalah **yang paling khusus**: prodi+angkatan, lalu prodi saja, lalu angkatan saja, lalu umum (`JenisBiaya::tarifUntuk`).
- Menghapus jenis biaya tidak mengubah tagihan lama, karena nama dan nominalnya sudah disalin ke rincian.

### 6.2 Menerbitkan tagihan semester

Admin membuka menu **Keuangan → Tagihan Mahasiswa**, lalu **Terbitkan Tagihan Semester Ini** (`TagihanController::terbitkan`):

1. Harus ada jenis biaya aktif berkategori `semester`. Bila tidak ada, penerbitan ditolak.
2. Bila masih ada KRS tanpa nilai di tahun akademik sebelumnya, sistem meminta konfirmasi (`tagihan_konfirmasi`). Alasannya, kuota SKS sebagian mahasiswa akan memakai angka "tanpa IPS". Admin bisa lanjut (`paksa`) atau batal.
3. Untuk setiap mahasiswa berstatus **`Aktif`**:
   - tagihan yang sudah ada **hanya dihitung ulang** bila `belum_bayar`, belum ada bukti, dan rinciannya tidak diketik manual (`TagihanSemester::bolehDihitungUlang`). Tagihan `lunas`, `menunggu_verifikasi`, `ditolak` (sudah ada bukti), atau berincian manual tidak disentuh;
   - rincian dihitung dengan `TagihanSemester::hitungRincian` lalu disimpan dengan `gantiRincian`;
   - bila totalnya **Rp0** (tidak ada tarif yang cocok), tagihan **tidak dibuat**. Tagihan lama yang boleh dihitung ulang ikut dihapus;
   - pesan hasil menyebut jumlah tagihan baru, yang dihitung ulang, yang tidak disentuh, dan mahasiswa tanpa tarif.
4. **Rumus rincian:**
   - komponen `tetap` = nominal tarif × 1;
   - komponen `per_sks` = nominal tarif × **kuota SKS**, yaitu batas SKS dari IPS semester sebelumnya, **bukan** SKS yang sudah diambil;
   - komponen tanpa tarif atau bernominal 0 dilewati.
   - Artinya tagihan terbit **sebelum** KRS diisi.
5. Nama dan nominal setiap komponen dibekukan di `tagihan_item`, jadi perubahan tarif berikutnya tidak mengubah tagihan yang sudah terbit.

### 6.3 Status dan pengelolaan tagihan

- **Status tagihan:** `belum_bayar`, `menunggu_verifikasi`, `ditolak`, `lunas` (trait `TagihanBerbukti`, sama dengan tagihan remidi dan susulan, tetapi **tanpa batas bayar** sehingga tidak pernah gugur). Setiap mahasiswa punya satu tagihan per tahun akademik. Daftar admin menampilkan mahasiswa tanpa tagihan sebagai **Belum Terbit**.
- **Alur bayar:**
  1. Mahasiswa mengunggah bukti (PDF/JPG/PNG, maks 5 MB) di **Biaya Kuliah**, untuk semester berjalan maupun riwayat yang belum lunas (`mahasiswa.tagihan-semester.bukti`). Status menjadi `menunggu_verifikasi`; bukti lama dihapus bila diganti.
  2. Admin membuka bukti (`berkas.bukti-semester`, hanya pemilik tagihan dan admin keuangan) lalu **Tandai Lunas** atau **Tolak** dengan alasan wajib. Bukti yang ditolak (`ditolak`) boleh diunggah ulang.
  3. Admin boleh **Tandai Lunas tanpa bukti** (mis. bayar di loket), atau **mengunggah bukti sendiri** dari halaman Rincian; unggahan admin langsung menandai lunas.
  4. **Batal Lunas** mengembalikan tagihan ke `menunggu_verifikasi` bila ada bukti, selain itu ke `belum_bayar`.
- Semua aksi bayar hanya untuk tagihan yang **sudah terbit**. `tanggal_lunas` diisi dan dikosongkan otomatis mengikuti status; verifikator dicatat di `diverifikasi_oleh`/`diverifikasi_at`.
- **Rincian manual:** admin bisa mengetik ulang komponen tagihan (minimal satu baris, total tidak boleh Rp0). Tagihan yang belum terbit ikut dibuat berstatus `belum_bayar`. Tagihan ditandai `rincian_manual` sehingga tidak ditimpa saat diterbitkan ulang. Tagihan `lunas` tidak bisa diubah rinciannya sebelum lunasnya dibatalkan.
- **Mahasiswa**, di menu **Biaya Kuliah**, melihat:
  - tagihan semester berjalan dan riwayatnya, beserta status, alasan tolak, dan isian unggah bukti;
  - panel "Dari Mana Angka Ini?" (tarif per SKS, kuota, SKS yang sudah diambil, sisa SKS yang sudah ditagih tetapi belum diambil);
  - tagihan remidi.

### 6.4 Kunci KRS oleh pembayaran

- Middleware `tagihan.lunas` (`PastikanTagihanLunas`) dipasang di semua rute KRS mahasiswa.
- Mahasiswa **dikunci** hanya bila semua syarat ini terpenuhi:
  - sakelar `kunci_krs_aktif` menyala;
  - ada tahun akademik aktif;
  - tagihan tahun itu **sudah terbit**;
  - tagihan itu belum `lunas` (termasuk yang `menunggu_verifikasi`).
- Akibatnya:
  - permintaan GET menampilkan halaman **KRS Terkunci** (rincian tagihan, batas KRS, dan keterangan bila bukti sedang diperiksa atau ditolak);
  - permintaan lain ditolak dengan pesan "Tagihan semester ini belum lunas…".
- Bila tagihan belum terbit, mahasiswa **tidak** dikunci.

---

## 7. KRS

Mahasiswa mengisi KRS di menu **Rencana Studi (KRS)**. Semua rute KRS dijaga `can:mahasiswa.krs` dan `tagihan.lunas`.

### 7.1 Syarat dasar

- Status mahasiswa harus `Aktif` (`Krs::STATUS_MAHASISWA_BOLEH_KRS`).
- Harus ada tahun akademik aktif dan periode KRS sedang berjalan. Di luar periode, daftar kelas tampil kosong.
- **Batas SKS** (`PengaturanAkademik::maksSksUntuk`) diambil dari IPS semester sebelumnya (`MahasiswaProfile::ipsSemesterSebelum`):
  - "semester sebelumnya" adalah tahun akademik **terakhir yang pernah diambil mahasiswa**;
  - IPS bernilai kosong bila belum pernah kuliah, atau bila ada nilai semester itu yang belum lengkap. Batasnya lalu memakai `maks_sks_tanpa_ips`.
- **Kelas yang ditawarkan** (`App\TawaranKrs`): kelas di tahun akademik aktif dari prodi mahasiswa, dengan mata kuliah yang termasuk salah satu dari:
  1. **semester ini:** semester mata kuliah sama dengan semester mahasiswa ([3.3](#33-pengguna-manage-user));
  2. **tertunda:** semester lebih kecil dengan paritas sama (Ganjil/Genap) dan **belum pernah diambil**, misalnya karena cuti. Mata kuliah semester 3 yang terlewat muncul di semester 5, lalu 7, dan seterusnya sampai diambil. Berlaku juga untuk mata kuliah Pilihan;
  3. **mengulang:** pernah diambil, nilainya sudah keluar, dan semua nilainya `boleh_diulang`.

  Mata kuliah yang sudah lulus (nilai tidak `boleh_diulang`) atau pengambilan lamanya belum bernilai tidak ditawarkan. Kelas yang sudah diambil tahun ini selalu tampil agar bisa dibatalkan. Halaman KRS memberi label **"Tertunda smt N"** atau **"Mengulang"**.
- **Prasyarat:** mata kuliah yang prasyaratnya belum lulus **tetap tampil tetapi terkunci** dengan alasan "Prasyarat: … belum lulus". Prasyarat dianggap lulus bila nilainya sudah keluar dan `lulus`, termasuk nilai yang masih `boleh_diulang` (bawaan: D). Prasyarat yang sedang diambil semester ini belum bernilai, jadi mata kuliah lanjutannya baru bisa diambil di periode berikutnya yang menawarkannya.

### 7.2 Mengambil kelas (`KrsController::store`)

Pemeriksaan dilakukan berurutan. Kegagalan pertama menghentikan proses.

1. Mata kuliah dari prodi mahasiswa dan kelas di tahun akademik aktif (bila tidak, 404).
2. Periode KRS sedang berjalan.
3. KRS belum disimpan (belum terkunci).
4. Status mahasiswa diizinkan.
5. Dalam transaksi, baris mahasiswa dan kelas dikunci (`lockForUpdate`).
6. Riwayat mata kuliah yang sama (`TawaranKrs::alasanTidakBolehAmbil`):
   - sudah diambil di tahun ini, termasuk di kelas paralel → ditolak;
   - pengambilan lama belum bernilai → ditolak;
   - nilai lama tidak `boleh_diulang` → ditolak ("sudah lulus").
7. Mata kuliah harus termasuk tawaran (semester ini, tertunda, atau mengulang; lihat 7.1), bila tidak ditolak ("tidak ditawarkan untuk semester Anda"). Lalu semua prasyarat harus sudah lulus, bila tidak ditolak ("Prasyarat: … belum lulus").
8. Tidak bentrok jadwal dengan kelas lain yang sudah diambil tahun ini (`Jadwal::bentrokUntukMahasiswa`).
9. Kelas belum penuh (jumlah KRS < `kapasitas`).
10. Total SKS tahun ini ditambah SKS kelas ini tidak melebihi batas SKS.
11. Baris KRS dibuat dengan status `Aktif`. Pasangan mahasiswa–kelas unik.

### 7.3 Membatalkan kelas

- **Mahasiswa:** hanya KRS miliknya, selama periode KRS, sebelum KRS disimpan, dan nilainya masih kosong.
  - Pembatalan juga menghapus pengajuan pindah kelas `pending` dari kelas itu (`Krs::cancel`).
- **Admin**, dari halaman kelas: hanya mensyaratkan nilai kosong. Periode dan kunci KRS tidak dicek.

### 7.4 Menyimpan (mengunci) KRS

1. Mahasiswa menekan **Simpan KRS**. Syaratnya: periode berjalan, belum pernah disimpan, dan minimal satu kelas sudah diambil.
2. Bila SKS yang diambil masih di bawah batas, sistem meminta konfirmasi (`krs_konfirmasi`).
3. Sistem mencatat baris `krs_semester` (waktu simpan). Sejak itu mahasiswa tidak bisa lagi menambah atau membatalkan kelas sendiri.
4. Jalan keluar setelah KRS terkunci:
   - **pindah kelas** ([bagian 16](#16-pindah-kelas));
   - **admin membuka kunci KRS** dari menu Tagihan Mahasiswa, yang menghapus baris `krs_semester`. Mahasiswa lalu bisa mengubah KRS selama periode masih berjalan.

---

## 8. Materi, tugas, dan quiz

### 8.1 Materi

- Dibuat admin atau dosen pengampu. Field: `judul_materi`, `pertemuan_ke`, `jenis` (`Materi` atau `Pengumuman`), `catatan`, dan berkas (maks 5 × 10 MB).
- Jenis berkas mengikuti whitelist `AllowedUpload`: dokumen office, pdf, txt, csv, gambar, arsip, mp3/mp4. Ekstensi dan isi berkas harus cocok.
- Berkas disimpan di disk privat dan diunduh lewat `BerkasController`. Pdf dan gambar dibuka *inline*, lainnya diunduh.

### 8.2 Tugas

- **Field tugas:** `judul_tugas`, `tenggat_waktu` (opsional), `catatan`, dan berkas soal.
- **Pengumpulan oleh mahasiswa** (`PengumpulanTugasController`):
  - harus punya KRS di kelas itu;
  - ditolak bila tenggat sudah lewat. Tanpa tenggat, tidak ada batas;
  - berkas pdf/doc/docx/xls/xlsx/ppt/pptx/zip/jpg/jpeg/png, 1–5 × 10 MB;
  - mengirim ulang mengganti semua berkas lama;
  - pengumpulan yang **sudah dinilai terkunci**.
- **Penilaian:** dosen atau admin mengisi nilai 0–100 per pengumpulan. Dosen tidak bisa menilai bila nilai kelas terkunci ([bagian 11.2](#112-kunci-nilai)).

### 8.3 Quiz

**Pengaturan quiz:**

- Field: `nama_quiz`, `catatan`, `waktu_pengerjaan` (1–1440 menit, opsional), `tenggat_waktu` (opsional).
- Jenis soal: `single_choice`, `multiple_choice`, `true_false`, `essay`. Poin 0–1000.
- Soal selain esai wajib punya minimal satu jawaban benar.

**Mengerjakan quiz:**

1. **Mulai** (`QuizAttemptController::start`):
   - mahasiswa harus ber-KRS dan tenggat belum lewat;
   - setiap mahasiswa hanya punya **satu attempt** per quiz (`createOrFirst`, pasangan quiz–mahasiswa unik).
2. **Batas waktu dihitung di server.** Batasnya adalah yang lebih dulu antara `mulai + waktu_pengerjaan` dan `tenggat_waktu`, dengan toleransi kirim 60 detik.
3. **Soal hanya dikirim ke browser selama attempt berjalan.** Kunci jawaban tidak pernah dikirim.
4. **Simpan otomatis** berlangsung setiap kali jawaban berubah (jeda 1,5 detik), dalam transaksi terkunci. Attempt yang sudah dikirim atau lewat waktu menolak simpanan baru.
5. **Kirim:** jawaban divalidasi per jenis soal, lalu dinilai.
   - Bila waktunya sudah habis, kiriman diabaikan dan yang dinilai adalah **draf terakhir**.
6. **Tutup otomatis:** attempt yang lewat waktu ditutup (`auto_closed`) saat halaman dibuka, saat simpan, atau saat kirim. Tidak ada cron.

**Penilaian:**

- Soal pilihan bernilai poin penuh bila jawaban **persis sama** dengan kunci, selain itu 0. Esai menunggu koreksi.
- Skor quiz adalah **jumlah poin mentah**, bukan skala 0–100.
- **Koreksi esai** dilakukan admin atau dosen per attempt, dengan poin 0 sampai poin soal. Dosen tidak bisa mengoreksi bila nilai kelas terkunci.
- **Mengubah atau menghapus soal** menilai ulang semua attempt. Poin esai yang sudah dikoreksi dipertahankan. Soal yang diubah menjadi esai dikosongkan poinnya.

### 8.4 Duplikasi

- Materi, tugas, dan quiz biasa bisa diduplikasi ke kelas lain. Berkas fisiknya ikut disalin.
- Dosen hanya bisa menduplikasi ke kelas yang ia ampu.
- Lembar soal ujian tidak bisa diduplikasi.

---

## 9. Presensi

### 9.1 Pertemuan

**Generate pertemuan** (`Pertemuan::generateUntuk`), oleh admin atau pengampu:

- Slot diambil dari jadwal mingguan kelas, mulai `tanggal_mulai` tahun akademik. Kelas tanpa jadwal ditolak.
- Pertemuan ke-⌊n/2⌋ menjadi **UTS** dan pertemuan ke-n menjadi **UAS** (bila n ≥ 4).
- Pertemuan yang sudah ada tidak diubah. Ada peringatan bila tanggal melewati akhir tahun akademik.
- Tidak ada pembatalan maupun susun ulang: jumlah pertemuan selalu sama dengan `jumlah_pertemuan` kelas.

**Jadwal ulang per pertemuan** (`PertemuanController::update`, admin atau pengampu):

- Tanggal, jam, ruang, dan dosen (khusus admin) hanya bisa diubah selama pertemuan masih `dijadwalkan`. Hanya pertemuan itu yang berubah.
- **Alasan wajib** bila salah satunya berubah. Perubahan dicek bentrok ruang, dosen, dan jadwal mingguan kelas lain.
- Setiap perubahan dicatat di `riwayat_jadwal_pertemuan` (dari → ke, alasan, pengubah, waktu; `Pertemuan::jadwalUlang`). Riwayat tampil di detail pertemuan; daftar pertemuan menandai "Dijadwal ulang: <alasan>".
- Pertemuan UTS/UAS yang sudah punya jadwal ujian hanya bisa dipindah dari menu Jadwal Ujian (`Ujian::sinkronkanPertemuan`, tidak dicatat di riwayat).
- Jenis dan catatan boleh diubah tanpa alasan dan tidak masuk riwayat.
- Pengajuan izin tetap menempel ke pertemuan yang dipindah. Mahasiswa melihat "Dipindah dari … : alasan" di halaman Presensi, dan Beranda menampilkan **Perubahan jadwal kuliah** untuk pertemuan mendatang yang dijadwal ulang dalam 14 hari terakhir (`App\PengingatJadwalPertemuan`), ditandai penting bila ia sudah mengajukan izin.

**Status pertemuan:**

| Status | Arti |
|---|---|
| `dijadwalkan` | Awal. Bisa dijadwal ulang dengan alasan (lihat di atas). |
| `berlangsung` | Dibuka dosen atau admin. Semua peserta KRS otomatis dibuatkan baris presensi `alpa`. |
| `selesai` | Ditutup dengan jurnal (`topik` wajib), atau ditutup otomatis. |

- **Terlewat** bukan status tersimpan. Artinya pertemuan masih `dijadwalkan` padahal jam akhirnya sudah lewat.
- **Siapa boleh membuka pertemuan:**
  - dosen hanya antara jam mulai dan jam akhir;
  - admin kapan saja setelah jam mulai (susulan).
- **Tutup otomatis:** pertemuan `berlangsung` yang sudah lewat 60 menit dari jam akhir diubah ke `selesai` saat halaman presensi dibuka. Tidak ada cron.
- **Dosen pengganti:** admin bisa mengganti dosen per pertemuan. Dosen pengganti hanya boleh mengelola pertemuan itu.
- Dosen tidak bisa mengubah pertemuan di tahun akademik nonaktif (`tahunAkademikTerkunci`).

### 9.2 Pencatatan kehadiran

**Status presensi mahasiswa:**

| Status | Dihitung hadir? |
|---|---|
| `hadir` | Ya |
| `terlambat` | Ya |
| `izin` | Tidak |
| `sakit` | Tidak |
| `alpa` | Tidak |

**Tiga cara pencatatan:**

1. **Manual oleh dosen/admin** saat pertemuan `berlangsung` atau `selesai` (metode `manual`).
2. **Mandiri lewat QR atau PIN** (metode `qr` atau `pin`):
   1. Dosen membuka presensi mandiri. Durasi bawaan 15 menit, bisa diperpanjang, dan tidak melewati jam akhir.
   2. Layar dosen menampilkan kode yang berganti setiap **30 detik**: HMAC dari id pertemuan dan periode, sebagai PIN 6 digit dan token QR. Kode dua periode sebelumnya masih diterima.
   3. Mahasiswa memindai QR atau mengetik PIN. Halaman QR hanya menampilkan konfirmasi; pencatatan terjadi saat mahasiswa menekan tombol (`throttle:10,1`).
   4. Sistem mencatat `hadir` bila masuk paling lambat jam mulai + toleransi, selain itu `terlambat`. Bila dosen masuk terlambat, acuan jamnya adalah jam masuk dosen.
   5. Status hadir yang sudah ada tidak ditimpa.
   6. Tanda perangkat (cookie) dicatat. Dosen melihat penanda bila beberapa mahasiswa memakai perangkat yang sama.
3. **Otomatis dari ujian online** (metode `ujian`), lihat [10.3](#103-mahasiswa-mengerjakan-ujian).

**Rekap kehadiran** hanya menghitung pertemuan `kuliah` yang `selesai`, dan hanya untuk mahasiswa yang masih ber-KRS.

### 9.3 Izin dan sakit

1. Mahasiswa mengajukan `izin` atau `sakit` per pertemuan, dengan alasan dan lampiran opsional (pdf/jpg/png, maks 3 × 5 MB).
   - Batas waktunya akhir hari tanggal pertemuan + `batas_pengajuan_izin_hari`.
   - Mahasiswa belum tercatat hadir.
2. Status pengajuan: `menunggu` → `disetujui` atau `ditolak`. Menolak wajib disertai catatan.
3. Pengajuan yang ditolak boleh diajukan ulang, dan statusnya kembali `menunggu`.
4. Yang memproses adalah **dosen pengampu atau admin**. Dosen pengganti dan kaprodi tidak bisa.
5. Bila disetujui, presensi pertemuan itu di-set `izin` atau `sakit` (metode `pengajuan`). Status hadir tidak diturunkan.

### 9.4 Syarat kehadiran ujian dan dispensasi

- Syarat berlaku bila sakelar `syarat_ujian_aktif` menyala. Batasnya `min_kehadiran_ujian` persen (bawaan 75).
- **Dasar hitung** (`App\SyaratUjian`):
  - UTS memakai pertemuan kuliah selesai **sebelum** pertemuan UTS;
  - UAS memakai semua pertemuan kuliah yang sudah selesai.
- Mahasiswa **memenuhi** syarat bila punya dispensasi, bila belum ada pertemuan yang dihitung, atau bila persentasenya ≥ batas.
- **Dispensasi** per mahasiswa, per jenis ujian (`uts`/`uas`), dengan alasan. Hanya bisa diberikan **admin atau kaprodi**.
- **Dampaknya:**
  - ujian **online** memblokir mahasiswa yang tidak memenuhi syarat (`Ujian::bolehIkut`);
  - ujian **tatap muka** hanya diberi penanda di daftar hadir dan PDF peserta ujian, tanpa memblokir.

### 9.5 Laporan dan peringatan

- **Ekspor presensi kelas** tersedia dalam PDF dan CSV.
- **Laporan kehadiran dosen** (khusus admin) berisi pertemuan terlaksana, dijadwal ulang, terlewat, oleh pengganti, dosen masuk terlambat, tanpa jurnal, dan rata-rata hadir.
- **Beranda mahasiswa:** mata kuliah dengan kehadiran di bawah batas, atau sisa jatah tidak hadir ≤ 1.
- **Beranda dosen:** pertemuan hari ini, jumlah izin yang menunggu, dan jumlah mahasiswa di bawah batas kehadiran.

---

## 10. Ujian UTS dan UAS

### 10.1 Penjadwalan oleh admin

**Satu jadwal ujian per kelas per jenis** (`uts`/`uas`). Kombinasi kelas dan jenis unik. Jadwal remidi dan ujian susulan memakai tabel yang sama dengan jenis `remidi`, `uts_susulan`, dan `uas_susulan` ([bagian 12](#12-remidi) dan [13](#13-ujian-susulan)).

**Field:**

- `mode`: `tatap_muka`, `online_berkas`, atau `online_soal`;
- `tanggal`, `jam_mulai`, `jam_akhir`;
- `ruang_id` (wajib untuk tatap muka, kosong untuk online);
- `pengawas`, `petunjuk`;
- `status`: `draf` atau `terbit`.

**Dua cara membuat:**

- **Satu per satu:**
  - tanggal harus berada dalam rentang tahun akademik;
  - dicek bentrok ruang dengan pertemuan dan ujian lain;
  - dicek juga bentrok mahasiswa (mahasiswa kelas ini punya ujian lain di jam yang sama). Bentrok mahasiswa bisa dilewati dengan centang "Tetap simpan".
- **Massal dari pertemuan:** jadwal dibuat sebagai `draf` bermode tatap muka untuk semua kelas yang belum punya, dengan tanggal, jam, dan ruang dari pertemuan UTS/UAS kelas itu.

**Setelah disimpan:**

- Pertemuan UTS/UAS kelas **mengikuti** tanggal, jam, dan ruang ujian, selama pertemuan itu masih `dijadwalkan` (`Ujian::sinkronkanPertemuan`).
- **Terbitkan:** jadwal terpilih, atau semua draf satu tahun akademik, diubah menjadi `terbit`. Ujian tatap muka tanpa ruang dilewati. Hanya jadwal `terbit` yang terlihat mahasiswa.
- Mode tidak bisa diganti, dan jadwal tidak bisa dihapus, setelah ada mahasiswa yang mengerjakan.

### 10.2 Persiapan oleh dosen

- Dosen melihat jadwal ujian kelas yang ia ampu, termasuk yang masih draf.
- **Mode `online_berkas`:** dosen mengunggah berkas soal (maks 5 × 20 MB) **sebelum** ujian dimulai.
- **Mode `online_soal`:** dosen membuat **lembar soal** memakai mesin quiz.
  - Satu quiz per ujian. Tenggatnya dipaksa sama dengan jam selesai ujian.
  - Soal tidak bisa diubah setelah ujian dimulai.

### 10.3 Mahasiswa mengerjakan ujian

- Mahasiswa hanya melihat ujian `terbit` dari kelas di KRS-nya, beserta status syarat kehadirannya.
- Mahasiswa yang **pengajuan susulannya disetujui** untuk ujian itu tidak bisa mengerjakan ujian utama online, dan di daftar jadwal ujian utamanya berlabel "Ikut jadwal susulan" ([13.5](#135-mengikuti-ujian-utama-padahal-mengajukan-susulan)).
- Semua penolakan ikut ujian memakai satu sumber pesan (`Ujian::alasanTidakBolehIkut`): bukan peserta kelas, bukan peserta remidi/susulan yang lunas, terdaftar susulan, atau belum memenuhi syarat kehadiran.
- **Halaman detail ujian:**
  - hitung mundur memakai jam server;
  - nama berkas soal baru dikirim setelah ujian dimulai, dan hanya bagi yang boleh ikut.
- **Mode `online_berkas`:**
  - kumpulkan 1–5 berkas jawaban **hanya selama jam ujian**. Unggahan yang selesai lewat jam selesai **ditolak**;
  - boleh mengganti berkas selama ujian berlangsung.
- **Mode `online_soal`:** mulai hanya bila ujian `terbit`, sedang berlangsung, dan mahasiswa boleh ikut. Soal dan opsi diacak per mahasiswa.
- **Kehadiran otomatis:** mengumpulkan berkas atau memulai lembar soal otomatis mencatat **hadir** (metode `ujian`) di pertemuan UTS/UAS.
  - Ujian tatap muka tidak mencatat kehadiran otomatis.
- **Kartu ujian (PDF)** per jenis berisi jadwal ujian terbit, dan kolom syarat kehadiran bila syarat aktif.

### 10.4 Penilaian ujian

- **`tatap_muka` dan `online_berkas`:**
  - dosen mengisi nilai 0–100 dan catatan per mahasiswa, **setelah ujian selesai**;
  - mode berkas hanya bisa dinilai bagi yang mengumpulkan.
- **`online_soal`:** dinilai lewat koreksi quiz. Skor dikonversi ke 0–100 (`skor / total poin × 100`, dibulatkan 2 desimal).
- **Rilis nilai:** nilai baru bisa dirilis setelah ujian selesai. Mahasiswa melihat nilai ujian hanya bila sudah dirilis.
- Nilai ujian adalah **angka per ujian**. Nilai ini **tidak** otomatis masuk ke huruf akhir ([bagian 11](#11-nilai-akhir-kunci-nilai-dan-hasil-studi)).
- **Daftar hadir PDF:** UTS/UAS dari menu Presensi (tab Peserta Ujian); remidi dari halaman ujian remidi.

---

## 11. Nilai akhir, kunci nilai, dan hasil studi

### 11.1 Nilai akhir

- Nilai akhir berupa **huruf** di `krs.nilai`, diisi **manual** oleh dosen pengampu atau admin di tabel **Nilai Mahasiswa** (`KelasKuliahController::updateGrade`).
- Huruf harus ada di skala nilai. Nilai boleh dikosongkan, kecuali pada jalur remidi.
- **Tidak ada perhitungan otomatis** huruf akhir dari tugas, quiz, UTS, UAS, atau presensi. Semua nilai komponen berdiri sendiri.
- **Kecuali mata kuliah TA/Skripsi:** huruf pendadaran yang lulus ditulis otomatis ke KRS mata kuliah TA ([14.4](#144-penilaian-dan-hasil-pendadaran)).

### 11.2 Kunci nilai

**Untuk dosen, nilai kelas terkunci bila salah satu berlaku** (`KontenKelas::pesanNilaiTerkunci`):

1. tahun akademik kelas **nonaktif**;
2. nilai kelas **sudah difinalisasi** (`nilai_final_at` terisi);
3. **batas input nilai sudah lewat**. Batasnya `nilai_dibuka_sampai` (batas pengganti dari admin) atau `batas_input_nilai` tahun akademik. Hari batas itu sendiri masih boleh.

**Yang terkunci:**

- huruf akhir;
- nilai tugas;
- koreksi esai quiz;
- mengubah atau menghapus soal quiz (karena menilai ulang);
- nilai ujian.

Yang **tidak** terkunci: presensi (hanya terkunci bila tahun akademik nonaktif), serta membuat atau mengubah materi, tugas, dan quiz.

**Admin tidak pernah terkunci.**

**Finalisasi nilai** (dosen atau admin):

- Ditolak bila ada **UAS terbit yang belum selesai**.
- Ditolak selama masih ada **UAS susulan yang berjalan** (`UjianSusulan::uasSusulanTertunda`): pemohon UAS susulan yang disetujui dan tidak ikut UAS utama, yang tagihannya belum terbit, belum lunas tetapi belum gugur, atau sudah lunas tetapi UAS susulannya belum dijadwalkan atau belum selesai. Pemohon yang tagihannya gugur tidak menahan finalisasi. Halaman kelas menampilkan jumlahnya.
- Mencatat waktu dan siapa yang memfinalisasi.
- Antarmuka memperingatkan jumlah mahasiswa yang belum punya huruf akhir, tetapi tidak memblokir.

**Buka kunci nilai (admin):**

- Status final dibatalkan.
- Bila batas input nilai tahun akademik sudah lewat, admin wajib memberi **batas baru khusus kelas itu**.
- Huruf akhir peserta remidi tetap tidak bisa diubah dosen di luar jalur remidi ([12.6](#126-huruf-akhir-peserta-remidi)).

### 11.3 Hasil studi

- **KHS:** semua KRS di satu tahun akademik.
  - IP = Σ(SKS × bobot) / ΣSKS, hanya atas mata kuliah yang sudah bernilai.
  - Tersedia unduhan PDF bertanda tangan dosen wali.
- **Transkrip:** nilai **terbaik** per mata kuliah, jumlah pengambilan, IPK, dan total SKS lulus (huruf bertanda `lulus`). Hanya tampil di layar; tidak ada PDF transkrip.
- **Dampak ke semester berikutnya:** huruf akhir menentukan IPS, lalu batas SKS KRS, kuota SKS tagihan, dan boleh atau tidaknya mata kuliah diulang.

---

## 12. Remidi

Remidi dilakukan **per mata kuliah (per kelas), paling banyak satu kali**. Alurnya melibatkan dosen, admin, dan mahasiswa. Tiga tanggal di tahun akademik mengatur urutannya: `batas_input_nilai` < `batas_bayar_remidi` < `batas_input_nilai_remidi`.

### 12.1 Daftar peserta

1. Bagian **Daftar Remidi** muncul di halaman kelas setelah nilai kelas **final**.
2. **Usulan otomatis** (`UsulanRemidi::susun`) mengambil mahasiswa yang memenuhi dua syarat:
   - huruf akhirnya bertanda **tidak lulus** atau **boleh diulang** (dengan skala bawaan: D dan E);
   - ia **ikut UAS**, termasuk bila ia mengerjakan **UAS susulan**. Arti "ikut" per mode ujian:
     - `online_soal`: sudah memulai lembar soal;
     - `online_berkas`: mengumpulkan berkas;
     - `tatap_muka`: nilai ujiannya terisi.
   - Bila kelas tidak punya UAS terbit, semua mahasiswa dianggap ikut.
   - Mahasiswa tanpa huruf akhir tidak diusulkan.
3. **Mengunci daftar:** dosen atau admin mencentang atau mencoret mahasiswa, lalu menekan **Kunci Daftar**.
   - Boleh menambah mahasiswa mana pun di kelas itu.
   - Boleh dikunci tanpa peserta, artinya tidak ada remidi.
   - Huruf akhir saat itu disimpan sebagai `nilai_awal`.
4. **Admin** bisa membuka kembali kunci daftar, **kecuali** tagihan remidi kelas itu sudah terbit.
5. **Kunci massal (admin):** di menu Tagihan Remidi tampil daftar kelas yang nilainya final tetapi daftar remidinya belum dikunci. Admin bisa menguncinya sekaligus dengan usulan otomatis.

### 12.2 Tagihan remidi

**Syarat terbit** (menu **Keuangan → Tagihan Remidi → Terbitkan Tagihan**):

- `batas_bayar_remidi` sudah diisi dan belum lewat;
- ada jenis biaya aktif berkategori `remidi`.

**Proses terbit:**

- Tagihan dibuat untuk setiap peserta dari kelas yang daftarnya dikunci dan belum punya tagihan. Tagihan yang sudah ada tidak diubah.
- **Nominal:** `tetap` = per mata kuliah; `per_sks` = tarif × SKS mata kuliah. Tarif dipilih yang paling khusus.
- Rincian dibekukan saat terbit.
- **Total 0 langsung `lunas`.**

**Status tagihan remidi:**

| Status | Terjadi saat |
|---|---|
| `belum_bayar` | Tagihan terbit. |
| `menunggu_verifikasi` | Mahasiswa mengunggah bukti bayar (pdf/jpg/png, maks 5 MB) sebelum batas bayar. Mengunggah ulang mengganti bukti lama. |
| `lunas` | Admin menandai lunas, dengan atau tanpa bukti (mis. bayar di loket). |
| `ditolak` | Admin menolak bukti dengan alasan. Mahasiswa bisa mengunggah ulang sebelum batas. |
| *gugur* | **Status tampilan saja**, tidak disimpan. Berlaku untuk `belum_bayar` atau `ditolak` yang batas bayarnya sudah lewat. Bukti yang terkirim sebelum batas (`menunggu_verifikasi`) tetap bisa diverifikasi sesudahnya. |

- Bukti bayar hanya bisa dibuka pemiliknya dan pemegang `admin.tagihan`.
- Halaman admin menempatkan bukti yang menunggu verifikasi di urutan teratas, dan menampilkan tanggal ujian remidi di setiap baris yang belum lunas.

### 12.3 Jadwal ujian remidi

- Dibuat **admin** di menu Jadwal Ujian dengan jenis **Remidi**. Satu per kelas.
- **Syarat kelas siap:**
  - kedua batas remidi di tahun akademik terisi;
  - daftar remidi dikunci;
  - ada **peserta yang lunas**.
- Tanggal harus **sesudah** batas bayar dan **paling lambat** batas input nilai remidi.
- Mode sama dengan UTS/UAS. Remidi **tidak** membuat pertemuan, tidak mencatat presensi, dan tidak memakai syarat kehadiran.
- **Jadwal remidi massal:** satu tanggal, jam, dan mode untuk semua kelas yang siap, dibuat sebagai draf. Ujian tatap muka perlu diisi ruangnya per kelas sebelum diterbitkan.
- Form jadwal memperingatkan bila masih ada bukti bayar kelas itu yang menunggu verifikasi.

### 12.4 Akses peserta

Hanya **peserta yang tagihannya lunas** (`RemidiPeserta::lunas`) yang bisa:

- melihat jadwal remidi dan halaman detailnya;
- mengunduh kartu remidi (PDF);
- mengunduh soal;
- mengumpulkan jawaban dan memulai lembar soal;
- tampil di daftar peserta dosen dan daftar hadir PDF.

Mahasiswa lain mendapat 404, sehingga tidak tahu remidi itu ada. Peserta yang baru lunas setelah jadwal dibuat otomatis ikut.

### 12.5 Nilai remidi

- Dosen menyiapkan soal dan memberi **nilai remidi (0–100)** lewat halaman ujian, dengan mekanisme yang sama seperti UTS/UAS.
- Semua itu **tetap bisa dilakukan walau nilai kelas sudah final**, selama **jendela remidi** masih terbuka:
  - remidi belum difinalisasi;
  - batas input nilai remidi belum lewat;
  - tahun akademik masih aktif.

### 12.6 Huruf akhir peserta remidi

- **Setelah ujian remidi selesai**, dosen bisa mengubah huruf akhir **peserta yang lunas** lewat tombol **Ubah Nilai Remidi**, walau nilai kelas sudah final.
  - Huruf **wajib** diisi.
  - Pilihan huruf dibatasi `huruf_maks_remidi` (kosong berarti bebas).
- **Peserta yang belum lunas, gugur, atau bukan peserta** tetap terkunci.
- **Walau admin membuka kunci nilai kelas**, huruf peserta remidi hanya bisa diubah dosen setelah ujian remidinya selesai.
- **Admin tidak terkena** batas huruf maupun kunci ini.

### 12.7 Menutup remidi

- Remidi terkunci lagi saat dosen atau admin menekan **Finalisasi Remidi** (hanya setelah ujian remidi selesai), atau saat batas input nilai remidi lewat.
- Admin bisa **Buka Finalisasi Remidi**. Batas input nilai remidi yang sudah lewat tetap mengunci dosen.
- Setelah dikunci, Daftar Remidi menampilkan per peserta:
  - jadwal ujian;
  - nilai awal;
  - status tagihan;
  - nilai remidi;
  - huruf akhir terkini.

### 12.8 Pengingat di beranda

- **Mahasiswa:**
  - tagihan remidi yang belum lunas (tidak termasuk yang gugur), beserta batas bayarnya;
  - jadwal ujian remidi mendatang, bila sudah lunas.
- **Dosen:**
  - kelas yang nilainya final tetapi daftar remidinya belum dikunci;
  - kelas yang ujian remidinya selesai tetapi remidinya belum difinalisasi.

---

## 13. Ujian susulan

Ujian susulan untuk mahasiswa yang tidak bisa mengikuti **UTS atau UAS**. Alurnya: mahasiswa mengajukan → admin menyetujui → admin menerbitkan tagihan → mahasiswa membayar → admin memverifikasi → jadwal susulan muncul. Aturan inti ada di `App\UjianSusulan`.

### 13.1 Siapa yang dianggap sudah ikut ujian utama

`UjianSusulan::pesertaUjianUtama` menentukan siapa yang **ikut** UTS/UAS:

- `online_soal`: sudah memulai lembar soal;
- `online_berkas`: mengumpulkan berkas jawaban;
- `tatap_muka`: tercatat `hadir`/`terlambat` di pertemuan UTS/UAS, **atau** nilai ujiannya sudah diisi.

Definisi ini dipakai di semua langkah susulan.

### 13.2 Pengajuan

1. Di halaman detail UTS/UAS, mahasiswa menekan **Ajukan ujian susulan**, mengisi alasan (maks 1000 karakter), dan melampirkan **bukti wajib** (1–3 berkas PDF/JPG/PNG, maks 5 MB). Rute dibatasi 10 permintaan per menit.
2. **Syarat mengajukan** (`UjianSusulan::alasanTidakBolehAjukan`):
   - ujiannya UTS/UAS berstatus `terbit`;
   - mahasiswa peserta KRS kelas itu;
   - sekarang belum lewat akhir hari **tanggal ujian + `batas_pengajuan_susulan_hari`** (bawaan 3). Pengajuan boleh dikirim **sebelum** ujian, sejak jadwal terbit;
   - belum punya pengajuan aktif (`menunggu`/`disetujui`) untuk ujian itu;
   - belum ikut ujian utama.
3. Status awal `menunggu`. Selama masih `menunggu`, mahasiswa bisa **membatalkan** (status `dibatalkan`, lampiran dihapus). Setelah dibatalkan atau ditolak, ia boleh mengajukan lagi selama masih dalam batas waktu.
4. Admin memproses di **Perkuliahan → Ujian Susulan** (izin `admin.ujian`):
   - **Setujui**: sistem memeriksa ulang apakah mahasiswa ternyata sudah ikut ujian utama. Kalau ya, persetujuan ditolak.
   - **Tolak**: alasan wajib dan ditampilkan ke mahasiswa.
   - Pengajuan yang menunggu tampil paling atas, lengkap dengan lampiran.
5. Lampiran hanya bisa dibuka pengaju dan pemegang `admin.ujian`.

### 13.3 Tagihan susulan

**Syarat terbit** (**Keuangan → Tagihan Susulan → Terbitkan Tagihan**, izin `admin.tagihan`):

- ada jenis biaya aktif berkategori `susulan`.

**Proses terbit:**

- Tagihan dibuat untuk setiap pengajuan `disetujui` di tahun akademik itu yang belum punya tagihan. Pengajuan yang mahasiswanya ternyata ikut ujian utama **dilewati**.
- **Nominal:** `tetap` = per ujian; `per_sks` = tarif × SKS mata kuliah. Tarif dipilih yang paling khusus, rincian dibekukan.
- **Batas bayar per tagihan** = tanggal terbit + `batas_bayar_susulan_hari` (bawaan 3).
- **Total 0 langsung `lunas`.**

**Pembayaran** memakai mekanisme yang sama dengan tagihan remidi (trait `TagihanBerbukti`, concern `VerifikasiBuktiBayar`): mahasiswa mengunggah bukti di **Biaya Kuliah**, admin menandai lunas atau menolak dengan alasan, dan tagihan yang belum lunas saat batas lewat menjadi **gugur**.

**Bila mahasiswa ternyata ikut ujian utama:**

- tagihan yang belum lunas tampil **`dibatalkan`**: bukti tidak bisa diunggah dan admin tidak bisa menandainya lunas;
- tagihan yang sudah lunas tetap `lunas`, tetapi diberi tanda **"Sudah ikut ujian utama – pengembalian dana di luar sistem"**.

### 13.4 Jadwal ujian susulan

- Dibuat **admin** di menu Jadwal Ujian dengan jenis **UTS Susulan** / **UAS Susulan** (`uts_susulan`/`uas_susulan`). Satu per kelas per jenis.
- **Syarat kelas siap:** ujian utamanya terbit, dan ada pemohon yang **disetujui, lunas, dan tidak ikut ujian utama**.
- **Tanggal:** tidak boleh sebelum tanggal ujian utama, dan paling lambat **batas input nilai** kelas (bila ada), supaya dosen masih sempat menilai.
- **Jadwal susulan massal:** satu tanggal, jam, dan mode untuk semua kelas yang siap, dibuat sebagai draf. Kelas yang tanggalnya tidak cocok dilewati dan dilaporkan jumlahnya.
- Halaman Jadwal Ujian menampilkan pengingat per jenis: "n kelas punya pemohon susulan … yang sudah lunas tetapi belum dijadwalkan".
- Susulan **tidak** membuat pertemuan dan **tidak** mengubah presensi.
- **Peserta susulan** (`UjianSusulan::pesertaSusulan`) = pengajuan disetujui + tagihan lunas + tidak ikut ujian utama. Hanya mereka yang:
  - melihat jadwal, detail, dan kartu PDF susulan;
  - mengunduh soal, mengumpulkan jawaban, dan memulai lembar soal;
  - tampil di daftar peserta dosen dan daftar hadir PDF.
  Mahasiswa lain mendapat 404.
- **Syarat kehadiran** jenis utamanya (UTS/UAS) tetap berlaku, kecuali ada dispensasi.
- Dosen menyiapkan soal dan memberi nilai (0–100) dengan mekanisme yang sama seperti UTS/UAS, dan tunduk pada kunci nilai kelas.

### 13.5 Mengikuti ujian utama padahal mengajukan susulan

| Kondisi | Akibat |
|---|---|
| Pengajuan masih `menunggu`, mahasiswa ikut ujian utama | Pengajuan tampil **dibatalkan** dan tidak bisa disetujui |
| Pengajuan `disetujui`, ujian utama **online** | Mahasiswa **tidak bisa** mengerjakan ujian utama ("Anda terdaftar ujian susulan…") |
| Pengajuan `disetujui`, ujian utama **tatap muka**, dosen mencatat hadir atau mengisi nilai utama | Hak susulan **gugur**: jadwal susulan tidak tampil; tagihan belum lunas **dibatalkan**; tagihan lunas ditandai untuk pengembalian dana di luar sistem. Nilai yang dipakai adalah nilai ujian utama. |

Status `dibatalkan`/`gugur` ini **dihitung saat ditampilkan**, bukan disimpan.

### 13.6 Keterkaitan dengan fitur lain

- **Finalisasi nilai** tertahan selama UAS susulan masih berjalan ([11.2](#112-kunci-nilai)).
- **Usulan remidi** menganggap mengerjakan UAS susulan sebagai ikut UAS ([12.1](#121-daftar-peserta)).
- **Beranda** mahasiswa dan dosen menampilkan pengingat susulan ([17.3](#173-beranda)).

---

## 14. Tugas akhir, pendadaran, dan wisuda

Tiga pengajuan berurutan di menu **Tugas Akhir & Wisuda** (mahasiswa, izin `mahasiswa.tugas-akhir`): pengajuan TA/Skripsi → pendaftaran pendadaran → pendaftaran wisuda. Admin memproses di **Administrasi → TA & Wisuda** (izin `admin.pengajuan-akademik`), dosen di **Bimbingan TA** (izin `dosen.bimbingan`). Syarat tiap tahap ada di `App\SyaratTugasAkhir`.

### 14.1 Aturan bersama pengajuan

- Ketiga jenis disimpan di tabel `pengajuan_akademik` (`jenis`: `tugas_akhir`, `pendadaran`, `wisuda`). Isian form per jenis ada di `isian`, berkas per kolom di `lampiran`.
- Di atas form tampil **daftar syarat** (✓/✗ dengan keterangan, mis. "Nilai E: Statistika."). Selama ada syarat yang belum terpenuhi, form **terkunci** dengan pesan "Anda belum memenuhi syarat…". Bukti bayar bukan syarat; diunggah di form.
- **Status:** `menunggu_pembimbing` (khusus pendadaran) → `menunggu` (admin) → `disetujui` / `perlu_perbaikan` / `ditolak`.
- Selama `menunggu`/`menunggu_pembimbing`, mahasiswa **tidak bisa membatalkan** dan **tidak bisa mengisi form baru** untuk jenis yang sama.
- `perlu_perbaikan` membuka **form yang sama** berisi isian lama; berkas boleh tidak diganti. `ditolak` membuka **form baru**.
- Perbaikan dan penolakan wajib bercatatan yang ditampilkan ke mahasiswa.
- Setiap kiriman dan keputusan dicatat di `riwayat_pengajuan_akademik` (peristiwa `dikirim`, `disetujui_pembimbing`, `perlu_perbaikan`, `ditolak`, `disetujui`) dan tampil sebagai riwayat di halaman mahasiswa.
- Persetujuan selalu **memeriksa ulang syarat**, karena KRS atau nilai bisa berubah sejak form dikirim.

### 14.2 Pengajuan TA/Skripsi

- **Syarat:** mengambil mata kuliah bertanda **TA/Skripsi** (`mata_kuliahs.tugas_akhir`, diatur di master Mata Kuliah) di KRS tahun akademik aktif.
- **Form:** judul, bidang, ringkasan proposal, usulan pembimbing 1 (wajib) dan 2 (opsional, berbeda), proposal PDF (maks 10 MB).
- **Admin menyetujui** sambil mengesahkan judul dan menetapkan pembimbing (maks 2, boleh berbeda dari usulan). Terbentuk baris `tugas_akhir` berstatus `berjalan`.
- Pembimbing melihat mahasiswanya di menu **Bimbingan TA** beserta proposal.

### 14.3 Pendaftaran pendadaran

- **Syarat:**
  - TA `berjalan`;
  - mengambil mata kuliah TA/Skripsi di tahun aktif;
  - SKS bernilai di transkrip (nilai terbaik per mata kuliah, **tanpa** mata kuliah TA) ≥ `min_sks_pendadaran` (Pengaturan Akademik, bawaan 138);
  - tidak ada nilai **E** di transkrip (nilai terbaik);
  - tidak ada mata kuliah (selain TA) yang **belum dinilai**, termasuk yang sedang diambil semester ini.
- **Form:** judul final (terisi dari judul TA), naskah PDF (maks 20 MB), lembar persetujuan pembimbing dan **bukti bayar pendadaran** (PDF/JPG/PNG, maks 5 MB).
- **Persetujuan pembimbing:** pendaftaran masuk `menunggu_pembimbing`. **Cukup satu** pembimbing yang menyetujui; pembimbing juga bisa meminta perbaikan atau menolak.
- **Kiriman ulang setelah perbaikan:** bila perbaikan diminta **admin**, kiriman ulang langsung ke admin (persetujuan pembimbing tetap berlaku); bila diminta **pembimbing**, kembali ke pembimbing.
- **Admin menyetujui sekaligus menjadwalkan:** tanggal (≥ hari ini), jam, ruang, dan **3 penguji** berbeda (penguji 1 = **ketua**; pembimbing boleh menjadi penguji). Judul final menjadi judul TA.
- **Cek bentrok** (`App\JadwalPendadaran`):
  - **ditolak:** ruang dipakai pendadaran lain, pertemuan kuliah, jadwal mingguan kelas yang belum punya pertemuan (di tahun akademik yang mencakup tanggal itu), atau ujian tatap muka pada jam beririsan;
  - **ditolak:** seorang penguji sudah menguji pendadaran lain pada jam beririsan;
  - **peringatan saja:** penguji sedang mengajar pada jam itu. Admin bisa menekan **Tetap Simpan**.
- Terbentuk baris `pendadaran` (`dijadwalkan`) dengan **nomor surat** urut per tahun (`001/PDD/IX/2026`).
- **Surat Tugas & Undangan Pendadaran** (PDF) memuat identitas, judul, pembimbing, jadwal, dan ketiga penguji dengan kolom tanda tangan. Bisa dibuka mahasiswa, admin, pembimbing, dan penguji.
- Jadwal tampil di halaman mahasiswa, pembimbing, dan **masing-masing penguji** beserta perannya.

### 14.4 Penilaian dan hasil pendadaran

- Setiap penguji mengisi **nilai 0–100** (dan catatan opsional) **sejak jam mulai** sampai hasil ditetapkan (`nilai_pendadaran`).
- Setelah ketiga nilai masuk, **ketua penguji** melihat rincian nilai, rata-rata, huruf, dan **usulan** (lulus bila hurufnya lulus), lalu menetapkan hasil:

| Hasil | Akibat |
|---|---|
| `lulus` | Pendadaran `selesai`, TA `selesai`, huruf menjadi nilai KRS mata kuliah TA (KRS yang belum dinilai lebih dulu) |
| `lulus_revisi` | Catatan revisi wajib. Status `revisi`: mahasiswa mengunggah naskah revisi (PDF), ketua **mengesahkan** (lalu sama seperti `lulus`) atau **mengembalikan** dengan catatan |
| `tidak_lulus` | Status `tidak_lulus`; TA tetap `berjalan` dan mahasiswa bisa mendaftar pendadaran ulang dari awal |

- **Konversi angka → huruf** memakai **angka minimal** per huruf di skala nilai (Pengaturan Akademik; bawaan A 80, B 70, C 60, D 50, E 0). Bila rata-ratanya jatuh di huruf tidak lulus, hasil **harus** `tidak_lulus`.

### 14.5 Pendaftaran wisuda dan SKL

- **Periode wisuda** (menu **Administrasi → Periode Wisuda**): nama, tanggal acara, tempat, batas daftar (≤ tanggal acara), kuota (kosong = tanpa batas). Periode **dibuka** sampai akhir hari batas daftar selama kuota tersisa. Periode berpendaftar tidak bisa dihapus; kuota tidak bisa diturunkan di bawah jumlah peserta.
- **Syarat daftar:** TA `selesai` (lulus pendadaran, revisi sudah disahkan), SKS minimal (sama dengan pendadaran), tidak ada nilai E, **semua** mata kuliah termasuk TA sudah dinilai, dan ada periode yang dibuka.
- **Form:** periode, **data ijazah** (nama, tempat dan tanggal lahir; terisi dari profil dan boleh dikoreksi), ukuran toga (S–XXL), pas foto (JPG/PNG, maks 2 MB), naskah final (PDF, maks 20 MB), bukti bebas pinjam dan **bukti bayar wisuda**.
- Admin melihat data ijazah yang **berbeda dari profil** ditandai. Persetujuan memeriksa ulang kuota periode; mahasiswa masuk **Daftar Mahasiswa Wisuda** (`wisuda`, satu per mahasiswa). Daftar bisa dicetak (PDF).
- **Generate SKL** (per mahasiswa atau massal) membekukan tanggal lulus (tanggal pendadaran), IPK dan total SKS dari transkrip, dan **predikat** (> 3,50 Dengan Pujian; > 3,00 Sangat Memuaskan; ≥ 2,76 Memuaskan; selain itu Cukup), memberi nomor urut per tahun (`001/SKL/IX/2026`), dan mengubah status mahasiswa menjadi **Lulus**. SKL (PDF) memakai data ijazah yang dikonfirmasi di form; bisa diunduh mahasiswa pemiliknya (status Lulus tetap bisa masuk) dan admin.

### 14.6 Biaya dan pengingat

- Biaya pendadaran dan wisuda **tidak ditagihkan**. Admin mengisinya sebagai jenis biaya kategori `pendadaran`/`wisuda` (nominal tetap, tarif per prodi/angkatan). Kartu **Biaya Pendadaran & Wisuda** selalu tampil di Biaya Kuliah, dan nominalnya ikut tampil di label bukti bayar form pendaftaran.
- **Beranda** menampilkan kartu "Tugas akhir & wisuda" ([17.3](#173-beranda)).

---

## 15. Cuti dan aktif kembali

Mahasiswa mengajukan di menu **Administrasi → Pengajuan Cuti** (izin `mahasiswa.pengajuan-cuti`); admin memproses di **Administrasi → Pengajuan Cuti** (izin `admin.pengajuan-cuti`, tab **Cuti** dan **Aktif Kembali**). Keduanya disimpan di `pengajuan_akademik` (`jenis` `cuti`/`aktif_kembali`) dengan aturan bersama yang sama seperti [14.1](#141-aturan-bersama-pengajuan): menunggu → disetujui/perlu perbaikan/ditolak, form terkunci selama menunggu, perbaikan memakai form yang sama, penolakan membuka form baru, catatan wajib, riwayat tercatat. Aturan ada di `App\PengajuanCuti`.

**Pengajuan cuti:**

- **Periode** diatur per semester di Tahun Akademik (`tanggal_cuti_awal`–`tanggal_cuti_akhir`). Mahasiswa memilih **semester yang ingin dicutikan** di antara tahun akademik yang periodenya sedang dibuka dan belum berakhir (bisa semester berjalan atau semester depan). Semester yang cutinya sudah disetujui tidak ditawarkan lagi.
- **Syarat:** status `Aktif`, jumlah cuti yang disetujui < `maks_cuti` (Pengaturan Akademik, bawaan 2), dan ada semester yang periodenya dibuka. Bila tidak terpenuhi, form terkunci dengan alasannya.
- **Form:** semester, alasan (wajib, maks 2000 karakter), **bukti bayar cuti** (wajib; PDF/JPG/PNG maks 5 MB), dokumen pendukung (opsional). Nominal biaya dari jenis biaya kategori `cuti` (informasi, tidak ditagihkan) tampil di form dan di Biaya Kuliah.
- **Admin menyetujui** setelah memeriksa ulang: status masih `Aktif`, belum mencapai batas cuti, semester tujuan belum berakhir. Akibatnya:
  - semester tujuan **sedang aktif** → status mahasiswa langsung `Cuti`;
  - semester tujuan **belum aktif** → status menjadi `Cuti` saat semester itu diaktifkan (hook `TahunAkademik::saved` → `PengajuanCuti::terapkan`), hanya bila statusnya masih `Aktif`.
- KRS dan tagihan yang sudah ada di semester itu **dibiarkan** (keputusan user); admin yang membereskan bila perlu.
- Mahasiswa `Cuti` tetap bisa masuk, tetapi tidak ditagih dan tidak bisa mengambil KRS ([2.1](#21-login), [3.3](#33-pengguna-manage-user)).

**Aktif kembali:**

- Hanya untuk mahasiswa berstatus `Cuti`, kapan saja (tanpa periode). Isian: keterangan (opsional).
- Admin menyetujui → status kembali `Aktif`. Status tidak kembali Aktif secara otomatis.

---

## 16. Pindah kelas

1. **Formulir pengajuan** hanya bisa dikirim bila admin membukanya di Pengaturan Akademik. Bila formulir tertutup, pengiriman ditolak 403; halaman riwayat tetap bisa dibuka.
2. **Mahasiswa mengajukan** (`Mahasiswa\PindahKelasController::store`):
   - memilih **kelas asal** dari KRS berstatus `Aktif` di tahun akademik aktif;
   - memilih **kelas tujuan** yang berbeda, dengan **mata kuliah dan tahun akademik yang sama**;
   - menulis alasan (maks 1000 karakter).
   - Tidak boleh ada pengajuan `pending` lain untuk kelas asal yang sama.
   - Kapasitas dan bentrok jadwal **tidak** dicek pada tahap ini.
3. **Status pengajuan:** `pending` → `disetujui` atau `ditolak`.
4. **Admin menyetujui** (`Admin\PindahKelasController::approve`):
   - ditolak bila KRS asal tidak ada, mahasiswa sudah terdaftar di kelas tujuan, atau tahun akademiknya berbeda;
   - **peringatan yang bisa dilewati** (admin menyetujui ulang dengan `force`):
     - KRS asal **sudah bernilai**;
     - jadwal kelas tujuan **bentrok** dengan kelas lain mahasiswa itu;
   - **kapasitas kelas tujuan sengaja tidak dicek**, karena admin boleh menyetujui ke kelas penuh.
5. **Dalam transaksi setelah disetujui:**
   - baris KRS dipindahkan ke kelas tujuan, **beserta nilainya**;
   - riwayat presensi dan pengajuan izin dipindah ke pertemuan dengan nomor yang sama di kelas tujuan, selama pertemuan tujuan tidak dibatalkan dan belum punya baris untuk mahasiswa itu. Pesan sukses menyebut jumlah yang dipindah dan yang tertinggal;
   - dispensasi ujian ikut dipindah.
6. **Admin menolak:** catatan wajib. KRS tidak berubah.
7. **Tidak ada notifikasi.** Mahasiswa melihat hasilnya di riwayat pengajuan.

---

## 17. Fitur pendukung

### 17.1 Info kuliah

- Admin membuat, mengubah, dan menghapus pengumuman: teks dan **satu lampiran wajib**.
- Semua mahasiswa melihat semua pengumuman. Tidak ada target prodi atau kelas.
- Dosen tidak punya menu info kuliah.

### 17.2 Akses berkas privat

Semua berkas unggahan (kecuali logo institusi) disimpan di disk privat dan diunduh lewat `BerkasController`.

| Berkas | Boleh diunduh oleh |
|---|---|
| Materi dan tugas | admin kelas, dosen pengampu, mahasiswa ber-KRS di kelas itu |
| Jawaban tugas | pemilik, dosen pengampu, admin |
| Lampiran izin | pengaju, dosen pengampu, admin presensi |
| Soal ujian | admin ujian dan dosen pengampu kapan saja; mahasiswa hanya bila ujian terbit, sudah dimulai, dan boleh ikut |
| Jawaban ujian | pemilik, dosen pengampu, admin ujian |
| Bukti bayar remidi | pemilik, pemegang `admin.tagihan` |
| Berkas pengajuan TA/pendadaran/wisuda | pemilik, pemegang `admin.pengajuan-akademik`, pembimbing TA-nya, penguji pendadaran dari pengajuan itu |
| Surat pendadaran dan naskah revisi | mahasiswa pemilik, pemegang `admin.pengajuan-akademik`, pembimbing, penguji |
| SKL | mahasiswa pemilik, pemegang `admin.pengajuan-akademik` |
| Lampiran info kuliah | pemegang `admin.info-kuliah` atau `mahasiswa.info-kuliah` |

- Jenis konten ditentukan dari ekstensi. Pdf dan gambar dibuka *inline*, selain itu diunduh.
- Setiap respons berkas diberi header `nosniff` dan CSP `sandbox`.

### 17.3 Beranda

- **Admin:** pengingat tugas akhir (jumlah pengajuan TA/pendadaran/wisuda yang menunggu keputusan dan peserta wisuda yang belum ber-SKL). Selebihnya masih pola placeholder.
- **Dosen:** presensi hari ini, pengingat remidi, ujian susulan yang perlu disiapkan soalnya atau dinilai, serta pengingat tugas akhir (pendaftaran pendadaran menunggu persetujuan, jadwal menguji, nilai yang perlu diisi, hasil yang perlu ditetapkan ketua, revisi yang perlu disahkan).
- **Mahasiswa:** peringatan kehadiran, pengingat remidi, pengajuan susulan yang menunggu, tagihan susulan yang belum lunas, jadwal susulan mendatang, serta pengingat tugas akhir (pengajuan yang diminta perbaikan, jadwal pendadaran, revisi yang perlu diunggah, periode wisuda, SKL terbit).

### 17.4 Fitur lain

- **Jadwal Kuliah (mahasiswa):** kelas-kelas di tahun akademik aktif beserta jadwal dan ruang.
- **Mahasiswa Kelas (dosen):** daftar mahasiswa di kelas yang diampu, dengan pencarian.

### 17.5 Placeholder

Halaman berikut menampilkan "Halaman … sedang disiapkan.":

- profil dosen dan profil mahasiswa;
- Info Perkuliahan;
- seluruh menu **Perpustakaan** (Katalog, Pinjaman Aktif, Riwayat Pinjaman).

---

## 18. Keterkaitan antarfitur dan daftar status

### 18.1 Keterkaitan utama

| Dari | Ke | Hubungan |
|---|---|---|
| Huruf akhir semester lalu | IPS → batas SKS KRS | `maksSksUntuk(ipsSemesterSebelum)` |
| Angkatan + tahun akademik | Semester mahasiswa → tawaran KRS | Semester dihitung, tidak disimpan; menentukan mata kuliah semester ini dan yang tertunda |
| Huruf akhir mata kuliah prasyarat | KRS mata kuliah lanjutan | Mata kuliah lanjutan terkunci sampai prasyaratnya lulus |
| Batas SKS (kuota) | Tagihan semester | Komponen `per_sks` dikali kuota, bukan SKS diambil |
| Tagihan semester | KRS | Bila `kunci_krs_aktif` menyala, tagihan terbit yang belum lunas mengunci KRS |
| KRS | Kelas, presensi, ujian, remidi | Peserta setiap fitur kelas adalah mahasiswa ber-KRS |
| Jadwal mingguan | Pertemuan | Pertemuan dibuat sekali dari jadwal (perubahan jadwal sesudahnya tidak ikut); UTS/UAS ada di pertemuan n/2 dan n |
| Jadwal ujian UTS/UAS | Pertemuan UTS/UAS | Tanggal, jam, dan ruang pertemuan mengikuti ujian |
| Presensi | Syarat ujian | Persentase hadir menentukan boleh ikut ujian online (bila sakelar menyala) |
| Ujian online | Presensi | Mengerjakan ujian mencatat hadir di pertemuan UTS/UAS |
| UAS | Finalisasi nilai, usulan remidi | Finalisasi menunggu UAS selesai; "ikut UAS" menjadi syarat usulan |
| Ikut ujian utama | Pengajuan & tagihan susulan | Yang ikut tidak bisa mengajukan; pengajuan menunggu tampil dibatalkan, yang disetujui gugur, tagihan belum lunas dibatalkan |
| Pengajuan susulan disetujui | Ujian utama online | Pemohon tidak bisa mengerjakan ujian utama online |
| Tagihan susulan lunas | Jadwal ujian susulan | Hanya pemohon lunas yang melihat dan mengikuti susulan |
| UAS susulan | Finalisasi nilai, usulan remidi | Finalisasi menunggu UAS susulan selesai atau gugur; mengerjakan UAS susulan = ikut UAS |
| Nilai final | Daftar remidi | Daftar remidi hanya bisa disusun setelah nilai final |
| Daftar remidi dikunci | Tagihan remidi | Tagihan diterbitkan dari daftar yang dikunci |
| Tagihan remidi lunas | Ujian remidi, huruf akhir | Hanya peserta lunas yang ikut remidi dan bisa diubah hurufnya |
| Pindah kelas | KRS, nilai, presensi, izin, dispensasi | Semuanya dipindah ke kelas tujuan |
| Skala nilai | KRS, IPK, remidi, pendadaran | `lulus`, `boleh_diulang`, dan bobot dipakai di semua bagian itu; angka minimal mengonversi nilai pendadaran |
| KRS mata kuliah TA/Skripsi | Pengajuan TA, pendaftaran pendadaran | Mengambil mata kuliah TA di tahun aktif menjadi syarat keduanya |
| Transkrip (nilai terbaik) | Pendadaran, wisuda | SKS minimal, tanpa nilai E, dan semua mata kuliah sudah dinilai |
| TA disahkan | Pendaftaran pendadaran, bimbingan dosen | Pembimbing menyetujui pendaftaran dan melihat mahasiswanya |
| Pendadaran lulus | KRS mata kuliah TA, TA selesai | Huruf pendadaran menjadi nilai akhir TA; TA selesai membuka pendaftaran wisuda |
| Jadwal kuliah, pertemuan, ujian | Jadwal pendadaran | Ruang yang terpakai menolak jadwal; penguji yang sedang mengajar hanya diberi peringatan |
| SKL terbit | Status mahasiswa | Status berubah menjadi `Lulus` |
| Cuti disetujui | Status mahasiswa | `Cuti` (langsung bila semesternya aktif, atau saat semester itu diaktifkan); tanpa tagihan dan KRS |
| Aktif kembali disetujui | Status mahasiswa | Kembali `Aktif` |

### 18.2 Daftar status

| Entitas | Nilai |
|---|---|
| Status mahasiswa | `Aktif`, `Nonaktif`, `Lulus`, `Dropout`, `Cuti`, `Mengundurkan Diri`, `Meninggal` |
| KRS | `status`: `Aktif`. Nilai berupa huruf dari skala nilai. |
| Tagihan semester | `belum_bayar`, `menunggu_verifikasi`, `lunas`, `ditolak`; tanpa batas bayar (tidak pernah gugur) |
| Tagihan remidi | `belum_bayar`, `menunggu_verifikasi`, `lunas`, `ditolak`; tampilan `gugur` |
| Pengajuan susulan | `menunggu`, `disetujui`, `ditolak`, `dibatalkan`; tampilan `dibatalkan` (menunggu tetapi ikut ujian utama) dan `gugur` (disetujui tetapi ikut ujian utama) |
| Tagihan susulan | `belum_bayar`, `menunggu_verifikasi`, `lunas`, `ditolak`; tampilan `gugur` (lewat batas) dan `dibatalkan` (ikut ujian utama sebelum lunas) |
| Pertemuan | jenis: `kuliah`, `uts`, `uas`. Status: `dijadwalkan`, `berlangsung`, `selesai`; tampilan "terlewat"; riwayat jadwal ulang |
| Presensi mahasiswa | `hadir`, `terlambat`, `izin`, `sakit`, `alpa`. Metode: `manual`, `qr`, `pin`, `pengajuan`, `ujian` |
| Pengajuan izin | `menunggu`, `disetujui`, `ditolak` |
| Ujian | jenis: `uts`, `uas`, `remidi`, `uts_susulan`, `uas_susulan`. Mode: `tatap_muka`, `online_berkas`, `online_soal`. Status: `draf`, `terbit` |
| Pengajuan pindah kelas | `pending`, `disetujui`, `ditolak` |
| Jenis biaya | cara hitung: `tetap`, `per_sks`. Kategori: `semester`, `remidi`, `susulan`, `pendadaran` (info), `wisuda` (info), `cuti` (info) |
| Pengajuan TA/pendadaran/wisuda/cuti/aktif kembali | `menunggu_pembimbing` (pendadaran), `menunggu`, `perlu_perbaikan`, `disetujui`, `ditolak`. Riwayat: `dikirim`, `disetujui_pembimbing`, dan status keputusan |
| Tugas akhir | `berjalan`, `selesai` |
| Pendadaran | `dijadwalkan`, `revisi`, `selesai`, `tidak_lulus`. Hasil: `lulus`, `lulus_revisi`, `tidak_lulus` |
| Peserta wisuda | tanpa status; SKL terbit bila `nomor_skl` terisi |
| Penanda kelas | `nilai_final_at`, `nilai_dibuka_sampai`, `remidi_dikunci_at`, `remidi_final_at` |

---

## 19. Perlu dikonfirmasi

Daftar ini berisi perilaku di kode yang ambigu, tampak tidak konsisten, atau belum bisa dipastikan maksudnya. Tidak ada kode yang diubah untuk dokumen ini.

### Autentikasi dan akses

1. ~~**Login lewat NIM/NIDN.**~~ **Selesai 28 Sep 2026:** login menerima NIM, NIDN, atau username (lihat 2.1).
2. ~~**Verifikasi email dan konfirmasi kata sandi tidak aktif.**~~ **Selesai 28 Sep 2026:** keduanya dipakai (lihat 2.4).
3. ~~**Pengguna tanpa role.**~~ **Selesai 28 Sep 2026:** `role_id` kosong diisi dari profil saat migrasi (lihat 1.1).
4. ~~**Role mahasiswa yang diberi permission admin.**~~ **Selesai 28 Sep 2026:** menu admin hanya untuk role Admin/Karyawan dan Dosen (lihat 1.3).

### Pengaturan dan data master

5. ~~**Urutan tanggal tahun akademik belum lengkap.**~~ **Selesai 28 Sep 2026:** KRS harus ditutup paling lambat tanggal akhir, batas input nilai dan batas bayar remidi tidak boleh sebelum tanggal akhir (lihat 3.1).
6. ~~**Skala nilai boleh tanpa huruf "tidak lulus" sama sekali.**~~ **Selesai 28 Sep 2026:** wajib ada huruf lulus dan tidak lulus, dan huruf tidak lulus wajib boleh diulang (lihat bagian 4).
7. ~~**Pesan penyusunan ulang pertemuan tidak lengkap.**~~ **Selesai 28 Sep 2026:** susun ulang dihapus; tanggal mulai terkunci bila sudah ada pertemuan, dan perubahan jadwal hanya per pertemuan dengan alasan (lihat 9.1).

### Keuangan dan KRS

8. ~~**Mahasiswa `Transfer Masuk` tidak pernah ditagih.**~~ **Selesai 28 Sep 2026:** status `Transfer Masuk` dihapus dan diubah menjadi `Aktif` (lihat 3.3).
9. ~~**Tandai lunas sebelum tagihan terbit** membuat tagihan bernilai 0 tanpa rincian, dan penerbitan berikutnya melewatinya.~~ **Selesai 28 Sep 2026:** tandai lunas/belum bayar hanya untuk tagihan terbit; tagihan semester kini dibayar lewat unggah bukti lalu diverifikasi admin (lihat 6.3).
10. ~~**"Tandai Belum Bayar" tanpa tagihan terbit** membuat tagihan Rp0 yang tetap mengunci KRS.~~ **Selesai 28 Sep 2026:** tagihan Rp0 tidak dibuat (dilaporkan "tanpa tarif"); tagihan kosong lama dihapus saat migrasi (lihat 6.2).
11. ~~**Terbitkan ulang menimpa rincian yang diketik manual.**~~ **Selesai 28 Sep 2026:** hanya tagihan `belum_bayar` tanpa bukti dan tanpa rincian manual yang dihitung ulang (lihat 6.2).
12. **Dua definisi "semester sebelumnya".** Peringatan nilai belum lengkap memakai tahun akademik global sebelumnya; IPS memakai tahun terakhir yang diambil mahasiswa. Hasil keduanya bisa berbeda.
13. **Label `per_sks` tidak cocok dengan rumus.** Label dan komentar menyebut "SKS yang diambil", padahal rumusnya memakai kuota SKS.
14. ~~**Konfirmasi awal tombol "Terbitkan Tagihan Semester Ini" kemungkinan terlewat.**~~ **Selesai 28 Sep 2026:** tombol memanggil `terbitkan()` tanpa argumen, jadi konfirmasi awal selalu tampil.
15. **Teks spanduk tidak sesuai perilaku.** Spanduk menyebut "tagihan akan bernilai nol" bila belum ada jenis biaya, padahal server menolak penerbitan.
16. **Admin membatalkan KRS tanpa cek periode atau kunci KRS.** Baris `krs_semester` tetap ada.
17. **Tarif ganda bisa lolos.** Unique `(jenis_biaya_id, prodi_id, angkatan)` tidak mencegah dua tarif umum (kolom NULL). Duplikat lain memicu error database, bukan pesan validasi.
18. **Filter status KRS tidak konsisten.** Sebagian perhitungan SKS memfilter status KRS `Aktif`, sebagian tidak. Belum berdampak karena semua KRS saat ini berstatus `Aktif`.
19. ~~**Urutan tahun akademik** di KHS dan Info Biaya berbasis teks (`semester`).~~ **Selesai 28 Sep 2026:** semua daftar tahun akademik diurutkan menurut `tanggal_mulai` (lihat 3.1).
20. **Belum ada transkrip PDF.** Hanya KHS yang punya PDF.

### Kelas, konten, dan presensi

21. **Konten tetap bisa diubah di tahun akademik nonaktif.** Materi, tugas, dan quiz tidak mengecek kunci tahun akademik maupun nilai final. Mahasiswa juga masih bisa mengumpulkan tugas dan mengerjakan quiz (hanya dibatasi tenggat).
22. **Menambah soal quiz tidak menilai ulang** attempt yang sudah ada dan tidak mengecek kunci nilai.
23. **Dua whitelist unggahan berbeda.** Unggahan dosen memakai `AllowedUpload`; pengumpulan tugas mahasiswa memakai daftar yang lebih sempit.
24. **Mengganti dosen kelas tidak mengubah dosen pertemuan yang sudah dibuat.** Dosen lama bisa terbaca sebagai "pengganti".
25. **Kapasitas ruang tidak dibandingkan dengan kapasitas kelas.**
26. **Duplikasi konten ke tahun akademik lain** hanya dibatasi di antarmuka, tidak di server.
27. **Tutup otomatis bergantung pada kunjungan halaman** (tidak ada cron), sehingga pertemuan bisa `selesai` tanpa jurnal.
28. **Izin/sakit bisa diajukan untuk pertemuan yang belum terjadi.** Tidak ada batas awal.
29. **Peringatan kehadiran di beranda tetap memakai `min_kehadiran_ujian`** walau sakelar syarat ujian mati.

### Ujian, nilai, dan remidi

30. **Penerbitan jadwal ujian melewati ujian tatap muka tanpa ruang** tanpa menyebutkan mana saja.
31. **Rilis nilai ujian dan pembuatan lembar soal hanya dibatasi di antarmuka.** Rilis nilai tidak mengecek kunci nilai; pembuatan lembar soal tidak mengecek apakah ujian sudah dimulai.
32. **Status ujian bisa dikembalikan dari `terbit` ke `draf`** walau sudah ada yang mengerjakan.
33. **Cakupan cek bentrok ujian.** Bentrok mahasiswa ikut menghitung ujian berstatus draf dan peserta remidi yang belum lunas. Jadwal remidi massal tidak dicek bentrok sama sekali.
34. **Huruf akhir tidak dihitung dari komponen.** Apakah memang akan tetap diisi manual?
35. **KHS dan transkrip menampilkan huruf sebelum nilai final.** Apakah mahasiswa memang boleh melihatnya?
36. **Admin bisa menandai lunas tagihan yang sudah gugur**, sehingga peserta itu bisa ikut remidi lagi.
37. **Finalisasi remidi tidak mencatat pelakunya** dan tidak mengecek tahun akademik nonaktif.
38. **Admin tidak terkena batas huruf maksimal remidi** maupun kunci huruf peserta remidi. Apakah memang disengaja?

### Pindah kelas dan lainnya

39. **Pengajuan ke kelas penuh atau bentrok tetap bisa dikirim.** Saat disetujui, admin hanya diperingatkan soal bentrok, tidak soal kapasitas.
40. **Persetujuan pindah kelas** tidak mengecek status KRS asal maupun apakah formulir masih dibuka.
41. **Satu `force` melewati dua peringatan sekaligus.** Bila KRS asal sudah bernilai, peringatan bentrok tidak pernah sempat tampil.
42. **Flash `pindah_kelas_error` dibaca di halaman mahasiswa** tetapi tidak pernah diisi controller.
43. **Tidak ada notifikasi** (email atau lainnya) untuk hasil pindah kelas, tagihan, remidi, atau ujian susulan. Semuanya hanya lewat halaman dan pesan flash.
44. **Info kuliah tanpa target** (prodi/kelas), dan dosen tidak punya akses.
45. **Beranda admin dan `/dashboard` sebagian besar masih placeholder** (admin hanya berisi pengingat tugas akhir). Begitu juga profil dosen dan mahasiswa, Info Perkuliahan, dan Perpustakaan.

### Ujian susulan

46. **Pengembalian dana susulan ditangani di luar sistem.** Tagihan lunas milik mahasiswa yang ternyata ikut ujian utama hanya diberi tanda; tidak ada pencatatan refund.
47. **Ujian utama tatap muka tidak bisa diblokir** bagi pemohon yang sudah disetujui. Hak susulan baru gugur bila dosen mencatat hadir atau mengisi nilai ujian utama; kalau keduanya tidak dilakukan, mahasiswa bisa mengikuti ujian utama dan susulan.
48. **Syarat kehadiran tidak diperiksa saat mengajukan atau menerbitkan tagihan susulan.** Mahasiswa bisa membayar lalu ditolak saat mengerjakan susulan karena tidak memenuhi syarat kehadiran.
49. **Admin masih bisa menandai lunas tagihan susulan yang sudah gugur** (sama dengan PD-36), sehingga pemohon itu kembali menjadi peserta susulan.
50. **Batas tanggal susulan mengikuti batas input nilai saat dijadwalkan.** Bila kelas tidak punya batas input nilai, tidak ada batas akhir; bila batas diubah kemudian, jadwal yang sudah ada tidak diperiksa ulang.
51. **Jadwal susulan massal tidak mengecek bentrok** ruang maupun mahasiswa, sama seperti jadwal remidi massal.

### Tugas akhir, pendadaran, dan wisuda

52. **Predikat kelulusan** hanya dihitung dari IPK (> 3,50 Dengan Pujian, > 3,00 Sangat Memuaskan, ≥ 2,76 Memuaskan, selain itu Cukup). Belum ada syarat lain seperti masa studi atau tanpa nilai mengulang untuk cum laude.
53. **Batas angka minimal skala nilai** (A 80, B 70, C 60, D 50) adalah bawaan sistem, bukan dari kebijakan kampus.
54. **SKS minimal wisuda memakai angka yang sama dengan pendadaran** (`min_sks_pendadaran`); tidak ada pengaturan terpisah.
55. **"Semua mata kuliah sudah dinilai" mencakup semester berjalan**, sehingga mahasiswa yang masih mengambil mata kuliah lain bersamaan dengan Skripsi baru bisa mendaftar pendadaran setelah nilainya keluar.
56. **Nilai E dicek sebagai huruf `E`**, bukan huruf bertanda tidak lulus di skala nilai.
57. **Nilai pendadaran ditulis ke KRS tanpa melihat kunci nilai kelas Skripsi.** Dosen pengampu kelas Skripsi juga masih bisa mengubah huruf itu lewat tabel nilai kelas.
58. **Nomor surat pendadaran dan SKL** dihitung dari jumlah nomor tahun itu + 1. Dua persetujuan yang benar-benar bersamaan bisa mendapat nomor surat yang sama (nomor SKL unik di database, sehingga yang kedua gagal).
59. **Koreksi data ijazah tidak memperbarui profil.** SKL memakai data dari form wisuda, sedangkan profil mahasiswa tetap data lama.
60. **Belum ada fitur mengubah** pembimbing setelah TA disahkan, jadwal/penguji pendadaran setelah terbit, peserta wisuda, atau membatalkan SKL (status Lulus).
61. **Cek bentrok pendadaran** tidak mencakup pembimbing yang bukan penguji maupun jadwal kuliah mahasiswanya sendiri.
62. **Tidak lulus pendadaran tidak mengisi nilai KRS Skripsi.** Bila semester berakhir, KRS itu tetap tanpa nilai dan mahasiswa harus mengambil Skripsi lagi di tahun aktif untuk mendaftar ulang.
63. **Kelas Skripsi** diperlakukan seperti kelas lain (KRS, tagihan per SKS), tetapi tidak punya jadwal, pertemuan, atau ujian; data demo membuatnya tanpa jadwal.
64. **Beranda belum menampilkan pengingat cuti**, baik untuk mahasiswa (perbaikan diminta, cuti disetujui) maupun admin (pengajuan menunggu). Admin melihat jumlah menunggu hanya di tab halaman Pengajuan Cuti.
65. **Status Cuti tidak berakhir sendiri.** Mahasiswa tetap `Cuti` di semester-semester berikutnya sampai pengajuan aktif kembali disetujui; tidak ada pengingat saat semester cutinya selesai.
66. **Status yang diubah manual di Manage User** (mis. Aktif → Cuti) tidak tercatat sebagai pengajuan, sehingga tidak dihitung dalam `maks_cuti`.
