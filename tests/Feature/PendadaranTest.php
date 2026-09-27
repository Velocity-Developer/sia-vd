<?php

use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Pendadaran;
use App\Models\PengajuanAkademik;
use App\Models\PengaturanAkademik;
use App\Models\Ruang;
use App\Models\TahunAkademik;
use App\Models\TugasAkhir;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Mahasiswa dengan TA berjalan (satu pembimbing), sedang mengambil Skripsi, dan sudah lulus satu mata kuliah
 * 3 SKS di semester lalu. SKS minimal pendadaran diturunkan menjadi 3.
 *
 * @return array{0: User, 1: User, 2: KelasKuliah}
 */
function mahasiswaSiapPendadaran(): array
{
    $kelasTa = createMateriKelasKuliah();
    $kelasTa->mataKuliah->update(['nama_matkul' => 'Skripsi', 'tugas_akhir' => true]);
    $mhs = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'kelas_id' => $kelasTa->id, 'status' => 'Aktif']);

    $lalu = TahunAkademik::firstOrCreate(['tahun' => '2024/2025', 'semester' => 'Genap'], ['tanggal_mulai' => '2025-02-01', 'tanggal_akhir' => '2025-07-31', 'tanggal_krs_awal' => '2025-02-01', 'tanggal_krs_akhir' => '2025-02-14', 'status' => false]);
    $kelasLalu = createMateriKelasKuliah($lalu);
    Krs::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'kelas_id' => $kelasLalu->id, 'status' => 'Aktif', 'nilai' => 'A']);
    PengaturanAkademik::current()->update(['min_sks_pendadaran' => 3]);

    $pembimbing = User::factory()->dosen()->create();
    TugasAkhir::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'judul' => 'Judul Awal', 'bidang' => 'SI', 'pembimbing_1_id' => $pembimbing->dosenProfile->id, 'status' => TugasAkhir::BERJALAN]);

    return [$mhs, $pembimbing, $kelasTa];
}

function isianPendadaran(array $ubah = []): array
{
    return [
        'judul' => 'Judul Final',
        'naskah' => UploadedFile::fake()->create('naskah.pdf', 500, 'application/pdf'),
        'persetujuan_pembimbing' => UploadedFile::fake()->image('persetujuan.jpg'),
        'bukti_bayar' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ...$ubah,
    ];
}

/**
 * @return array<string, mixed>
 */
function jadwalPendadaran(Ruang $ruang, array $penguji, array $ubah = []): array
{
    return [
        'tanggal' => '2025-10-06', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'ruang_id' => $ruang->id,
        'penguji_1_id' => $penguji[0]->dosenProfile->id, 'penguji_2_id' => $penguji[1]->dosenProfile->id, 'penguji_3_id' => $penguji[2]->dosenProfile->id,
        ...$ubah,
    ];
}

/**
 * Pendaftaran yang sudah dikirim mahasiswa dan disetujui pembimbing (menunggu admin).
 */
function pendaftaranMenungguAdmin(User $mhs, User $pembimbing): PengajuanAkademik
{
    test()->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), isianPendadaran());
    $p = PengajuanAkademik::where('jenis', 'pendadaran')->latest('id')->first();
    test()->actingAs($pembimbing)->post(route('dosen.bimbingan.pendadaran.setujui', $p));

    return $p->fresh();
}

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2025-10-01 09:00:00');
});

