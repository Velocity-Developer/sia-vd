<?php

use App\Models\PengaturanPmb;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function periodePmbPayload(array $ubah = []): array
{
    return [
        'kode' => 'PMB2027-1',
        'tahun_angkatan' => 2027,
        'tanggal_buka' => '2027-01-02',
        'tanggal_tutup' => '2027-03-31',
        'tanggal_usm_mulai' => '2027-04-05',
        'tanggal_usm_selesai' => '2027-04-07',
        'tanggal_her' => '2027-05-10',
        'nilai_minimal' => 60.5,
        'kapasitas' => 120,
        'biaya_pendaftaran' => 250000,
        'tanggal_pembayaran_mulai' => '2027-01-02',
        'tanggal_pembayaran_selesai' => '2027-04-01',
        'is_open' => true,
        ...$ubah,
    ];
}

it('mengelola periode PMB', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.periode-pmb.store'), periodePmbPayload())
        ->assertRedirect(route('admin.periode-pmb.index'));
    $periode = PengaturanPmb::query()->where('kode', 'PMB2027-1')->firstOrFail();
    expect($periode->is_open)->toBeTrue()
        ->and($periode->nilai_minimal)->toBe(60.5)
        ->and($periode->tanggal_her->toDateString())->toBe('2027-05-10');

    $this->actingAs($admin)->post(route('admin.periode-pmb.store'), periodePmbPayload())
        ->assertSessionHasErrors('kode');

    $this->actingAs($admin)->put(route('admin.periode-pmb.update', $periode), periodePmbPayload(['kapasitas' => 80, 'is_open' => false]))
        ->assertRedirect(route('admin.periode-pmb.index'));
    expect($periode->fresh())->kapasitas->toBe(80)->is_open->toBeFalse();

    $this->actingAs($admin)->get(route('admin.periode-pmb.index', ['search' => '2027']))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/PengaturanPmb')->where('periode.total', 1));
    $this->actingAs($admin)->get(route('admin.periode-pmb.edit', $periode))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/PengaturanPmbForm')->where('pengaturanPmb.tanggal_buka', '2027-01-02'));

    $this->actingAs($admin)->delete(route('admin.periode-pmb.destroy', $periode))->assertRedirect(route('admin.periode-pmb.index'));
    expect(PengaturanPmb::query()->count())->toBe(0);
});

it('menolak rentang tanggal periode PMB yang terbalik', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.periode-pmb.store'), periodePmbPayload([
        'tanggal_tutup' => '2027-01-01',
        'tanggal_usm_selesai' => '2027-04-01',
        'tanggal_pembayaran_selesai' => '2026-12-31',
        'nilai_minimal' => 101,
        'kapasitas' => 0,
    ]))->assertSessionHasErrors(['tanggal_tutup', 'tanggal_usm_selesai', 'tanggal_pembayaran_selesai', 'nilai_minimal', 'kapasitas']);
    expect(PengaturanPmb::query()->count())->toBe(0);
});

it('menolak dua periode PMB dibuka dengan tanggal bertumpuk', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.periode-pmb.store'), periodePmbPayload());

    $this->actingAs($admin)->post(route('admin.periode-pmb.store'), periodePmbPayload(['kode' => 'PMB2027-2', 'tanggal_buka' => '2027-03-01', 'tanggal_tutup' => '2027-05-31']))
        ->assertSessionHasErrors('is_open');
    // Ditutup, atau dibuka setelah periode pertama berakhir, tetap boleh.
    $this->actingAs($admin)->post(route('admin.periode-pmb.store'), periodePmbPayload(['kode' => 'PMB2027-2', 'is_open' => false]))
        ->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.periode-pmb.store'), periodePmbPayload(['kode' => 'PMB2027-3', 'tanggal_buka' => '2027-04-01', 'tanggal_tutup' => '2027-05-31']))
        ->assertSessionHasNoErrors();

    // Mengedit periode yang sama tidak dianggap bertumpuk dengan dirinya sendiri.
    $pertama = PengaturanPmb::query()->where('kode', 'PMB2027-1')->firstOrFail();
    $this->actingAs($admin)->put(route('admin.periode-pmb.update', $pertama), periodePmbPayload(['kapasitas' => 50]))->assertSessionHasNoErrors();
});

it('membatasi periode PMB untuk pemegang izin', function () {
    $mahasiswa = User::factory()->mahasiswa()->create();

    $this->actingAs($mahasiswa)->get(route('admin.periode-pmb.index'))->assertForbidden();
});
