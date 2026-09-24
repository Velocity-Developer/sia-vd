<?php

use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengajuanPindahKelas;
use App\Models\PengaturanPindahKelas;
use App\Models\Ruang;
use App\Models\User;

function createAdminPindahKelas(): array
{
    $admin = User::factory()->admin()->create();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $profile = $mahasiswa->mahasiswaProfile;

    $kelasAsal = createMateriKelasKuliah();
    $kelasTujuan = KelasKuliah::create([
        'kode_kelas' => $kelasAsal->kode_kelas.'-B',
        'kapasitas' => $kelasAsal->kapasitas,
        'dosen_id' => $kelasAsal->dosen_id,
        'matkul_id' => $kelasAsal->matkul_id,
        'tahun_akademik_id' => $kelasAsal->tahun_akademik_id,
    ]);

    $krs = Krs::create(['mahasiswa_id' => $profile->id, 'kelas_id' => $kelasAsal->id, 'status' => 'Aktif']);

    $pengajuan = PengajuanPindahKelas::create([
        'mahasiswa_id' => $profile->id,
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Jadwal bentrok.',
        'status' => PengajuanPindahKelas::STATUS_PENDING,
    ]);

    return [$admin, $mahasiswa, $profile, $kelasAsal, $kelasTujuan, $krs, $pengajuan];
}

it('lets admin open the pindah kelas management page', function () {
    [$admin, , , , , , $pengajuan] = createAdminPindahKelas();

    $this->actingAs($admin)->get(route('admin.pindah-kelas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/PindahKelas')
            ->where('isActive', false)
            ->where('pengajuans.data.0.id', $pengajuan->id)
            ->where('pengajuans.data.0.nilai_terisi', false));
});

it('toggles the pindah kelas form setting', function () {
    PengaturanPindahKelas::current()->update(['is_active' => false]);
    [$admin] = createAdminPindahKelas();

    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.pindah-kelas'), ['is_active' => true])
        ->assertRedirect()
        ->assertSessionHas('success');

    $pengaturan = PengaturanPindahKelas::current();
    expect($pengaturan->is_active)->toBeTrue()
        ->and($pengaturan->updated_by)->toBe($admin->id);

    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.pindah-kelas'), ['is_active' => false])->assertRedirect();
    expect(PengaturanPindahKelas::current()->is_active)->toBeFalse();
});

it('approves a pengajuan by moving the krs row to the target class', function () {
    [$admin, , $profile, $kelasAsal, $kelasTujuan, $krs, $pengajuan] = createAdminPindahKelas();

    $this->actingAs($admin)->put(route('admin.pindah-kelas.approve', $pengajuan))
        ->assertRedirect()
        ->assertSessionHas('success');

    $krs->refresh();
    expect($krs->kelas_id)->toBe($kelasTujuan->id)
        ->and($krs->mahasiswa_id)->toBe($profile->id)
        ->and(Krs::where('mahasiswa_id', $profile->id)->where('kelas_id', $kelasAsal->id)->exists())->toBeFalse();

    $pengajuan->refresh();
    expect($pengajuan->status)->toBe(PengajuanPindahKelas::STATUS_DISETUJUI)
        ->and($pengajuan->diproses_oleh)->toBe($admin->id)
        ->and($pengajuan->diproses_at)->not->toBeNull();
});

it('keeps the krs row id and grade when approving', function () {
    [$admin, , , , $kelasTujuan, $krs, $pengajuan] = createAdminPindahKelas();
    $krs->update(['nilai' => 'A']);
    $originalId = $krs->id;

    $this->actingAs($admin)->put(route('admin.pindah-kelas.approve', $pengajuan), ['force' => true])->assertRedirect();

    $moved = Krs::findOrFail($originalId);
    expect($moved->kelas_id)->toBe($kelasTujuan->id)
        ->and($moved->nilai)->toBe('A')
        ->and(Krs::count())->toBe(1);
});

it('warns the admin when the krs row already has a grade', function () {
    [$admin, , , , $kelasTujuan, $krs, $pengajuan] = createAdminPindahKelas();
    $krs->update(['nilai' => 'B']);

    $this->actingAs($admin)->put(route('admin.pindah-kelas.approve', $pengajuan))
        ->assertRedirect()
        ->assertSessionHas('pindah_kelas_warning');

    expect($pengajuan->fresh()->status)->toBe(PengajuanPindahKelas::STATUS_PENDING)
        ->and($krs->fresh()->kelas_id)->not->toBe($kelasTujuan->id);
});

