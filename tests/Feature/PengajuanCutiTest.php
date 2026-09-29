<?php

use App\Models\PengajuanAkademik;
use App\Models\PengaturanAkademik;
use App\Models\TahunAkademik;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function tahunCuti(bool $aktif = true, bool $dibuka = true, string $tahun = '2026/2027', string $semester = 'Ganjil'): TahunAkademik
{
    return TahunAkademik::create([
        'tahun' => $tahun, 'semester' => $semester,
        'tanggal_mulai' => now()->subMonth()->toDateString(), 'tanggal_akhir' => now()->addMonths(4)->toDateString(),
        'tanggal_krs_awal' => now()->subMonth()->toDateString(), 'tanggal_krs_akhir' => now()->subWeeks(2)->toDateString(),
        'tanggal_cuti_awal' => $dibuka ? now()->subDay()->toDateString() : null,
        'tanggal_cuti_akhir' => $dibuka ? now()->addWeek()->toDateString() : null,
        'status' => $aktif,
    ]);
}

function isianCuti(TahunAkademik $tahun, array $lain = []): array
{
    return [
        'tahun_akademik_id' => $tahun->id,
        'alasan' => 'Bekerja di luar kota selama satu semester.',
        'bukti_bayar' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ...$lain,
    ];
}

it('lets an active student apply for leave and the admin approve it', function () {
    $tahun = tahunCuti();
    $mhs = User::factory()->mahasiswa()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($mhs)->get(route('mahasiswa.pengajuan-cuti'))->assertInertia(fn ($page) => $page
        ->component('Mahasiswa/PengajuanCuti')
        ->where('cuti.keadaan', 'baru')
        ->where('cuti.tahunOptions.0.id', $tahun->id));

    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), isianCuti($tahun))->assertSessionHasNoErrors()->assertSessionHas('success');
    $pengajuan = PengajuanAkademik::query()->where('jenis', 'cuti')->sole();
    expect($pengajuan->status)->toBe('menunggu')->and($pengajuan->lampiran)->toHaveKey('bukti_bayar');

    // Selama menunggu, form terkunci.
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), isianCuti($tahun))->assertSessionHasErrors('tahun_akademik_id');
    // Berkasnya bisa dibuka admin cuti.
    $this->actingAs($admin)->get(route('berkas.pengajuan-akademik', [$pengajuan, 'bukti_bayar']))->assertOk();

    $this->actingAs($admin)->post(route('admin.pengajuan-cuti.setujui', $pengajuan))->assertSessionHas('success', fn (string $p) => str_contains($p, 'kini Cuti'));
    expect($pengajuan->fresh()->status)->toBe('disetujui')
        ->and($mhs->mahasiswaProfile->fresh()->status)->toBe('Cuti');

    // Mahasiswa Cuti tetap bisa masuk dan kini bisa mengajukan aktif kembali (refresh: relasi profil ikut dimuat ulang).
    $mhs->refresh();
    $this->actingAs($mhs)->get(route('mahasiswa.pengajuan-cuti'))->assertOk()->assertInertia(fn ($page) => $page->where('aktifKembali.keadaan', 'baru'));
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.aktif-kembali'), ['keterangan' => 'Siap kuliah lagi.'])->assertSessionHas('success');
    $aktif = PengajuanAkademik::query()->where('jenis', 'aktif_kembali')->sole();
    $this->actingAs($admin)->post(route('admin.pengajuan-cuti.setujui', $aktif))->assertSessionHas('success');
    expect($mhs->mahasiswaProfile->fresh()->status)->toBe('Aktif');
});

it('applies leave for a future semester when that semester is activated', function () {
    $sekarang = tahunCuti(dibuka: false);
    $depan = tahunCuti(aktif: false, tahun: '2026/2027', semester: 'Genap');
    $mhs = User::factory()->mahasiswa()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), isianCuti($depan))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.pengajuan-cuti.setujui', PengajuanAkademik::query()->sole()))
        ->assertSessionHas('success', fn (string $p) => str_contains($p, 'saat semester itu diaktifkan'));
    expect($mhs->mahasiswaProfile->fresh()->status)->toBe('Aktif');

    $sekarang->update(['status' => false]);
    $depan->update(['status' => true]);
    expect($mhs->mahasiswaProfile->fresh()->status)->toBe('Cuti');
});

