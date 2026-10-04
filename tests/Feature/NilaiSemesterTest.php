<?php

use App\Models\KomponenNilai;
use App\Models\Krs;
use App\Models\NilaiKomponen;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\RemidiPeserta;
use App\Models\User;
use App\SegarkanKehadiran;

/**
 * Kelas berisi dua mahasiswa dan komponen UTS 40% + UAS 60% (skala umum: A ≥ 80, B ≥ 70, C ≥ 60, D ≥ 50, E ≥ 0).
 */
function nilaiSemesterSetup(): array
{
    $kelas = createMateriKelasKuliah();
    $krs = collect(['2301A001', '2301A002'])->map(function (string $nim) use ($kelas): Krs {
        $mhs = User::factory()->mahasiswa()->create()->mahasiswaProfile;
        $mhs->update(['prodi_id' => $kelas->mataKuliah->prodi_id, 'nim' => $nim]);

        return Krs::create(['mahasiswa_id' => $mhs->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    });
    $uts = KomponenNilai::create(['nama' => 'UTS', 'persen' => 40, 'sumber' => KomponenNilai::MANUAL, 'urutan' => 0]);
    $uas = KomponenNilai::create(['nama' => 'UAS', 'persen' => 60, 'sumber' => KomponenNilai::MANUAL, 'urutan' => 1]);

    return [$kelas, $krs, $uts, $uas, User::factory()->admin()->create()];
}

function komponenBaris(string $nama, float $persen, string $sumber = KomponenNilai::MANUAL, ?int $id = null): array
{
    return array_filter(['id' => $id, 'nama' => $nama, 'persen' => $persen, 'sumber' => $sumber], fn ($v) => $v !== null);
}

/**
 * Pertemuan kuliah selesai ke-n dengan status presensi per mahasiswa.
 *
 * @param  array<int, string>  $status  mahasiswa_id => status presensi
 */
function pertemuanSelesai($kelas, int $ke, array $status): void
{
    $pertemuan = Pertemuan::create([
        'kelas_id' => $kelas->id, 'pertemuan_ke' => $ke, 'tanggal' => '2025-09-0'.$ke, 'jam_mulai' => '08:00', 'jam_akhir' => '10:00',
        'jenis' => Pertemuan::KULIAH, 'status' => Pertemuan::SELESAI, 'dosen_id' => $kelas->dosen_id,
    ]);
    foreach ($status as $mahasiswaId => $s) {
        PresensiMahasiswa::create(['pertemuan_id' => $pertemuan->id, 'mahasiswa_id' => $mahasiswaId, 'status' => $s, 'metode' => 'manual']);
    }
}

it('menyimpan komponen nilai hanya bila jumlah persennya 100', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.komponen-nilai.index'))->assertInertia(fn ($page) => $page->component('Admin/KomponenNilai'));

    $this->actingAs($admin)->put(route('admin.komponen-nilai.update'), ['komponen' => [komponenBaris('UTS', 40), komponenBaris('UAS', 50)]])
        ->assertSessionHasErrors('komponen');
    $this->actingAs($admin)->put(route('admin.komponen-nilai.update'), ['komponen' => [komponenBaris('UTS', 50), komponenBaris('uts', 50)]])
        ->assertSessionHasErrors('komponen.1.nama');
    $this->actingAs($admin)->put(route('admin.komponen-nilai.update'), ['komponen' => [
        komponenBaris('Hadir', 50, KomponenNilai::KEHADIRAN), komponenBaris('Absen', 50, KomponenNilai::KEHADIRAN),
    ]])->assertSessionHasErrors('komponen');
    expect(KomponenNilai::query()->count())->toBe(0);

    $this->actingAs($admin)->put(route('admin.komponen-nilai.update'), ['komponen' => [
        komponenBaris('Kehadiran', 10, KomponenNilai::KEHADIRAN), komponenBaris('Tugas', 20), komponenBaris('UTS', 30), komponenBaris('UAS', 40),
    ]])->assertSessionHas('success');
    expect(KomponenNilai::urut()->pluck('persen', 'nama')->all())->toBe(['Kehadiran' => 10.0, 'Tugas' => 20.0, 'UTS' => 30.0, 'UAS' => 40.0])
        ->and(KomponenNilai::query()->where('nama', 'Kehadiran')->value('sumber'))->toBe(KomponenNilai::KEHADIRAN);

    // Menukar nama antarkomponen tidak bentrok dengan indeks unik, dan komponen yang dihapus ikut hilang.
    [$a, $b] = KomponenNilai::urut()->all();
    $this->actingAs($admin)->put(route('admin.komponen-nilai.update'), ['komponen' => [
        komponenBaris('Tugas', 50, id: $a->id), komponenBaris('Kehadiran', 50, id: $b->id),
    ]])->assertSessionHas('success');
    expect(KomponenNilai::urut()->pluck('nama', 'id')->all())->toBe([$a->id => 'Tugas', $b->id => 'Kehadiran']);
});

it('menolak menghapus komponen yang sudah berisi nilai', function () {
    [, $krs, $uts, $uas, $admin] = nilaiSemesterSetup();
    NilaiKomponen::create(['krs_id' => $krs[0]->id, 'komponen_nilai_id' => $uts->id, 'nilai' => 80]);

    $this->actingAs($admin)->put(route('admin.komponen-nilai.update'), ['komponen' => [komponenBaris('UAS', 100, id: $uas->id)]])
        ->assertSessionHasErrors('komponen');
    expect(KomponenNilai::query()->count())->toBe(2);
});

it('menghitung nilai akhir dan huruf dari komponen di Nilai Semester', function () {
    [$kelas, $krs, $uts, $uas, $admin] = nilaiSemesterSetup();

    $this->actingAs($admin)->get(route('admin.nilai-semester.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->component('Admin/NilaiSemester')->has('kelas', 1)->where('kelas.0.peserta', 2)->where('persenLengkap', true));

    // 85×40% + 75×60% = 79 → B. Baris kedua belum lengkap: angka tersimpan, huruf manual dari dosen tidak disentuh.
    $krs[1]->update(['nilai' => 'C']);
    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [
        $krs[0]->id => [$uts->id => 85, $uas->id => 75],
        $krs[1]->id => [$uts->id => 90, $uas->id => null],
    ]])->assertSessionHas('success');

    expect($krs[0]->fresh())->nilai->toBe('B')->nilai_angka->toBe(79.0)
        ->and($krs[1]->fresh())->nilai->toBe('C')->nilai_angka->toBeNull()
        ->and(NilaiKomponen::query()->count())->toBe(3);

    $this->actingAs($admin)->get(route('admin.nilai-semester.show', $kelas))
        ->assertInertia(fn ($page) => $page->component('Admin/NilaiSemesterKelas')->has('mahasiswa', 2)->where('mahasiswa.0.nilai_angka', 79)
            ->where("mahasiswa.0.nilai.{$uts->id}", 85));

    // Komponen dikosongkan lagi → nilai akhir dan huruf dari komponen ikut dikosongkan.
    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [$krs[0]->id => [$uts->id => 85, $uas->id => null]]])
        ->assertSessionHas('success');
    expect($krs[0]->fresh())->nilai->toBeNull()->nilai_angka->toBeNull();

    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [$krs[0]->id => [$uts->id => 101]]])
        ->assertSessionHasErrors("nilai.{$krs[0]->id}.{$uts->id}");
});

