<?php

use App\Models\PengajuanAkademik;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function tahunBeranda(bool $aktif = true, string $semester = 'Ganjil', bool $krsDibuka = false): TahunAkademik
{
    return TahunAkademik::create([
        'tahun' => '2026/2027', 'semester' => $semester,
        'tanggal_mulai' => now()->subMonth()->toDateString(), 'tanggal_akhir' => now()->addMonths(4)->toDateString(),
        'tanggal_krs_awal' => now()->subWeek()->toDateString(),
        'tanggal_krs_akhir' => ($krsDibuka ? now()->addWeek() : now()->subDay())->toDateString(),
        'tanggal_cuti_awal' => now()->subDay()->toDateString(), 'tanggal_cuti_akhir' => now()->addWeek()->toDateString(),
        'status' => $aktif,
    ]);
}

function ajukanCutiBeranda(User $mhs, TahunAkademik $tahun): PengajuanAkademik
{
    test()->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), [
        'tahun_akademik_id' => $tahun->id,
        'alasan' => 'Bekerja di luar kota.',
    ])->assertSessionHasNoErrors();

    return PengajuanAkademik::query()->where('jenis', PengajuanAkademik::CUTI)->latest('id')->firstOrFail();
}

it('renders the student dashboard with study summary', function () {
    tahunBeranda();
    $mhs = User::factory()->mahasiswa()->create();

    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Mahasiswa/Dashboard')
        ->where('ringkasan.nama', $mhs->name)
        ->where('ringkasan.ipk', null)
        ->where('ringkasan.sks_semester', 0)
        ->where('tahunAkademik', '2026/2027 Ganjil')
        ->where('kuliahHariIni', [])
        ->where('tugasMendatang', []));
});

it('reminds the student about the open KRS period and unpaid semester bill', function () {
    $tahun = tahunBeranda(krsDibuka: true);
    $mhs = User::factory()->mahasiswa()->create();
    TagihanSemester::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'tahun_akademik_id' => $tahun->id, 'status' => TagihanSemester::BELUM_BAYAR, 'total' => 2500000]);

    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page
        ->where('pengingat.semester.pesan.0.teks', fn (string $t) => str_contains($t, 'Rp 2.500.000 belum dibayar'))
        ->where('pengingat.semester.pesan.1.teks', fn (string $t) => str_contains($t, 'KRS Anda belum disimpan'))
        ->where('pengingat.semester.tautan', route('mahasiswa.info-biaya-kuliah')));
});

it('shows leave request progress on the student dashboard (PD-64)', function () {
    $tahun = tahunBeranda();
    $mhs = User::factory()->mahasiswa()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page->where('pengingat.cuti', null));

    $pengajuan = ajukanCutiBeranda($mhs, $tahun);
    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page
        ->where('pengingat.cuti.pesan.0', ['teks' => 'Pengajuan cuti semester 2026/2027 Ganjil menunggu keputusan admin.', 'penting' => false])
        ->where('pengingat.cuti.tautan', route('mahasiswa.pengajuan-cuti')));

    $this->actingAs($admin)->post(route('admin.pengajuan-cuti.perbaikan', $pengajuan), ['catatan' => 'Bukti bayar buram']);
    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page
        ->where('pengingat.cuti.pesan.0', ['teks' => 'Pengajuan cuti semester 2026/2027 Ganjil diminta perbaikan: Bukti bayar buram', 'penting' => true]));

    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), ['tahun_akademik_id' => $tahun->id, 'alasan' => 'Bekerja di luar kota.'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.pengajuan-cuti.setujui', $pengajuan));
    $mhs->refresh();
    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page
        ->has('pengingat.cuti.pesan', 1)
        ->where('pengingat.cuti.pesan.0.teks', fn (string $t) => str_starts_with($t, 'Anda sedang cuti pada semester 2026/2027 Ganjil')));
});

it('reminds the student and the admin when the leave semester has ended (PD-65)', function () {
    $ganjil = tahunBeranda();
    $genap = tahunBeranda(aktif: false, semester: 'Genap');
    $mhs = User::factory()->mahasiswa()->create();
    $admin = User::factory()->admin()->create();

    $pengajuan = ajukanCutiBeranda($mhs, $ganjil);
    $this->actingAs($admin)->post(route('admin.pengajuan-cuti.setujui', $pengajuan))->assertSessionHas('success');
    expect($mhs->mahasiswaProfile->fresh()->status)->toBe('Cuti');

    $judulAdmin = 'Mahasiswa Cuti yang semester cutinya sudah berakhir';
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('tindakan', fn ($t) => collect($t)->where('judul', $judulAdmin)->isEmpty()));

    // Semester berikutnya diaktifkan, status tetap Cuti.
    $ganjil->update(['status' => false]);
    $genap->update(['status' => true]);
    $mhs->refresh();
    expect($mhs->mahasiswaProfile->status)->toBe('Cuti');

    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page
        ->where('pengingat.cuti.pesan', [[
            'teks' => 'Semester cuti Anda (2026/2027 Ganjil) sudah berakhir. Ajukan aktif kembali agar dapat mengisi KRS dan mengikuti perkuliahan.',
            'penting' => true,
        ]]));
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('tindakan', fn ($t) => collect($t)->firstWhere('judul', $judulAdmin)['jumlah'] === 1));

    // Setelah mengajukan aktif kembali, pengingat berganti menjadi status pengajuan dan admin melihatnya sebagai pengajuan menunggu.
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.aktif-kembali'), ['keterangan' => 'Siap kuliah lagi.'])->assertSessionHas('success');
    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page
        ->where('pengingat.cuti.pesan', [['teks' => 'Pengajuan aktif kembali menunggu keputusan admin.', 'penting' => false]]));
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('tindakan', fn ($t) => collect($t)->where('judul', $judulAdmin)->isEmpty()
            && collect($t)->firstWhere('judul', 'Pengajuan aktif kembali')['jumlah'] === 1));
});
