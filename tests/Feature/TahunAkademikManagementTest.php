<?php

use App\Models\TahunAkademik;
use App\Models\User;

function tahunAkademikAdmin(): User
{
    return User::factory()->admin()->create();
}

function tahunAkademikDosen(): User
{
    return User::factory()->dosen()->create();
}

it('allows admins to manage academic years', function () {
    $admin = tahunAkademikAdmin();
    $payload = ['tahun' => '2025/2026', 'semester' => 'Ganjil', 'tanggal_mulai' => '2025-08-01', 'tanggal_akhir' => '2026-01-31', 'status' => true];
    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), $payload)->assertRedirect();
    $tahunAkademik = TahunAkademik::firstOrFail();
    expect($tahunAkademik->status)->toBeTrue();
    $this->actingAs($admin)->put(route('admin.tahun-akademik.update', $tahunAkademik), array_replace($payload, ['semester' => 'Genap']))->assertRedirect();
    expect($tahunAkademik->fresh()->semester)->toBe('Genap');
});

it('rejects a second active academic year', function () {
    $admin = tahunAkademikAdmin();
    TahunAkademik::create([
        'tahun' => '2025/2026',
        'semester' => 'Ganjil',
        'tanggal_mulai' => '2025-08-01',
        'tanggal_akhir' => '2026-01-31',
        'status' => true,
    ]);

    $this->actingAs($admin)->post(route('admin.tahun-akademik.store'), [
        'tahun' => '2026/2027',
        'semester' => 'Ganjil',
        'tanggal_mulai' => '2026-08-01',
        'tanggal_akhir' => '2027-01-31',
        'status' => true,
    ])->assertSessionHasErrors('status');

    expect(TahunAkademik::where('status', true)->count())->toBe(1);
});

it('denies non-admin academic year access', function () {
    $this->actingAs(tahunAkademikDosen())->get(route('admin.tahun-akademik.index'))->assertForbidden();
});
