<?php

namespace Database\Seeders;

use App\AllowedUpload;
use App\Models\DosenProfile;
use App\Models\Fakultas;
use App\Models\InfoKuliah;
use App\Models\Jadwal;
use App\Models\JenisBiaya;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\Materi;
use App\Models\PengajuanPindahKelas;
use App\Models\PengaturanAkademik;
use App\Models\PengaturanInstitusi;
use App\Models\PengaturanPindahKelas;
use App\Models\PengumpulanTugas;
use App\Models\ProgramStudi;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Role;
use App\Models\Ruang;
use App\Models\SkalaNilai;
use App\Models\TagihanItem;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use App\Models\TarifBiaya;
use App\Models\Tugas;
use App\Models\User;
use App\PermissionCatalog;
use App\UserType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Data demo yang mengikuti aturan sistem: satu tahun akademik aktif dengan periode KRS berjalan,
 * mahasiswa per angkatan mengambil paket mata kuliah semesternya (dibatasi SKS sesuai IPS semester
 * sebelumnya), jadwal tanpa bentrok ruang/dosen/mahasiswa, nilai memakai skala yang terdaftar, serta
 * quiz dan tugas yang tersimpan dalam format yang sama dengan hasil pemakaian aplikasi.
 *
 * MENGHAPUS seluruh data akademik lalu mengisinya ulang, jadi hanya untuk lingkungan pengembangan.
 * Akun yang sudah ada tidak diganti kata sandinya.
 */
class DemoSeeder extends Seeder
{
    /** Jumlah mahasiswa per angkatan pada tiap program studi. */
    private const MAHASISWA_PER_ANGKATAN = 5;

    /** Mata kuliah yang ditawarkan per semester pada tiap program studi. */
    private const MATKUL_PER_SEMESTER = 4;

    private const KOTA = ['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Malang', 'Bogor', 'Depok', 'Tangerang', 'Makassar'];

    private const AGAMA = ['Islam', 'Kristen Protestan', 'Kristen Katolik', 'Hindu', 'Buddha', 'Konghucu'];

    private const PEKERJAAN = ['Karyawan Swasta', 'Pegawai Negeri Sipil (PNS)', 'Wiraswasta / Pengusaha', 'Guru / Dosen', 'Pedagang', 'Ibu Rumah Tangga'];

    private const PENGHASILAN = ['Rp3.000.000 – Rp4.999.999', 'Rp5.000.000 – Rp7.499.999', 'Rp7.500.000 – Rp9.999.999', 'Rp10.000.000 – Rp14.999.999'];

    private const HARI = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

