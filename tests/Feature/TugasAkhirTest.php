<?php

use App\Models\JenisBiaya;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengajuanAkademik;
use App\Models\PengaturanAkademik;
use App\Models\TugasAkhir;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Kelas mata kuliah TA/Skripsi di tahun akademik aktif dengan satu mahasiswa yang mengambilnya.
 *
 * @return array{0: KelasKuliah, 1: User}
 */
function kelasTa(): array
{
    $kelas = createMateriKelasKuliah();
    $kelas->mataKuliah->update(['nama_matkul' => 'Skripsi', 'tugas_akhir' => true]);
    $mhs = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

    return [$kelas, $mhs];
}

function isianTa(array $ubah = []): array
{
    return [
        'judul' => 'Sistem Rekomendasi Mata Kuliah Pilihan',
        'bidang' => 'Sistem Informasi',
        'ringkasan' => 'Latar belakang dan metode.',
        'usulan_pembimbing_1_id' => User::factory()->dosen()->create()->dosenProfile->id,
        'proposal' => UploadedFile::fake()->create('proposal.pdf', 200, 'application/pdf'),
        ...$ubah,
    ];
}

beforeEach(fn () => Storage::fake('local'));

it('marks a course as the TA/Skripsi course from the course form', function () {
    [$kelas] = kelasTa();
    $admin = User::factory()->admin()->create();
    $mk = $kelas->mataKuliah;

    $this->actingAs($admin)->put(route('admin.mata-kuliah.update', $mk), [
        'kode_matkul' => $mk->kode_matkul, 'nama_matkul' => $mk->nama_matkul, 'sks' => 6, 'semester' => 8, 'jenis' => 'Wajib', 'prodi_id' => $mk->prodi_id,
    ])->assertSessionHasNoErrors();
    expect($mk->fresh()->tugas_akhir)->toBeFalse();

    $this->actingAs($admin)->put(route('admin.mata-kuliah.update', $mk), [
        'kode_matkul' => $mk->kode_matkul, 'nama_matkul' => $mk->nama_matkul, 'sks' => 6, 'semester' => 8, 'jenis' => 'Wajib', 'prodi_id' => $mk->prodi_id, 'tugas_akhir' => true,
    ])->assertSessionHasNoErrors();
    expect($mk->fresh()->tugas_akhir)->toBeTrue();
});

it('locks the TA form until the student takes the TA/Skripsi course this semester', function () {
    [$kelas, $mhs] = kelasTa();
    $kelas->mataKuliah->update(['tugas_akhir' => false]);

    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->component('Mahasiswa/TugasAkhir')
        ->where('pengajuanTa.keadaan', 'belum_memenuhi')
        ->where('pengajuanTa.syarat.0.terpenuhi', false));
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa())
        ->assertSessionHasErrors(['judul' => 'Anda belum memenuhi syarat pengajuan tugas akhir.']);

    // Mata kuliah TA di tahun akademik yang tidak aktif tidak dihitung.
    $kelas->mataKuliah->update(['tugas_akhir' => true]);
    $kelas->tahunAkademik->update(['status' => false]);
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page->where('pengajuanTa.keadaan', 'belum_memenuhi'));

    $kelas->tahunAkademik->update(['status' => true]);
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pengajuanTa.keadaan', 'baru')
        ->where('pengajuanTa.syarat.0.keterangan', 'Skripsi'));
    expect(PengajuanAkademik::count())->toBe(0);
});

it('blocks new submissions while one is waiting and has no cancel', function () {
    [, $mhs] = kelasTa();

    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa(['proposal' => null]))->assertSessionHasErrors('proposal');
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa())->assertSessionHas('success');

    $p = PengajuanAkademik::sole();
    expect($p->status)->toBe(PengajuanAkademik::MENUNGGU)
        ->and($p->jenis)->toBe(PengajuanAkademik::TUGAS_AKHIR)
        ->and($p->isian['judul'])->toBe('Sistem Rekomendasi Mata Kuliah Pilihan')
        ->and($p->riwayat)->toHaveCount(1);
    Storage::disk('local')->assertExists($p->lampiran['proposal']);

    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page->where('pengajuanTa.keadaan', 'menunggu'));
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa())
        ->assertSessionHasErrors(['judul' => 'Pengajuan Anda masih menunggu diproses admin.']);
    expect(PengajuanAkademik::count())->toBe(1);
});

