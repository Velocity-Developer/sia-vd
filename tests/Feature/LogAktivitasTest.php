<?php

use App\Models\LogAktivitas;
use App\Models\PengaturanEmail;
use App\Models\Ruang;
use App\Models\User;

it('records changes made by signed-in users and shows them to admin', function () {
    $admin = User::factory()->admin()->create();

    // Tanpa pengguna login (seeder, konsol) tidak dicatat.
    Ruang::create(['kode_ruang' => 'R-01', 'nama_ruang' => 'Ruang Satu', 'kapasitas' => 30]);
    expect(LogAktivitas::query()->count())->toBe(0);

    $this->actingAs($admin);
    $ruang = Ruang::create(['kode_ruang' => 'R-02', 'nama_ruang' => 'Ruang Dua', 'kapasitas' => 30]);
    $ruang->update(['kapasitas' => 40]);
    $ruang->update(['kapasitas' => 40]);
    $ruang->delete();

    $log = LogAktivitas::query()->where('objek_tipe', 'Ruang')->orderBy('id')->get();
    expect($log->pluck('aksi')->all())->toBe(['dibuat', 'diubah', 'dihapus'])
        ->and($log[1]->perubahan)->toBe(['kapasitas' => ['lama' => 30, 'baru' => 40]])
        ->and($log[1]->user_id)->toBe($admin->id)
        ->and($log[0]->label)->toBe('Ruang Dua');

    $this->get(route('admin.log-aktivitas.index', ['aksi' => 'diubah']))
        ->assertInertia(fn ($page) => $page->component('Admin/LogAktivitas')->where('log.total', 1)->where('log.data.0.objek', 'Ruang'));
    $this->actingAs(User::factory()->dosen()->create())->get(route('admin.log-aktivitas.index'))->assertForbidden();
});

it('never stores secret values', function () {
    $this->actingAs(User::factory()->admin()->create());
    PengaturanEmail::query()->create(['mailer' => 'smtp', 'host' => 'mail.test', 'port' => 465, 'username' => 'a', 'password' => 'rahasia-sekali', 'from_address' => 'a@b.test', 'from_name' => 'A']);

    $log = LogAktivitas::query()->where('objek_tipe', 'PengaturanEmail')->sole();
    expect($log->perubahan['password']['baru'])->toBe('••••••')
        ->and(json_encode($log->perubahan))->not->toContain('rahasia-sekali');
});
