<?php

use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\Fakultas;
use App\Models\InfoKuliah;
use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanPindahKelas;
use App\Models\PengaturanAkademik;
use App\Models\PengaturanInstitusi;
use App\Models\PengumpulanTugas;
use App\Models\ProgramStudi;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\SkalaNilai;
use App\Models\TahunAkademik;
use App\Models\Tugas;
use App\Models\User;
use App\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds accounts and master data without duplicates', function () {
    $this->seed();
    $this->seed();

    expect(AdminProfile::count())->toBe(1)
        ->and(DosenProfile::count())->toBe(16)
        ->and(MahasiswaProfile::count())->toBe(60)
        ->and(User::count())->toBe(AdminProfile::count() + DosenProfile::count() + MahasiswaProfile::count())
        ->and(Fakultas::count())->toBe(2)
        ->and(ProgramStudi::count())->toBe(4)
        ->and(PengaturanInstitusi::count())->toBe(1)
        ->and(Hash::check('11111', User::where('username', 'admin')->value('password')))->toBeTrue()
        ->and(Hash::check('22222', User::where('username', '22222')->value('password')))->toBeTrue()
        ->and(Hash::check('33333', User::where('username', '33333')->value('password')))->toBeTrue();

    // Setiap fakultas punya dekan, setiap prodi punya kaprodi, setiap dosen punya prodi.
    expect(Fakultas::whereNull('dekan_id')->count())->toBe(0)
        ->and(ProgramStudi::whereNull('kaprodi')->count())->toBe(0)
        ->and(DosenProfile::whereNull('prodi_id')->count())->toBe(0);
});

it('seeds mahasiswa data that passes the same rules as the user form', function () {
    $this->seed();

    $mahasiswa = MahasiswaProfile::with('user')->get();

    expect($mahasiswa->whereNull('nim')->count())->toBe(0)
        ->and($mahasiswa->pluck('nim')->duplicates())->toBeEmpty()
        ->and($mahasiswa->pluck('nisn')->duplicates())->toBeEmpty()
        ->and($mahasiswa->pluck('email_alternatif')->duplicates())->toBeEmpty()
        ->and($mahasiswa->every(fn (MahasiswaProfile $item): bool => preg_match('/^\d{10}$/', (string) $item->nisn) === 1))->toBeTrue()
        ->and($mahasiswa->every(fn (MahasiswaProfile $item): bool => $item->email_alternatif !== $item->user->email))->toBeTrue()
        ->and($mahasiswa->every(fn (MahasiswaProfile $item): bool => $item->status === 'Aktif' && $item->dosen_wali_id !== null && $item->prodi_id !== null))->toBeTrue();
});

it('seeds exactly one active tahun akademik whose KRS period is open', function () {
    $this->seed();

    $aktif = TahunAkademik::where('status', true)->get();

    expect($aktif)->toHaveCount(1)
        ->and(now()->betweenIncluded($aktif->first()->tanggal_krs_awal, $aktif->first()->tanggal_krs_akhir))->toBeTrue()
        ->and(TahunAkademik::count())->toBeGreaterThan(1);

    // Kode kelas unik per tahun akademik, sesuai aturan unique di database.
    expect(KelasKuliah::get(['tahun_akademik_id', 'kode_kelas'])->map(fn ($kelas): string => $kelas->tahun_akademik_id.$kelas->kode_kelas)->duplicates())->toBeEmpty();
});

