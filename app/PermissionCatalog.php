<?php

namespace App;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Daftar permission (menu & fitur) yang dikenal aplikasi.
 *
 * Katalog ini hanya dipakai untuk menyinkronkan tabel `permissions` dan hak akses bawaan
 * role sistem. Pengecekan akses saat runtime selalu membaca data role & permission di database.
 */
class PermissionCatalog
{
    /**
     * Permission milik fitur per klien (config/client.php). Selama fiturnya mati, permission ini dianggap
     * tidak dimiliki siapa pun dan disembunyikan dari Kelola Role, tetapi tetap tersimpan di role-nya.
     * Jangan masukkan Role::SUPER_PERMISSION: izin itu juga menentukan siapa yang boleh mengelola akun.
     */
    public const FITUR = [
        'admin.tagihan' => 'keuangan',
        'admin.jenis-biaya' => 'keuangan',
        'mahasiswa.info-biaya' => 'keuangan',
        'admin.materi' => 'materi',
        'dosen.materi' => 'materi',
        'admin.tugas' => 'tugas',
        'dosen.tugas' => 'tugas',
        'admin.quiz' => 'quiz',
        'dosen.quiz' => 'quiz',
        'admin.pindah-kelas' => 'pindah_kelas',
        'mahasiswa.pindah-kelas' => 'pindah_kelas',
    ];

    /**
     * Hak akses bawaan role sistem Prodi (LingkupProdi::ROLE), sesuai matriks konsep Yapika. Datanya dibatasi ke
     * prodi akun (LingkupProdi); Tahun Akademik, Ruang, dan Predikat hanya bisa dilihat.
     */
    public const IZIN_PRODI = [
        'admin.dashboard',
        'admin.tahun-akademik', 'admin.mata-kuliah', 'admin.bobot-nilai', 'admin.predikat', 'admin.batas-sks', 'admin.ruang', 'admin.syarat-ujian', 'admin.kurikulum',
        'admin.penasehat-akademik', 'admin.kelas-kuliah', 'admin.jadwal', 'admin.ujian', 'admin.ketua-kelas',
        'admin.presensi', 'admin.rekap-presensi', 'admin.presensi-dosen', 'admin.verifikasi-presensi-dosen',
        'admin.input-krs', 'admin.cetak-kst', 'admin.kartu-ujian', 'admin.verifikasi-krs', 'admin.status-krs', 'admin.rekap-krs',
        'admin.nilai-semester', 'admin.detail-nilai', 'admin.pendataan-nilai', 'admin.validasi-nilai',
        'admin.khs', 'admin.transkrip-nilai',
    ];

    public static function fiturAktif(string $key): bool
    {
        return ! isset(self::FITUR[$key]) || Feature::aktif(self::FITUR[$key]);
    }

    /**
     * Permission yang fiturnya sedang mati.
     *
     * @return list<string>
     */
    public static function milikFiturMati(): array
    {
        return array_values(array_filter(array_keys(self::FITUR), fn (string $key): bool => ! self::fiturAktif($key)));
    }