it('mengambil komponen kehadiran dari presensi, bukan dari isian', function () {
    [$kelas, $krs, $uts, $uas, $admin] = nilaiSemesterSetup();
    $uts->update(['persen' => 30]);
    $uas->update(['persen' => 50]);
    $hadir = KomponenNilai::create(['nama' => 'Kehadiran', 'persen' => 20, 'sumber' => KomponenNilai::KEHADIRAN, 'urutan' => 2]);
    [$a, $b] = [$krs[0]->mahasiswa_id, $krs[1]->mahasiswa_id];

    // Mahasiswa A: hadir, terlambat, alpa, hadir → 75%. Mahasiswa B tanpa presensi.
    pertemuanSelesai($kelas, 1, [$a => PresensiMahasiswa::HADIR]);
    pertemuanSelesai($kelas, 2, [$a => PresensiMahasiswa::TERLAMBAT]);
    pertemuanSelesai($kelas, 3, [$a => PresensiMahasiswa::ALPA]);
    pertemuanSelesai($kelas, 4, [$a => PresensiMahasiswa::HADIR]);

    $this->actingAs($admin)->get(route('admin.nilai-semester.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('mahasiswa.0.kehadiran', 75)->where('mahasiswa.1.kehadiran', null));

    // 75×20% + 80×30% + 70×50% = 74 → B. Isian 100 untuk Kehadiran diabaikan. B belum punya kehadiran → belum lengkap.
    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [
        $krs[0]->id => [$uts->id => 80, $uas->id => 70, $hadir->id => 100],
        $krs[1]->id => [$uts->id => 80, $uas->id => 70],
    ]])->assertSessionHas('success');

    expect($krs[0]->fresh())->nilai->toBe('B')->nilai_angka->toBe(74.0)
        ->and(NilaiKomponen::query()->where('krs_id', $krs[0]->id)->where('komponen_nilai_id', $hadir->id)->value('nilai'))->toEqual(75)
        ->and($krs[1]->fresh()->nilai_angka)->toBeNull();

    // Presensi dikoreksi (alpa → hadir): komponen Kehadiran & nilai akhir ikut dihitung ulang di akhir request.
    PresensiMahasiswa::query()->where('mahasiswa_id', $a)->where('status', PresensiMahasiswa::ALPA)->first()->update(['status' => PresensiMahasiswa::HADIR]);
    SegarkanKehadiran::jalankan();
    expect($krs[0]->fresh()->nilai_angka)->toBe(79.0)
        ->and(NilaiKomponen::query()->where('krs_id', $krs[0]->id)->where('komponen_nilai_id', $hadir->id)->value('nilai'))->toEqual(100)
        ->and($krs[1]->fresh()->nilai_angka)->toBeNull();

    // Nilai tervalidasi tidak ikut berubah.
    $krs[0]->update(['nilai_divalidasi_at' => now()]);
    PresensiMahasiswa::query()->where('mahasiswa_id', $a)->where('status', PresensiMahasiswa::TERLAMBAT)->first()->update(['status' => PresensiMahasiswa::ALPA]);
    SegarkanKehadiran::jalankan();
    expect($krs[0]->fresh()->nilai_angka)->toBe(79.0);
});