it('lists the defence requirements and locks the form until they are met', function () {
    [$mhs, , $kelasTa] = mahasiswaSiapPendadaran();
    $m = $mhs->mahasiswaProfile;

    // Skripsi yang belum dinilai tidak menghalangi.
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranPendadaran.keadaan', 'baru')
        ->where('pendaftaranPendadaran.syarat.2.keterangan', 'Sudah 3 SKS bernilai.'));

    PengaturanAkademik::current()->update(['min_sks_pendadaran' => 138]);
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranPendadaran.keadaan', 'belum_memenuhi')
        ->where('pendaftaranPendadaran.syarat.2.label', 'Menempuh minimal 138 SKS (di luar TA/Skripsi)')
        ->where('pendaftaranPendadaran.syarat.2.terpenuhi', false));
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), isianPendadaran())
        ->assertSessionHasErrors(['judul' => 'Anda belum memenuhi syarat pendaftaran pendadaran.']);
    PengaturanAkademik::current()->update(['min_sks_pendadaran' => 3]);

    // Nilai E menghalangi, kecuali sudah diperbaiki dengan mengulang.
    $kelasE = createMateriKelasKuliah(TahunAkademik::where('status', false)->first());
    $kelasE->mataKuliah->update(['nama_matkul' => 'Statistika']);
    Krs::create(['mahasiswa_id' => $m->id, 'kelas_id' => $kelasE->id, 'status' => 'Aktif', 'nilai' => 'E']);
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranPendadaran.keadaan', 'belum_memenuhi')
        ->where('pendaftaranPendadaran.syarat.3.keterangan', 'Nilai E: Statistika.'));
    $ulang = KelasKuliah::create(['kode_kelas' => 'STAT-U', 'tahun_akademik_id' => $kelasTa->tahun_akademik_id, 'kapasitas' => 30, 'dosen_id' => $kelasE->dosen_id, 'matkul_id' => $kelasE->matkul_id]);
    Krs::create(['mahasiswa_id' => $m->id, 'kelas_id' => $ulang->id, 'status' => 'Aktif', 'nilai' => 'B']);
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page->where('pendaftaranPendadaran.syarat.3.terpenuhi', true));

    // Mata kuliah lain yang belum dinilai menghalangi.
    $kelasKosong = createMateriKelasKuliah();
    $kelasKosong->mataKuliah->update(['nama_matkul' => 'Etika Profesi']);
    Krs::create(['mahasiswa_id' => $m->id, 'kelas_id' => $kelasKosong->id, 'status' => 'Aktif']);
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranPendadaran.keadaan', 'belum_memenuhi')
        ->where('pendaftaranPendadaran.syarat.4.keterangan', 'Belum dinilai: Etika Profesi.'));
});

it('keeps the defence step locked until the TA is approved', function () {
    $kelas = createMateriKelasKuliah();
    $kelas->mataKuliah->update(['tugas_akhir' => true]);
    $mhs = User::factory()->mahasiswa()->create();
    Krs::create(['mahasiswa_id' => $mhs->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'status' => 'Aktif']);

    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page->where('pendaftaranPendadaran.keadaan', 'terkunci'));
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), isianPendadaran())
        ->assertSessionHasErrors(['judul' => 'Tugas akhir Anda belum disahkan.']);
});

it('sends the registration to a supervisor before the admin', function () {
    [$mhs, $pembimbing] = mahasiswaSiapPendadaran();
    $admin = User::factory()->admin()->create();

    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), isianPendadaran(['bukti_bayar' => null]))->assertSessionHasErrors('bukti_bayar');
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), isianPendadaran())->assertSessionHas('success');
    $p = PengajuanAkademik::where('jenis', 'pendadaran')->sole();
    expect($p->status)->toBe(PengajuanAkademik::MENUNGGU_PEMBIMBING)
        ->and($p->tugas_akhir_id)->toBe(TugasAkhir::sole()->id)
        ->and(array_keys($p->lampiran))->toBe(['naskah', 'persetujuan_pembimbing', 'bukti_bayar']);

    // Terkunci selama menunggu, tanpa pembatalan.
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page->where('pendaftaranPendadaran.keadaan', 'menunggu'));
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), isianPendadaran())
        ->assertSessionHasErrors(['judul' => 'Pendaftaran Anda masih menunggu diproses.']);

    // Admin belum bisa memproses; dosen lain tidak bisa menyetujui.
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.perbaikan', $p), ['catatan' => 'x'])->assertSessionHas('error');
    $this->actingAs(User::factory()->dosen()->create())->post(route('dosen.bimbingan.pendadaran.setujui', $p))->assertNotFound();

    $this->actingAs($pembimbing)->get(route('dosen.bimbingan.index'))->assertInertia(fn ($page) => $page->has('menungguPersetujuan', 1));
    $this->actingAs($pembimbing)->post(route('dosen.bimbingan.pendadaran.setujui', $p))->assertSessionHas('success');
    $p->refresh();
    expect($p->status)->toBe(PengajuanAkademik::MENUNGGU)
        ->and($p->disetujui_pembimbing_oleh)->toBe($pembimbing->dosenProfile->id);
    $this->actingAs($pembimbing)->post(route('dosen.bimbingan.pendadaran.setujui', $p))->assertSessionHas('error', 'Pendaftaran ini sudah diproses.');

    // Perbaikan dari admin dikirim ulang langsung ke admin (persetujuan pembimbing tetap berlaku).
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.perbaikan', $p), ['catatan' => 'Bukti bayar buram'])->assertSessionHas('success');
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), ['judul' => 'Judul Final', 'bukti_bayar' => UploadedFile::fake()->image('bukti.png')])
        ->assertSessionHas('success');
    expect($p->fresh()->status)->toBe(PengajuanAkademik::MENUNGGU)
        ->and($p->fresh()->riwayat->pluck('status')->all())->toBe(['dikirim', 'disetujui_pembimbing', 'perlu_perbaikan', 'dikirim']);
});