    private const SLOT = [['07:30:00', '09:10:00'], ['09:20:00', '11:00:00'], ['13:00:00', '14:40:00'], ['14:50:00', '16:30:00']];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder menghapus data akademik dan tidak boleh dijalankan di production.');
        }

        // APP_ENV=local pun bisa berisi data nyata, jadi penghapusan selalu dikonfirmasi lebih dulu.
        // Tanpa konfirmasi (mis. dijalankan dari skrip) seeder berhenti tanpa menyentuh data.
        if (! app()->runningUnitTests() && Krs::query()->exists() && $this->command?->confirm('Seluruh KRS, nilai, kelas, dan konten kelas akan DIHAPUS lalu diganti data demo. Lanjutkan?', false) !== true) {
            $this->command?->warn('Data demo dilewati; data yang ada dibiarkan.');

            return;
        }

        PermissionCatalog::sync();
        $this->bersihkan();
        $this->pengaturan();

        $dosen = $this->dosen();
        $prodi = $this->fakultasDanProdi($dosen);
        $mataKuliah = $this->mataKuliah($prodi);
        $ruang = $this->ruang();
        $tahunAkademik = $this->tahunAkademik();
        $mahasiswa = $this->mahasiswa($prodi, $dosen, $tahunAkademik->last());

        $this->bersihkanSisaDemoLama($dosen, $mahasiswa, $prodi, $mataKuliah, $ruang);
        $this->kelasDanJadwal($tahunAkademik, $prodi, $mataKuliah, $dosen, $ruang);
        $this->krsDanNilai($tahunAkademik, $mahasiswa);
        $this->kontenKelas($tahunAkademik->last(), $mahasiswa);
        $this->pindahKelas($tahunAkademik->last(), $mahasiswa);
        $this->infoKuliah();
        $this->keuangan($tahunAkademik, $mahasiswa);
    }

    private function bersihkan(): void
    {
        QuizAnswer::query()->delete();
        QuizAttempt::query()->delete();
        PengumpulanTugas::query()->delete();
        PengajuanPindahKelas::query()->delete();
        Krs::query()->delete();
        Question::query()->delete();
        Quiz::query()->delete();
        Tugas::query()->delete();
        Materi::query()->delete();
        Jadwal::query()->delete();
        KelasKuliah::query()->delete();
        KrsSemester::query()->delete();
        TagihanItem::query()->delete();
        TagihanSemester::query()->delete();
        TarifBiaya::query()->delete();
        JenisBiaya::query()->delete();
        TahunAkademik::query()->delete();
        InfoKuliah::query()->delete();
    }

    /**
     * Buang data demo dari seeder versi sebelumnya (dosen/mahasiswa, mata kuliah, prodi, dan ruang yang
     * tidak lagi dipakai) agar isi database persis seperti rancangan data demo saat ini.
     *
     * @param  Collection<int, DosenProfile>  $dosen
     * @param  Collection<int, MahasiswaProfile>  $mahasiswa
     * @param  Collection<int, ProgramStudi>  $prodi
     * @param  Collection<int, MataKuliah>  $mataKuliah
     * @param  Collection<int, Ruang>  $ruang
     */
    private function bersihkanSisaDemoLama(Collection $dosen, Collection $mahasiswa, Collection $prodi, Collection $mataKuliah, Collection $ruang): void
    {
        // Urutannya mengikuti aturan foreign key: mata kuliah dulu, lalu prodi/fakultas, baru akunnya.
        MataKuliah::query()->whereNotIn('id', $mataKuliah->pluck('id'))->delete();
        ProgramStudi::query()->whereNotIn('id', $prodi->pluck('id'))->delete();
        Fakultas::query()->whereNotIn('id', $prodi->pluck('fakultas_id')->unique())->delete();
        Ruang::query()->whereNotIn('id', $ruang->pluck('id'))->delete();

        User::query()
            ->whereNot(fn ($query) => $query->whereIn('id', $dosen->pluck('user_id'))->orWhereIn('id', $mahasiswa->pluck('user_id')))
            ->whereHas('role', fn ($query) => $query->whereIn('user_type', [UserType::Dosen, UserType::Mahasiswa]))
            ->get()
            ->each(fn (User $user) => $user->delete());
    }

    private function pengaturan(): void
    {
        PengaturanInstitusi::firstOrCreate(
            ['id' => PengaturanInstitusi::SINGLETON_ID],
            ['nama_pt' => 'SIA VD', 'singkatan' => 'SIA VD'],
        );
        PengaturanPindahKelas::current()->update(['is_active' => true]);
        PengaturanAkademik::current();
    }

    /**
     * Admin dan 16 dosen; empat dosen untuk tiap program studi agar setiap kelas punya pengampu
     * tanpa membuat jadwal dosen bentrok.
     *
     * @return Collection<int, DosenProfile>
     */
    private function dosen(): Collection
    {
        $admin = $this->akun('admin', 'Administrator', 'admin@example.com', UserType::Admin, '11111');
        $admin->adminProfile()->updateOrCreate([], [
            'nomor_induk' => 'A00001',
            ...$this->profilUmum(0, 1985),
        ]);

        $nama = [
            'Budi Santoso', 'Sri Wahyuni', 'Agus Firmansyah', 'Dewi Lestari',
            'Rizky Ramadhan', 'Nurul Hidayah', 'Bayu Pratama', 'Ratna Kusuma',
            'Hendra Wijaya', 'Maya Anggraini', 'Fajar Nugroho', 'Intan Permata',
            'Doni Saputra', 'Wulan Safitri', 'Eko Prasetyo', 'Lina Marlina',
        ];
        $jabatan = ['Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'];

        return collect($nama)->map(function (string $nama, int $index) use ($jabatan) {
            // Dosen pertama memakai username 22222, akun contoh yang dipakai tim.
            $username = $index === 0 ? '22222' : 'dosen'.$index;
            $user = $this->akun($username, $nama, $username.'@example.com', UserType::Dosen, $username);

            return $user->dosenProfile()->updateOrCreate([], [
                'nidn' => 'D'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT),
                'jabatan_fungsional' => $jabatan[$index % count($jabatan)],
                'pendidikan_terakhir' => $index % 4 === 3 ? 'S3' : 'S2',
                'status_kepegawaian' => $index % 5 === 4 ? 'Tidak Tetap' : 'Tetap',
                ...$this->profilUmum($index, 1975 + ($index % 12)),
            ]);
        });
    }

    /**
     * @param  Collection<int, DosenProfile>  $dosen
     * @return Collection<int, ProgramStudi>
     */
    private function fakultasDanProdi(Collection $dosen): Collection
    {
        $data = [
            ['FTI', 'Fakultas Teknologi Informasi', [
                ['TI-S1', 'Teknik Informatika', 'S1'],
                ['SI-S1', 'Sistem Informasi', 'S1'],
            ]],
            ['FEB', 'Fakultas Ekonomi dan Bisnis', [
                ['AK-S1', 'Akuntansi', 'S1'],
                ['MJ-S1', 'Manajemen', 'S1'],
            ]],
        ];

        $prodi = collect();
        $urutanProdi = 0;

        foreach ($data as $indexFakultas => [$kode, $namaFakultas, $daftarProdi]) {
            $fakultas = Fakultas::updateOrCreate(['kode_fakultas' => $kode], [
                'nama_fakultas' => $namaFakultas,
                'dekan_id' => $dosen[$indexFakultas * 8]->id,
                'tanggal_berdiri' => '2001-08-17',
                'no_telp' => '021-555'.str_pad((string) ($indexFakultas + 1), 4, '0', STR_PAD_LEFT),
                'email' => strtolower($kode).'@example.ac.id',
            ]);

            foreach ($daftarProdi as $indexProdi => [$kodeProdi, $namaProdi, $jenjang]) {
                $prodi->push(ProgramStudi::updateOrCreate(['kode_prodi' => $kodeProdi], [
                    'fakultas_id' => $fakultas->id,
                    'nama_prodi' => $namaProdi,
                    'jenjang' => $jenjang,
                    'status_akreditasi' => ['Unggul', 'Baik Sekali'][$indexProdi % 2],
                    'no_sk_akreditasi' => '00'.($indexFakultas + 1).($indexProdi + 1).'/SK/BAN-PT/2022',
                    'tanggal_akreditasi_mulai' => '2022-06-01',
                    'tanggal_akreditasi_akhir' => '2027-06-01',
                    'kaprodi' => $dosen[$urutanProdi * 4]->id,
                    'tahun_berdiri' => 2001 + $urutanProdi,
                ]));
                $urutanProdi++;
            }
        }

        // Setiap dosen mengajar di satu program studi: empat dosen berurutan per prodi.
        $dosen->each(fn ($profil, int $index) => $profil->update(['prodi_id' => $prodi[intdiv($index, 4)]->id]));

        return $prodi;
    }

    /**
     * Paket mata kuliah semester 1–6 tiap program studi; satu semester berisi 4 mata kuliah (16 SKS),
     * masih di bawah batas SKS terendah pada Pengaturan Akademik.
     *
     * @param  Collection<int, ProgramStudi>  $prodi
     * @return Collection<int, MataKuliah>
     */
    private function mataKuliah(Collection $prodi): Collection
    {
        $namaPerProdi = [
            'TI-S1' => ['Algoritma dan Pemrograman', 'Struktur Data', 'Matematika Diskret', 'Pengantar Teknologi Informasi', 'Basis Data', 'Pemrograman Berorientasi Objek', 'Sistem Operasi', 'Statistika', 'Jaringan Komputer', 'Rekayasa Perangkat Lunak', 'Pemrograman Web', 'Analisis Algoritma', 'Kecerdasan Buatan', 'Sistem Terdistribusi', 'Keamanan Informasi', 'Interaksi Manusia dan Komputer', 'Pembelajaran Mesin', 'Komputasi Awan', 'Pemrograman Mobile', 'Manajemen Proyek TI', 'Data Mining', 'Pengolahan Citra', 'Metodologi Penelitian', 'Etika Profesi'],
            'SI-S1' => ['Konsep Sistem Informasi', 'Pemrograman Dasar', 'Matematika Bisnis', 'Pengantar Manajemen', 'Basis Data Bisnis', 'Analisis Proses Bisnis', 'Statistika Bisnis', 'Akuntansi Dasar', 'Perancangan Sistem Informasi', 'Sistem Informasi Manajemen', 'Pemrograman Aplikasi Bisnis', 'Manajemen Basis Data', 'E-Business', 'Analitik Bisnis', 'Tata Kelola Teknologi Informasi', 'Sistem Pendukung Keputusan', 'Enterprise Resource Planning', 'Audit Sistem Informasi', 'Keamanan Sistem Informasi', 'Manajemen Proyek Sistem Informasi', 'Integrasi Sistem', 'Inovasi Digital', 'Metodologi Penelitian', 'Kewirausahaan Digital'],
            'AK-S1' => ['Pengantar Akuntansi', 'Pengantar Ekonomi', 'Matematika Ekonomi', 'Pengantar Bisnis', 'Akuntansi Keuangan Menengah', 'Akuntansi Biaya', 'Statistika Ekonomi', 'Hukum Bisnis', 'Akuntansi Manajemen', 'Perpajakan', 'Sistem Informasi Akuntansi', 'Manajemen Keuangan', 'Auditing', 'Akuntansi Keuangan Lanjutan', 'Analisis Laporan Keuangan', 'Akuntansi Sektor Publik', 'Audit Internal', 'Teori Akuntansi', 'Perpajakan Lanjutan', 'Akuntansi Syariah', 'Akuntansi Forensik', 'Sistem Pengendalian Manajemen', 'Metodologi Penelitian', 'Etika Bisnis dan Profesi'],
            'MJ-S1' => ['Pengantar Manajemen', 'Pengantar Bisnis', 'Matematika Bisnis', 'Pengantar Ekonomi', 'Manajemen Pemasaran', 'Manajemen Keuangan', 'Statistika Bisnis', 'Perilaku Organisasi', 'Manajemen Operasional', 'Manajemen Sumber Daya Manusia', 'Riset Pemasaran', 'Komunikasi Bisnis', 'Manajemen Strategik', 'Manajemen Risiko', 'Kepemimpinan', 'Bisnis Internasional', 'Manajemen Investasi', 'Pemasaran Digital', 'Manajemen Inovasi', 'Manajemen Kinerja', 'Studi Kelayakan Bisnis', 'Manajemen Perubahan', 'Metodologi Penelitian', 'Kewirausahaan'],
        ];

        $mataKuliah = collect();

        foreach ($prodi as $item) {
            foreach ($namaPerProdi[$item->kode_prodi] as $index => $nama) {
                $semester = intdiv($index, self::MATKUL_PER_SEMESTER) + 1;
                $urut = $index % self::MATKUL_PER_SEMESTER;

                $mataKuliah->push(MataKuliah::updateOrCreate(
                    ['kode_matkul' => substr($item->kode_prodi, 0, 2).$semester.str_pad((string) ($urut + 1), 2, '0', STR_PAD_LEFT)],
                    [
                        'nama_matkul' => $nama,
                        'sks' => 4,
                        'semester' => $semester,
                        'jenis' => $urut === 3 ? 'Pilihan' : 'Wajib',
                        'prodi_id' => $item->id,
                    ],
                ));
            }
        }

        return $mataKuliah;
    }

    /**
     * @return Collection<int, Ruang>
     */
    private function ruang(): Collection
    {
        return collect([
            ['R-101', 'Ruang Kuliah 101', 40, 'Gedung A lantai 1'],
            ['R-102', 'Ruang Kuliah 102', 40, 'Gedung A lantai 1'],
            ['R-201', 'Ruang Kuliah 201', 35, 'Gedung A lantai 2'],
            ['R-202', 'Ruang Kuliah 202', 35, 'Gedung A lantai 2'],
            ['LAB-1', 'Laboratorium Komputer 1', 30, 'Gedung B lantai 1'],
            ['LAB-2', 'Laboratorium Komputer 2', 30, 'Gedung B lantai 2'],
        ])->map(fn (array $item): Ruang => Ruang::updateOrCreate(['kode_ruang' => $item[0]], [
            'nama_ruang' => $item[1],
            'kapasitas' => $item[2],
            'detail' => $item[3],
        ]));
    }

    /**
     * Tiga tahun akademik berurutan; hanya yang terakhir aktif, dengan periode KRS yang sedang berjalan
     * sehingga mahasiswa bisa langsung mencoba pengisian KRS.
     *
     * @return Collection<int, TahunAkademik>
     */
    private function tahunAkademik(): Collection
    {
        $mulaiAktif = Carbon::today()->subWeek();
        $tahunAktif = (int) $mulaiAktif->year;

        $periode = [
            [($tahunAktif - 1).'/'.$tahunAktif, 'Ganjil', $mulaiAktif->copy()->subMonths(12)],
            [($tahunAktif - 1).'/'.$tahunAktif, 'Genap', $mulaiAktif->copy()->subMonths(6)],
            [$tahunAktif.'/'.($tahunAktif + 1), 'Ganjil', $mulaiAktif],
        ];

        return collect($periode)->map(fn (array $item, int $index): TahunAkademik => TahunAkademik::create([
            'tahun' => $item[0],
            'semester' => $item[1],
            'tanggal_mulai' => $item[2],
            'tanggal_akhir' => $item[2]->copy()->addMonths(5),
            'tanggal_krs_awal' => $item[2]->copy()->subWeek(),
            'tanggal_krs_akhir' => $item[2]->copy()->addWeeks(2),
            'status' => $index === count($periode) - 1,
        ]));
    }

    /**
     * Mahasiswa tiga angkatan per program studi. Angkatan terlama sudah menempuh dua semester
     * sebelumnya sehingga kini di semester 5; angkatan terbaru baru masuk semester 1.
     *
     * @param  Collection<int, ProgramStudi>  $prodi
     * @param  Collection<int, DosenProfile>  $dosen
     * @return Collection<int, MahasiswaProfile>
     */
    private function mahasiswa(Collection $prodi, Collection $dosen, TahunAkademik $tahunAktif): Collection
    {
        $namaDepan = ['Andi', 'Citra', 'Dimas', 'Fitri', 'Galih', 'Hana', 'Irfan', 'Kirana', 'Lukman', 'Mira', 'Naufal', 'Oktavia', 'Putra', 'Rani', 'Satria'];
        $namaBelakang = ['Saputra', 'Maharani', 'Prasetya', 'Handayani', 'Wibowo', 'Salsabila', 'Hakim', 'Pertiwi', 'Ardiansyah', 'Utami'];
        $tahunMasukAktif = (int) explode('/', $tahunAktif->tahun)[0];
        $mahasiswa = collect();
        $nomor = 0;

        foreach ($prodi as $indexProdi => $item) {
            foreach ([2, 1, 0] as $mundur) {
                $angkatan = $tahunMasukAktif - $mundur;
                $semester = $mundur * 2 + 1;

                for ($urut = 1; $urut <= self::MAHASISWA_PER_ANGKATAN; $urut++) {
                    $nomor++;
                    // Mahasiswa pertama memakai username 33333, akun contoh yang dipakai tim.
                    $username = $nomor === 1 ? '33333' : 'mahasiswa'.$nomor;
                    $nama = $namaDepan[($nomor - 1) % count($namaDepan)].' '.$namaBelakang[($nomor + $mundur) % count($namaBelakang)];
                    $user = $this->akun($username, $nama, $username.'@example.com', UserType::Mahasiswa, $username);

                    $mahasiswa->push($user->mahasiswaProfile()->updateOrCreate([], [
                        'nim' => $angkatan.str_pad((string) ($indexProdi + 1), 2, '0', STR_PAD_LEFT).str_pad((string) $urut, 3, '0', STR_PAD_LEFT),
                        'angkatan' => $angkatan,
                        'semester' => $semester,
                        'status' => 'Aktif',
                        'prodi_id' => $item->id,
                        'dosen_wali_id' => $dosen[$indexProdi * 4 + ($urut % 4)]->id,
                        'sekolah_asal' => ['SMA Negeri 1 '.self::KOTA[$nomor % 10], 'SMK Negeri 2 '.self::KOTA[($nomor + 3) % 10]][$nomor % 2],
                        'nisn' => str_pad((string) (1000000000 + $nomor), 10, '0', STR_PAD_LEFT),
                        'email_alternatif' => $username.'.alt@example.com',
                        'nama_ayah_kandung' => 'Bapak '.$namaBelakang[$nomor % count($namaBelakang)],
                        'nama_ibu_kandung' => 'Ibu '.$namaBelakang[($nomor + 5) % count($namaBelakang)],
                        'tanggal_lahir_ayah' => Carbon::create(1970 + ($nomor % 8), ($nomor % 12) + 1, 12)->toDateString(),
                        'tanggal_lahir_ibu' => Carbon::create(1972 + ($nomor % 8), (($nomor + 4) % 12) + 1, 5)->toDateString(),
                        'pendidikan_terakhir_ayah' => ['SMA/SMK', 'D3', 'S1', 'S2'][$nomor % 4],
                        'pendidikan_terakhir_ibu' => ['SMA/SMK', 'D3', 'S1'][$nomor % 3],
                        'pekerjaan_ayah' => self::PEKERJAAN[$nomor % count(self::PEKERJAAN)],
                        'pekerjaan_ibu' => self::PEKERJAAN[($nomor + 2) % count(self::PEKERJAAN)],
                        'penghasilan_ayah' => self::PENGHASILAN[$nomor % count(self::PENGHASILAN)],
                        'penghasilan_ibu' => self::PENGHASILAN[($nomor + 1) % count(self::PENGHASILAN)],
                        'no_telepon_ayah' => '0812'.str_pad((string) (3000 + $nomor), 8, '0', STR_PAD_LEFT),
                        'no_telepon_ibu' => '0813'.str_pad((string) (4000 + $nomor), 8, '0', STR_PAD_LEFT),
                        'email_ayah' => 'ayah.'.$username.'@example.com',
                        'email_ibu' => 'ibu.'.$username.'@example.com',
                        'alamat_ayah' => 'Jl. Melati No. '.$nomor.', '.self::KOTA[$nomor % 10],
                        'alamat_ibu' => 'Jl. Melati No. '.$nomor.', '.self::KOTA[$nomor % 10],
                        ...$this->profilUmum($nomor, $angkatan - 18),
                    ]));
                }
            }
        }

        return $mahasiswa;
    }

    /**
     * Kelas dibuat hanya untuk semester yang memang ditempuh salah satu angkatan pada tahun tersebut,
     * lalu dijadwalkan agar tidak bentrok ruang, dosen, maupun antar kelas dalam satu paket semester.
     *
     * @param  Collection<int, TahunAkademik>  $tahunAkademik
     * @param  Collection<int, ProgramStudi>  $prodi
     * @param  Collection<int, MataKuliah>  $mataKuliah
     * @param  Collection<int, DosenProfile>  $dosen
     * @param  Collection<int, Ruang>  $ruang
     */
    private function kelasDanJadwal(Collection $tahunAkademik, Collection $prodi, Collection $mataKuliah, Collection $dosen, Collection $ruang): void
    {
        $ruangTerpakai = [];   // "tahun-hari-slot" => id ruang yang sudah dipakai
        $dosenTerpakai = [];   // "tahun-hari-slot" => id dosen yang sudah mengajar

        foreach ($tahunAkademik as $urutanTahun => $tahun) {
            foreach ($prodi as $indexProdi => $item) {
                foreach ($this->semesterDitempuh($urutanTahun) as $indexPaket => $semester) {
                    $paket = $mataKuliah->where('prodi_id', $item->id)->where('semester', $semester)->values();
                    $pengampuProdi = $dosen->where('prodi_id', $item->id)->values();

                    foreach ($paket as $indexMatkul => $matkul) {
                        // Tahun berjalan punya dua kelas paralel per mata kuliah agar pindah kelas bisa dicoba.
                        $jumlahKelas = $tahun->status ? 2 : 1;

                        for ($section = 0; $section < $jumlahKelas; $section++) {
                            // Satu paket semester adalah satu rombongan belajar, jadi tiap mata kuliahnya
                            // menempati slot berbeda; kelas paralel dipindah ke hari lain agar mahasiswa
                            // yang mencampur kelas A dan B tetap tidak bentrok.
                            $hari = self::HARI[($indexProdi + $indexPaket + $section * 2) % count(self::HARI)];
                            $slot = $indexMatkul % count(self::SLOT);
                            $kunci = $tahun->id.'-'.$hari.'-'.$slot;

                            $pengampu = $pengampuProdi->first(fn ($profil): bool => ! in_array($profil->id, $dosenTerpakai[$kunci] ?? [], true))
                                ?? $pengampuProdi->first();
                            $ruangDipakai = $ruang->first(fn (Ruang $r): bool => ! in_array($r->id, $ruangTerpakai[$kunci] ?? [], true))
                                ?? $ruang->first();

                            $kelas = KelasKuliah::create([
                                'kode_kelas' => $matkul->kode_matkul.'-'.chr(65 + $section),
                                'tahun_akademik_id' => $tahun->id,
                                'kapasitas' => 30,
                                'dosen_id' => $pengampu->id,
                                'matkul_id' => $matkul->id,
                            ]);

                            Jadwal::create([
                                'kelas_id' => $kelas->id,
                                'hari' => $hari,
                                'jam_mulai' => self::SLOT[$slot][0],
                                'jam_akhir' => self::SLOT[$slot][1],
                                'ruang_id' => $ruangDipakai->id,
                            ]);

                            $ruangTerpakai[$kunci][] = $ruangDipakai->id;
                            $dosenTerpakai[$kunci][] = $pengampu->id;
                        }
                    }
                }
            }
        }
    }

    /**
     * Semester yang ditempuh angkatan-angkatan pada tahun akademik ke-$urutanTahun (0 = paling lama).
     *
     * @return list<int>
     */
    private function semesterDitempuh(int $urutanTahun): array
    {
        return match ($urutanTahun) {
            0 => [1, 3],
            1 => [2, 4],
            default => [1, 3, 5],
        };
    }

    /**
     * KRS tiap mahasiswa: paket semester yang sesuai, dibatasi SKS menurut IPS semester sebelumnya.
     * Tahun-tahun lampau sudah bernilai lengkap; tahun aktif masih kosong karena kuliah sedang berjalan.
     *
     * @param  Collection<int, TahunAkademik>  $tahunAkademik
     * @param  Collection<int, MahasiswaProfile>  $mahasiswa
     */
    private function krsDanNilai(Collection $tahunAkademik, Collection $mahasiswa): void
    {
        $huruf = SkalaNilai::semua()->keys()->values();
        $tahunAktif = $tahunAkademik->last();

        foreach ($tahunAkademik as $urutanTahun => $tahun) {
            foreach ($mahasiswa as $indexMahasiswa => $profil) {
                // Tiap tahun akademik di data demo adalah satu semester berurutan (Ganjil, Genap, Ganjil).
                $semester = $profil->semester - (count($tahunAkademik) - 1 - $urutanTahun);

                if ($semester < 1) {
                    continue;
                }

                $maksSks = PengaturanAkademik::maksSksUntuk($profil->ipsSemesterSebelum($tahun)['ips'] ?? null);
                $terpakai = 0;

                // Bila satu mata kuliah punya kelas paralel, mahasiswa hanya masuk ke salah satunya.
                $kelasSemester = KelasKuliah::query()
                    ->where('tahun_akademik_id', $tahun->id)
                    ->whereHas('mataKuliah', fn ($query) => $query->where('prodi_id', $profil->prodi_id)->where('semester', $semester))
                    ->with('mataKuliah:id,sks')
                    ->orderBy('kode_kelas')
                    ->get()
                    ->groupBy('matkul_id')
                    ->map(fn (Collection $paralel) => $paralel->values()[$indexMahasiswa % $paralel->count()])
                    ->values();

                foreach ($kelasSemester as $indexKelas => $kelas) {
                    if ($terpakai + $kelas->mataKuliah->sks > $maksSks) {
                        continue;
                    }

                    // Nilai hanya untuk tahun yang sudah selesai; hurufnya berputar agar IP beragam.
                    // Nilai E dihindari supaya tidak ada mata kuliah tertinggal yang menyulitkan demo.
                    Krs::create([
                        'mahasiswa_id' => $profil->id,
                        'kelas_id' => $kelas->id,
                        'status' => 'Aktif',
                        'nilai' => $tahun->is($tahunAktif) ? null : $huruf[($indexMahasiswa + $indexKelas + $urutanTahun) % max($huruf->count() - 1, 1)],
                    ]);

                    $terpakai += $kelas->mataKuliah->sks;
                }
            }
        }
    }

    /**
     * Materi, tugas, dan quiz untuk kelas tahun berjalan, beserta pengumpulan dan pengerjaan mahasiswa
     * dalam format yang sama dengan yang dihasilkan aplikasi.
     *
     * @param  Collection<int, MahasiswaProfile>  $mahasiswa
     */
    private function kontenKelas(TahunAkademik $tahunAktif, Collection $mahasiswa): void
    {
        $kelasAktif = KelasKuliah::query()
            ->where('tahun_akademik_id', $tahunAktif->id)
            ->with('dosen:id,user_id', 'mataKuliah:id,nama_matkul', 'krs:id,kelas_id,mahasiswa_id')
            ->get();

        foreach ($kelasAktif as $indexKelas => $kelas) {
            $pengunggah = $kelas->dosen->user_id;

            foreach ([1, 2] as $pertemuan) {
                Materi::create([
                    'kelas_id' => $kelas->id,
                    'judul_materi' => 'Pertemuan '.$pertemuan.': '.$kelas->mataKuliah->nama_matkul,
                    'pertemuan_ke' => $pertemuan,
                    'jenis' => 'Materi',
                    'file' => [],
                    'catatan' => 'Ringkasan materi pertemuan '.$pertemuan.'.',
                    'uploaded_by' => $pengunggah,
                ]);
            }

            $tugas = Tugas::create([
                'kelas_id' => $kelas->id,
                'judul_tugas' => 'Tugas 1 '.$kelas->mataKuliah->nama_matkul,
                'tenggat_waktu' => Carbon::today()->addWeek()->setTime(23, 59),
                'catatan' => 'Kerjakan dan unggah dalam format PDF.',
                'file' => [],
                'uploaded_by' => $pengunggah,
            ]);

            $quiz = Quiz::create([
                'kelas_id' => $kelas->id,
                'nama_quiz' => 'Quiz 1 '.$kelas->mataKuliah->nama_matkul,
                'catatan' => 'Quiz pemahaman materi pertemuan 1–2.',
                'waktu_pengerjaan' => 30,
                'tenggat_waktu' => Carbon::today()->addDays(3)->setTime(23, 59),
                'uploaded_by' => $pengunggah,
            ]);

            $soal = $this->soalQuiz($quiz);
            $peserta = $mahasiswa->whereIn('id', $kelas->krs->pluck('mahasiswa_id'))->values();

            // Sebagian kelas sudah dikerjakan: satu attempt selesai dinilai, satu menunggu koreksi esai.
            if ($indexKelas % 3 === 0) {
                foreach ($peserta->take(2) as $urutan => $profil) {
                    $this->kerjakanQuiz($quiz, $soal, $profil, esaiDinilai: $urutan === 0);
                }
            }

            if ($indexKelas % 2 === 0) {
                foreach ($peserta->take(2) as $urutan => $profil) {
                    PengumpulanTugas::create([
                        'tugas_id' => $tugas->id,
                        'mahasiswa_id' => $profil->id,
                        'file_jawaban' => [],
                        'nilai' => $urutan === 0 ? 85 : null,
                        'submitted_at' => Carbon::today()->subDay()->setTime(20, 15),
                    ]);
                }
            }
        }
    }

    /**
     * @return Collection<int, Question>
     */
    private function soalQuiz(Quiz $quiz): Collection
    {
        return collect([
            $quiz->questions()->create([
                'question_text' => 'Manakah pernyataan yang benar tentang materi pertemuan 1?',
                'question_type' => 'single_choice',
                'question_option' => [
                    ['text' => 'Pernyataan A', 'is_correct' => true],
                    ['text' => 'Pernyataan B', 'is_correct' => false],
                    ['text' => 'Pernyataan C', 'is_correct' => false],
                ],
                'points' => 30,
            ]),
            $quiz->questions()->create([
                'question_text' => 'Pilih semua komponen yang dibahas pada pertemuan 2.',
                'question_type' => 'multiple_choice',
                'question_option' => [
                    ['text' => 'Komponen 1', 'is_correct' => true],
                    ['text' => 'Komponen 2', 'is_correct' => false],
                    ['text' => 'Komponen 3', 'is_correct' => true],
                ],
                'points' => 40,
            ]),
            $quiz->questions()->create([
                'question_text' => 'Jelaskan penerapan materi ini pada satu studi kasus sederhana.',
                'question_type' => 'essay',
                'points' => 30,
            ]),
        ]);
    }

    /**
     * Kerjakan quiz seperti mahasiswa: jawaban tersimpan sebagai daftar, pilihan dinilai otomatis,
     * esai menunggu koreksi dosen kecuali memang sudah dinilai.
     *
     * @param  Collection<int, Question>  $soal
     */
    private function kerjakanQuiz(Quiz $quiz, Collection $soal, MahasiswaProfile $profil, bool $esaiDinilai): void
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'mahasiswa_id' => $profil->id,
            'started_at' => Carbon::today()->subDay()->setTime(9, 0),
        ]);

        $attempt->setRelation('quiz', $quiz)->finalize([
            $soal[0]->id => 'Pernyataan A',
            $soal[1]->id => ['Komponen 1', 'Komponen 3'],
            $soal[2]->id => 'Materi ini saya terapkan pada studi kasus sederhana di tempat magang.',
        ]);

        if ($esaiDinilai) {
            QuizAnswer::query()
                ->where('attempt_id', $attempt->id)
                ->where('question_id', $soal[2]->id)
                ->update(['point' => 25]);
            $attempt->recalculateScore();
        }
    }

    /**
     * Satu pengajuan pindah kelas yang masih menunggu, antar kelas mata kuliah yang sama di tahun aktif.
     *
     * @param  Collection<int, MahasiswaProfile>  $mahasiswa
     */
    private function pindahKelas(TahunAkademik $tahunAktif, Collection $mahasiswa): void
    {
        $krs = Krs::query()
            ->whereIn('mahasiswa_id', $mahasiswa->pluck('id'))
            ->whereHas('kelasKuliah', fn ($query) => $query->where('tahun_akademik_id', $tahunAktif->id))
            ->with('kelasKuliah:id,matkul_id,tahun_akademik_id')
            ->get();

        foreach ($krs as $item) {
            $tujuan = KelasKuliah::query()
                ->where('tahun_akademik_id', $tahunAktif->id)
                ->where('matkul_id', $item->kelasKuliah->matkul_id)
                ->whereKeyNot($item->kelas_id)
                ->whereDoesntHave('krs', fn ($query) => $query->where('mahasiswa_id', $item->mahasiswa_id))
                ->first();

            if ($tujuan !== null) {
                PengajuanPindahKelas::create([
                    'mahasiswa_id' => $item->mahasiswa_id,
                    'kelas_asal_id' => $item->kelas_id,
                    'kelas_tujuan_id' => $tujuan->id,
                    'alasan' => 'Jadwal kelas ini bentrok dengan kegiatan asisten laboratorium.',
                    'status' => PengajuanPindahKelas::STATUS_PENDING,
                ]);

                return;
            }
        }
    }

    private function infoKuliah(): void
    {
        $adminId = User::query()->where('username', 'admin')->value('id');

        $pengumuman = [
            'pengumuman-perkuliahan' => 'Perkuliahan semester berjalan dimulai sesuai jadwal pada menu Jadwal Kuliah.',
            'pengumuman-krs' => 'Pengisian KRS dibuka sampai dua pekan setelah perkuliahan dimulai.',
            'pengumuman-ketidakhadiran' => 'Mahasiswa yang berhalangan hadir wajib mengunggah surat keterangan ke dosen pengampu.',
        ];

        foreach ($pengumuman as $berkas => $informasi) {
            InfoKuliah::create([
                'information' => $informasi,
                'file' => $this->berkasDemo('info-kuliahs', $berkas, $informasi),
                'uploaded_by' => $adminId,
            ]);
        }
    }

    /**
     * Tulis satu berkas PDF sederhana ke disk privat, agar tautan unduhan pada data demo benar-benar bisa dibuka.
     */
    /**
     * Biaya kuliah: dua komponen (SPP tetap per prodi dan SPP per SKS), lalu tagihan tiap semester.
     * Semester lampau ditandai lunas, semester berjalan sebagian saja agar kedua status ada isinya.
     *
     * @param  Collection<int, TahunAkademik>  $tahunAkademik
     * @param  Collection<int, MahasiswaProfile>  $mahasiswa
     */
    private function keuangan(Collection $tahunAkademik, Collection $mahasiswa): void
    {
        $sppTetap = JenisBiaya::query()->create([
            'kode' => 'SPP-TETAP',
            'nama' => 'SPP Tetap',
            'cara_hitung' => JenisBiaya::TETAP,
            'keterangan' => 'Biaya tetap per semester.',
            'aktif' => true,
            'urutan' => 1,
        ]);

        $sppSks = JenisBiaya::query()->create([
            'kode' => 'SPP-SKS',
            'nama' => 'SPP per SKS',
            'cara_hitung' => JenisBiaya::PER_SKS,
            'keterangan' => 'Dihitung dari jumlah SKS yang diambil.',
            // Nonaktif: tagihan terbit sebelum KRS diisi, jadi jumlah SKS belum diketahui.
            'aktif' => false,
            'urutan' => 2,
        ]);

        // Tarif umum sebagai jaring pengaman, lalu tarif khusus per program studi.
        $sppTetap->tarif()->create(['prodi_id' => null, 'angkatan' => null, 'nominal' => 3_000_000]);
        $sppSks->tarif()->create(['prodi_id' => null, 'angkatan' => null, 'nominal' => 150_000]);

        foreach (ProgramStudi::query()->orderBy('id')->get() as $urutan => $prodi) {
            $sppTetap->tarif()->create([
                'prodi_id' => $prodi->id,
                'angkatan' => null,
                'nominal' => 3_000_000 + ($urutan * 500_000),
            ]);
            $sppSks->tarif()->create([
                'prodi_id' => $prodi->id,
                'angkatan' => null,
                'nominal' => 150_000 + ($urutan * 25_000),
            ]);
        }

        $jenisBiaya = JenisBiaya::query()->where('aktif', true)->with('tarif')->orderBy('urutan')->get();
        $tahunAktif = $tahunAkademik->last();

        foreach ($tahunAkademik as $tahun) {
            foreach ($mahasiswa as $urutan => $profil) {
                $tagihan = TagihanSemester::query()->create([
                    'mahasiswa_id' => $profil->id,
                    'tahun_akademik_id' => $tahun->id,
                    'status' => TagihanSemester::BELUM_BAYAR,
                ]);

                $tagihan->susunRincian($profil, $jenisBiaya);

                if ($tagihan->total === 0) {
                    // Tidak ada komponen biaya yang berlaku untuk mahasiswa ini.
                    $tagihan->delete();

                    continue;
                }

                // Semester lampau dianggap sudah selesai dibayar; semester berjalan dua dari tiga mahasiswa.
                $lunas = $tahun->id !== $tahunAktif->id || $urutan % 3 !== 0;

                if ($lunas) {
                    $tagihan->forceFill([
                        'status' => TagihanSemester::LUNAS,
                        'tanggal_lunas' => $tahun->tanggal_mulai,
                    ])->save();
                }

                // KRS semester lampau sudah dikunci mahasiswa; semester berjalan sengaja dibiarkan
                // terbuka agar alur simpan KRS bisa dicoba.
                if ($tahun->id !== $tahunAktif->id && $profil->krs()->whereHas('kelasKuliah', fn ($query) => $query->where('tahun_akademik_id', $tahun->id))->exists()) {
                    KrsSemester::query()->updateOrCreate(
                        ['mahasiswa_id' => $profil->id, 'tahun_akademik_id' => $tahun->id],
                        ['disimpan_pada' => $tahun->tanggal_krs_akhir ?? $tahun->tanggal_mulai],
                    );
                }
            }
        }
    }

    private function berkasDemo(string $direktori, string $nama, string $isi): string
    {
        $path = $direktori.'/'.$nama.'-'.Str::lower(Str::random(6)).'.pdf';
        $teks = str_replace(['(', ')'], '', $isi);
        $konten = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            ."4 0 obj<</Length 90>>stream\nBT /F1 12 Tf 60 760 Td ({$teks}) Tj ET\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

        Storage::disk(AllowedUpload::DISK)->put($path, $konten);

        return $path;
    }

    /**
     * Kolom profil yang sama untuk semua peran.
     *
     * @return array<string, string>
     */
    private function profilUmum(int $nomor, int $tahunLahir): array
    {
        $kota = self::KOTA[$nomor % count(self::KOTA)];

        return [
            'tempat_lahir' => $kota,
            'tanggal_lahir' => Carbon::create($tahunLahir, ($nomor % 12) + 1, ($nomor % 27) + 1)->toDateString(),
            'jenis_kelamin' => $nomor % 2 === 0 ? 'Laki-laki' : 'Perempuan',
            'agama' => self::AGAMA[$nomor % count(self::AGAMA)],
            'no_telepon' => '0811'.str_pad((string) (2000 + $nomor), 8, '0', STR_PAD_LEFT),
            'alamat' => 'Jl. Pendidikan No. '.($nomor + 1).', '.$kota,
            'kewarganegaraan' => 'Indonesia',
        ];
    }

    /**
     * Buat atau perbarui akun demo tanpa mengganti kata sandi akun yang sudah ada.
     */
    private function akun(string $username, string $nama, string $email, UserType $tipe, string $password): User
    {
        $user = User::query()->firstOrNew(['username' => $username]);
        $user->fill(['name' => $nama, 'email' => $email, 'role_id' => Role::system($tipe)->id]);

        if (! $user->exists) {
            $user->password = Hash::make($password);
        }

        $user->save();

        return $user;
    }
}
