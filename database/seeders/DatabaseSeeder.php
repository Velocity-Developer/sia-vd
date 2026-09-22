<?php

namespace Database\Seeders;

use App\Models\Fakultas;
use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MataKuliah;
use App\Models\Materi;
use App\Models\PengaturanInstitusi;
use App\Models\PengumpulanTugas;
use App\Models\ProgramStudi;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Role;
use App\Models\Ruang;
use App\Models\TahunAkademik;
use App\Models\Tugas;
use App\Models\User;
use App\PermissionCatalog;
use App\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        PermissionCatalog::sync();
        $adminRoleId = Role::system(UserType::Admin)->id;
        $dosenRoleId = Role::system(UserType::Dosen)->id;
        $mahasiswaRoleId = Role::system(UserType::Mahasiswa)->id;

        PengaturanInstitusi::updateOrCreate(
            ['id' => PengaturanInstitusi::SINGLETON_ID],
            ['nama_pt' => 'SIA VD', 'singkatan' => 'SIA VD'],
        );

        QuizAnswer::query()->delete();
        QuizAttempt::query()->delete();
        PengumpulanTugas::query()->delete();
        Krs::query()->delete();
        Question::query()->delete();
        Quiz::query()->delete();
        Tugas::query()->delete();
        Materi::query()->delete();
        Jadwal::query()->delete();
        KelasKuliah::query()->delete();
        TahunAkademik::query()->delete();

        $kota = ['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Malang', 'Bogor', 'Depok', 'Tangerang', 'Makassar'];
        $agama = ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu'];
        $jenisKelamin = ['Laki-laki', 'Perempuan'];
        $sekolah = ['SMA Negeri 1 Bandung', 'SMA Negeri 3 Jakarta', 'SMK Negeri 2 Yogyakarta', 'SMA Negeri 5 Surabaya'];
        $pendidikan = ['SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3'];
        $pekerjaan = ['Tidak Bekerja', 'Karyawan Swasta', 'Pegawai Negeri Sipil (PNS)', 'TNI / Polri', 'Wiraswasta / Pengusaha', 'Profesional', 'Guru / Dosen', 'Tenaga Kesehatan', 'Petani', 'Peternak', 'Nelayan', 'Pedagang', 'Ibu Rumah Tangga', 'Freelancer', 'Pensiunan', 'Sudah Meninggal', 'Lainnya'];
        $penghasilan = ['Kurang dari Rp1.000.000', 'Rp1.000.000 – Rp2.999.999', 'Rp3.000.000 – Rp4.999.999', 'Rp5.000.000 – Rp7.499.999', 'Rp7.500.000 – Rp9.999.999', 'Rp10.000.000 – Rp14.999.999', 'Rp15.000.000 atau lebih', 'Tidak Berpenghasilan'];

        $admin = User::updateOrCreate(['username' => 'admin'], [
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => Hash::make('11111'),
            'role_id' => $adminRoleId,
        ]);
        $admin->adminProfile()->updateOrCreate([], [
            'nomor_induk' => 'A'.fake()->unique()->numerify('#####'), 'tempat_lahir' => 'Jakarta', 'tanggal_lahir' => '1985-01-10', 'jenis_kelamin' => 'Laki-laki',
            'agama' => 'Islam', 'no_telepon' => '081234567890', 'alamat' => 'Jl. Merdeka No. 1, Jakarta', 'kewarganegaraan' => 'Indonesia',
        ]);

        $dosenProfiles = [];

        $dosen = User::updateOrCreate(['username' => '22222'], ['name' => 'Budi Santoso', 'email' => '22222@example.com', 'password' => Hash::make('22222'), 'role_id' => $dosenRoleId]);
        $dosenProfiles[] = $dosen->dosenProfile()->updateOrCreate([], ['nidn' => 'D22222', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1980-02-22', 'jenis_kelamin' => 'Laki-laki', 'agama' => 'Islam', 'no_telepon' => '081222222222', 'alamat' => 'Jl. Dosen No. 22, Bandung', 'kewarganegaraan' => 'Indonesia', 'jabatan_fungsional' => 'Lektor', 'pendidikan_terakhir' => 'S3', 'status_kepegawaian' => 'Tetap']);

        for ($index = 1; $index <= 50; $index++) {
            $username = 'dosen'.$index;
            $user = User::updateOrCreate(['username' => $username], ['name' => ['Andi', 'Siti', 'Rizky', 'Dewi', 'Agus'][$index % 5].' '.['Pratama', 'Lestari', 'Wijaya', 'Permata', 'Hidayat'][$index % 5], 'email' => $username.'@example.com', 'password' => Hash::make($username), 'role_id' => $dosenRoleId]);
            $dosenProfiles[] = $user->dosenProfile()->updateOrCreate([], ['nidn' => 'D'.str_pad((string) $index, 5, '0', STR_PAD_LEFT), 'tempat_lahir' => $kota[$index % count($kota)], 'tanggal_lahir' => '198'.($index % 10).'-'.str_pad((string) (($index - 1) % 12 + 1), 2, '0', STR_PAD_LEFT).'-15', 'jenis_kelamin' => $jenisKelamin[$index % 2], 'agama' => $agama[$index % count($agama)], 'no_telepon' => '0812'.str_pad((string) $index, 8, '0', STR_PAD_LEFT), 'alamat' => 'Jl. Pendidikan No. '.$index.', '.$kota[$index % count($kota)], 'kewarganegaraan' => 'Indonesia', 'jabatan_fungsional' => 'Asisten Ahli', 'pendidikan_terakhir' => $index % 2 ? 'S2' : 'S3', 'status_kepegawaian' => 'Tetap']);
        }

        $fakultasData = [
            ['kode_fakultas' => 'FTI', 'nama_fakultas' => 'Fakultas Teknologi Informasi', 'dekan' => 0, 'tanggal_berdiri' => '2001-08-17', 'no_telp' => '021-5551001', 'email' => 'fti@example.ac.id', 'program_studis' => [
                ['kode_prodi' => 'TI-S1', 'nama_prodi' => 'Teknik Informatika', 'jenjang' => 'S1', 'status_akreditasi' => 'Unggul', 'no_sk_akreditasi' => '123/SK/BAN-PT/2022', 'tanggal_akreditasi_mulai' => '2022-06-01', 'tanggal_akreditasi_akhir' => '2027-06-01', 'kaprodi' => 1, 'tahun_berdiri' => 2001],
                ['kode_prodi' => 'SI-S1', 'nama_prodi' => 'Sistem Informasi', 'jenjang' => 'S1', 'status_akreditasi' => 'Baik Sekali', 'no_sk_akreditasi' => '124/SK/BAN-PT/2023', 'tanggal_akreditasi_mulai' => '2023-07-01', 'tanggal_akreditasi_akhir' => '2028-07-01', 'kaprodi' => 2, 'tahun_berdiri' => 2003],
            ]],
            ['kode_fakultas' => 'FEB', 'nama_fakultas' => 'Fakultas Ekonomi dan Bisnis', 'dekan' => 3, 'tanggal_berdiri' => '1998-03-20', 'no_telp' => '021-5551002', 'email' => 'feb@example.ac.id', 'program_studis' => [
                ['kode_prodi' => 'MNJ-S1', 'nama_prodi' => 'Manajemen', 'jenjang' => 'S1', 'status_akreditasi' => 'Unggul', 'no_sk_akreditasi' => '125/SK/BAN-PT/2022', 'tanggal_akreditasi_mulai' => '2022-08-01', 'tanggal_akreditasi_akhir' => '2027-08-01', 'kaprodi' => 4, 'tahun_berdiri' => 1998],
                ['kode_prodi' => 'AK-S1', 'nama_prodi' => 'Akuntansi', 'jenjang' => 'S1', 'status_akreditasi' => 'Baik Sekali', 'no_sk_akreditasi' => '126/SK/BAN-PT/2023', 'tanggal_akreditasi_mulai' => '2023-09-01', 'tanggal_akreditasi_akhir' => '2028-09-01', 'kaprodi' => 5, 'tahun_berdiri' => 2000],
            ]],
        ];

        foreach ($fakultasData as $fakultasItem) {
            $programStudis = $fakultasItem['program_studis'];
            unset($fakultasItem['program_studis']);
            $dekanIndex = $fakultasItem['dekan'];
            unset($fakultasItem['dekan']);
            $fakultas = Fakultas::updateOrCreate(['kode_fakultas' => $fakultasItem['kode_fakultas']], [...$fakultasItem, 'dekan_id' => $dosenProfiles[$dekanIndex]->id]);

            foreach ($programStudis as $programStudi) {
                $kaprodiIndex = $programStudi['kaprodi'];
                unset($programStudi['kaprodi']);
                ProgramStudi::updateOrCreate(['kode_prodi' => $programStudi['kode_prodi']], [...$programStudi, 'fakultas_id' => $fakultas->id, 'kaprodi' => $dosenProfiles[$kaprodiIndex]->id]);
            }
        }

        $programStudiIds = ProgramStudi::pluck('id')->all();

        foreach ($dosenProfiles as $index => $profile) {
            $profile->update(['prodi_id' => $programStudiIds[$index % count($programStudiIds)]]);
        }

        $mahasiswa = User::updateOrCreate(['username' => '33333'], ['name' => 'Citra Maharani', 'email' => '33333@example.com', 'password' => Hash::make('33333'), 'role_id' => $mahasiswaRoleId]);
        $mahasiswa->mahasiswaProfile()->updateOrCreate([], ['nim' => '33333', 'angkatan' => 2023, 'semester' => 6, 'status' => 'Aktif', 'tempat_lahir' => 'Jakarta', 'tanggal_lahir' => '2003-03-03', 'jenis_kelamin' => 'Perempuan', 'agama' => 'Islam', 'no_telepon' => '081333333333', 'alamat' => 'Jl. Mahasiswa No. 33, Jakarta', 'kewarganegaraan' => 'Indonesia', 'dosen_wali_id' => $dosenProfiles[0]->id, 'prodi_id' => $programStudiIds[0], 'sekolah_asal' => $sekolah[0], 'nisn' => '0033333333', 'email_alternatif' => 'citra@gmail.com', 'nama_ayah_kandung' => 'Hendra Maharani', 'nama_ibu_kandung' => 'Lina Maharani', 'tanggal_lahir_ayah' => '1975-05-20', 'tanggal_lahir_ibu' => '1978-08-15', 'pendidikan_terakhir_ayah' => 'S1', 'pendidikan_terakhir_ibu' => 'S1', 'pekerjaan_ayah' => 'Pegawai Negeri Sipil (PNS)', 'pekerjaan_ibu' => 'Guru / Dosen', 'penghasilan_ayah' => 'Rp5.000.000 – Rp7.499.999', 'penghasilan_ibu' => 'Rp3.000.000 – Rp4.999.999', 'no_telepon_ayah' => '081333333334', 'no_telepon_ibu' => '081333333335', 'email_ayah' => 'hendra.maharani@example.com', 'email_ibu' => 'lina.maharani@example.com', 'alamat_ayah' => 'Jl. Melati No. 10, Jakarta', 'alamat_ibu' => 'Jl. Melati No. 10, Jakarta']);

        for ($index = 1; $index <= 100; $index++) {
            $username = 'mahasiswa'.$index;
            $user = User::updateOrCreate(['username' => $username], ['name' => ['Fajar', 'Nabila', 'Dimas', 'Putri', 'Bagas'][$index % 5].' '.['Saputra', 'Anggraini', 'Kurniawan', 'Salsabila', 'Ramadhan'][$index % 5], 'email' => $username.'@example.com', 'password' => Hash::make($username), 'role_id' => $mahasiswaRoleId]);
            $user->mahasiswaProfile()->updateOrCreate([], ['nim' => 'M'.str_pad((string) $index, 5, '0', STR_PAD_LEFT), 'angkatan' => 2022 + ($index % 3), 'semester' => 2 + ($index % 8), 'status' => 'Aktif', 'tempat_lahir' => $kota[$index % count($kota)], 'tanggal_lahir' => '200'.($index % 6).'-'.str_pad((string) (($index - 1) % 12 + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) (($index - 1) % 25 + 1), 2, '0', STR_PAD_LEFT), 'jenis_kelamin' => $jenisKelamin[$index % 2], 'agama' => $agama[$index % count($agama)], 'no_telepon' => '0821'.str_pad((string) $index, 8, '0', STR_PAD_LEFT), 'alamat' => 'Jl. Pelajar No. '.$index.', '.$kota[$index % count($kota)], 'kewarganegaraan' => 'Indonesia', 'dosen_wali_id' => $dosenProfiles[$index % count($dosenProfiles)]->id, 'prodi_id' => $programStudiIds[$index % count($programStudiIds)], 'sekolah_asal' => $sekolah[$index % count($sekolah)], 'nisn' => '00'.str_pad((string) $index, 8, '0', STR_PAD_LEFT), 'email_alternatif' => $username.'@mail.com', 'nama_ayah_kandung' => 'Joko '.$user->name, 'nama_ibu_kandung' => 'Sari '.$user->name, 'tanggal_lahir_ayah' => '197'.($index % 10).'-'.str_pad((string) (($index - 1) % 12 + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) (($index - 1) % 25 + 1), 2, '0', STR_PAD_LEFT), 'tanggal_lahir_ibu' => '197'.(($index + 3) % 10).'-'.str_pad((string) (($index - 1) % 12 + 1), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) (($index - 1) % 25 + 1), 2, '0', STR_PAD_LEFT), 'pendidikan_terakhir_ayah' => $pendidikan[$index % count($pendidikan)], 'pendidikan_terakhir_ibu' => $pendidikan[($index + 1) % count($pendidikan)], 'pekerjaan_ayah' => $pekerjaan[$index % count($pekerjaan)], 'pekerjaan_ibu' => $pekerjaan[($index + 1) % count($pekerjaan)], 'penghasilan_ayah' => $penghasilan[$index % count($penghasilan)], 'penghasilan_ibu' => $penghasilan[($index + 1) % count($penghasilan)], 'no_telepon_ayah' => '0813'.str_pad((string) $index, 8, '0', STR_PAD_LEFT), 'no_telepon_ibu' => '0814'.str_pad((string) $index, 8, '0', STR_PAD_LEFT), 'email_ayah' => 'ayah'.$index.'@example.com', 'email_ibu' => 'ibu'.$index.'@example.com', 'alamat_ayah' => 'Jl. Keluarga No. '.$index.', '.$kota[$index % count($kota)], 'alamat_ibu' => 'Jl. Keluarga No. '.$index.', '.$kota[$index % count($kota)]]);
        }

        // Mata kuliah per prodi
        $mataKuliahSeed = [
            'TI-S1' => [
                ['IF101', 'Algoritma dan Pemrograman', 3, 1, 'Wajib'], ['IF102', 'Matematika Diskrit', 3, 1, 'Wajib'], ['IF201', 'Struktur Data', 3, 2, 'Wajib'], ['IF202', 'Basis Data', 3, 2, 'Wajib'], ['IF301', 'Pemrograman Web', 3, 3, 'Wajib'], ['IF302', 'Jaringan Komputer', 3, 3, 'Wajib'], ['IF401', 'Kecerdasan Buatan', 3, 5, 'Pilihan'],
                ['IF402', 'Keamanan Siber', 3, 6, 'Pilihan'],
                ['IF403', 'Pemrograman Mobile', 3, 6, 'Pilihan'],
                ['IF404', 'Cloud Computing', 3, 6, 'Pilihan'],
                ['IF405', 'Analisis Data', 3, 6, 'Pilihan'],
            ],
            'SI-S1' => [
                ['SI101', 'Pengantar Sistem Informasi', 3, 1, 'Wajib'], ['SI102', 'Algoritma dan Pemrograman', 3, 1, 'Wajib'], ['SI201', 'Analisis dan Perancangan Sistem', 3, 2, 'Wajib'], ['SI202', 'Basis Data Lanjut', 3, 3, 'Wajib'], ['SI301', 'Manajemen Proyek TI', 3, 4, 'Wajib'], ['SI302', 'E-Bisnis', 3, 5, 'Pilihan'],
            ],
            'MNJ-S1' => [
                ['MN101', 'Pengantar Manajemen', 3, 1, 'Wajib'], ['MN102', 'Ekonomi Mikro', 3, 1, 'Wajib'], ['MN201', 'Manajemen Pemasaran', 3, 3, 'Wajib'], ['MN202', 'Manajemen Keuangan', 3, 4, 'Wajib'], ['MN301', 'Kewirausahaan', 2, 5, 'Pilihan'],
            ],
            'AK-S1' => [
                ['AK101', 'Pengantar Akuntansi', 3, 1, 'Wajib'], ['AK102', 'Matematika Ekonomi', 3, 1, 'Wajib'], ['AK201', 'Akuntansi Keuangan Menengah', 3, 3, 'Wajib'], ['AK202', 'Pajak dan Perpajakan', 3, 4, 'Wajib'], ['AK301', 'Audit Internal', 2, 6, 'Pilihan'],
            ],
        ];

        $mataKuliahIds = [];
        foreach ($mataKuliahSeed as $kodeProdi => $items) {
            $prodi = ProgramStudi::where('kode_prodi', $kodeProdi)->first();
            if (! $prodi) {
                continue;
            }

            foreach ($items as [$kode, $nama, $sks, $semester, $jenis]) {
                $mk = MataKuliah::updateOrCreate(['kode_matkul' => $kode], ['nama_matkul' => $nama, 'sks' => $sks, 'semester' => $semester, 'jenis' => $jenis, 'prodi_id' => $prodi->id]);
                $mataKuliahIds[] = $mk->id;
            }
        }

        // Ruang
        $ruangSeed = [
            ['R101', 'Ruang Kuliah 101', 40, 'Gedung A Lt.1 - Proyektor & AC'],
            ['R102', 'Ruang Kuliah 102', 40, 'Gedung A Lt.1 - Proyektor & AC'],
            ['R201', 'Ruang Kuliah 201', 60, 'Gedung A Lt.2 - Proyektor & AC'],
            ['LAB1', 'Lab Komputer 1', 30, 'Gedung B Lt.1 - 30 PC'],
            ['LAB2', 'Lab Komputer 2', 30, 'Gedung B Lt.2 - 30 PC'],
            ['AUD', 'Aula Utama', 200, 'Gedung C - Sound & Multimedia'],
        ];

        $ruangIds = [];
        foreach ($ruangSeed as [$kode, $nama, $kapasitas, $detail]) {
            $ruang = Ruang::updateOrCreate(['kode_ruang' => $kode], ['nama_ruang' => $nama, 'kapasitas' => $kapasitas, 'detail' => $detail]);
            $ruangIds[] = $ruang->id;
        }

        // Kelas Kuliah — kode_kelas unik per matkul (A/B/C), round-robin dosen & tahun ajaran
        $tahunAkademik = [];
        foreach ([
            ['2023/2024', 'Ganjil', '2023-08-01', '2024-01-31'],
            ['2023/2024', 'Genap', '2024-02-01', '2024-07-31'],
            ['2024/2025', 'Ganjil', '2024-08-01', '2025-01-31'],
            ['2024/2025', 'Genap', '2025-02-01', '2025-07-31'],
            ['2025/2026', 'Ganjil', '2025-08-01', '2026-01-31'],
        ] as [$tahun, $semester, $tanggalMulai, $tanggalAkhir]) {
            $akademik = TahunAkademik::updateOrCreate(['tahun' => $tahun, 'semester' => $semester], ['tanggal_mulai' => $tanggalMulai, 'tanggal_akhir' => $tanggalAkhir, 'tanggal_krs_awal' => $tanggalMulai, 'tanggal_krs_akhir' => date('Y-m-d', strtotime($tanggalMulai.' +14 days')), 'status' => $tahun === '2025/2026' && $semester === 'Ganjil']);
            $tahunAkademik[] = $akademik->id;
        }

        foreach ($tahunAkademik as $periodeIndex => $tahunAkademikId) {
            foreach ($mataKuliahIds as $index => $matkulId) {
                $mk = MataKuliah::find($matkulId);
                for ($section = 0; $section < 3; $section++) {
                    $suffix = chr(65 + $section);
                    $tahunAkademik = TahunAkademik::find($tahunAkademikId);
                    $kodeTahun = substr($tahunAkademik->tahun, 2, 2).substr($tahunAkademik->tahun, 7, 2);
                    $kodeKelas = ($mk?->kode_matkul ?? 'KK-'.$matkulId).'-'.$kodeTahun.'-'.$periodeIndex.$suffix;
                    KelasKuliah::create(['kode_kelas' => $kodeKelas, 'tahun_akademik_id' => $tahunAkademikId, 'kapasitas' => 30 + $section * 10, 'dosen_id' => $section === 0 ? $dosenProfiles[0]->id : $dosenProfiles[($index + $section) % count($dosenProfiles)]->id, 'matkul_id' => $matkulId]);
                }
            }
        }

        $kelasKonten = KelasKuliah::all();

        foreach ($kelasKonten as $kelas) {
            Materi::updateOrCreate(
                ['kelas_id' => $kelas->id, 'judul_materi' => 'Pengantar Materi Perkuliahan'],
                [
                    'pertemuan_ke' => 1,
                    'jenis' => 'Materi',
                    'file' => [],
                    'catatan' => 'Materi pembuka perkuliahan.',
                    'uploaded_by' => $dosen->id,
                ],
            );
            Materi::updateOrCreate(
                ['kelas_id' => $kelas->id, 'judul_materi' => 'Pengumuman Perkuliahan'],
                [
                    'pertemuan_ke' => 1,
                    'jenis' => 'Pengumuman',
                    'file' => [],
                    'catatan' => 'Perkuliahan dimulai sesuai jadwal.',
                    'uploaded_by' => $dosen->id,
                ],
            );

            $tugas = Tugas::updateOrCreate(
                ['kelas_id' => $kelas->id, 'judul_tugas' => 'Tugas Pertemuan 1'],
                [
                    'file' => [],
                    'tenggat_waktu' => '2025-09-15 23:59:00',
                    'catatan' => 'Kerjakan secara mandiri.',
                    'uploaded_by' => $dosen->id,
                ],
            );

            $quiz = Quiz::updateOrCreate(
                ['kelas_id' => $kelas->id, 'nama_quiz' => 'Quiz Materi Pertemuan 1'],
                [
                    'catatan' => 'Quiz pemahaman materi dasar.',
                    'waktu_pengerjaan' => 30,
                    'tenggat_waktu' => '2025-09-20 23:59:00',
                    'uploaded_by' => $dosen->id,
                ],
            );
            Question::updateOrCreate(
                ['quiz_id' => $quiz->id, 'question_text' => 'Apa tujuan utama mempelajari materi ini?'],
                [
                    'question_type' => 'single_choice',
                    'question_option' => [
                        ['text' => 'Memahami konsep dasar', 'is_correct' => true],
                        ['text' => 'Menghindari perkuliahan', 'is_correct' => false],
                        ['text' => 'Menghapus tugas', 'is_correct' => false],
                    ],
                    'points' => 10,
                ],
            );
        }

        $mahasiswaProfiles = User::ofType(UserType::Mahasiswa)->with('mahasiswaProfile')->get()->pluck('mahasiswaProfile')->filter()->values();
        $tahunDenganNilai = TahunAkademik::whereIn('tahun', ['2023/2024', '2024/2025'])
            ->whereIn('semester', ['Ganjil', 'Genap'])
            ->pluck('id')
            ->all();
        $nilaiHuruf = ['A', 'B', 'C', 'D', 'E'];

        foreach ($kelasKonten as $kelasIndex => $kelas) {
            $beriNilai = in_array($kelas->tahun_akademik_id, $tahunDenganNilai, true);

            foreach ($mahasiswaProfiles->slice(0, 3) as $studentIndex => $profile) {
                Krs::create(['mahasiswa_id' => $profile->id, 'kelas_id' => $kelas->id, 'nilai' => $beriNilai ? $nilaiHuruf[($kelasIndex + $studentIndex) % count($nilaiHuruf)] : null, 'status' => 'Aktif']);
            }
            $tugas = $kelas->tugas->first();
            if ($tugas) {
                PengumpulanTugas::create(['tugas_id' => $tugas->id, 'mahasiswa_id' => $mahasiswaProfiles[0]->id, 'file_jawaban' => [], 'nilai' => 85, 'submitted_at' => '2025-10-01 10:00:00']);
            }
            $quiz = $kelas->quizzes->first();
            $question = $quiz?->questions->first();
            if ($quiz && $question) {
                $attempt = QuizAttempt::create(['quiz_id' => $quiz->id, 'mahasiswa_id' => $mahasiswaProfiles[0]->id, 'started_at' => '2025-10-01 09:00:00', 'submitted_at' => '2025-10-01 09:20:00', 'score' => 10]);
                QuizAnswer::create(['attempt_id' => $attempt->id, 'question_id' => $question->id, 'answer' => ['text' => 'Memahami konsep dasar'], 'point' => 10]);
            }
        }

        // Jadwal — Senin..Jumat, jam 07:00/10:00/13:00/16:00, round-robin kelas & ruang
        $hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
        $slot = [['07:00:00', '09:30:00'], ['10:00:00', '12:30:00'], ['13:00:00', '15:30:00'], ['16:00:00', '18:30:00']];
        $kelasIds = KelasKuliah::pluck('id')->all();
        foreach ($kelasIds as $index => $kelasId) {
            [$mulai, $akhir] = $slot[$index % count($slot)];
            Jadwal::updateOrCreate(['kelas_id' => $kelasId], [
                'hari' => $hari[$index % count($hari)],
                'jam_mulai' => $mulai,
                'jam_akhir' => $akhir,
                'ruang_id' => $ruangIds[$index % count($ruangIds)],
            ]);
        }
    }
}
