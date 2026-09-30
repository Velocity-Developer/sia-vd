<?php

use App\Feature;
use App\Models\LogPengaturanFitur;
use App\Models\PengaturanFitur;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('uses the database override over the config default, but never for locked or unregistered features', function () {
    config(['client.fitur.keuangan.default' => true, 'client.fitur.kelola_role.default' => false]);
    PengaturanFitur::atur('keuangan', false);
    PengaturanFitur::atur('kelola_role', true);

    expect(Feature::aktif('keuangan'))->toBeFalse()
        ->and(Feature::aktif('kelola_role'))->toBeTrue();

    // locked = ikut config walau override tersimpan sebelum fitur dikunci.
    config(['client.fitur.keuangan.locked' => true, 'client.fitur.kelola_role.locked' => 'true']);
    expect(Feature::aktif('keuangan'))->toBeTrue()
        ->and(Feature::aktif('kelola_role'))->toBeFalse();

    PengaturanFitur::query()->create(['nama' => 'tidak_ada', 'aktif' => true]);
    expect(Feature::aktif('tidak_ada'))->toBeFalse();
});

it('falls back to the config default once the override is removed', function () {
    config(['client.fitur.keuangan.default' => true]);
    PengaturanFitur::atur('keuangan', false);
    expect(Feature::aktif('keuangan'))->toBeFalse();

    PengaturanFitur::atur('keuangan', null);
    expect(Feature::aktif('keuangan'))->toBeTrue()
        ->and(PengaturanFitur::query()->count())->toBe(0);
});

it('applies overrides to dependencies too', function () {
    config(['client.fitur' => [
        'a' => ['default' => true, 'locked' => false, 'butuh' => ['b']],
        'b' => ['default' => true, 'locked' => false, 'butuh' => []],
    ]]);
    // Ditulis langsung: lewat atur() perubahan ini ditolak karena ikut mematikan `a` (lihat PanelDeveloperTest).
    PengaturanFitur::query()->create(['nama' => 'b', 'aktif' => false]);

    expect(Feature::aktif('a'))->toBeFalse();
});

it('reads every override with one query and serves later checks from the cache', function () {
    PengaturanFitur::atur('keuangan', false);
    PengaturanFitur::atur('kelola_role', true);

    DB::enableQueryLog();
    Feature::aktif('keuangan');
    Feature::aktif('kelola_role');
    Feature::aktif('keuangan');
    expect(DB::getQueryLog())->toHaveCount(1)
        ->and(Cache::get('fitur.override'))->toBe(['keuangan' => false, 'kelola_role' => true]);

    // Request berikutnya (memo request kosong) tetap membaca dari cache, tanpa query.
    app()->forgetScopedInstances();
    DB::flushQueryLog();
    Feature::aktif('keuangan');
    expect(DB::getQueryLog())->toHaveCount(0);

    PengaturanFitur::atur('keuangan', true);
    expect(Cache::has('fitur.override'))->toBeFalse()
        ->and(Feature::aktif('keuangan'))->toBeTrue();
});

it('logs each change with who made it, and skips unchanged values', function () {
    $admin = User::factory()->admin()->create();

    PengaturanFitur::atur('keuangan', false, $admin);
    PengaturanFitur::atur('keuangan', false, $admin);
    PengaturanFitur::atur('keuangan', true);
    PengaturanFitur::atur('keuangan', null, $admin);

    expect(LogPengaturanFitur::query()->orderBy('id')->get(['nama', 'aktif_lama', 'aktif_baru', 'diubah_oleh'])->toArray())->toBe([
        ['nama' => 'keuangan', 'aktif_lama' => null, 'aktif_baru' => false, 'diubah_oleh' => $admin->id],
        ['nama' => 'keuangan', 'aktif_lama' => false, 'aktif_baru' => true, 'diubah_oleh' => null],
        ['nama' => 'keuangan', 'aktif_lama' => true, 'aktif_baru' => null, 'diubah_oleh' => $admin->id],
    ]);
});

it('refuses to override unregistered or locked features', function () {
    config(['client.fitur.keuangan.locked' => true]);

    expect(fn () => PengaturanFitur::atur('tidak_ada', true))->toThrow(InvalidArgumentException::class, 'tidak terdaftar')
        ->and(fn () => PengaturanFitur::atur('keuangan', false))->toThrow(InvalidArgumentException::class, 'dikunci')
        ->and(PengaturanFitur::query()->count())->toBe(0)
        ->and(LogPengaturanFitur::query()->count())->toBe(0);
});

it('treats a missing table as no override without caching the result', function () {
    Schema::drop('pengaturan_fitur');

    expect(Feature::aktif('keuangan'))->toBeTrue()
        ->and(Cache::has('fitur.override'))->toBeFalse();
});