it('lets the student fix and resend the same submission when asked for a revision', function () {
    [, $mhs] = kelasTa();
    $admin = User::factory()->admin()->create();
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa());
    $p = PengajuanAkademik::sole();
    $proposalLama = $p->lampiran['proposal'];

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.perbaikan', $p))->assertSessionHasErrors('catatan');
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.perbaikan', $p), ['catatan' => 'Perjelas rumusan masalah'])->assertSessionHas('success');
    expect($p->fresh()->status)->toBe(PengajuanAkademik::PERLU_PERBAIKAN);

    // Keputusan kedua atas pengajuan yang sama ditolak.
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.tolak', $p), ['catatan' => 'x'])->assertSessionHas('error', 'Pengajuan ini sudah diproses.');

    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pengajuanTa.keadaan', 'perbaikan')
        ->where('pengajuanTa.pengajuan.catatan', 'Perjelas rumusan masalah'));

    // Proposal boleh tidak diganti saat perbaikan.
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa(['judul' => 'Judul Diperbaiki', 'proposal' => null]))
        ->assertSessionHas('success');

    $p->refresh();
    expect(PengajuanAkademik::count())->toBe(1)
        ->and($p->status)->toBe(PengajuanAkademik::MENUNGGU)
        ->and($p->catatan)->toBeNull()
        ->and($p->isian['judul'])->toBe('Judul Diperbaiki')
        ->and($p->lampiran['proposal'])->toBe($proposalLama)
        ->and($p->riwayat->pluck('status')->all())->toBe(['dikirim', 'perlu_perbaikan', 'dikirim']);

    // Proposal pengganti menghapus berkas lama.
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.perbaikan', $p), ['catatan' => 'Ganti proposal']);
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa());
    Storage::disk('local')->assertMissing($proposalLama);
    Storage::disk('local')->assertExists($p->fresh()->lampiran['proposal']);
});

it('opens a new form after a rejection', function () {
    [, $mhs] = kelasTa();
    $admin = User::factory()->admin()->create();
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa());

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.tolak', PengajuanAkademik::sole()), ['catatan' => 'Topik di luar bidang prodi']);

    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pengajuanTa.keadaan', 'baru')
        ->where('pengajuanTa.pengajuan.status', 'ditolak'));
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa())->assertSessionHas('success');
    expect(PengajuanAkademik::pluck('status')->all())->toBe(['ditolak', 'menunggu']);
});

it('approves a TA with the admin-chosen title and supervisors', function () {
    [, $mhs] = kelasTa();
    $admin = User::factory()->admin()->create();
    [$p1, $p2] = [User::factory()->dosen()->create(), User::factory()->dosen()->create()];
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa());
    $p = PengajuanAkademik::sole();

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p), ['judul' => 'Judul Sah', 'pembimbing_1_id' => $p1->dosenProfile->id, 'pembimbing_2_id' => $p1->dosenProfile->id])
        ->assertSessionHasErrors('pembimbing_2_id');
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p), ['judul' => 'Judul Sah', 'pembimbing_1_id' => $p1->dosenProfile->id, 'pembimbing_2_id' => $p2->dosenProfile->id])
        ->assertSessionHas('success');

    $ta = TugasAkhir::sole();
    expect($ta->judul)->toBe('Judul Sah')
        ->and($ta->bidang)->toBe('Sistem Informasi')
        ->and($ta->status)->toBe(TugasAkhir::BERJALAN)
        ->and($ta->pembimbing_1_id)->toBe($p1->dosenProfile->id)
        ->and($ta->pembimbing_2_id)->toBe($p2->dosenProfile->id)
        ->and($p->fresh()->status)->toBe(PengajuanAkademik::DISETUJUI);

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p), ['judul' => 'Lagi', 'pembimbing_1_id' => $p1->dosenProfile->id])
        ->assertSessionHas('error', 'Pengajuan ini sudah diproses.');
    expect(TugasAkhir::count())->toBe(1);

    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pengajuanTa.keadaan', 'selesai')
        ->where('tugasAkhir.judul', 'Judul Sah')
        ->where('tugasAkhir.pembimbing', [$p1->name, $p2->name]));
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa())
        ->assertSessionHasErrors(['judul' => 'Tugas akhir Anda sudah disahkan.']);

    // Kedua pembimbing melihat mahasiswanya, dosen lain tidak.
    $this->actingAs($p2)->get(route('dosen.bimbingan.index'))->assertInertia(fn ($page) => $page
        ->component('Dosen/Bimbingan')
        ->has('bimbingan', 1)
        ->where('bimbingan.0.peran', 'Pembimbing 2')
        ->where('bimbingan.0.pembimbing_lain', $p1->name));
    $this->actingAs(User::factory()->dosen()->create())->get(route('dosen.bimbingan.index'))->assertInertia(fn ($page) => $page->has('bimbingan', 0));
});

it('rechecks the TA course when approving', function () {
    [$kelas, $mhs] = kelasTa();
    $admin = User::factory()->admin()->create();
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa());
    Krs::where('kelas_id', $kelas->id)->delete();

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', PengajuanAkademik::sole()), [
        'judul' => 'Judul', 'pembimbing_1_id' => User::factory()->dosen()->create()->dosenProfile->id,
    ])->assertSessionHas('error');
    expect(TugasAkhir::count())->toBe(0)->and(PengajuanAkademik::sole()->status)->toBe(PengajuanAkademik::MENUNGGU);
});

