<?php

use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\User;

it('summarises attendance per student and course and exports it to Excel', function () {
    $kelas = createMateriKelasKuliah();
    $mhs = User::factory()->mahasiswa()->create()->mahasiswaProfile;
    $mhs->update(['nim' => '2501001', 'prodi_id' => $kelas->mataKuliah->prodi_id]);
    Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    foreach (['hadir', 'terlambat', 'izin', 'alpa'] as $i => $status) {
        $p = Pertemuan::create(['kelas_id' => $kelas->id, 'pertemuan_ke' => $i + 1, 'tanggal' => '2025-08-0'.($i + 4), 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'jenis' => Pertemuan::KULIAH, 'status' => Pertemuan::SELESAI]);
        PresensiMahasiswa::create(['pertemuan_id' => $p->id, 'mahasiswa_id' => $mhs->id, 'status' => $status]);
    }
    PengaturanAkademik::current()->update(['syarat_ujian_aktif' => true, 'min_kehadiran_ujian' => 75]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.rekap-presensi.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/RekapPresensiMahasiswa')
            ->where('rekap.data.0.nim', '2501001')
            ->where('rekap.data.0.dihitung', 4)
            ->where('rekap.data.0.persen', 50)
            ->where('rekap.data.0.memenuhi', false));

    $this->actingAs($admin)->get(route('admin.rekap-presensi.unduh'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});