it('sends a supervisor-requested fix back to the supervisor', function () {
    [$mhs, $pembimbing] = mahasiswaSiapPendadaran();
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), isianPendadaran());
    $p = PengajuanAkademik::where('jenis', 'pendadaran')->sole();

    $this->actingAs($pembimbing)->post(route('dosen.bimbingan.pendadaran.perbaikan', $p))->assertSessionHasErrors('catatan');
    $this->actingAs($pembimbing)->post(route('dosen.bimbingan.pendadaran.perbaikan', $p), ['catatan' => 'Bab 4 belum lengkap'])->assertSessionHas('success');
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranPendadaran.keadaan', 'perbaikan')
        ->where('pendaftaranPendadaran.pengajuan.catatan', 'Bab 4 belum lengkap'));

    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), ['judul' => 'Judul Final', 'naskah' => UploadedFile::fake()->create('n2.pdf', 300, 'application/pdf')])
        ->assertSessionHas('success');
    expect($p->fresh()->status)->toBe(PengajuanAkademik::MENUNGGU_PEMBIMBING);

    // Ditolak pembimbing: form baru.
    $this->actingAs($pembimbing)->post(route('dosen.bimbingan.pendadaran.tolak', $p), ['catatan' => 'Belum layak uji']);
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page->where('pendaftaranPendadaran.keadaan', 'baru'));
});

it('schedules the defence with three examiners and publishes it', function () {
    [$mhs, $pembimbing] = mahasiswaSiapPendadaran();
    $admin = User::factory()->admin()->create();
    $ruang = Ruang::create(['kode_ruang' => 'R-SID', 'nama_ruang' => 'Ruang Sidang', 'kapasitas' => 10]);
    $penguji = [$pembimbing, User::factory()->dosen()->create(), User::factory()->dosen()->create()];
    $p = pendaftaranMenungguAdmin($mhs, $pembimbing);

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p), jadwalPendadaran($ruang, [$penguji[0], $penguji[0], $penguji[2]]))
        ->assertSessionHasErrors('penguji_2_id');
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p), jadwalPendadaran($ruang, $penguji, ['tanggal' => '2025-09-30']))
        ->assertSessionHasErrors('tanggal');
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p), jadwalPendadaran($ruang, $penguji))->assertSessionHas('success');

    $jadwal = Pendadaran::sole();
    expect($jadwal->status)->toBe(Pendadaran::DIJADWALKAN)
        ->and($jadwal->penguji_1_id)->toBe($pembimbing->dosenProfile->id)
        ->and($p->fresh()->status)->toBe(PengajuanAkademik::DISETUJUI)
        ->and(TugasAkhir::sole()->judul)->toBe('Judul Final');

    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranPendadaran.keadaan', 'terjadwal')
        ->where('pendaftaranPendadaran.jadwal.jam_mulai', '09:00')
        ->where('pendaftaranPendadaran.jadwal.ruang', 'R-SID Ruang Sidang')
        ->where('pendaftaranPendadaran.jadwal.penguji.0.peran', 'Ketua Penguji'));
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-pendadaran'), isianPendadaran())
        ->assertSessionHasErrors(['judul' => 'Pendadaran Anda sudah dijadwalkan.']);

    // Jadwal tampil di halaman tiap penguji; penguji bisa membuka naskah.
    $this->actingAs($penguji[2])->get(route('dosen.bimbingan.index'))->assertInertia(fn ($page) => $page
        ->has('jadwalPendadaran', 1)
        ->where('jadwalPendadaran.0.peran', 'Penguji 3')
        ->has('bimbingan', 0));
    $this->actingAs($penguji[2])->get(route('berkas.pengajuan-akademik', [$p, 'naskah']))->assertOk();
    $this->actingAs($pembimbing)->get(route('dosen.bimbingan.index'))->assertInertia(fn ($page) => $page->where('jadwalPendadaran.0.peran', 'Ketua Penguji'));
});