it('approves past a full target class because admin overrides capacity', function () {
    [$admin, , , , $kelasTujuan, , $pengajuan] = createAdminPindahKelas();
    $kelasTujuan->update(['kapasitas' => 1]);

    $lain = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $lain->mahasiswaProfile->id, 'kelas_id' => $kelasTujuan->id, 'status' => 'Aktif']);

    $this->actingAs($admin)->put(route('admin.pindah-kelas.approve', $pengajuan))->assertRedirect();

    expect($pengajuan->fresh()->status)->toBe(PengajuanPindahKelas::STATUS_DISETUJUI)
        ->and($kelasTujuan->krs()->count())->toBe(2);
});

it('rejects a pengajuan with a note without touching krs', function () {
    [$admin, , , $kelasAsal, , $krs, $pengajuan] = createAdminPindahKelas();

    $this->actingAs($admin)->put(route('admin.pindah-kelas.reject', $pengajuan), ['catatan_admin' => 'Kelas tujuan sudah tidak tersedia.'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $pengajuan->refresh();
    expect($pengajuan->status)->toBe(PengajuanPindahKelas::STATUS_DITOLAK)
        ->and($pengajuan->catatan_admin)->toBe('Kelas tujuan sudah tidak tersedia.')
        ->and($pengajuan->diproses_oleh)->toBe($admin->id)
        ->and($krs->fresh()->kelas_id)->toBe($kelasAsal->id);
});

it('requires a note when rejecting', function () {
    [$admin, , , , , , $pengajuan] = createAdminPindahKelas();

    $this->actingAs($admin)->put(route('admin.pindah-kelas.reject', $pengajuan), [])
        ->assertSessionHasErrors('catatan_admin');

    expect($pengajuan->fresh()->status)->toBe(PengajuanPindahKelas::STATUS_PENDING);
});

it('does not process a pengajuan that is not pending', function () {
    [$admin, , , , , , $pengajuan] = createAdminPindahKelas();
    $pengajuan->update(['status' => PengajuanPindahKelas::STATUS_DISETUJUI]);

    $this->actingAs($admin)->put(route('admin.pindah-kelas.approve', $pengajuan))->assertNotFound();
    $this->actingAs($admin)->put(route('admin.pindah-kelas.reject', $pengajuan), ['catatan_admin' => 'Apa pun.'])->assertNotFound();
});

it('fails approve when the origin krs row is missing', function () {
    [$admin, , , , , $krs, $pengajuan] = createAdminPindahKelas();
    $krs->delete();

    $this->actingAs($admin)->put(route('admin.pindah-kelas.approve', $pengajuan))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($pengajuan->fresh()->status)->toBe(PengajuanPindahKelas::STATUS_PENDING);
});

it('forbids non admin roles from managing pindah kelas', function () {
    [, $mahasiswa, , , , , $pengajuan] = createAdminPindahKelas();

    $this->actingAs($mahasiswa)->get(route('admin.pindah-kelas.index'))->assertForbidden();
    $this->actingAs($mahasiswa)->put(route('admin.pengaturan-akademik.pindah-kelas'), ['is_active' => true])->assertForbidden();
    $this->actingAs($mahasiswa)->put(route('admin.pindah-kelas.approve', $pengajuan))->assertForbidden();
    $this->actingAs($mahasiswa)->put(route('admin.pindah-kelas.reject', $pengajuan), ['catatan_admin' => 'X'])->assertForbidden();
});

it('warns before approving a move that clashes with the student schedule', function () {
    [$admin, , , $kelasAsal, $kelasTujuan, , $pengajuan] = createAdminPindahKelas();
    $ruang = Ruang::create(['kode_ruang' => 'R-901', 'nama_ruang' => 'Ruang 901', 'kapasitas' => 30]);
    $kelasLain = KelasKuliah::create(['kode_kelas' => 'LAIN-A', 'tahun_akademik_id' => $kelasAsal->tahun_akademik_id, 'kapasitas' => 30, 'dosen_id' => $kelasAsal->dosen_id, 'matkul_id' => $kelasAsal->matkul_id]);
    Krs::create(['mahasiswa_id' => $pengajuan->mahasiswa_id, 'kelas_id' => $kelasLain->id, 'status' => 'Aktif']);
    Jadwal::create(['kelas_id' => $kelasLain->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruang->id]);
    Jadwal::create(['kelas_id' => $kelasTujuan->id, 'hari' => 'Senin', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'ruang_id' => $ruang->id]);

    $this->actingAs($admin)->put(route('admin.pindah-kelas.approve', $pengajuan))
        ->assertSessionHas('pindah_kelas_warning', fn (string $pesan): bool => str_contains($pesan, 'bentrok'));
    expect($pengajuan->fresh()->status)->toBe(PengajuanPindahKelas::STATUS_PENDING);

    $this->actingAs($admin)->put(route('admin.pindah-kelas.approve', $pengajuan), ['force' => true])->assertSessionHas('success');
    expect($pengajuan->fresh()->status)->toBe(PengajuanPindahKelas::STATUS_DISETUJUI);
});
