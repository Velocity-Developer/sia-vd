<?php

use App\Models\GelombangKompre;
use App\Models\Krs;
use App\Models\MataKuliah;
use App\Models\PengajuanAkademik;
use App\Models\PengaturanAkademik;
use App\Models\PeriodeWisuda;
use App\Models\TugasAkhir;
use App\Models\User;
use App\Models\Wisuda;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Penilaian TA/Skripsi, PPL, dan KKM sesuai alur Yapika: jenis penilaian mata kuliah, nilai langsung di Nilai Semester,
 * pengajuan KKM/PPL/Kompre, menu Nilai KKM, dan TA tanpa pendadaran (flag `pendadaran` mati).
 */
function kelasJenis(string $jenis): array
{
    $kelas = createMateriKelasKuliah();
    $kelas->mataKuliah->update(['jenis_penilaian' => $jenis]);
    $mhs = User::factory()->mahasiswa()->create();
    $mhs->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id]);
    $krs = Krs::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

    return [$kelas->fresh('mataKuliah'), $mhs, $krs];
}

function matikanPendadaran(): void
{
    config(['client.fitur.pendadaran.default' => false]);
}

beforeEach(fn () => Storage::fake('local'));

it('keeps jenis_penilaian and tugas_akhir in sync', function () {
    $mk = createMateriKelasKuliah()->mataKuliah;
    expect($mk->fresh()->jenis_penilaian)->toBe(MataKuliah::REGULER);

    $mk->update(['tugas_akhir' => true]);
    expect($mk->fresh()->jenis_penilaian)->toBe(MataKuliah::TUGAS_AKHIR);

    $mk->update(['jenis_penilaian' => MataKuliah::PPL]);
    expect($mk->fresh())->tugas_akhir->toBeFalse()->jenis_penilaian->toBe(MataKuliah::PPL);
});

it('grades PPL classes with one final score in Nilai Semester', function () {
    [$kelas, , $krs] = kelasJenis(MataKuliah::PPL);
    $admin = User::factory()->admin()->create();
    aturKomponenNilai();

    $this->actingAs($admin)->get(route('admin.nilai-semester.show', $kelas))
        ->assertInertia(fn ($page) => $page->where('langsung', true)->has('komponen', 1)->where('komponen.0.id', 0)->where('kelas.dinilai_di', null));

    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [$krs->id => [0 => 82]]])->assertSessionHas('success');
    expect($krs->fresh())->nilai_angka->toBe(82.0)->nilai->toBe('A');
    expect($krs->nilaiKomponen()->count())->toBe(0);

    // Dosen pengampu juga menilai lewat halaman kelas.
    $this->actingAs($kelas->dosen->user)->put(route('dosen.kelas-kuliah.nilai-komponen', $kelas), ['nilai' => [$krs->id => [0 => 65]]])->assertSessionHas('success');
    expect($krs->fresh()->nilai)->toBe('C');

    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [$krs->id => [0 => null]]]);
    expect($krs->fresh())->nilai->toBeNull()->nilai_angka->toBeNull();
});

it('refuses KKM classes in Nilai Semester', function () {
    [$kelas, , $krs] = kelasJenis(MataKuliah::KKM);
    $admin = User::factory()->admin()->create();
    aturKomponenNilai();

    $this->actingAs($admin)->get(route('admin.nilai-semester.show', $kelas))->assertInertia(fn ($page) => $page->where('kelas.dinilai_di', 'kkm'));
    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [$krs->id => [0 => 80]]])->assertSessionHas('error');
    expect($krs->fresh()->nilai)->toBeNull();
});

