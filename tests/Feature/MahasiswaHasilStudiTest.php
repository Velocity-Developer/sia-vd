<?php

use App\Models\Krs;
use App\Models\PengaturanInstitusi;
use App\Models\TahunAkademik;
use App\Models\User;
use App\UserType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('shows student study results filtered by selected academic year', function () {
    $this->seed();

    $mahasiswa = User::ofType(UserType::Mahasiswa)->first();
    $profil = $mahasiswa->mahasiswaProfile;
    // Tahun akademik pada data demo relatif terhadap hari ini, jadi diambil yang paling lama.
    $tahunAkademik = TahunAkademik::orderBy('tanggal_mulai')->first();
    $expectedCount = Krs::where('mahasiswa_id', $profil->id)->whereHas('kelasKuliah', fn ($query) => $query->where('tahun_akademik_id', $tahunAkademik->id))->count();

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.hasil-studi', ['tahun_akademik_id' => $tahunAkademik->id]))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Mahasiswa/HasilStudi')
            ->where('tahunAkademikTerpilih', $tahunAkademik->id)
            ->has('krs', $expectedCount)
            ->has('tahunAkademiks', TahunAkademik::count())
        );
});

it('defaults study results to active academic year', function () {
    $this->seed();

    $mahasiswa = User::ofType(UserType::Mahasiswa)->first();
    $activeYear = TahunAkademik::where('status', true)->first();

    $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.hasil-studi'))
        ->assertInertia(fn ($page) => $page->where('tahunAkademikTerpilih', $activeYear->id));
});

it('downloads study result card as pdf for the selected academic year', function () {
    $this->seed();

    $mahasiswa = User::ofType(UserType::Mahasiswa)->first();
    // Tahun akademik pada data demo relatif terhadap hari ini, jadi diambil yang paling lama.
    $tahunAkademik = TahunAkademik::orderBy('tanggal_mulai')->first();

    $response = $this->actingAs($mahasiswa)
        ->get(route('mahasiswa.hasil-studi.download', ['tahun_akademik_id' => $tahunAkademik->id]))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain("khs-{$mahasiswa->mahasiswaProfile->nim}-".str_replace('/', '-', $tahunAkademik->tahun)."-{$tahunAkademik->semester}.pdf");
});

it('downloads the study result card with the institution logo', function () {
    Storage::fake('public');
    $this->seed();

    $path = UploadedFile::fake()->image('logo.png')->store('institusi', 'public');
    PengaturanInstitusi::current()->update(['logo' => $path, 'nama_pt' => 'Universitas Contoh Nusantara']);

    $this->actingAs(User::ofType(UserType::Mahasiswa)->first())
        ->get(route('mahasiswa.hasil-studi.download'))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');
});

it('forbids study result card download for user without student profile', function () {
    $this->seed();

    $admin = User::ofType(UserType::Admin)->first();

    $this->actingAs($admin)
        ->get(route('mahasiswa.hasil-studi.download'))
        ->assertForbidden();
});

it('renders the study result card header from the institution settings', function () {
    $this->seed();

    $institusi = PengaturanInstitusi::current();
    $institusi->update([
        'nama_pt' => 'Universitas Contoh Nusantara',
        'alamat' => 'Jl. Pendidikan No. 1, Bandung',
        'telepon' => '022-1234567',
        'email' => 'info@example.ac.id',
        'website' => 'https://example.ac.id',
    ]);

    $mahasiswa = User::ofType(UserType::Mahasiswa)->first()->mahasiswaProfile;

    $html = view('pdf.khs', [
        'institusi' => $institusi->refresh(),
        'logoSrc' => null,
        'kontak' => ['Jl. Pendidikan No. 1, Bandung', 'Telp. 022-1234567', 'info@example.ac.id', 'https://example.ac.id'],
        'mahasiswa' => $mahasiswa->loadMissing('user', 'prodi'),
        'tahunAkademik' => null,
        'krs' => collect(),
        'ringkasan' => ['totalSks' => 0, 'totalSksDinilai' => 0, 'totalMutu' => 0, 'ip' => null],
    ])->render();

    expect($html)
        ->toContain('Universitas Contoh Nusantara')
        ->toContain('Jl. Pendidikan No. 1, Bandung')
        ->toContain('Telp. 022-1234567')
        ->toContain('info@example.ac.id')
        ->toContain('https://example.ac.id')
        ->not->toContain(config('app.name'));
});

it('embeds the institution logo into the study result card header', function () {
    Storage::fake('public');
    $this->seed();

    $path = UploadedFile::fake()->image('logo.png')->store('institusi', 'public');
    PengaturanInstitusi::current()->update(['logo' => $path]);

    $institusi = PengaturanInstitusi::current();
    $logoSrc = 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($path));

    $html = view('pdf.khs', [
        'institusi' => $institusi,
        'logoSrc' => $logoSrc,
        'kontak' => [],
        'mahasiswa' => User::ofType(UserType::Mahasiswa)->first()->mahasiswaProfile->loadMissing('user', 'prodi'),
        'tahunAkademik' => null,
        'krs' => collect(),
        'ringkasan' => ['totalSks' => 0, 'totalSksDinilai' => 0, 'totalMutu' => 0, 'ip' => null],
    ])->render();

    expect($html)->toContain($logoSrc);
});

it('adds the institution data to the shared inertia props', function () {
    $this->seed();

    $institusi = PengaturanInstitusi::current();
    $institusi->update(['nama_pt' => 'Universitas Contoh Nusantara', 'singkatan' => 'UCN']);

    $this->actingAs(User::ofType(UserType::Mahasiswa)->first())
        ->get(route('mahasiswa.hasil-studi'))
        ->assertInertia(fn ($page) => $page
            ->where('institusi.nama_pt', 'Universitas Contoh Nusantara')
            ->where('institusi.singkatan', 'UCN')
        );
});
