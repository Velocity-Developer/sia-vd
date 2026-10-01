<?php

use App\Models\Krs;
use App\Models\PengaturanInstitusi;
use App\Models\TahunAkademik;
use App\Models\User;
use App\UserType;

/**
 * Data dasar view PDF (kop) untuk dirender langsung tanpa dompdf.
 *
 * @return array<string, mixed>
 */
function kopPdf(): array
{
    $institusi = PengaturanInstitusi::current();

    return ['institusi' => $institusi, 'logoSrc' => null, 'kontak' => $institusi->kontakKop()];
}

function mahasiswaBerKrsAktif(): User
{
    $tahunAktif = TahunAkademik::aktif();

    return User::ofType(UserType::Mahasiswa)
        ->whereHas('mahasiswaProfile.krs.kelasKuliah', fn ($query) => $query->where('tahun_akademik_id', $tahunAktif->id))
        ->firstOrFail();
}

it('downloads the transcript as pdf', function () {
    $this->seed();
    $mahasiswa = User::ofType(UserType::Mahasiswa)->first();

    $response = $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.transkrip.download'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))->toContain("transkrip-{$mahasiswa->mahasiswaProfile->nim}.pdf");
});

it('downloads the study plan of the active academic year as pdf', function () {
    $this->seed();
    $mahasiswa = mahasiswaBerKrsAktif();
    $tahun = TahunAkademik::aktif();

    $response = $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.krs.download'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('krs-'.$mahasiswa->mahasiswaProfile->nim.'-'.str($tahun->tahun.'-'.$tahun->semester)->slug().'.pdf');
});

it('returns 404 for the study plan pdf when no class was taken that year', function () {
    $this->seed();
    $mahasiswa = mahasiswaBerKrsAktif();
    Krs::where('mahasiswa_id', $mahasiswa->mahasiswaProfile->id)
        ->whereHas('kelasKuliah', fn ($query) => $query->where('tahun_akademik_id', TahunAkademik::aktif()->id))
        ->delete();

    $this->actingAs($mahasiswa)->get(route('mahasiswa.krs.download'))->assertNotFound();
});

it('forbids transcript and study plan pdf for users without a student profile', function () {
    $this->seed();
    $admin = User::ofType(UserType::Admin)->first();

    $this->actingAs($admin)->get(route('mahasiswa.transkrip.download'))->assertForbidden();
    $this->actingAs($admin)->get(route('mahasiswa.krs.download'))->assertForbidden();
});

it('signs the transcript, study plan, and exam card with the advisor, head of program, and student', function () {
    $this->seed();
    $profil = mahasiswaBerKrsAktif()->mahasiswaProfile->muatPengesahan();
    $dosenPa = $profil->dosenWali;
    $kaprodi = $profil->prodi->ketuaProgramStudi;
    expect($dosenPa)->not->toBeNull()->and($kaprodi)->not->toBeNull();

    $tahun = TahunAkademik::aktif();
    $html = [
        view('pdf.transkrip', kopPdf() + [
            'mahasiswa' => $profil,
            'transkrip' => collect(),
            'ringkasan' => ['totalMatkul' => 0, 'totalSks' => 0, 'totalSksLulus' => 0, 'totalMutu' => 0, 'ipk' => null],
        ])->render(),
        view('pdf.krs', kopPdf() + [
            'mahasiswa' => $profil,
            'tahunAkademik' => $tahun,
            'krs' => $profil->krs()->with('kelasKuliah.mataKuliah')->get(),
            'ipsSebelumnya' => null,
            'maksSks' => 20,
            'kunci' => null,
        ])->render(),
        view('pdf.kartu-ujian', kopPdf() + [
            'mahasiswa' => $profil,
            'tahun' => $tahun,
            'jenis' => 'uts',
            'ujians' => collect(),
            'syaratAktif' => false,
        ])->render(),
    ];

    foreach ($html as $dokumen) {
        expect($dokumen)
            ->toContain('Dosen Pembimbing Akademik')
            ->toContain(e($dosenPa->user->name))
            ->toContain('NIDN. '.$dosenPa->nidn)
            ->toContain('Ketua Program Studi')
            ->toContain(e($kaprodi->user->name))
            ->toContain('NIDN. '.$kaprodi->nidn)
            ->toContain(e($profil->user->name))
            ->toContain('NIM. '.$profil->nim);
    }
});

it('leaves a blank signature line when the advisor is not found', function () {
    $this->seed();
    $profil = User::ofType(UserType::Mahasiswa)->first()->mahasiswaProfile->muatPengesahan();
    $profil->setRelation('dosenWali', null);

    $html = view('pdf.transkrip', kopPdf() + [
        'mahasiswa' => $profil,
        'transkrip' => collect(),
        'ringkasan' => ['totalMatkul' => 0, 'totalSks' => 0, 'totalSksLulus' => 0, 'totalMutu' => 0, 'ipk' => null],
    ])->render();

    expect($html)->toContain('Dosen Pembimbing Akademik')->toContain('NIDN. ....................');
});