it('membiarkan dosen mengisi nilai per komponen di halaman kelas dan menutup pilihan huruf langsung', function () {
    [$kelas, $krs, $uts, $uas] = nilaiSemesterSetup();
    $dosen = $kelas->dosen->user;

    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.show', $kelas))
        ->assertInertia(fn ($page) => $page->component('Kelas/KelasKuliahShow')->has('nilaiKomponen.komponen', 2)->has('nilaiKomponen.mahasiswa', 2));

    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.nilai-komponen', $kelas), ['nilai' => [$krs[0]->id => [$uts->id => 50, $uas->id => 40]]])
        ->assertSessionHas('success');
    expect($krs[0]->fresh())->nilai->toBe('E')->nilai_angka->toBe(44.0);

    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs[0]]), ['nilai' => 'A'])->assertSessionHas('error');
    expect($krs[0]->fresh()->nilai)->toBe('E');

    // Dosen lain tidak boleh; setelah finalisasi dosen terkunci.
    $lain = User::factory()->dosen()->create();
    $this->actingAs($lain)->put(route('dosen.kelas-kuliah.nilai-komponen', $kelas), ['nilai' => []])->assertForbidden();
    $kelas->update(['nilai_final_at' => now()]);
    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.nilai-komponen', $kelas), ['nilai' => [$krs[0]->id => [$uts->id => 90, $uas->id => 90]]])
        ->assertSessionHas('error');
    expect($krs[0]->fresh()->nilai)->toBe('E');
});

it('tidak menghitung ulang peserta remidi yang daftarnya sudah dikunci', function () {
    [$kelas, $krs, $uts, $uas, $admin] = nilaiSemesterSetup();
    $krs[0]->update(['nilai' => 'C', 'nilai_angka' => 44]);
    RemidiPeserta::create(['kelas_id' => $kelas->id, 'mahasiswa_id' => $krs[0]->mahasiswa_id, 'nilai_awal' => 'E', 'diusulkan' => true]);
    $kelas->update(['nilai_final_at' => now(), 'remidi_dikunci_at' => now()]);

    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [$krs[0]->id => [$uts->id => 90, $uas->id => 90]]])
        ->assertSessionHas('success');
    expect($krs[0]->fresh()->nilai)->toBe('C');

    // Admin tetap bisa memperbaiki huruf hasil remidi secara langsung.
    $this->actingAs($admin)->put(route('admin.kelas-kuliah.krs.nilai', [$kelas, $krs[0]]), ['nilai' => 'B'])->assertSessionHas('success');
    expect($krs[0]->fresh()->nilai)->toBe('B');
});

it('menolak Nilai Semester bila persen komponen belum 100 atau kelasnya TA', function () {
    [$kelas, $krs, $uts, , $admin] = nilaiSemesterSetup();
    $uts->update(['persen' => 30]);

    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [$krs[0]->id => [$uts->id => 80]]])
        ->assertSessionHas('error');

    $uts->update(['persen' => 40]);
    $kelas->mataKuliah->update(['tugas_akhir' => true]);
    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas->fresh()), ['nilai' => [$krs[0]->id => [$uts->id => 80]]])
        ->assertSessionHas('error');
    expect(NilaiKomponen::query()->count())->toBe(0);
});

it('menutup menu penilaian untuk dosen', function () {
    [$kelas] = nilaiSemesterSetup();
    $dosen = User::factory()->dosen()->create();

    $this->actingAs($dosen)->get(route('admin.nilai-semester.index'))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.nilai-semester.show', $kelas))->assertForbidden();
    $this->actingAs($dosen)->get(route('admin.komponen-nilai.index'))->assertForbidden();
});
