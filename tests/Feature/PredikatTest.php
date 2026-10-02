<?php

use App\Models\Predikat;
use App\Models\User;
use App\Models\Wisuda;

it('starts with the four default predicates', function () {
    $this->actingAs(User::factory()->admin()->create())->get(route('admin.predikat.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/Predikat')->has('predikat', 4)
            ->where('predikat.0.nama', 'Dengan Pujian (Cum Laude)')->where('predikat.3.nama', 'Cukup'));
});

it('lets admin add, edit, and delete a predicate that the SKL then uses', function () {
    $admin = User::factory()->admin()->create();
    Predikat::where('nama', 'Dengan Pujian (Cum Laude)')->update(['bobot_minimal' => 3.51, 'bobot_maksimal' => 3.89]);

    $this->actingAs($admin)->post(route('admin.predikat.store'), ['nama' => 'Summa Cum Laude', 'bobot_minimal' => 3.9, 'bobot_maksimal' => 4])
        ->assertRedirect(route('admin.predikat.index'));
    expect(Wisuda::predikat(3.95))->toBe('Summa Cum Laude')->and(Wisuda::predikat(3.6))->toBe('Dengan Pujian (Cum Laude)');

    $summa = Predikat::where('nama', 'Summa Cum Laude')->firstOrFail();
    $this->actingAs($admin)->get(route('admin.predikat.edit', $summa))->assertOk();
    $this->actingAs($admin)->put(route('admin.predikat.update', $summa), ['nama' => 'Summa Cum Laude', 'bobot_minimal' => 3.95, 'bobot_maksimal' => 4])
        ->assertSessionHas('success');
    expect(Wisuda::predikat(3.92))->toBeNull();

    $this->actingAs($admin)->delete(route('admin.predikat.destroy', $summa))->assertSessionHas('success');
    expect(Predikat::count())->toBe(4);
});

it('rejects overlapping or reversed ranges and duplicate names', function (array $isian, string $kolom, ?string $pesan) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.predikat.store'), $isian)
        ->assertSessionHasErrors($pesan ? [$kolom => $pesan] : $kolom);

    expect(Predikat::count())->toBe(4);
})->with([
    'beririsan' => [['nama' => 'Baru', 'bobot_minimal' => 3.4, 'bobot_maksimal' => 3.6], 'bobot_minimal', 'Rentang beririsan dengan predikat Dengan Pujian (Cum Laude) (3.51–4.00).'],
    'terbalik' => [['nama' => 'Baru', 'bobot_minimal' => 3, 'bobot_maksimal' => 2], 'bobot_maksimal', null],
    'di atas 4' => [['nama' => 'Baru', 'bobot_minimal' => 3, 'bobot_maksimal' => 4.5], 'bobot_maksimal', null],
    'nama ganda' => [['nama' => 'Cukup', 'bobot_minimal' => 0, 'bobot_maksimal' => 0], 'nama', null],
]);

it('keeps the predicate menu admin-only', function () {
    $this->actingAs(User::factory()->dosen()->create())->get(route('admin.predikat.index'))->assertForbidden();
    $this->actingAs(User::factory()->mahasiswa()->create())->post(route('admin.predikat.store'), [])->assertForbidden();
});