    /**
     * @return list<array{key: string, name: string, group: string, user_type: ?UserType, description: string, defaults: list<UserType>}>
     */
    public static function definitions(): array
    {
        $admin = [UserType::Admin];

        return [
            ['key' => 'admin.dashboard', 'name' => 'Dashboard Admin', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Membuka dashboard admin.', 'defaults' => $admin],
            ['key' => 'admin.info-kuliah', 'name' => 'Informasi & Pengumuman', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola informasi & pengumuman untuk mahasiswa.', 'defaults' => $admin],
            ['key' => 'admin.tahun-akademik', 'name' => 'Tahun Akademik', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola tahun akademik dan periode KRS.', 'defaults' => $admin],
            ['key' => 'admin.fakultas', 'name' => 'Fakultas', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data fakultas.', 'defaults' => $admin],
            ['key' => 'admin.program-studi', 'name' => 'Program Studi', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data program studi.', 'defaults' => $admin],
            ['key' => 'admin.kurikulum', 'name' => 'Kurikulum', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola kurikulum program studi beserta mata kuliah, semester, dan sifat wajib/pilihannya.', 'defaults' => $admin],
            ['key' => 'admin.mata-kuliah', 'name' => 'Mata Kuliah', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data mata kuliah.', 'defaults' => $admin],
            ['key' => 'admin.bobot-nilai', 'name' => 'Bobot Nilai', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengatur bobot nilai huruf per program studi.', 'defaults' => $admin],
            ['key' => 'admin.prasyarat', 'name' => 'Mata Kuliah Prasyarat', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola pasangan mata kuliah dan mata kuliah prasyaratnya.', 'defaults' => $admin],
            ['key' => 'admin.predikat', 'name' => 'Predikat', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengatur predikat kelulusan per rentang IPK.', 'defaults' => $admin],
            ['key' => 'admin.batas-sks', 'name' => 'Batas SKS per Semester', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengatur batas SKS per semester tiap program studi.', 'defaults' => $admin],
            ['key' => 'admin.syarat-ujian', 'name' => 'Syarat Ujian & Remedial', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengatur syarat kehadiran ujian UTS/UAS dan huruf maksimal setelah remidi, umum atau per program studi.', 'defaults' => $admin],
            ['key' => 'admin.penasehat-akademik', 'name' => 'Set Penasehat Akademik', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengisi dosen wali (pembimbing akademik) mahasiswa aktif per rentang NIM.', 'defaults' => $admin],
            ['key' => 'admin.ketua-kelas', 'name' => 'Ketua Kelas', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Menetapkan ketua kelas (mahasiswa peserta) di tiap kelas kuliah.', 'defaults' => $admin],
            ['key' => 'admin.ruang', 'name' => 'Ruang', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data ruang.', 'defaults' => $admin],
            ['key' => 'admin.agama', 'name' => 'Agama', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data agama.', 'defaults' => $admin],
            ['key' => 'admin.provinsi', 'name' => 'Provinsi', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data provinsi.', 'defaults' => $admin],
            ['key' => 'admin.kota', 'name' => 'Kota/Kabupaten', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data kota/kabupaten per provinsi.', 'defaults' => $admin],
            ['key' => 'admin.periode-pmb', 'name' => 'Periode PMB', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengatur periode penerimaan mahasiswa baru (PMB).', 'defaults' => $admin],
            ['key' => 'admin.informasi-pmb', 'name' => 'Informasi PMB', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengubah isi halaman publik Informasi PMB (syarat, jadwal tes, biaya, kontak).', 'defaults' => $admin],
            ['key' => 'admin.pendaftar-pmb', 'name' => 'Calon Maba (PMB)', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Melihat calon mahasiswa baru, mengisi nilai dan status kelulusannya, serta menyalin yang lulus ke Data Mahasiswa (perlu juga izin Data Mahasiswa).', 'defaults' => $admin],
            ['key' => 'admin.badan-hukum', 'name' => 'Badan Hukum', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola data badan hukum penyelenggara perguruan tinggi.', 'defaults' => $admin],
            ['key' => 'admin.kelas-kuliah', 'name' => 'Kelas Kuliah', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola kelas kuliah beserta jadwal, materi, tugas, quiz, dan nilai.', 'defaults' => $admin],
            ['key' => 'admin.jadwal', 'name' => 'Jadwal Kelas', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola jadwal seluruh kelas kuliah.', 'defaults' => $admin],
            ['key' => 'admin.materi', 'name' => 'Materi', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola materi seluruh kelas kuliah.', 'defaults' => $admin],
            ['key' => 'admin.tugas', 'name' => 'Tugas', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola tugas seluruh kelas kuliah.', 'defaults' => $admin],
            ['key' => 'admin.quiz', 'name' => 'Quiz', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola quiz seluruh kelas kuliah.', 'defaults' => $admin],
            ['key' => 'admin.presensi', 'name' => 'Presensi', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengelola pertemuan, presensi dosen, dan presensi mahasiswa seluruh kelas kuliah.', 'defaults' => $admin],
            ['key' => 'admin.rekap-presensi', 'name' => 'Rekap Presensi Mahasiswa', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Melihat dan mengunduh rekap kehadiran mahasiswa per mata kuliah dalam satu tahun akademik.', 'defaults' => $admin],
            ['key' => 'admin.presensi-dosen', 'name' => 'Presensi Dosen', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Melihat dan mengoreksi presensi dosen per pertemuan, rekap kehadiran dosen, dan mencetak BAP.', 'defaults' => $admin],
            ['key' => 'admin.verifikasi-presensi-dosen', 'name' => 'Verifikasi Presensi Dosen', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Menyetujui, menolak, atau membatalkan verifikasi presensi dosen per pertemuan.', 'defaults' => $admin],
            ['key' => 'admin.ujian', 'name' => 'Jadwal Ujian', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Menyusun, menerbitkan, dan mengatur mode jadwal UTS/UAS seluruh kelas.', 'defaults' => $admin],
            ['key' => 'admin.pindah-kelas', 'name' => 'Pindah Kelas', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Memproses pengajuan dan pengaturan pindah kelas.', 'defaults' => $admin],
            ['key' => 'admin.pengajuan-akademik', 'name' => 'Pengajuan & Pendaftaran', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Memproses pengajuan tugas akhir, pendadaran, KKM, PPL, ujian komprehensif, dan wisuda.', 'defaults' => $admin],
            ['key' => 'admin.verifikasi-krs', 'name' => 'Verifikasi KRS', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Menyetujui KRS mahasiswa atau mengembalikannya untuk revisi.', 'defaults' => $admin],
            ['key' => 'admin.input-krs', 'name' => 'Input KRS', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengisi KRS atas nama mahasiswa (menambah/mengeluarkan kelas, menyimpan, menyetujui) di luar periode KRS.', 'defaults' => $admin],
            ['key' => 'admin.status-krs', 'name' => 'Status KRS', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengubah status KRS mahasiswa menjadi Ya (disetujui & terkunci) atau Tidak (dibuka untuk diubah).', 'defaults' => $admin],
            ['key' => 'admin.cetak-kst', 'name' => 'Cetak KST', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mencetak Kartu Studi Tetap (KRS yang disetujui) per mahasiswa.', 'defaults' => $admin],
            ['key' => 'admin.kartu-ujian', 'name' => 'Cetak Kartu Ujian', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mencetak kartu UTS/UAS per mahasiswa dari KRS yang disetujui.', 'defaults' => $admin],
            ['key' => 'admin.rekap-krs', 'name' => 'Rekap KRS', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Melihat dan mengunduh rekap KRS per kelas dan per mahasiswa.', 'defaults' => $admin],
            ['key' => 'admin.nilai-semester', 'name' => 'Nilai Semester', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengisi angka komponen nilai mahasiswa per kelas; nilai akhir dan huruf dihitung otomatis.', 'defaults' => $admin],
            ['key' => 'admin.detail-nilai', 'name' => 'Detail Nilai', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Melihat nilai tiap mahasiswa per mata kuliah (angka per komponen, nilai akhir, huruf, status validasi) dalam satu tahun akademik.', 'defaults' => $admin],
            ['key' => 'admin.validasi-nilai', 'name' => 'Validasi Nilai', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Menu Validasi Nilai: memvalidasi atau membatalkan validasi nilai akhir mahasiswa per mata kuliah; nilai tervalidasi terkunci untuk dosen dan admin.', 'defaults' => $admin],
            ['key' => 'admin.pendataan-nilai', 'name' => 'Pendataan Nilai Akhir', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Melihat rekap konversi nilai akhir angka ke huruf dan status validasinya per mata kuliah yang pernah diambil mahasiswa.', 'defaults' => $admin],
            ['key' => 'admin.nilai-kkm', 'name' => 'Nilai KKM', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengisi nilai KKM/PKL/KKN mahasiswa yang pengajuan Kuliah Kerja Mahasiswa-nya disetujui; huruf dihitung dari bobot nilai prodi.', 'defaults' => $admin],
            ['key' => 'admin.komponen-nilai', 'name' => 'Komponen Nilai', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengatur komponen nilai dan persen bobotnya (berjumlah 100%) untuk menghitung nilai akhir.', 'defaults' => $admin],
            ['key' => 'admin.khs', 'name' => 'KHS', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Melihat dan mengunduh Kartu Hasil Studi mahasiswa per tahun akademik.', 'defaults' => $admin],
            ['key' => 'admin.transkrip-nilai', 'name' => 'Transkrip Nilai', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Melihat dan mengunduh transkrip nilai mahasiswa.', 'defaults' => $admin],
            ['key' => 'admin.pengajuan-cuti', 'name' => 'Pengajuan Cuti', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Memproses pengajuan cuti dan aktif kembali mahasiswa.', 'defaults' => $admin],
            ['key' => 'admin.jenis-biaya', 'name' => 'Jenis Biaya', 'group' => 'Keuangan', 'user_type' => null, 'description' => 'Mengelola jenis biaya kuliah dan tarifnya per prodi/angkatan.', 'defaults' => $admin],
            ['key' => 'admin.tagihan', 'name' => 'Tagihan Mahasiswa', 'group' => 'Keuangan', 'user_type' => null, 'description' => 'Menerbitkan tagihan semester dan mengubah status pembayaran mahasiswa.', 'defaults' => $admin],
            ['key' => 'admin.users.dosen', 'name' => 'Manage User Dosen', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Mengelola akun dan profil dosen.', 'defaults' => $admin],
            ['key' => 'admin.users.mahasiswa', 'name' => 'Manage User Mahasiswa', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Mengelola akun dan profil mahasiswa.', 'defaults' => $admin],
            ['key' => 'admin.users.karyawan', 'name' => 'Manage User Karyawan', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Mengelola akun dan profil karyawan.', 'defaults' => $admin],
            ['key' => 'admin.log-aktivitas', 'name' => 'Log Aktivitas', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Melihat jejak data yang dibuat, diubah, atau dihapus pengguna serta riwayat masuk/keluar.', 'defaults' => $admin],
            ['key' => 'admin.roles', 'name' => 'Kelola Role', 'group' => 'Manajemen Pengguna', 'user_type' => null, 'description' => 'Menambah, mengubah, menghapus role dan mengatur hak aksesnya.', 'defaults' => $admin],
            ['key' => 'admin.institusi', 'name' => 'Perguruan Tinggi', 'group' => 'Administrasi', 'user_type' => null, 'description' => 'Mengubah identitas, logo, dan data legal perguruan tinggi.', 'defaults' => $admin],
            ['key' => 'admin.pengaturan-email', 'name' => 'Pengaturan Email', 'group' => 'Pengaturan Sistem', 'user_type' => null, 'description' => 'Mengatur pengiriman surel (SMTP) dan mengirim surel uji.', 'defaults' => $admin],
            ['key' => 'admin.pengaturan-akademik', 'name' => 'Pengaturan Akademik', 'group' => 'Pengaturan Sistem', 'user_type' => null, 'description' => 'Mengatur KRS, batas SKS, skala nilai, presensi, dan form pindah kelas.', 'defaults' => $admin],
            ['key' => 'admin.pengaturan-tampilan', 'name' => 'Pengaturan Tampilan', 'group' => 'Pengaturan Sistem', 'user_type' => null, 'description' => 'Mengatur nama di tab browser, favicon, halaman masuk, dan sidebar bawaan.', 'defaults' => $admin],
            ['key' => 'admin.pengaturan-recaptcha', 'name' => 'Pengaturan reCAPTCHA', 'group' => 'Pengaturan Sistem', 'user_type' => null, 'description' => 'Mengatur Google reCAPTCHA v2 (site key & secret key) di halaman masuk.', 'defaults' => $admin],
            ['key' => 'admin.pengaturan-maintenance', 'name' => 'Pengaturan Maintenance', 'group' => 'Pengaturan Sistem', 'user_type' => null, 'description' => 'Menyalakan mode maintenance untuk dosen/mahasiswa. Pemegang izin ini tetap bisa masuk saat maintenance.', 'defaults' => $admin],

            ['key' => 'dosen.dashboard', 'name' => 'Beranda Dosen', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Membuka beranda dan profil dosen.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.kelas-kuliah', 'name' => 'Kelas Kuliah Dosen', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Mengelola kelas yang diampu beserta materi, tugas, quiz, dan nilai.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.jadwal', 'name' => 'Jadwal Mengajar', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Melihat jadwal mengajar dari kelas yang diampu.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.materi', 'name' => 'Materi Dosen', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Mengelola materi dari kelas yang diampu.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.tugas', 'name' => 'Tugas Dosen', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Mengelola tugas dari kelas yang diampu.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.quiz', 'name' => 'Quiz Dosen', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Mengelola quiz dari kelas yang diampu.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.presensi', 'name' => 'Presensi Dosen', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Membuka pertemuan, mengisi jurnal, dan mencatat presensi mahasiswa di kelas yang diampu.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.ujian', 'name' => 'Ujian Dosen', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Melihat jadwal ujian kelas yang diampu, menyiapkan soal, dan menilai jawaban.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.mahasiswa-kelas', 'name' => 'Mahasiswa Kelas', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Melihat daftar mahasiswa di kelas yang diampu.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.bimbingan', 'name' => 'Bimbingan & Pendadaran', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Melihat mahasiswa bimbingan tugas akhir dan jadwal pendadaran.', 'defaults' => [UserType::Dosen]],
            ['key' => 'dosen.pengajuan-pa', 'name' => 'Pengajuan Mahasiswa PA', 'group' => 'Dosen', 'user_type' => UserType::Dosen, 'description' => 'Melihat (tanpa memproses) pengajuan mahasiswa bimbingan akademiknya.', 'defaults' => [UserType::Dosen]],

            ['key' => 'mahasiswa.dashboard', 'name' => 'Beranda Mahasiswa', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Membuka beranda, profil, dan halaman informasi mahasiswa.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.info-kuliah', 'name' => 'Informasi & Pengumuman Mahasiswa', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Melihat informasi & pengumuman.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.krs', 'name' => 'Rencana Studi (KRS)', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Mengisi kartu rencana studi.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.hasil-studi', 'name' => 'Hasil Studi & Transkrip', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Melihat dan mengunduh KHS serta transkrip nilai.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.jadwal-kuliah', 'name' => 'Jadwal & Kelas Kuliah', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Melihat jadwal, materi, serta mengerjakan tugas dan quiz.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.presensi', 'name' => 'Presensi Mahasiswa', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Presensi mandiri lewat QR/PIN dan melihat riwayat kehadiran.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.ujian', 'name' => 'Ujian Mahasiswa', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Melihat jadwal ujian, mencetak kartu ujian, dan mengerjakan ujian online.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.pindah-kelas', 'name' => 'Pengajuan Pindah Kelas', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Mengajukan pindah kelas.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.tugas-akhir', 'name' => 'Tugas Akhir & Wisuda', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Mengajukan tugas akhir, mendaftar pendadaran, dan mendaftar wisuda.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.pengajuan-kegiatan', 'name' => 'Pengajuan KKM/PKL/KKN, PPL & Kompre', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Mengajukan judul Kuliah Kerja Mahasiswa (KKM/PKL/KKN), Praktek Pengalaman Lapangan, dan Ujian Komprehensif.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.pengajuan-cuti', 'name' => 'Pengajuan Cuti', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Mengajukan cuti dan aktif kembali.', 'defaults' => [UserType::Mahasiswa]],
            ['key' => 'mahasiswa.info-biaya', 'name' => 'Info Biaya Kuliah', 'group' => 'Mahasiswa', 'user_type' => UserType::Mahasiswa, 'description' => 'Melihat tagihan dan status pembayaran kuliah.', 'defaults' => [UserType::Mahasiswa]],
        ];
    }

    /**
     * Sinkronkan tabel permissions dan role sistem.
     *
     * Role sistem baru mendapat seluruh hak akses bawaannya. Untuk role sistem yang sudah ada,
     * hanya permission yang baru ditambahkan ke katalog yang diberikan, sehingga pengaturan
     * yang sudah diubah Admin tidak ditimpa.
     */
    public static function sync(): void
    {
        DB::transaction(function (): void {
            $definitions = self::definitions();
            $newKeys = [];

            foreach ($definitions as $order => $definition) {
                $permission = Permission::query()->updateOrCreate(['key' => $definition['key']], [
                    'name' => $definition['name'],
                    'group' => $definition['group'],
                    'user_type' => $definition['user_type'],
                    'description' => $definition['description'],
                    'sort_order' => $order + 1,
                ]);

                if ($permission->wasRecentlyCreated) {
                    $newKeys[] = $definition['key'];
                }
            }

            foreach (UserType::cases() as $type) {
                $role = Role::query()->firstOrCreate(['slug' => $type->value], [
                    'name' => Str::headline($type->value),
                    'user_type' => $type,
                    'description' => 'Role bawaan untuk '.$type->label().'.',
                    'is_system' => true,
                ]);

                $defaultKeys = collect($definitions)
                    ->filter(fn (array $definition): bool => in_array($type, $definition['defaults'], true))
                    ->pluck('key')
                    ->when(! $role->wasRecentlyCreated, fn ($keys) => $keys->intersect($newKeys));

                if ($defaultKeys->isNotEmpty()) {
                    $role->permissions()->syncWithoutDetaching(Permission::query()->whereIn('key', $defaultKeys)->pluck('id'));
                }
            }

            // Role sistem Prodi: akun karyawan yang datanya dibatasi ke satu program studi.
            $prodi = Role::query()->firstOrCreate(['slug' => LingkupProdi::ROLE], [
                'name' => 'Prodi',
                'user_type' => UserType::Admin,
                'description' => 'Role bawaan untuk akun Program Studi: hanya melihat dan mengubah data prodinya sendiri.',
                'is_system' => true,
            ]);
            $izinProdi = collect(self::IZIN_PRODI)->when(! $prodi->wasRecentlyCreated, fn ($keys) => $keys->intersect($newKeys));
            if ($izinProdi->isNotEmpty()) {
                $prodi->permissions()->syncWithoutDetaching(Permission::query()->whereIn('key', $izinProdi)->pluck('id'));
            }
        });
    }
}