it('refuses room and examiner clashes but only warns about teaching clashes', function () {
    [$mhs, $pembimbing] = mahasiswaSiapPendadaran();
    [$mhs2, $pembimbing2] = mahasiswaSiapPendadaran();
    $admin = User::factory()->admin()->create();
    $ruang = Ruang::create(['kode_ruang' => 'R-A', 'nama_ruang' => 'Ruang A', 'kapasitas' => 10]);
    $ruangB = Ruang::create(['kode_ruang' => 'R-B', 'nama_ruang' => 'Ruang B', 'kapasitas' => 10]);
    $penguji = [User::factory()->dosen()->create(), User::factory()->dosen()->create(), User::factory()->dosen()->create()];

    $p1 = pendaftaranMenungguAdmin($mhs, $pembimbing);
    $p2 = pendaftaranMenungguAdmin($mhs2, $pembimbing2);
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p1), jadwalPendadaran($ruang, $penguji))->assertSessionHas('success');

    // Ruang dipakai pendadaran lain di jam beririsan.
    $lain = [User::factory()->dosen()->create(), User::factory()->dosen()->create(), User::factory()->dosen()->create()];
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p2), jadwalPendadaran($ruang, $lain, ['jam_mulai' => '10:00', 'jam_akhir' => '12:00']))
        ->assertSessionHasErrors(['ruang_id' => 'Ruang sudah dipakai pendadaran '.$mhs->name.' (09:00–11:00).']);

    // Seorang penguji menguji pendadaran lain di jam yang sama.
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p2), jadwalPendadaran($ruangB, [$lain[0], $lain[1], $penguji[1]]))
        ->assertSessionHasErrors('penguji_3_id');

    // Ruang dipakai jadwal kuliah mingguan (Senin 08:00–10:00).
    $kelas = createMateriKelasKuliah();
    Jadwal::create(['kelas_id' => $kelas->id, 'hari' => 'Senin', 'jam_mulai' => '08:00', 'jam_akhir' => '10:00', 'ruang_id' => $ruangB->id]);
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p2), jadwalPendadaran($ruangB, $lain))
        ->assertSessionHasErrors(['ruang_id' => 'Ruang sudah dipakai kuliah kelas '.$kelas->kode_kelas.' (08:00–10:00).']);

    // Penguji sedang mengajar: hanya peringatan, bisa tetap disimpan.
    $ruangC = Ruang::create(['kode_ruang' => 'R-C', 'nama_ruang' => 'Ruang C', 'kapasitas' => 10]);
    $dosenKelas = User::find($kelas->dosen->user_id);
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p2), jadwalPendadaran($ruangC, [$dosenKelas, $lain[1], $lain[2]]))
        ->assertSessionHasErrors(['peringatan.0' => 'Jadwal penguji '.$dosenKelas->name.' akan bentrok dengan kuliah kelas '.$kelas->kode_kelas.' (08:00–10:00).']);
    expect(Pendadaran::count())->toBe(1);
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p2), jadwalPendadaran($ruangC, [$dosenKelas, $lain[1], $lain[2]], ['abaikan_peringatan' => true]))
        ->assertSessionHas('success');
    expect(Pendadaran::count())->toBe(2);
});

it('rechecks the requirements before scheduling', function () {
    [$mhs, $pembimbing] = mahasiswaSiapPendadaran();
    $admin = User::factory()->admin()->create();
    $p = pendaftaranMenungguAdmin($mhs, $pembimbing);
    Krs::whereHas('kelasKuliah.mataKuliah', fn ($q) => $q->where('tugas_akhir', false))->update(['nilai' => 'E']);

    $ruang = Ruang::create(['kode_ruang' => 'R-X', 'nama_ruang' => 'Ruang X', 'kapasitas' => 10]);
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p), jadwalPendadaran($ruang, [User::factory()->dosen()->create(), User::factory()->dosen()->create(), User::factory()->dosen()->create()]))
        ->assertSessionHas('error');
    expect(Pendadaran::count())->toBe(0)->and($p->fresh()->status)->toBe(PengajuanAkademik::MENUNGGU);
});