it('seeds KRS that follows the prodi, semester, and SKS rules', function () {
    $this->seed();

    $krs = Krs::with('mahasiswa', 'kelasKuliah.mataKuliah', 'kelasKuliah.tahunAkademik')->get();

    // Setiap KRS berada di program studi mahasiswanya.
    expect($krs->every(fn (Krs $item): bool => $item->kelasKuliah->mataKuliah->prodi_id === $item->mahasiswa->prodi_id))->toBeTrue();

    // Satu mahasiswa tidak mengambil mata kuliah yang sama dua kali dalam satu tahun akademik.
    $ganda = $krs->groupBy(fn (Krs $item): string => $item->mahasiswa_id.'-'.$item->kelasKuliah->tahun_akademik_id.'-'.$item->kelasKuliah->matkul_id)
        ->filter(fn ($rows): bool => $rows->count() > 1);
    expect($ganda)->toBeEmpty();

    // Beban SKS per tahun akademik tidak melebihi batas menurut IPS semester sebelumnya.
    foreach ($krs->groupBy(fn (Krs $item): string => $item->mahasiswa_id.'-'.$item->kelasKuliah->tahun_akademik_id) as $rows) {
        $profil = $rows->first()->mahasiswa;
        $tahun = $rows->first()->kelasKuliah->tahunAkademik;
        $maks = PengaturanAkademik::maksSksUntuk($profil->ipsSemesterSebelum($tahun)['ips'] ?? null);

        expect($rows->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks))->toBeLessThanOrEqual($maks);
    }

    // Nilai memakai huruf yang terdaftar; tahun berjalan belum dinilai, tahun lampau sudah lengkap.
    $huruf = SkalaNilai::huruf();
    $tahunAktifId = TahunAkademik::where('status', true)->value('id');
    $tahunBerjalan = $krs->filter(fn (Krs $item): bool => $item->kelasKuliah->tahun_akademik_id === $tahunAktifId);
    $tahunLampau = $krs->filter(fn (Krs $item): bool => $item->kelasKuliah->tahun_akademik_id !== $tahunAktifId);

    expect($tahunBerjalan->whereNotNull('nilai'))->toBeEmpty()
        ->and($tahunLampau)->not->toBeEmpty()
        ->and($tahunLampau->whereNull('nilai'))->toBeEmpty()
        ->and($tahunLampau->every(fn (Krs $item): bool => in_array($item->nilai, $huruf, true)))->toBeTrue();
});

it('seeds schedules without room, dosen, or student clashes', function () {
    $this->seed();

    $jadwal = Jadwal::with('kelasKuliah')->get();
    $pesertaKelas = Krs::get(['kelas_id', 'mahasiswa_id'])->groupBy('kelas_id')->map(fn ($rows) => $rows->pluck('mahasiswa_id'));

    foreach ($jadwal as $index => $a) {
        foreach ($jadwal->slice($index + 1) as $b) {
            $beririsan = $a->hari === $b->hari
                && $a->kelasKuliah->tahun_akademik_id === $b->kelasKuliah->tahun_akademik_id
                && $a->jam_mulai < $b->jam_akhir && $a->jam_akhir > $b->jam_mulai;

            if (! $beririsan) {
                continue;
            }

            expect($a->ruang_id)->not->toBe($b->ruang_id)
                ->and($a->kelasKuliah->dosen_id)->not->toBe($b->kelasKuliah->dosen_id)
                ->and(($pesertaKelas[$a->kelas_id] ?? collect())->intersect($pesertaKelas[$b->kelas_id] ?? collect()))->toBeEmpty();
        }
    }
});

it('seeds quiz and tugas content in the format the application produces', function () {
    $this->seed();

    $kelasAktif = KelasKuliah::where('tahun_akademik_id', TahunAkademik::where('status', true)->value('id'))->count();

    expect(Quiz::count())->toBe($kelasAktif)
        ->and(Question::count())->toBe(Quiz::count() * 3)
        ->and(Tugas::count())->toBe($kelasAktif)
        ->and(QuizAttempt::count())->toBeGreaterThan(0)
        ->and(PengumpulanTugas::count())->toBeGreaterThan(0)
        ->and(InfoKuliah::count())->toBe(3)
        ->and(PengajuanPindahKelas::where('status', PengajuanPindahKelas::STATUS_PENDING)->count())->toBe(1);

    // Jawaban tersimpan sebagai daftar, dan poin soal pilihan sama dengan hasil penilaian otomatis.
    $jawaban = QuizAnswer::with('question')->get();
    expect($jawaban->every(fn (QuizAnswer $item): bool => $item->answer === null || array_is_list($item->answer)))->toBeTrue();

    foreach ($jawaban->filter(fn (QuizAnswer $item): bool => $item->question->question_type !== 'essay') as $item) {
        $dikirim = $item->question->question_type === 'multiple_choice' ? ($item->answer ?? []) : ($item->answer[0] ?? null);
        expect((float) $item->point)->toBe($item->question->pointFor($dikirim));
    }

    // Ada attempt yang esainya sudah dinilai dan ada yang masih menunggu koreksi dosen.
    $esai = $jawaban->filter(fn (QuizAnswer $item): bool => $item->question->question_type === 'essay');
    expect($esai->whereNotNull('point'))->not->toBeEmpty()
        ->and($esai->whereNull('point'))->not->toBeEmpty();
});

it('seeds students who can immediately fill in their KRS', function () {
    $this->seed();

    $mahasiswa = User::ofType(UserType::Mahasiswa)->where('username', '33333')->firstOrFail();

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('periodeKrsAktif', true)
            ->where('bolehKrs', true)
            ->has('kelasKuliahs'));
});