it('submits a KKM request, adds the KKM course to KRS on approval, and grades it in Nilai KKM', function () {
    [$kelas, $mhs, $krsLama] = kelasJenis(MataKuliah::KKM);
    $krsLama->delete();
    $admin = User::factory()->admin()->create();

    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kkm'), [
        'jenis_kkm' => 'pkl',
        'judul' => 'PKL Gizi Balita',
        'berkas_syarat' => UploadedFile::fake()->create('syarat.pdf', 100, 'application/pdf'),
    ])->assertSessionHas('success');
    $pengajuan = PengajuanAkademik::query()->where('jenis', 'kkm')->firstOrFail();
    expect($pengajuan->status)->toBe(PengajuanAkademik::MENUNGGU);

    expect($pengajuan->isian['jenis_kkm'])->toBe('pkl');

    $this->actingAs($admin)->get(route('admin.persetujuan-kkm.index'))
        ->assertInertia(fn ($page) => $page->where('pengajuan.total', 1)->where('pengajuan.data.0.jenis_kkm', 'PKL')->where('judul', 'Persetujuan KKM/PKL/KKN'));
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $pengajuan))->assertSessionHas('success');
    $krs = Krs::query()->where('mahasiswa_id', $mhs->mahasiswaProfile->id)->where('kelas_id', $kelas->id)->firstOrFail();

    // Sudah disetujui: tidak bisa diajukan lagi.
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kkm'), [
        'judul' => 'Lagi', 'berkas_syarat' => UploadedFile::fake()->create('syarat.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('judul');

    $this->actingAs($admin)->get(route('admin.nilai-kkm.index'))
        ->assertInertia(fn ($page) => $page->component('Admin/NilaiKkm')->where('mahasiswa.total', 1)->where('mahasiswa.data.0.krs_id', $krs->id)
            ->where('mahasiswa.data.0.jenis_kkm', 'PKL')->where('mahasiswa.data.0.judul', 'PKL Gizi Balita'));
    $this->actingAs($admin)->put(route('admin.nilai-kkm.update', $krs), ['nilai' => 75])->assertSessionHas('success');
    expect($krs->fresh())->nilai_angka->toBe(75.0)->nilai->toBe('B');

    $krs->update(['nilai_divalidasi_at' => now(), 'nilai_divalidasi_oleh' => $admin->id]);
    $this->actingAs($admin)->put(route('admin.nilai-kkm.update', $krs), ['nilai' => 90])->assertSessionHas('error');
    expect($krs->fresh()->nilai)->toBe('B');

    $this->actingAs($kelas->dosen->user)->get(route('admin.nilai-kkm.index'))->assertForbidden();
});

it('approves KKM without a KRS when the prodi has no KKM class yet', function () {
    [$kelas, $mhs, $krs] = kelasJenis(MataKuliah::REGULER);
    $admin = User::factory()->admin()->create();
    $pengajuan = PengajuanAkademik::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'jenis' => 'kkm', 'isian' => ['judul' => 'Seminar'], 'lampiran' => [], 'status' => 'menunggu']);

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $pengajuan))->assertSessionHas('error');
    expect($pengajuan->fresh()->status)->toBe(PengajuanAkademik::DISETUJUI);
    $this->actingAs($admin)->get(route('admin.nilai-kkm.index'))->assertInertia(fn ($page) => $page->where('mahasiswa.data.0.krs_id', null));

    // Setelah mata kuliah KKM ditandai, admin memasukkannya ke KRS dari menu Nilai KKM.
    $kelas->mataKuliah->update(['jenis_penilaian' => MataKuliah::KKM]);
    $krs->delete();
    $this->actingAs($admin)->post(route('admin.nilai-kkm.tambah-krs', $mhs->mahasiswaProfile))->assertSessionHas('success');
    expect(Krs::query()->where('mahasiswa_id', $mhs->mahasiswaProfile->id)->where('kelas_id', $kelas->id)->exists())->toBeTrue();
});

