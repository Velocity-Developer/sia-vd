<?php

use App\Feature;
use Illuminate\Support\Env;

it('registers the client features and lists their env keys in .env.example', function () {
    expect(array_keys(config('client.fitur')))->toBe(['kelola_role', 'keuangan']);

    $contoh = file_get_contents(base_path('.env.example'));
    foreach (['KELOLA_ROLE', 'KEUANGAN'] as $kunci) {
        expect($contoh)->toContain("FEATURE_{$kunci}=false")->toContain("LOCK_{$kunci}=false");
    }
});

it('keeps every feature off and unlocked when the env keys are not set', function () {
    // Kosongkan sementara FEATURE_*/LOCK_* yang mungkin diisi .env mesin ini, lalu baca config dari berkasnya
    // (TestCase menyalakan sebagian fitur untuk tes lama).
    $repo = Env::getRepository();
    $kunci = collect(['KELOLA_ROLE', 'KEUANGAN'])->flatMap(fn (string $k): array => ["FEATURE_{$k}", "LOCK_{$k}"]);
    $asli = $kunci->mapWithKeys(fn (string $k): array => [$k => $repo->get($k)]);
    $kunci->each(fn (string $k) => $repo->clear($k));

    try {
        config(['client' => require config_path('client.php')]);
    } finally {
        $asli->filter(fn ($v) => $v !== null)->each(fn (string $v, string $k) => $repo->set($k, $v));
    }

    foreach (config('client.fitur') as $nama => $fitur) {
        expect($fitur['default'])->toBeFalse()
            ->and($fitur['locked'])->toBeFalse()
            ->and($fitur['butuh'])->toBe([])
            ->and(Feature::aktif($nama))->toBeFalse();
    }
});

it('treats unregistered features as off', function () {
    expect(Feature::aktif('tidak_ada'))->toBeFalse()
        ->and(Feature::aktif(''))->toBeFalse()
        ->and(Feature::aktif('keuangan.default'))->toBeFalse();
});

it('turns a feature on from its default value', function () {
    config(['client.fitur.keuangan.default' => true, 'client.fitur.kelola_role.default' => false]);

    expect(Feature::aktif('keuangan'))->toBeTrue()
        ->and(Feature::aktif('kelola_role'))->toBeFalse();
});

it('reads string env values as booleans', function () {
    config(['client.fitur.keuangan.default' => 'false', 'client.fitur.kelola_role.default' => 'true']);

    expect(Feature::aktif('keuangan'))->toBeFalse()
        ->and(Feature::aktif('kelola_role'))->toBeTrue();
});

it('does not use locked to decide the status', function () {
    config(['client.fitur.kelola_role' => ['default' => false, 'locked' => true, 'butuh' => []]]);
    expect(Feature::aktif('kelola_role'))->toBeFalse()
        ->and(config('client.fitur.kelola_role.locked'))->toBeTrue();

    config(['client.fitur.kelola_role.default' => true]);
    expect(Feature::aktif('kelola_role'))->toBeTrue();
});

it('turns a feature off when one of its dependencies is off', function () {
    config(['client.fitur' => [
        'a' => ['default' => true, 'locked' => false, 'butuh' => ['b']],
        'b' => ['default' => true, 'locked' => false, 'butuh' => ['c']],
        'c' => ['default' => false, 'locked' => false, 'butuh' => []],
        'd' => ['default' => true, 'locked' => false, 'butuh' => ['tidak_ada']],
    ]]);

    expect(Feature::aktif('a'))->toBeFalse()
        ->and(Feature::aktif('b'))->toBeFalse()
        ->and(Feature::aktif('d'))->toBeFalse();

    config(['client.fitur.c.default' => true]);

    expect(Feature::aktif('a'))->toBeTrue()
        ->and(Feature::aktif('b'))->toBeTrue();
});

it('treats circular dependencies as off without looping', function () {
    config(['client.fitur' => [
        'a' => ['default' => true, 'locked' => false, 'butuh' => ['b']],
        'b' => ['default' => true, 'locked' => false, 'butuh' => ['a']],
        'c' => ['default' => true, 'locked' => false, 'butuh' => ['c']],
    ]]);

    expect(Feature::aktif('a'))->toBeFalse()
        ->and(Feature::aktif('b'))->toBeFalse()
        ->and(Feature::aktif('c'))->toBeFalse();
});
