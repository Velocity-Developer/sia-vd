<?php

use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengajuanSusulan;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\Ruang;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Kelas dengan dua mahasiswa, pertemuan tergenerate, dan UTS terbit 6 Okt 2025 13:00-15:00.
 *
 * @return array{0: KelasKuliah, 1: list<User>, 2: Ujian}
 */
function kelasSusulan(string $mode = 'tatap_muka'): array
{
    $kelas = createMateriKelasKuliah();
    $ruang = Ruang::firstOrCreate(['kode_ruang' => 'R-SUS'], ['nama_ruang' => 'Ruang Susulan', 'kapasitas' => 40]);
    Jadwal::create(['kelas_id' => $kelas->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruang->id]);
    $mhs = [];
    foreach ([1, 2] as $_) {
        $mhs[] = $user = User::factory()->mahasiswa()->create();
        Krs::create(['mahasiswa_id' => $user->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);
    }
    Pertemuan::generateUntuk($kelas->fresh());
    $ujian = Ujian::create([
        'kelas_id' => $kelas->id, 'jenis' => 'uts', 'mode' => $mode, 'tanggal' => '2025-10-06', 'jam_mulai' => '13:00', 'jam_akhir' => '15:00',
        'ruang_id' => $mode === 'tatap_muka' ? $ruang->id : null, 'status' => 'terbit',
    ]);

    return [$kelas->fresh(), $mhs, $ujian];
}

function isianSusulan(): array
{
    return ['alasan' => 'Sakit demam', 'lampiran' => [UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf')]];
}

beforeEach(fn () => Storage::fake('local'));

it('lets a student apply with proof before the exam and within the window after it', function () {
    [, $mhs, $ujian] = kelasSusulan();

    $this->travelTo('2025-10-05 09:00:00');
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), ['alasan' => 'Sakit'])->assertSessionHasErrors('lampiran');
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan())->assertSessionHas('success');
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan())
        ->assertSessionHasErrors(['alasan' => 'Anda sudah punya pengajuan susulan untuk ujian ini.']);

    $p = PengajuanSusulan::first();
    expect($p->status)->toBe(PengajuanSusulan::MENUNGGU)->and($p->lampiran)->toHaveCount(1);
    Storage::disk('local')->assertExists($p->lampiran[0]);

    // Bawaan 3 hari sesudah ujian: 9 Okt masih boleh, 10 Okt sudah lewat.
    $this->travelTo('2025-10-09 23:00:00');
    $this->actingAs($mhs[1])->get(route('mahasiswa.ujian.show', $ujian))->assertInertia(fn ($page) => $page->where('susulan.boleh_ajukan', true));
    $this->travelTo('2025-10-10 00:30:00');
    $this->actingAs($mhs[1])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan())
        ->assertSessionHasErrors(['alasan' => 'Batas pengajuan ujian susulan sudah lewat.']);
});

it('refuses applications for draft exams, outsiders, and students who sat the main exam', function () {
    [$kelas, $mhs, $ujian] = kelasSusulan();
    $this->travelTo('2025-10-06 16:00:00');

    $ujian->update(['status' => 'draf']);
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan())->assertSessionHasErrors('alasan');
    $ujian->update(['status' => 'terbit']);

    $luar = User::factory()->mahasiswa()->create();
    $this->actingAs($luar)->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan())
        ->assertSessionHasErrors(['alasan' => 'Anda bukan peserta kelas ini.']);

    // Tatap muka: tercatat hadir di pertemuan UTS berarti ikut ujian utama.
    $pertemuanUts = Pertemuan::where('kelas_id', $kelas->id)->where('jenis', 'uts')->first();
    PresensiMahasiswa::create(['pertemuan_id' => $pertemuanUts->id, 'mahasiswa_id' => $mhs[0]->mahasiswaProfile->id, 'status' => PresensiMahasiswa::HADIR, 'metode' => 'manual']);
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan())
        ->assertSessionHasErrors(['alasan' => 'Anda sudah mengikuti ujian ini.']);
    $this->actingAs($mhs[0])->get(route('mahasiswa.ujian.show', $ujian))->assertInertia(fn ($page) => $page->where('susulan.boleh_ajukan', false));
});

it('treats an online submission as sitting the main exam', function () {
    [, $mhs, $ujian] = kelasSusulan('online_berkas');
    UjianJawaban::create(['ujian_id' => $ujian->id, 'mahasiswa_id' => $mhs[0]->mahasiswaProfile->id, 'berkas' => ['a.pdf'], 'dikumpulkan_at' => '2025-10-06 14:00:00']);
    $this->travelTo('2025-10-06 16:00:00');

    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan())
        ->assertSessionHasErrors(['alasan' => 'Anda sudah mengikuti ujian ini.']);
    $this->actingAs($mhs[1])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan())->assertSessionHas('success');
});