it('approves PPL and Kompre requests without side effects', function () {
    [, $mhs] = kelasJenis(MataKuliah::PPL);
    $admin = User::factory()->admin()->create();

    $gelombang = GelombangKompre::create(['nama' => 'Gelombang I', 'tanggal_buka' => today()->subDay(), 'tanggal_tutup' => today()->addDay(), 'tanggal_ujian' => today()->addDays(3)]);
    foreach (['ppl', 'kompre'] as $jenis) {
        $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', $jenis), [
            'judul' => 'RSUD Kota', 'berkas_syarat' => UploadedFile::fake()->image('syarat.jpg'),
            ...($jenis === 'kompre' ? ['gelombang_kompre_id' => $gelombang->id] : []),
        ])->assertSessionHas('success');
        $pengajuan = PengajuanAkademik::query()->where('jenis', $jenis)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $pengajuan))->assertSessionHas('success');
        expect($pengajuan->fresh()->status)->toBe(PengajuanAkademik::DISETUJUI);
    }

    $this->actingAs($mhs)->get(route('mahasiswa.pengajuan-ppl'))
        ->assertInertia(fn ($page) => $page->component('Mahasiswa/PengajuanKegiatan')->where('keadaan', 'selesai')->where('judul', 'Pengajuan PPL')
            ->has('riwayat', 1));
});

it('grades TA through Nilai Semester and finishes the thesis when pendadaran is off', function () {
    matikanPendadaran();
    [$kelas, $mhs, $krs] = kelasJenis(MataKuliah::TUGAS_AKHIR);
    $admin = User::factory()->admin()->create();
    $mahasiswa = $mhs->mahasiswaProfile;

    // Pengajuan TA tanpa pembimbing.
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), [
        'judul' => 'Status Gizi Balita', 'bidang' => 'Gizi', 'ringkasan' => 'Metode.',
        'proposal' => UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf'),
    ])->assertSessionHas('success');
    $pengajuan = PengajuanAkademik::query()->where('jenis', 'tugas_akhir')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $pengajuan), ['judul' => 'Status Gizi Balita di Makassar'])->assertSessionHas('success');
    $ta = TugasAkhir::milik($mahasiswa->id);
    expect($ta)->pembimbing_1_id->toBeNull()->status->toBe(TugasAkhir::BERJALAN);

    $this->actingAs($admin)->get('/admin/pendaftaran-pendadaran')->assertNotFound();
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), [])->assertNotFound();

    aturKomponenNilai();
    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [$krs->id => [0 => 88]]])->assertSessionHas('success');
    expect($krs->fresh()->nilai)->toBe('A')->and($ta->fresh()->status)->toBe(TugasAkhir::SELESAI);

    // Nilai dihapus → TA kembali berjalan.
    $this->actingAs($admin)->put(route('admin.nilai-semester.update', $kelas), ['nilai' => [$krs->id => [0 => null]]]);
    expect($ta->fresh()->status)->toBe(TugasAkhir::BERJALAN);
});

