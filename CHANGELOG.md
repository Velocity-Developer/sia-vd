# Changelog

Semua perubahan penting dicatat di sini. Format mengikuti [Keep a Changelog](https://keepachangelog.com/id/1.1.0/).
Mulai 1.0.0 repo memakai [Semantic Versioning](https://semver.org/lang/id/); riwayat sebelum 1.0.0 dikelompokkan per tanggal.

## [Belum dirilis]

### Diubah
- **Foto profil tampil sebagai avatar**: `auth.user.avatar` = `User::fotoUrl()` (sidebar, menu pengguna, kartu
  identitas dashboard dosen & mahasiswa). Setiap pengguna (karyawan/dosen/mahasiswa) bisa mengganti/menghapus
  fotonya sendiri di Pengaturan Profil (`POST settings/profile/foto`, `profile.foto`; jpg/png/webp maks 2 MB,
  berkas lama dihapus). `User::urlFoto()` dipakai bersama UserController.
- **Halaman depan baru** (sesuai contoh klien Yapika): `/login` kini memakai `PortalLayout` — menu atas
  SIAKAD · Pengumuman · Kalender Akademik, deskripsi kampus (Pengaturan Sistem → Tampilan, komponen
  `DeskripsiKampus`) di kiri, form masuk di kanan. Halaman publik baru `/pengumuman` (pita gelap
  Informasi & Pengumuman sesuai contoh, 10 per halaman) dan `/kalender-akademik` (tanggal-tanggal
  Tahun Akademik aktif). Lampiran Informasi & Pengumuman (`berkas.info-kuliah`) kini publik tanpa login.
  Kolom opsional `info_kuliahs.kategori` (label di daftar). Sitemap memuat kedua halaman baru.
- Seksi sidebar admin **Mahasiswa Baru** dipindah tepat di bawah **Master**.
- **Jenis Biaya dihapus dari admin selama fitur keuangan mati** (Yapika): izin `admin.jenis-biaya` masuk
  `PermissionCatalog::FITUR` (keuangan), rute diberi `fitur:keuangan` (404), menu & seksi Keuangan hilang, dan
  `JenisBiaya::infoUntuk()` mengembalikan kosong sehingga info biaya cuti/TA/wisuda tidak tampil ke mahasiswa.

### Ditambahkan
- **Teks hak cipta seragam** "©{tahun} {nama institusi}. All Rights Reserved. Design by Velocity Developer" (tautan
  velocitydeveloper.com, tab baru; komponen `HakCipta`): halaman masuk,
  PMB, footer baru di semua halaman setelah login, dan email. Nama dari Pengaturan Sistem → Institusi.
- **sitemap.xml & robots.txt dinamis** (`SeoController`, rute `sitemap`/`robots`): sitemap hanya halaman publik
  (Informasi PMB dengan lastmod dari `informasi_pmb`, Formulir PMB, Login); robots.txt menutup /admin, /dosen,
  /mahasiswa, /pengaturan-sistem, /dev, /berkas, /settings, /pmb/selesai, /pmb/kecamatan dan menunjuk ke sitemap.
  Alamat memakai APP_URL. `public/robots.txt` statis dihapus.
- **Impor Kelas Kuliah dari Excel** (`admin/impor/kelas-kuliah`, izin `admin.kelas-kuliah`, juga role Prodi untuk MK
  prodinya; tombol "Impor Excel" di Kelas Kuliah). Kolom: Kode Kelas, Tahun Akademik (mis. `2025/2026 Ganjil`, kosong =
  TA aktif), Kode MK, NIDN Dosen (wajib kecuali MK TA/Skripsi), Kapasitas, Jumlah Pertemuan (kosong = bawaan). Kode kelas
  unik per TA (di database dan di dalam berkas). Lembar referensi Tahun Akademik, Mata Kuliah, Dosen. Jadwal tetap diisi
  di Jadwal Kelas. `Impor::pesan()` untuk pesan galat tambahan per jenis.
- **Penamaan & struktur menu konsep Yapika poin C (4 Okt).** Migrasi `2026_10_04_170000` (sinkron nama izin).
  - Seksi admin **Pengguna & Akses** menjadi **Tools**: **Create User** (`admin.pengguna.buat`; pilih Prodi, Dosen,
    atau Mahasiswa → form kelola user yang sudah ada; Prodi = form karyawan dengan role Prodi terpilih lewat
    `?role=prodi`), **Data Pengguna** (`admin.pengguna.index`; semua akun dalam satu daftar dengan filter jenis &
    pencarian nama/username/email/NIM/NIDN/nomor induk; akun developer disembunyikan), Kelola Role, Log Aktivitas.
    Menu Karyawan dilepas dari sidebar (rutenya tetap). Kedua menu tampil bila punya salah satu izin
    `admin.users.{karyawan,dosen,mahasiswa}`; pilihan/jenis dibatasi per izin.
  - Admin **Presensi** → **Presensi Mahasiswa** (sidebar, judul, breadcrumb).
  - Dosen: menu **Input Nilai** (`dosen.input-nilai.index`, izin `dosen.kelas-kuliah`) — daftar kelas yang diampu,
    tombol "Isi Nilai" membuka halaman kelas dengan bagian Nilai Mahasiswa terbuka (`?bagian=nilai`).
  - **Info Kuliah** → **Informasi & Pengumuman** (menu admin & mahasiswa, judul halaman, nama izin, pesan sukses).
  - Jadwal tetap lewat Kelas Kuliah + Jadwal Kelas (keputusan user).
- **Menu konsep Yapika poin B (4 Okt).** Migrasi `2026_10_04_120000` s.d. `2026_10_04_160000`; dependensi baru
  `phpoffice/phpspreadsheet` ^5.0 (impor/ekspor Excel).
  - **Syarat Ujian & Remedial** (Akademik → Konfigurasi, izin `admin.syarat-ujian`, juga role Prodi): syarat kehadiran
    UTS/UAS, minimal %, izin & sakit dihitung hadir (baru), dan huruf maksimal remidi — umum atau **per prodi**
    (`syarat_ujian_prodis`, `PengaturanAkademik::untukProdi()`). Isian ini dipindah dari Pengaturan Sistem → Akademik
    (rute `admin.pengaturan-akademik.remidi` dihapus). Akun Prodi hanya mengatur prodinya.
  - **Kurikulum** (Akademik → Konfigurasi, izin `admin.kurikulum`, juga Prodi): kurikulum per prodi + daftar MK dengan
    semester dan sifat Wajib/Pilihan per kurikulum (`kurikulums`, `kurikulum_mata_kuliah`). Belum dipakai aturan KRS.
  - **Ketua Kelas** (Akademik → Perkuliahan, izin `admin.ketua-kelas`, juga Prodi): satu peserta per kelas kuliah
    (`kelas_kuliah.ketua_kelas_id`). Rombel tidak dibuat terpisah; memakai kode kelas kuliah.
  - **Rekap Presensi Mahasiswa** (Akademik → Perkuliahan, izin `admin.rekap-presensi`, juga Prodi): H/T/I/S/A dan %
    per mahasiswa per MK dalam satu TA, status syarat ujian prodi, unduh Excel.
  - **Informasi PMB**: halaman publik `/pmb` (`pmb.informasi`; jadwal dari periode aktif + teks syarat/jadwal tes/biaya/
    kontak dari admin di Mahasiswa Baru → Konfigurasi → Informasi PMB, izin `admin.informasi-pmb`). Tombol PMB di
    halaman masuk kini menuju halaman ini. Halaman `/` tetap ke login.
  - **Log Aktivitas** (Pengguna & Akses, izin `admin.log-aktivitas`): semua model Eloquent yang dibuat/diubah/dihapus
    pengguna login + masuk/keluar (`App\CatatAktivitas`, tabel `log_aktivitas`); nilai rahasia/`$hidden` disamarkan.
    Update massal lewat query builder dan proses tanpa pengguna tidak tercatat.
  - **Impor Data Excel** (`admin/impor/{mahasiswa|dosen|mata-kuliah}`, izin sesuai menu datanya): template .xlsx dengan
    lembar Petunjuk & referensi kode, semua baris divalidasi dulu (galat per baris, tidak ada yang tersimpan bila ada
    galat), akun langsung terverifikasi, opsi kirim tautan atur kata sandi. Tombol "Impor Excel" di Data Mahasiswa,
    Data Dosen, Mata Kuliah.
  - **Menu mahasiswa Cetak KST dan Cetak Kartu UTS & UAS** (halaman status + unduh, rute `mahasiswa.cetak-kst`,
    `mahasiswa.cetak-kartu-ujian`).
- **Alur Yapika A1–A9 (4 Okt).** Migrasi `2026_10_04_080000` s.d. `2026_10_04_110000`.
  - **Penilaian satu alur:** menu Penilaian diurutkan Nilai Semester → Detail Nilai → Pendataan Nilai Akhir → Nilai KKM →
    Validasi Nilai → Tambah Komponen Nilai. **Pendataan Nilai Akhir kini rekap baca-saja** (angka → huruf Bobot Nilai,
    bobot, asal huruf, status validasi; rute `admin.pendataan-nilai.show`, isian huruf manual dihapus). "Finalisasi Nilai"
    dosen menjadi **Kirim ke Validasi**, "Buka Kunci Nilai" admin menjadi **Kembalikan ke Dosen**; Validasi Nilai menolak
    nilai kelas berdosen yang belum dikirim (kolom "Belum dikirim dosen").
  - **Remidi membuka validasi:** huruf hasil remidi boleh disimpan untuk nilai tervalidasi; validasinya dibuka otomatis dan
    nilai kembali menunggu validasi ulang.
  - **KHS, transkrip, dan SKL hanya dari nilai tervalidasi** (KHS menampilkan "Menunggu validasi"); syarat wisuda baru
    "Semua nilai sudah divalidasi"; nilai semester lalu disahkan otomatis oleh migrasi.
  - **UTS/UAS tatap muka dinilai lewat komponen nilai** (isian nilai di halaman ujian disembunyikan/ditolak,
    `Ujian::nilaiLewatKomponen`); komponen **Kehadiran dihitung ulang otomatis** saat presensi/pertemuan berubah
    (`App\SegarkanKehadiran`).
  - **Satu kartu ujian:** Kartu UTS/UAS mahasiswa memakai PDF & syarat yang sama dengan cetakan admin (`App\KartuUjian`,
    format contoh klien); kartu remidi/susulan tetap dari jadwalnya.
  - **PMB:** status hasil seleksi **Diterima**/Ditolak (dulu Lulus); **Salin ke Master Mahasiswa wajib mengisi NIM**, yang
    sekaligus menjadi username.
  - **Dosen PA melihat pengajuan mahasiswa bimbingannya** (menu dosen Pengajuan & Pendaftaran → Pengajuan Mahasiswa PA,
    izin `dosen.pengajuan-pa`, baca-saja, termasuk berkas lampiran).
  - **Wisuda:** unggahan **Surat bebas pustaka** + **Surat keterangan lunas** (menggantikan bukti bayar wisuda); admin wajib
    mencentang **Bebas pustaka** dan **Lunas** saat menyetujui (tercatat di `isian.dicentang`).
  - **Pendaftaran Sidang** (jenis pengajuan `sidang`) saat fitur pendadaran mati: form + berkas + persetujuan admin, butuh
    judul TA disahkan; jadwal/penguji di luar sistem. Middleware `fitur:!nama` dan kunci menu `tanpaFitur` untuk rute/menu
    pengganti.
  - **Gelombang Ujian Komprehensif** (tabel `gelombang_kompre`, menu admin Ujian Komprehensif → Gelombang Ujian
    Komprehensif): pendaftaran buka–tutup, tanggal ujian, kuota; pengajuan kompre wajib memilih gelombang yang dibuka.
  - **Pengajuan cuti tanpa bukti bayar** (isian dihapus; dokumen pendukung tetap opsional).
- **Menu Pengajuan & Pendaftaran** (sidebar admin & mahasiswa; migrasi `2026_10_04_070000_add_naskah_to_tugas_akhir`:
  kolom `tugas_akhir.naskah`, `naskah_diunggah_at`). Enam seksi: **Status Mahasiswa** (Pengajuan Cuti; admin juga
  **Mahasiswa Cuti** — daftar mahasiswa berstatus Cuti dengan semester cuti, alasan, jumlah cuti, dan tanda pengajuan aktif
  kembali, `Admin\MahasiswaCutiController`), **Tugas Akhir/Skripsi** (mahasiswa: Pengajuan Judul & Upload TA; admin:
  Persetujuan Tugas Akhir), **Kuliah Kerja Mahasiswa** (Pengajuan Judul / Persetujuan KKM/PKL/KKN), **Praktek Pengalaman
  Lapangan** (Pengajuan PPL / Daftar PPL), **Ujian Komprehensif** (Pengajuan / Daftar Ujian Komprehensif), **Wisuda** (Pengajuan Wisuda / Daftar Wisuda + Periode Wisuda). Halaman admin
  lama bertab kini satu halaman per jenis (`PengajuanAkademikController::RUTE`, alamat lama dialihkan); halaman mahasiswa
  Tugas Akhir & Wisuda dipecah menjadi Pengajuan Judul & Upload TA dan Pengajuan Wisuda (`mahasiswa.wisuda`); halaman
  KKM, PPL & Kompre dipecah per jenis (`mahasiswa.pengajuan-kkm/ppl/kompre`). **KKM kini Kuliah Kerja Mahasiswa**: form
  wajib memilih KKM/PKL/KKN (`PengajuanAkademik::JENIS_KKM`, isian `jenis_kkm`), ditampilkan di persetujuan dan Nilai KKM.
  **Upload naskah TA** (PDF maks. 20 MB) sesudah judul disahkan; naskah ini sekaligus **naskah final wisuda** (unggahan
  "Naskah final" di form wisuda dihapus, diganti syarat "Naskah TA sudah diunggah"), terkunci selama pendaftaran wisuda
  diproses dan sesudah terdaftar; berkas `berkas.naskah-ta` untuk mahasiswa, admin (tautan di Persetujuan TA dan Daftar
  Wisuda), dan pembimbing. Label submenu sidebar yang panjang kini membungkus.
- **Role Prodi** (migrasi `2026_10_04_060000_add_prodi_to_admin_profiles`: kolom `admin_profiles.prodi_id`, role sistem
  `prodi` berjenis Admin/Karyawan dengan izin bawaan `PermissionCatalog::IZIN_PRODI` sesuai matriks konsep). Akun Prodi
  dibuat di menu Karyawan: pilih role Prodi lalu Program Studi (wajib; role lain mengosongkannya). Akun Prodi hanya
  melihat dan mengubah data prodinya: global scope `Models\Concerns\DibatasiProdi` pada ProgramStudi, MataKuliah,
  MahasiswaProfile, KelasKuliah, Krs, KrsSemester, Pertemuan, Jadwal, Ujian, BobotNilai, BatasSksProdi (daftar, pencarian,
  dan akses lewat URL ke prodi lain → 404; menyimpan data prodi lain → 403). Tahun Akademik, Ruang, dan Predikat hanya
  bisa dilihat, dan koreksi Presensi Dosen tetap milik Admin (`App\LingkupProdi::RUTE_TERLARANG`, middleware
  `BatasiAksiProdi`); tombolnya disembunyikan lewat prop bersama `auth.prodi`. Program studi yang dipakai akun Prodi
  tidak bisa dihapus.
- **Menu Akademik → Hasil Studi** (migrasi `2026_10_04_050000_add_izin_hasil_studi`: izin `admin.khs` dan
  `admin.transkrip-nilai`, bawaan Admin). **KHS**: daftar mahasiswa ber-KRS per tahun akademik/prodi dengan tombol
  Download (PDF KHS) dan Detail (KHS sama seperti tampilan mahasiswa, pilih tahun akademik, tombol Download KHS).
  **Transkrip Nilai**: daftar semua mahasiswa ber-KRS dengan tombol Download dan Detail (transkrip + Download Transkrip).
  Data & PDF KHS/transkrip kini satu sumber di `App\HasilStudi` (dipakai juga halaman mahasiswa); tampilan bersama di
  komponen `IsiKhs.vue` dan `IsiTranskrip.vue`.
- **Penilaian TA/Skripsi, PPL, dan KKM sesuai alur Yapika** (migrasi `2026_10_04_040000_add_jenis_penilaian_to_mata_kuliahs`:
  kolom `mata_kuliahs.jenis_penilaian` reguler/tugas_akhir/ppl/kkm, selalu selaras dengan `tugas_akhir`;
  `tugas_akhir.pembimbing_1_id` boleh kosong; izin `admin.nilai-kkm` dan `mahasiswa.pengajuan-kegiatan`).
  Form Mata Kuliah memakai pilihan **Jenis Penilaian** (pengganti centang TA/Skripsi).
  - **PPL** (dan **TA/Skripsi** bila pendadaran mati) dinilai **langsung** di Nilai Semester / halaman kelas: satu kolom
    Nilai Akhir 0–100 tanpa komponen, huruf dari Bobot Nilai prodi (`NilaiSemester::langsung/komponenKelas`).
  - **KKM (seminar)**: kelas KKM ditolak di Nilai Semester; nilainya di menu baru **Penilaian → Nilai KKM** (admin,
    `Admin\NilaiKkmController`): mahasiswa dengan pengajuan KKM disetujui, angka 0–100 → huruf ke KRS mata kuliah KKM;
    nilai tervalidasi terkunci; tombol "Masukkan ke KRS" bila KRS KKM belum ada.
  - **Pengajuan KKM, PPL & Kompre** (mahasiswa, `Mahasiswa\PengajuanKegiatanController`, halaman tab per jenis: judul/topik,
    keterangan, berkas syarat + tambahan) diproses di **Pengajuan & Pendaftaran** (dulu "TA & Wisuda"). KKM yang disetujui
    otomatis masuk KRS kelas mata kuliah KKM prodinya di tahun aktif (`App\KrsKkm`).
  - **Flag fitur `pendadaran`** (`FEATURE_PENDADARAN`, bawaan nyala; Yapika mati): mati = tanpa pendaftaran pendadaran,
    jadwal/penguji, bimbingan dosen, dan revisi naskah; pengajuan TA tanpa (usulan) pembimbing; TA selesai otomatis saat
    nilai MK TA/Skripsi lulus (`TugasAkhir::sinkronDariNilai`, dipicu `Krs::saved`) sehingga wisuda terbuka.
  - **Tanggal lulus (yudisium)** diisi admin saat menyetujui wisuda (wajib bila pendadaran mati) dan dipakai SKL;
    **transkrip** (halaman & PDF) menampilkan judul TA dan tanggal lulus.
- **Menu Akademik → Penilaian** (migrasi `2026_10_04_010000_create_komponen_nilai_tables`: tabel `komponen_nilais`,
  `nilai_komponens`, kolom `krs.nilai_angka`; izin `admin.komponen-nilai` dan `admin.nilai-semester`, bawaan Admin).
  **Tambah Komponen Nilai**: komponen global (mis. Kehadiran, Tugas, UTS, UAS) dengan persen yang harus berjumlah 100%;
  komponen yang sudah berisi nilai tidak bisa dihapus. **Nilai Semester**: daftar kelas per tahun akademik/prodi, lalu isi
  angka 0–100 per komponen per mahasiswa; nilai akhir = rata-rata berbobot (`App\NilaiSemester`), huruf otomatis dari angka
  minimal skala nilai prodi mata kuliah (Bobot Nilai, atau Skala Nilai umum). Baris belum lengkap hanya menyimpan angkanya;
  huruf yang diisi dosen tanpa komponen tidak disentuh. Kelas TA/Skripsi tetap dari pendadaran.
  Komponen punya **sumber**: diisi dosen/admin, atau **Otomatis dari kehadiran** (paling banyak satu; persentase
  hadir/terlambat di pertemuan kuliah yang selesai, dasar sama dengan syarat UAS — `SyaratUjian::pertemuanDihitung`).
  **Halaman kelas dosen/admin**: bila komponen lengkap (100%) dan angka minimal skala prodi terisi, tabel Nilai Mahasiswa
  berubah menjadi isian per komponen (komponen `TabelNilaiKomponen.vue`, rute `{admin,dosen}.kelas-kuliah.nilai-komponen`,
  kunci nilai dosen tetap berlaku); memilih huruf langsung ditolak kecuali huruf hasil remidi. Peserta remidi yang daftarnya
  sudah dikunci tidak dihitung ulang dari komponen.
- **Penilaian → Detail Nilai** (migrasi `2026_10_04_020000_add_validasi_nilai_to_krs`: kolom `krs.nilai_divalidasi_at`,
  `nilai_divalidasi_oleh`; izin `admin.detail-nilai` dan `admin.validasi-nilai`, bawaan Admin; `Admin\DetailNilaiController`).
  Daftar mahasiswa ber-KRS per tahun akademik/prodi (NIM, nama, angkatan, Detail), lalu nilai per mata kuliah (kode MK,
  mata kuliah, kelas, angka tiap komponen, nilai akhir, huruf, status validasi; baca saja).
- **Penilaian → Pendataan Nilai Akhir** (migrasi `2026_10_04_030000_add_izin_pendataan_nilai`, izin `admin.pendataan-nilai`,
  bawaan Admin; `Admin\PendataanNilaiController`): daftar mahasiswa ber-KRS (NIM, nama, angkatan, Edit) → semua mata
  kuliah yang pernah diambil, dikelompokkan per tahun akademik (kode MK, mata kuliah, SKS, nilai huruf, Hapus/Simpan).
  Huruf dipilih dari skala nilai prodi mata kuliah dan disimpan tanpa nilai angka; Hapus mengosongkan huruf & nilai akhir.
  Nilai tervalidasi ditolak.
- **Penilaian → Validasi Nilai** (menu sendiri, izin `admin.validasi-nilai`, `Admin\ValidasiNilaiController`; daftar
  mahasiswa bersama Detail Nilai lewat trait `Concerns\NilaiMahasiswa` + komponen `DaftarMahasiswaNilai.vue`): pilih
  mahasiswa → nilai akhir & huruf per mata kuliah dengan kolom **Validasi Nilai** (Validasi/Batalkan per mata kuliah, atau
  **Validasi Semua**/**Batalkan Semua** untuk mata kuliah berhuruf di tahun itu). Nilai tervalidasi terkunci untuk dosen
  dan admin (tabel komponen di halaman kelas, Nilai Semester, dan huruf remidi).
- **Menu Akademik → KRS** (migrasi `2026_10_04_000000_add_izin_menu_krs`, izin baru `admin.input-krs`, `admin.status-krs`,
  `admin.cetak-kst`, `admin.kartu-ujian`, `admin.rekap-krs` untuk role Admin): **Input KRS** (admin menambah/mengeluarkan kelas
  atas nama mahasiswa di luar periode KRS dengan aturan tawaran/prasyarat/bentrok/kapasitas/batas SKS yang sama, lalu
  "Simpan" atau "Simpan & Setujui"), **Verifikasi KRS** (dipindah dari Administrasi), **Status KRS** (Ya = disetujui &
  terkunci, Tidak = dibuka untuk diubah), **Cetak KST** (Kartu Studi Tetap, PDF `pdf.kst`), **Cetak Kartu Ujian** (UTS/UAS
  terpisah dari KRS disetujui, PDF `pdf.kartu-ujian-krs`, ditolak bila kehadiran kurang dari syarat), **Rekap KRS** (per
  kelas & per mahasiswa, unduh CSV). Logika ambil kelas dipindah ke `App\AmbilKelasKrs` (dipakai mahasiswa & admin);
  filter bersama di trait `Concerns\FilterKrs`. Mahasiswa juga bisa **Cetak KST** sendiri dari halaman KRS (rute
  `mahasiswa.krs.kst`, hanya bila KRS disetujui); PDF KST dibuat `App\KartuStudiTetap` (dipakai admin & mahasiswa).
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
- **Huruf akhir tidak bisa lagi dipilih langsung** di tabel Nilai Mahasiswa (dosen maupun admin): nilai hanya lewat
  komponen. Sebelum komponen 100% dan angka minimal skala prodi terisi, halaman kelas menampilkan alasan kelas belum bisa
  dinilai (prop `nilaiBelumSiap`). `updateGrade` kini hanya untuk huruf hasil remidi (dosen lewat jalur remidi; admin untuk
  peserta remidi yang daftarnya dikunci, prop `hurufRemidiAdmin`) dan hurufnya wajib diisi.
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
