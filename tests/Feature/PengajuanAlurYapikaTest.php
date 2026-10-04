<?php

use App\Models\GelombangKompre;
use App\Models\PengajuanAkademik;
use App\Models\TugasAkhir;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Alur Pengajuan & Pendaftaran Yapika: dosen PA melihat pengajuan mahasiswanya, pendaftaran sidang saat pendadaran
 * mati, dan gelombang ujian komprehensif.
 */
beforeEach(fn () => Storage::fake('local'));

function berkasSyarat(array $lain = []): array
{
    return ['judul' => 'Topik', 'berkas_syarat' => UploadedFile::fake()->create('syarat.pdf', 50, 'application/pdf'), ...$lain];
}

function gelombangKompre(array $ubah = []): GelombangKompre
{
    return GelombangKompre::create([
        'nama' => 'Gelombang I', 'tanggal_buka' => today()->subDay()->toDateString(), 'tanggal_tutup' => today()->addDays(5)->toDateString(),
        'tanggal_ujian' => today()->addDays(10)->toDateString(), 'kuota' => null, ...$ubah,
    ]);
}

it('shows advisees submissions read-only to their academic advisor', function () {
    $dosen = User::factory()->dosen()->create();
    $lain = User::factory()->dosen()->create();
    $mhs = User::factory()->mahasiswa()->create();
    $mhs->mahasiswaProfile->update(['dosen_wali_id' => $dosen->dosenProfile->id]);
    $bukanPa = User::factory()->mahasiswa()->create();
    $bukanPa->mahasiswaProfile->update(['dosen_wali_id' => $lain->dosenProfile->id]);

    $p = PengajuanAkademik::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'jenis' => 'ppl', 'isian' => ['judul' => 'RS Labuang Baji'], 'lampiran' => ['berkas_syarat' => 'pengajuan-akademik/a.pdf'], 'status' => 'menunggu', 'diajukan_at' => now()]);
    PengajuanAkademik::create(['mahasiswa_id' => $bukanPa->mahasiswaProfile->id, 'jenis' => 'ppl', 'isian' => ['judul' => 'Lain'], 'lampiran' => [], 'status' => 'menunggu']);
    Storage::disk('local')->put('pengajuan-akademik/a.pdf', 'isi');

    $this->actingAs($dosen)->get(route('dosen.pengajuan-pa.index'))->assertInertia(fn ($page) => $page
        ->component('Dosen/PengajuanMahasiswaPa')
        ->has('pengajuan.data', 1)
        ->where('pengajuan.data.0.ringkasan', 'RS Labuang Baji')
        ->where('pengajuan.data.0.jenis', 'PPL'));
    $this->actingAs($dosen)->get(route('berkas.pengajuan-akademik', [$p, 'berkas_syarat']))->assertOk();

    // Dosen lain (bukan PA) tidak melihatnya; dosen tidak bisa memproses.
    $this->flushSession()->actingAs($lain)->get(route('dosen.pengajuan-pa.index'))
        ->assertInertia(fn ($page) => $page->has('pengajuan.data', 1)->where('pengajuan.data.0.ringkasan', 'Lain'));
    $this->actingAs($lain)->get(route('berkas.pengajuan-akademik', [$p, 'berkas_syarat']))->assertForbidden();
    $this->actingAs($dosen)->post(route('admin.pengajuan-akademik.setujui', $p))->assertForbidden();
});

it('lets students register for the thesis defense when pendadaran is off', function () {
    config(['client.fitur.pendadaran.default' => false]);
    $mhs = User::factory()->mahasiswa()->create();
    $admin = User::factory()->admin()->create();

    // Belum ada judul TA yang disahkan.
    $this->actingAs($mhs)->get(route('mahasiswa.pendaftaran-sidang'))->assertInertia(fn ($page) => $page->where('keadaan', 'belum_memenuhi'));
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'sidang'), berkasSyarat())->assertSessionHasErrors('judul');

    $ta = TugasAkhir::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'judul' => 'Status Gizi Balita', 'bidang' => 'Gizi', 'status' => TugasAkhir::BERJALAN]);
    $this->actingAs($mhs)->get(route('mahasiswa.pendaftaran-sidang'))
        ->assertInertia(fn ($page) => $page->where('keadaan', 'baru')->where('judulAwal', 'Status Gizi Balita'));
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'sidang'), berkasSyarat(['judul' => 'Status Gizi Balita']))->assertSessionHas('success');
    $p = PengajuanAkademik::query()->where('jenis', 'sidang')->sole();
    expect($p->tugas_akhir_id)->toBe($ta->id);

    $this->actingAs($admin)->get(route('admin.pendaftaran-sidang.index'))->assertInertia(fn ($page) => $page->has('pengajuan.data', 1));
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p))->assertSessionHas('success');
    expect($p->fresh()->status)->toBe(PengajuanAkademik::DISETUJUI);
});

