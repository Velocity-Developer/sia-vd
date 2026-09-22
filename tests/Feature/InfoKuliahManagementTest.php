<?php

use App\Models\InfoKuliah;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('admin manages info kuliah with file upload', function () {
    Storage::fake('local');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.info-kuliah.store'), [
        'information' => 'Jadwal kuliah tersedia.',
        'file' => UploadedFile::fake()->create('jadwal.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $info = InfoKuliah::first();
    expect($info->uploaded_by)->toBe($admin->id);
    Storage::disk('local')->assertExists($info->file);

    $this->actingAs($admin)->delete(route('admin.info-kuliah.destroy', $info))->assertRedirect();
    expect(InfoKuliah::find($info->id))->toBeNull();
});

it('requires information and file when creating info kuliah', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('admin.info-kuliah.store'), [])->assertSessionHasErrors(['information', 'file']);
});

it('lets mahasiswa view info kuliah but blocks other roles', function () {
    $admin = User::factory()->admin()->create();
    $mahasiswa = User::factory()->mahasiswa()->create();
    $info = InfoKuliah::create(['information' => 'Pengumuman akademik.', 'file' => 'info-kuliahs/pengumuman.pdf', 'uploaded_by' => $admin->id]);

    $this->actingAs($mahasiswa)->get(route('mahasiswa.info-kuliah'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/InfoKuliah')->where('infoKuliahs.data.0.id', $info->id));

    $this->actingAs($admin)->get(route('mahasiswa.info-kuliah'))->assertForbidden();
});