it('lets the student cancel only while waiting', function () {
    [, $mhs, $ujian] = kelasSusulan();
    $this->travelTo('2025-10-06 16:00:00');
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan());
    $p = PengajuanSusulan::first();

    $this->actingAs($mhs[1])->delete(route('mahasiswa.ujian-susulan.batalkan', $p))->assertNotFound();
    $this->actingAs($mhs[0])->delete(route('mahasiswa.ujian-susulan.batalkan', $p))->assertSessionHas('success');
    expect($p->fresh()->status)->toBe(PengajuanSusulan::DIBATALKAN);

    // Setelah dibatalkan boleh mengajukan lagi; yang sudah diproses tidak bisa dibatalkan.
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan())->assertSessionHas('success');
    $baru = PengajuanSusulan::latest('id')->first();
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.ujian-susulan.setujui', $baru));
    $this->actingAs($mhs[0])->delete(route('mahasiswa.ujian-susulan.batalkan', $baru))->assertSessionHas('error');
});

it('lets admin approve or reject, re-checking attendance of the main exam', function () {
    [$kelas, $mhs, $ujian] = kelasSusulan();
    $admin = User::factory()->admin()->create();
    $this->travelTo('2025-10-05 09:00:00');
    foreach ($mhs as $m) {
        $this->actingAs($m)->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan());
    }
    [$a, $b] = PengajuanSusulan::orderBy('id')->get();

    // Mahasiswa pertama tetap datang ke ujian: pengajuannya tampil dibatalkan dan tidak bisa disetujui.
    $pertemuanUts = Pertemuan::where('kelas_id', $kelas->id)->where('jenis', 'uts')->first();
    PresensiMahasiswa::create(['pertemuan_id' => $pertemuanUts->id, 'mahasiswa_id' => $a->mahasiswa_id, 'status' => PresensiMahasiswa::TERLAMBAT, 'metode' => 'manual']);
    $this->travelTo('2025-10-06 16:00:00');

    $this->actingAs($admin)->get(route('admin.ujian-susulan.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->has('pengajuan.data', 2)->where('jumlahMenunggu', 2));
    $this->actingAs($admin)->post(route('admin.ujian-susulan.setujui', $a))->assertSessionHas('error');
    expect($a->fresh()->status)->toBe(PengajuanSusulan::MENUNGGU)
        ->and($a->fresh()->statusTampil(true))->toBe(PengajuanSusulan::DIBATALKAN);

    $this->actingAs($admin)->post(route('admin.ujian-susulan.tolak', $b), ['catatan' => ''])->assertSessionHasErrors('catatan');
    $this->actingAs($admin)->post(route('admin.ujian-susulan.setujui', $b))->assertSessionHas('success');
    expect($b->fresh()->status)->toBe(PengajuanSusulan::DISETUJUI)->and($b->fresh()->diproses_oleh)->toBe($admin->id);
    $this->actingAs($admin)->post(route('admin.ujian-susulan.tolak', $b), ['catatan' => 'x'])->assertSessionHas('error');

    // Disetujui lalu tercatat hadir (tatap muka) = hak susulan gugur.
    PresensiMahasiswa::create(['pertemuan_id' => $pertemuanUts->id, 'mahasiswa_id' => $b->mahasiswa_id, 'status' => PresensiMahasiswa::HADIR, 'metode' => 'manual']);
    $this->actingAs($mhs[1])->get(route('mahasiswa.ujian.show', $ujian))->assertInertia(fn ($page) => $page->where('susulan.pengajuan.status', 'gugur'));
    $this->actingAs($mhs[1])->get(route('admin.ujian-susulan.index'))->assertForbidden();
});

it('serves attachments to the applicant and exam admins only, and stores the settings', function () {
    [, $mhs, $ujian] = kelasSusulan();
    $this->travelTo('2025-10-06 16:00:00');
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan());
    $p = PengajuanSusulan::first();
    $admin = User::factory()->admin()->create();

    $this->actingAs($mhs[0])->get(route('berkas.lampiran-susulan', [$p, 0]))->assertOk();
    $this->actingAs($mhs[1])->get(route('berkas.lampiran-susulan', [$p, 0]))->assertForbidden();
    $this->actingAs($admin)->get(route('berkas.lampiran-susulan', [$p, 0]))->assertOk();

    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.susulan'), ['batas_pengajuan_susulan_hari' => 5, 'batas_bayar_susulan_hari' => 0])
        ->assertSessionHasErrors('batas_bayar_susulan_hari');
    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.susulan'), ['batas_pengajuan_susulan_hari' => 5, 'batas_bayar_susulan_hari' => 2])
        ->assertSessionHas('success');
    expect(PengaturanAkademik::current()->batas_pengajuan_susulan_hari)->toBe(5);
});
