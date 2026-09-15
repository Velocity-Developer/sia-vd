<?php

use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\User;
use App\Role;

it('shows task submissions and updates their grades', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $mahasiswa = User::factory()->create(['role' => Role::Mahasiswa]);
    $kelas = createMateriKelasKuliah();
    $tugas = Tugas::create([
        'kelas_id' => $kelas->id,
        'uploaded_by' => $admin->id,
        'judul_tugas' => 'Tugas Basis Data',
    ]);
    $submission = PengumpulanTugas::create([
        'tugas_id' => $tugas->id,
        'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id,
        'file_jawaban' => ['pengumpulan-tugas/jawaban.pdf'],
        'submitted_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.kelas-kuliah.tugas.show', [$kelas, $tugas]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/TugasShow')
            ->where('tugas.id', $tugas->id)
            ->where('tugas.pengumpulan_tugas.0.id', $submission->id)
            ->where('tugas.pengumpulan_tugas.0.mahasiswa.user.name', $mahasiswa->name));

    $this->actingAs($admin)
        ->put(route('admin.kelas-kuliah.tugas.pengumpulan.nilai', [$kelas, $tugas, $submission]), ['nilai' => 88])
        ->assertRedirect()
        ->assertSessionHas('success', 'Nilai berhasil diperbarui.');

    $this->assertDatabaseHas('pengumpulan_tugas', [
        'id' => $submission->id,
        'nilai' => 88,
    ]);
});

it('rejects submissions that do not belong to the task', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $mahasiswa = User::factory()->create(['role' => Role::Mahasiswa]);
    $kelas = createMateriKelasKuliah();
    $tugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $admin->id, 'judul_tugas' => 'Tugas Satu']);
    $otherTugas = Tugas::create(['kelas_id' => $kelas->id, 'uploaded_by' => $admin->id, 'judul_tugas' => 'Tugas Dua']);
    $submission = PengumpulanTugas::create([
        'tugas_id' => $otherTugas->id,
        'mahasiswa_id' => $mahasiswa->mahasiswaProfile->id,
        'file_jawaban' => ['pengumpulan-tugas/jawaban.pdf'],
    ]);

    $this->actingAs($admin)
        ->put(route('admin.kelas-kuliah.tugas.pengumpulan.nilai', [$kelas, $tugas, $submission]), ['nilai' => 88])
        ->assertNotFound();

    expect($submission->fresh()->nilai)->toBeNull();
});