it('requires the graduation date when approving wisuda without pendadaran and prints it on the transcript', function () {
    matikanPendadaran();
    $this->travelTo('2025-10-20 09:00:00');
    [, $mhs, $krs] = kelasJenis(MataKuliah::TUGAS_AKHIR);
    $krs->update(['nilai' => 'A', 'nilai_divalidasi_at' => now()]);
    $mahasiswa = $mhs->mahasiswaProfile;
    // Syarat SKS di luar TA: satu mata kuliah reguler bernilai (dan tervalidasi).
    Krs::create(['mahasiswa_id' => $mahasiswa->id, 'kelas_id' => createMateriKelasKuliah()->id, 'status' => 'Aktif', 'nilai' => 'B', 'nilai_divalidasi_at' => now()]);
    PengaturanAkademik::current()->update(['min_sks_pendadaran' => 3]);
    $ta = TugasAkhir::create(['mahasiswa_id' => $mahasiswa->id, 'judul' => 'Status Gizi Balita', 'bidang' => 'Gizi', 'status' => TugasAkhir::SELESAI, 'selesai_at' => now(), 'naskah' => 'tugas-akhir/n.pdf']);
    $periode = PeriodeWisuda::create(['nama' => 'Wisuda I 2025', 'tanggal_acara' => '2025-11-20', 'tempat' => 'Aula', 'batas_daftar' => '2025-11-01']);
    $pengajuan = PengajuanAkademik::create(['mahasiswa_id' => $mahasiswa->id, 'jenis' => 'wisuda', 'tugas_akhir_id' => $ta->id, 'isian' => ['periode_wisuda_id' => $periode->id], 'lampiran' => [], 'status' => 'menunggu']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $pengajuan))->assertSessionHasErrors('tanggal_lulus');
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $pengajuan), ['tanggal_lulus' => '2025-10-15', 'bebas_pustaka' => '1', 'lunas' => '1'])->assertSessionHas('success');
    $wisuda = Wisuda::query()->where('mahasiswa_id', $mahasiswa->id)->firstOrFail();
    expect($wisuda->tanggal_lulus->toDateString())->toBe('2025-10-15');

    $wisuda->terbitkanSkl($admin->id);
    expect($wisuda->fresh()->tanggal_lulus->toDateString())->toBe('2025-10-15');

    $this->actingAs($mhs)->get(route('mahasiswa.transkrip'))
        ->assertInertia(fn ($page) => $page->where('kelulusan.judul_ta', 'Status Gizi Balita')->where('kelulusan.tanggal_lulus', '2025-10-15'));
});

it('requires the KKM/PKL/KKN type when submitting a KKM request', function () {
    [, $mhs] = kelasJenis(MataKuliah::KKM);
    $isian = ['judul' => 'KKN Desa Sehat', 'berkas_syarat' => UploadedFile::fake()->create('syarat.pdf', 100, 'application/pdf')];

    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kkm'), $isian)->assertSessionHasErrors('jenis_kkm');
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kkm'), [...$isian, 'jenis_kkm' => 'magang'])->assertSessionHasErrors('jenis_kkm');
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'kkm'), [...$isian, 'jenis_kkm' => 'kkn'])->assertSessionHas('success');

    $this->actingAs($mhs)->get(route('mahasiswa.pengajuan-kkm'))->assertInertia(fn ($page) => $page
        ->where('judul', 'Pengajuan Judul KKM/PKL/KKN')->where('keadaan', 'menunggu')->has('jenisKkmOptions', 3)->where('riwayat.0.jenis', 'KKN'));
    // PPL tidak meminta jenis kegiatan.
    $this->actingAs($mhs)->post(route('mahasiswa.pengajuan-kegiatan.ajukan', 'ppl'), ['judul' => 'RSUD', 'berkas_syarat' => UploadedFile::fake()->image('s.jpg')])
        ->assertSessionHas('success');
});

it('redirects the old combined Pengajuan & Pendaftaran addresses to the per-type pages', function () {
    [, $mhs] = kelasJenis(MataKuliah::PPL);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.pengajuan-akademik.index', ['jenis' => 'ppl', 'status' => 'menunggu']))
        ->assertRedirect(route('admin.daftar-ppl.index', ['status' => 'menunggu']));
    $this->actingAs($admin)->get(route('admin.pengajuan-akademik.index'))->assertRedirect(route('admin.persetujuan-ta.index'));
    $this->actingAs($admin)->get(route('admin.pengajuan-kompre.index'))->assertInertia(fn ($page) => $page->where('filter.jenis', 'kompre'));
    $this->actingAs($mhs)->get(route('mahasiswa.pengajuan-kegiatan', ['jenis' => 'ppl']))->assertRedirect(route('mahasiswa.pengajuan-ppl'));
    $this->actingAs($mhs)->get(route('mahasiswa.pengajuan-kegiatan'))->assertRedirect(route('mahasiswa.pengajuan-kkm'));
    $this->actingAs($mhs)->get(route('admin.daftar-ppl.index'))->assertForbidden();
});