it('lists waiting submissions first for the admin', function () {
    [, $mhs] = kelasTa();
    $admin = User::factory()->admin()->create();
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa());

    $this->actingAs($admin)->get(route('admin.pengajuan-akademik.index'))->assertInertia(fn ($page) => $page
        ->component('Admin/PengajuanAkademik')
        ->where('filter.jenis', 'tugas_akhir')
        ->where('jumlahMenunggu.tugas_akhir', 1)
        ->has('pengajuan.data', 1)
        ->where('pengajuan.data.0.lampiran', ['proposal'])
        ->has('pengajuan.data.0.usulan_pembimbing', 1));

    $this->actingAs(User::factory()->dosen()->create())->get(route('admin.pengajuan-akademik.index'))->assertForbidden();
    $this->actingAs($mhs)->get(route('dosen.bimbingan.index'))->assertForbidden();
});

it('serves submission files only to the owner, admins, and the supervisors', function () {
    [, $mhs] = kelasTa();
    $admin = User::factory()->admin()->create();
    $pembimbing = User::factory()->dosen()->create();
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa());
    $p = PengajuanAkademik::sole();
    $url = route('berkas.pengajuan-akademik', [$p, 'proposal']);

    $this->actingAs($mhs)->get($url)->assertOk();
    $this->actingAs($admin)->get($url)->assertOk();
    $this->actingAs(User::factory()->mahasiswa()->create())->get($url)->assertForbidden();
    $this->actingAs($pembimbing)->get($url)->assertForbidden();
    $this->actingAs($mhs)->get(route('berkas.pengajuan-akademik', [$p, 'naskah']))->assertNotFound();

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p), ['judul' => 'Judul', 'pembimbing_1_id' => $pembimbing->dosenProfile->id]);
    $this->actingAs($pembimbing)->get($url)->assertOk();
    $this->actingAs(User::factory()->dosen()->create())->get($url)->assertForbidden();
});

it('saves the minimum credits for the defence', function () {
    $admin = User::factory()->admin()->create();
    expect(PengaturanAkademik::current()->min_sks_pendadaran)->toBe(138)
        ->and(PengaturanAkademik::current()->min_sks_ambil_ta)->toBe(120);

    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.tugas-akhir'), ['min_sks_ambil_ta' => 110, 'min_sks_pendadaran' => 144])->assertSessionHas('success');
    expect(PengaturanAkademik::current()->min_sks_pendadaran)->toBe(144)
        ->and(PengaturanAkademik::current()->min_sks_ambil_ta)->toBe(110);
    $this->actingAs($admin)->put(route('admin.pengaturan-akademik.tugas-akhir'), ['min_sks_ambil_ta' => 110, 'min_sks_pendadaran' => -1])->assertSessionHasErrors('min_sks_pendadaran');
});

it('reminds the student and the admin on the dashboard', function () {
    [, $mhs] = kelasTa();
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page->where('pengingatTugasAkhir', null));

    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-ta'), isianTa());
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page
        ->where('pengingatTugasAkhir.pesan.0.teks', '1 pengajuan tugas akhir menunggu keputusan.'));

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.perbaikan', PengajuanAkademik::sole()), ['catatan' => 'Perjelas metode']);
    $this->actingAs($mhs)->get(route('mahasiswa.dashboard'))->assertInertia(fn ($page) => $page
        ->where('pengingat.tugasAkhir.pesan.0', ['teks' => 'Pengajuan tugas akhir diminta perbaikan: Perjelas metode', 'penting' => true])
        ->where('pengingat.tugasAkhir.tautan', route('mahasiswa.tugas-akhir')));
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertInertia(fn ($page) => $page->where('pengingatTugasAkhir', null));
});

it('shows the defence and graduation fees as information only', function () {
    [$kelas, $mhs] = kelasTa();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.jenis-biaya.store'), [
        'kode' => 'PDD', 'nama' => 'Biaya Pendadaran', 'cara_hitung' => 'per_sks', 'kategori' => 'pendadaran', 'aktif' => true,
        'tarif' => [['prodi_id' => null, 'angkatan' => null, 'nominal' => 750000], ['prodi_id' => $kelas->mataKuliah->prodi_id, 'angkatan' => null, 'nominal' => 900000]],
    ])->assertSessionHasNoErrors();
    expect(JenisBiaya::where('kode', 'PDD')->value('cara_hitung'))->toBe('tetap');

    $mhs->mahasiswaProfile->update(['prodi_id' => $kelas->mataKuliah->prodi_id]);
    $this->actingAs($mhs)->get(route('mahasiswa.info-biaya-kuliah'))->assertInertia(fn ($page) => $page
        ->where('biayaTugasAkhir.pendadaran', [['nama' => 'Biaya Pendadaran', 'nominal' => 900000, 'keterangan' => null]])
        ->where('biayaTugasAkhir.wisuda', []));
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page->where('biaya.pendadaran.0.nominal', 900000));
});
