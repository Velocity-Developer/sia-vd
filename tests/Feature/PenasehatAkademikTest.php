<?php

use App\Models\MahasiswaProfile;
use App\Models\User;

function mahasiswaBerNim(string $nim, string $status = 'Aktif'): MahasiswaProfile
{
    $profil = User::factory()->mahasiswa()->create()->mahasiswaProfile;
    $profil->update(['nim' => $nim, 'status' => $status, 'dosen_wali_id' => null]);

    return $profil;
}

it('lists only Aktif and Pindahan students with a NIM, sorted by NIM', function () {
    mahasiswaBerNim('2401003');
    mahasiswaBerNim('2401001', 'Pindahan');
    mahasiswaBerNim('2401002', 'Lulus');

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.penasehat-akademik.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/PenasehatAkademik')
            ->where('mahasiswa', fn ($daftar) => collect($daftar)->pluck('nim')->filter(fn ($nim) => str_starts_with($nim, '2401'))->values()->all() === ['2401001', '2401003']));
});

it('sets the academic advisor for active students within the NIM range', function () {
    $a = mahasiswaBerNim('2402001');
    $b = mahasiswaBerNim('2402002', 'Pindahan');
    $lulus = mahasiswaBerNim('2402003', 'Lulus');
    $c = mahasiswaBerNim('2402004');
    $luar = mahasiswaBerNim('2402005');
    $dosen = User::factory()->dosen()->create()->dosenProfile;

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.penasehat-akademik.update'), ['nim_awal' => '2402001', 'nim_akhir' => '2402004', 'dosen_wali_id' => $dosen->id])
        ->assertRedirect(route('admin.penasehat-akademik.index'))
        ->assertSessionHas('success', fn ($pesan) => str_contains($pesan, '3 mahasiswa'));

    expect(collect([$a, $b, $c])->map(fn ($mhs) => $mhs->fresh()->dosen_wali_id)->all())->toBe([$dosen->id, $dosen->id, $dosen->id])
        ->and($lulus->fresh()->dosen_wali_id)->toBeNull()
        ->and($luar->fresh()->dosen_wali_id)->toBeNull();
});

it('rejects a reversed range, an inactive NIM, and an inactive lecturer', function () {
    mahasiswaBerNim('2403001');
    mahasiswaBerNim('2403002');
    mahasiswaBerNim('2403003', 'Lulus');
    $dosen = User::factory()->dosen()->create()->dosenProfile;
    $nonaktif = User::factory()->dosen()->create()->dosenProfile;
    $nonaktif->update(['status' => 'Nonaktif']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.penasehat-akademik.update'), ['nim_awal' => '2403002', 'nim_akhir' => '2403001', 'dosen_wali_id' => $dosen->id])
        ->assertSessionHasErrors('nim_akhir');
    $this->actingAs($admin)->put(route('admin.penasehat-akademik.update'), ['nim_awal' => '2403001', 'nim_akhir' => '2403003', 'dosen_wali_id' => $dosen->id])
        ->assertSessionHasErrors('nim_akhir');
    $this->actingAs($admin)->put(route('admin.penasehat-akademik.update'), ['nim_awal' => '2403001', 'nim_akhir' => '2403002', 'dosen_wali_id' => $nonaktif->id])
        ->assertSessionHasErrors('dosen_wali_id');

    expect(MahasiswaProfile::whereNotNull('dosen_wali_id')->where('nim', 'like', '2403%')->count())->toBe(0);
});

it('forbids users without the permission', function () {
    $this->actingAs(User::factory()->mahasiswa()->create())->get(route('admin.penasehat-akademik.index'))->assertForbidden();
});