it('hides the simple defense registration while pendadaran is on', function () {
    $mhs = User::factory()->mahasiswa()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($mhs)->get('/mahasiswa/pendaftaran-sidang')->assertNotFound();
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'sidang'), berkasSyarat())->assertNotFound();
    $this->actingAs($admin)->get('/admin/pendaftaran-sidang')->assertNotFound();
});

it('manages comprehensive exam waves and requires an open one', function () {
    $admin = User::factory()->admin()->create();
    $mhs = User::factory()->mahasiswa()->create();

    $this->actingAs($admin)->post(route('admin.gelombang-kompre.store'), [
        'nama' => 'Gelombang I', 'tanggal_buka' => today()->toDateString(), 'tanggal_tutup' => today()->subDay()->toDateString(), 'tanggal_ujian' => today()->toDateString(),
    ])->assertSessionHasErrors('tanggal_tutup');
    $this->actingAs($admin)->post(route('admin.gelombang-kompre.store'), [
        'nama' => 'Gelombang I', 'tanggal_buka' => today()->toDateString(), 'tanggal_tutup' => today()->addDays(3)->toDateString(),
        'tanggal_ujian' => today()->addDays(7)->toDateString(), 'kuota' => 1,
    ])->assertSessionHas('success');
    $buka = GelombangKompre::sole();
    $tutup = gelombangKompre(['nama' => 'Lama', 'tanggal_buka' => today()->subDays(10)->toDateString(), 'tanggal_tutup' => today()->subDays(5)->toDateString(), 'tanggal_ujian' => today()->subDays(2)->toDateString()]);

    $this->actingAs($mhs)->get(route('mahasiswa.pengajuan-kompre'))
        ->assertInertia(fn ($page) => $page->has('gelombangOptions', 1)->where('gelombangOptions.0.id', $buka->id));
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kompre'), berkasSyarat())->assertSessionHasErrors('gelombang_kompre_id');
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kompre'), berkasSyarat(['gelombang_kompre_id' => $tutup->id]))->assertSessionHasErrors('gelombang_kompre_id');
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kompre'), berkasSyarat(['gelombang_kompre_id' => (string) $buka->id]))->assertSessionHas('success');

    $p = PengajuanAkademik::query()->where('jenis', 'kompre')->sole();
    expect($p->isian['gelombang_kompre_id'])->toBe($buka->id)
        ->and($buka->pendaftar()->count())->toBe(1)->and($buka->bisaDidaftar())->toBeFalse();

    // Kuota penuh: mahasiswa lain tidak bisa memilihnya; perbaikan milik sendiri tetap boleh.
    $lain = User::factory()->mahasiswa()->create();
    $this->flushSession()->actingAs($lain)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kompre'), berkasSyarat(['gelombang_kompre_id' => $buka->id]))
        ->assertSessionHasErrors('gelombang_kompre_id');
    $this->flushSession()->actingAs($admin)->post(route('admin.pengajuan-akademik.perbaikan', $p), ['catatan' => 'Lengkapi berkas.']);
    $this->flushSession()->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kompre'), ['judul' => 'Topik', 'gelombang_kompre_id' => $buka->id])
        ->assertSessionHas('success');

    $this->flushSession()->actingAs($admin)->get(route('admin.pengajuan-kompre.index'))
        ->assertInertia(fn ($page) => $page->where('pengajuan.data.0.gelombang_kompre.nama', 'Gelombang I'));
    $this->actingAs($admin)->get(route('admin.gelombang-kompre.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/GelombangKompre')->where('gelombang.0.jumlah_pendaftar', 1));
    $this->actingAs($admin)->delete(route('admin.gelombang-kompre.destroy', $buka))->assertSessionHas('error');
    $this->actingAs($admin)->put(route('admin.gelombang-kompre.update', $buka), [
        'nama' => 'Gelombang I', 'tanggal_buka' => $buka->tanggal_buka->toDateString(), 'tanggal_tutup' => $buka->tanggal_tutup->toDateString(),
        'tanggal_ujian' => $buka->tanggal_ujian->toDateString(), 'kuota' => 5,
    ])->assertSessionHas('success');
    $this->actingAs($admin)->delete(route('admin.gelombang-kompre.destroy', $tutup))->assertSessionHas('success');
});
