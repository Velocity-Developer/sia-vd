<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\PengajuanPindahKelas;
use App\Models\PengaturanPindahKelas;
use App\Models\User;

function createPindahKelasMahasiswa(): array
{
    $mahasiswa = User::factory()->mahasiswa()->create();
    $profile = $mahasiswa->mahasiswaProfile;

    $kelasAsal = createMateriKelasKuliah();
    $kelasTujuan = KelasKuliah::create([
        'kode_kelas' => $kelasAsal->kode_kelas.'-B',
        'kapasitas' => $kelasAsal->kapasitas,
        'dosen_id' => $kelasAsal->dosen_id,
        'matkul_id' => $kelasAsal->matkul_id,
    ]);

    Krs::create(['mahasiswa_id' => $profile->id, 'kelas_id' => $kelasAsal->id, 'status' => 'Aktif']);

    return [$mahasiswa, $profile, $kelasAsal, $kelasTujuan];
}

it('hides the pindah kelas form when the setting is inactive', function () {
    PengaturanPindahKelas::current()->update(['is_active' => false]);
    [$mahasiswa] = createPindahKelasMahasiswa();

    $this->actingAs($mahasiswa)->get(route('mahasiswa.pindah-kelas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/PindahKelas')->where('isActive', false));
});

it('shows the pindah kelas form with active classes when the setting is active', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [$mahasiswa, , $kelasAsal, $kelasTujuan] = createPindahKelasMahasiswa();

    $this->actingAs($mahasiswa)->get(route('mahasiswa.pindah-kelas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mahasiswa/PindahKelas')
            ->where('isActive', true)
            ->where('kelasAsal.0.id', $kelasAsal->id)
            ->where('kelasTujuan', fn ($kelas) => collect($kelas)->pluck('id')->contains($kelasTujuan->id)));
});

it('lets mahasiswa submit a pindah kelas request', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [$mahasiswa, $profile, $kelasAsal, $kelasTujuan] = createPindahKelasMahasiswa();

    $this->actingAs($mahasiswa)->post(route('mahasiswa.pindah-kelas.store'), [
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Jadwal bentrok dengan mata kuliah lain.',
    ])->assertRedirect();

    $pengajuan = PengajuanPindahKelas::first();
    expect($pengajuan->mahasiswa_id)->toBe($profile->id)
        ->and($pengajuan->kelas_asal_id)->toBe($kelasAsal->id)
        ->and($pengajuan->kelas_tujuan_id)->toBe($kelasTujuan->id)
        ->and($pengajuan->status)->toBe(PengajuanPindahKelas::STATUS_PENDING)
        ->and($pengajuan->diproses_oleh)->toBeNull();
});

it('rejects a pindah kelas request when the setting is inactive', function () {
    PengaturanPindahKelas::current()->update(['is_active' => false]);
    [$mahasiswa, , $kelasAsal, $kelasTujuan] = createPindahKelasMahasiswa();

    $this->actingAs($mahasiswa)->post(route('mahasiswa.pindah-kelas.store'), [
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Alasan apa pun.',
    ])->assertForbidden();

    expect(PengajuanPindahKelas::count())->toBe(0);
});

it('rejects a target class identical to the origin class', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [$mahasiswa, , $kelasAsal] = createPindahKelasMahasiswa();

    $this->actingAs($mahasiswa)->post(route('mahasiswa.pindah-kelas.store'), [
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasAsal->id,
        'alasan' => 'Alasan apa pun.',
    ])->assertSessionHasErrors('kelas_tujuan_id');

    expect(PengajuanPindahKelas::count())->toBe(0);
});

it('rejects a second pending request for the same origin class', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [$mahasiswa, $profile, $kelasAsal, $kelasTujuan] = createPindahKelasMahasiswa();

    PengajuanPindahKelas::create([
        'mahasiswa_id' => $profile->id,
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Pengajuan pertama.',
        'status' => PengajuanPindahKelas::STATUS_PENDING,
    ]);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.pindah-kelas.store'), [
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Pengajuan kedua.',
    ])->assertSessionHasErrors('kelas_asal_id');

    expect(PengajuanPindahKelas::count())->toBe(1);
});

it('allows a new request after the previous one was processed', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [$mahasiswa, $profile, $kelasAsal, $kelasTujuan] = createPindahKelasMahasiswa();

    PengajuanPindahKelas::create([
        'mahasiswa_id' => $profile->id,
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Pengajuan lama.',
        'status' => PengajuanPindahKelas::STATUS_DITOLAK,
    ]);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.pindah-kelas.store'), [
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Pengajuan baru.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(PengajuanPindahKelas::count())->toBe(2);
});

