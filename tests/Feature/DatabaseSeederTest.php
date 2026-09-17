<?php

use App\Models\AdminProfile;
use App\Models\DosenProfile;
use App\Models\Fakultas;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\ProgramStudi;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\TahunAkademik;
use App\Models\Tugas;
use App\Models\User;
use App\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('seeds role users without duplicates', function () {
    $this->seed();
    $this->seed();

    expect(User::count())->toBe(153)
        ->and(AdminProfile::count())->toBe(1)
        ->and(DosenProfile::count())->toBe(51)
        ->and(MahasiswaProfile::count())->toBe(101)
        ->and(Fakultas::count())->toBe(2)
        ->and(ProgramStudi::count())->toBe(4)
        ->and(Fakultas::where('kode_fakultas', 'FTI')->whereHas('dekan')->whereHas('programStudis', fn ($query) => $query->where('kode_prodi', 'TI-S1')->whereHas('ketuaProgramStudi'))->exists())->toBeTrue()
        ->and(ProgramStudi::where('kode_prodi', 'AK-S1')->whereHas('fakultas', fn ($query) => $query->where('kode_fakultas', 'FEB'))->whereHas('ketuaProgramStudi')->exists())->toBeTrue()
        ->and(User::where('role', Role::Dosen->value)->count())->toBe(51)
        ->and(User::where('role', Role::Mahasiswa->value)->count())->toBe(101)
        ->and(User::where('username', 'admin')->whereHas('adminProfile', fn ($query) => $query->whereKeyNot(0))->exists())->toBeTrue()
        ->and(User::where('username', 'dosen1')->whereHas('dosenProfile', fn ($query) => $query->whereNotNull('nidn')->whereNotNull('tanggal_lahir'))->exists())->toBeTrue()
        ->and(User::where('username', 'mahasiswa1')->whereHas('mahasiswaProfile', fn ($query) => $query->whereNotNull('nim')->whereNotNull('alamat'))->exists())->toBeTrue()
        ->and(Hash::check('11111', User::where('username', 'admin')->value('password')))->toBeTrue()
        ->and(Hash::check('22222', User::where('username', '22222')->value('password')))->toBeTrue()
        ->and(Hash::check('33333', User::where('username', '33333')->value('password')))->toBeTrue();
});

it('seeds all academic periods and learning relations', function () {
    $this->seed();

    expect(TahunAkademik::count())->toBe(5)
        ->and(TahunAkademik::where('status', true)->value('tahun'))->toBe('2025/2026')
        ->and(KelasKuliah::count())->toBe(MataKuliah::count() * 5 * 3)
        ->and(KelasKuliah::where('kode_kelas', 'IF101-2324-0A')->exists())->toBeTrue()
        ->and(Krs::count())->toBeGreaterThan(0)
        ->and(Materi::count())->toBe(KelasKuliah::count() * 2)
        ->and(Tugas::count())->toBe(KelasKuliah::count())
        ->and(Quiz::count())->toBe(KelasKuliah::count())
        ->and(Question::count())->toBe(Quiz::count())
        ->and(PengumpulanTugas::count())->toBeGreaterThan(0)
        ->and(QuizAttempt::count())->toBeGreaterThan(0)
        ->and(QuizAnswer::count())->toBeGreaterThan(0);
});