it('only offers semesters whose leave period is open', function () {
    tahunCuti(dibuka: false);
    $mhs = User::factory()->mahasiswa()->create();

    $this->actingAs($mhs)->get(route('mahasiswa.pengajuan-cuti'))->assertInertia(fn ($page) => $page
        ->where('cuti.keadaan', 'belum_memenuhi')
        ->where('cuti.alasan', 'Periode pengajuan cuti sedang tidak dibuka.')
        ->has('cuti.tahunOptions', 0));
    $tertutup = TahunAkademik::query()->sole();
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), isianCuti($tertutup))->assertSessionHasErrors('tahun_akademik_id');
    expect(PengajuanAkademik::count())->toBe(0);
});

it('requires payment proof and enforces the leave limit', function () {
    $tahun = tahunCuti();
    $mhs = User::factory()->mahasiswa()->create();

    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), isianCuti($tahun, ['bukti_bayar' => null]))->assertSessionHasErrors('bukti_bayar');

    PengaturanAkademik::current()->update(['maks_cuti' => 1]);
    $lama = tahunCuti(aktif: false, dibuka: false, tahun: '2025/2026');
    PengajuanAkademik::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'jenis' => 'cuti', 'isian' => ['tahun_akademik_id' => $lama->id], 'lampiran' => [], 'status' => 'disetujui']);

    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), isianCuti($tahun))
        ->assertSessionHasErrors(['tahun_akademik_id' => 'Anda sudah mengambil cuti 1 semester, batas cuti selama studi 1 semester.']);
});

it('rejects leave for students who are not active and return for students not on leave', function () {
    $tahun = tahunCuti();
    $mhs = User::factory()->mahasiswa()->create();
    $mhs->mahasiswaProfile->update(['status' => 'Lulus']);

    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), isianCuti($tahun))->assertSessionHasErrors('tahun_akademik_id');
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.aktif-kembali'), [])->assertSessionHasErrors('keterangan');
    expect(PengajuanAkademik::count())->toBe(0);
});

it('lets the admin ask for a fix and the student resend without re-uploading', function () {
    $tahun = tahunCuti();
    $mhs = User::factory()->mahasiswa()->create();
    $admin = User::factory()->admin()->create();
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), isianCuti($tahun));
    $pengajuan = PengajuanAkademik::query()->sole();

    $this->actingAs($admin)->post(route('admin.pengajuan-cuti.perbaikan', $pengajuan), ['catatan' => 'Perjelas alasan.'])->assertSessionHas('success');
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-cuti.ajukan'), isianCuti($tahun, ['alasan' => 'Alasan diperjelas.', 'bukti_bayar' => null]))
        ->assertSessionHasNoErrors();

    expect($pengajuan->fresh())->status->toBe('menunggu')->isian->alasan->toBe('Alasan diperjelas.')
        ->and($pengajuan->fresh()->lampiran)->toHaveKey('bukti_bayar');

    // Pengajuan cuti tidak bisa diproses dari halaman TA & Wisuda.
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $pengajuan))->assertNotFound();
});

it('validates the leave period on the academic year form', function () {
    $admin = User::factory()->admin()->create();
    $data = ['tahun' => '2027/2028', 'semester' => 'Ganjil', 'tanggal_mulai' => '2027-08-01', 'tanggal_akhir' => '2028-01-31',
        'tanggal_krs_awal' => '2027-07-20', 'tanggal_krs_akhir' => '2027-08-10', 'status' => false];

    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), [...$data, 'tanggal_cuti_awal' => '2027-07-01'])
        ->assertSessionHasErrors('tanggal_cuti_akhir');
    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), [...$data, 'tanggal_cuti_awal' => '2027-07-01', 'tanggal_cuti_akhir' => '2028-02-10'])
        ->assertSessionHasErrors('tanggal_cuti_akhir');
    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), [...$data, 'tanggal_cuti_awal' => '2027-07-01', 'tanggal_cuti_akhir' => '2027-08-15'])
        ->assertSessionHasNoErrors();
    expect(TahunAkademik::query()->where('tahun', '2027/2028')->value('tanggal_cuti_akhir')->toDateString())->toBe('2027-08-15');
});