it('rejects a target class from a different mata kuliah', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [$mahasiswa, , $kelasAsal] = createPindahKelasMahasiswa();

    $matkulLain = MataKuliah::create([
        'kode_matkul' => 'XX'.bin2hex(random_bytes(3)),
        'nama_matkul' => 'Basis Data',
        'sks' => 3,
        'semester' => 1,
        'jenis' => 'Wajib',
        'prodi_id' => $kelasAsal->mataKuliah->prodi_id,
    ]);
    $kelasLain = createMateriKelasKuliah();
    $kelasLain->update(['matkul_id' => $matkulLain->id]);

    $this->actingAs($mahasiswa)->post(route('mahasiswa.pindah-kelas.store'), [
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasLain->id,
        'alasan' => 'Alasan apa pun.',
    ])->assertSessionHasErrors('kelas_tujuan_id');

    expect(PengajuanPindahKelas::count())->toBe(0);
});

it('rejects a origin class not taken by the mahasiswa', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [$mahasiswa, , , $kelasTujuan] = createPindahKelasMahasiswa();
    $kelasOrangLain = createMateriKelasKuliah();

    $this->actingAs($mahasiswa)->post(route('mahasiswa.pindah-kelas.store'), [
        'kelas_asal_id' => $kelasOrangLain->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Alasan apa pun.',
    ])->assertSessionHasErrors('kelas_asal_id');

    expect(PengajuanPindahKelas::count())->toBe(0);
});

it('shows the submission history to the mahasiswa', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [$mahasiswa, $profile, $kelasAsal, $kelasTujuan] = createPindahKelasMahasiswa();

    $pengajuan = PengajuanPindahKelas::create([
        'mahasiswa_id' => $profile->id,
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Riwayat pengajuan.',
        'status' => PengajuanPindahKelas::STATUS_PENDING,
    ]);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.pindah-kelas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Mahasiswa/PindahKelas')
            ->where('pengajuans.0.id', $pengajuan->id)
            ->where('pengajuans.0.status', PengajuanPindahKelas::STATUS_PENDING));
});

it('forbids non mahasiswa roles from the pindah kelas page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('mahasiswa.pindah-kelas'))->assertForbidden();
    $this->actingAs($admin)->post(route('mahasiswa.pindah-kelas.store'), [])->assertForbidden();
});

it('creates the singleton pengaturan row only once', function () {
    $pengaturan = PengaturanPindahKelas::current();

    expect($pengaturan->id)->toBe(PengaturanPindahKelas::SINGLETON_ID)
        ->and($pengaturan->is_active)->toBeFalse()
        ->and(PengaturanPindahKelas::count())->toBe(1)
        ->and(PengaturanPindahKelas::current()->id)->toBe(PengaturanPindahKelas::SINGLETON_ID);
});

it('does not count a pending request from another mahasiswa', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [, $profile, $kelasAsal, $kelasTujuan] = createPindahKelasMahasiswa();

    $mahasiswaLain = User::factory()->mahasiswa()->create();
    $mahasiswaLain->mahasiswaProfile->update(['prodi_id' => $profile->prodi_id]);
    Krs::create(['mahasiswa_id' => $mahasiswaLain->mahasiswaProfile->id, 'kelas_id' => $kelasAsal->id, 'status' => 'Aktif']);

    PengajuanPindahKelas::create([
        'mahasiswa_id' => $profile->id,
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Pengajuan mahasiswa lain.',
        'status' => PengajuanPindahKelas::STATUS_PENDING,
    ]);

    $this->actingAs($mahasiswaLain)->post(route('mahasiswa.pindah-kelas.store'), [
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Pengajuan mahasiswa lain yang berbeda.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(PengajuanPindahKelas::where('mahasiswa_id', $mahasiswaLain->mahasiswaProfile->id)->count())->toBe(1);
});

it('requires an existing mahasiswa profile', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    $user = User::factory()->mahasiswa()->create();
    $user->mahasiswaProfile()->delete();

    $this->actingAs($user)->get(route('mahasiswa.pindah-kelas'))->assertForbidden();
});

it('removes pengajuan rows when the mahasiswa profile is deleted', function () {
    PengaturanPindahKelas::current()->update(['is_active' => true]);
    [, $profile, $kelasAsal, $kelasTujuan] = createPindahKelasMahasiswa();

    PengajuanPindahKelas::create([
        'mahasiswa_id' => $profile->id,
        'kelas_asal_id' => $kelasAsal->id,
        'kelas_tujuan_id' => $kelasTujuan->id,
        'alasan' => 'Akan dihapus.',
        'status' => PengajuanPindahKelas::STATUS_PENDING,
    ]);

    MahasiswaProfile::query()->whereKey($profile->id)->delete();

    expect(PengajuanPindahKelas::count())->toBe(0);
});
