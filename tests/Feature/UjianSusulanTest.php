<?php

use App\Models\Jadwal;
use App\Models\JenisBiaya;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\PengajuanSusulan;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\Ruang;
use App\Models\TagihanSusulan;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\User;
use App\UsulanRemidi;
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

/**
 * Pengajuan susulan disetujui untuk kedua mahasiswa kelasSusulan(); jam sekarang 7 Okt 2025 09:00.
 *
 * @return array{0: KelasKuliah, 1: list<User>, 2: Ujian, 3: User}
 */
function susulanDisetujui(string $mode = 'tatap_muka'): array
{
    [$kelas, $mhs, $ujian] = kelasSusulan($mode);
    $admin = User::factory()->admin()->create();
    test()->travelTo('2025-10-06 16:00:00');
    foreach ($mhs as $m) {
        test()->actingAs($m)->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan());
    }
    foreach (PengajuanSusulan::all() as $p) {
        test()->actingAs($admin)->post(route('admin.ujian-susulan.setujui', $p));
    }
    test()->travelTo('2025-10-07 09:00:00');

    return [$kelas, $mhs, $ujian, $admin];
}

function jenisBiayaSusulan(int $nominal = 100_000, string $cara = JenisBiaya::TETAP): JenisBiaya
{
    $jenis = JenisBiaya::create(['kode' => "SUSULAN-{$cara}-{$nominal}", 'nama' => 'Biaya Ujian Susulan', 'cara_hitung' => $cara, 'kategori' => JenisBiaya::SUSULAN, 'aktif' => true]);
    $jenis->tarif()->create(['nominal' => $nominal]);

    return $jenis;
}

it('issues susulan bills for approved applications with a per-bill deadline', function () {
    [$kelas, $mhs, , $admin] = susulanDisetujui();

    $this->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id])
        ->assertSessionHas('error');
    jenisBiayaSusulan(50_000, JenisBiaya::PER_SKS);

    $this->actingAs($admin)->get(route('admin.tagihan-susulan.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->where('ringkasan.belum_ditagih', 2));
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id])->assertSessionHas('success');

    $t = TagihanSusulan::where('mahasiswa_id', $mhs[0]->mahasiswaProfile->id)->first();
    expect($t->total)->toBe(50_000 * $kelas->mataKuliah->sks)
        ->and($t->status)->toBe(TagihanSusulan::BELUM_BAYAR)
        ->and($t->batas_bayar->toDateString())->toBe('2025-10-10');

    $this->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id])
        ->assertSessionHas('success', 'Tidak ada pengajuan baru yang perlu ditagih.');
    expect(TagihanSusulan::count())->toBe(2);
});

it('runs the payment cycle and lapses unpaid bills after their own deadline', function () {
    [$kelas, $mhs, , $admin] = susulanDisetujui();
    jenisBiayaSusulan();
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);
    [$a, $b] = TagihanSusulan::orderBy('id')->get();
    $bukti = fn () => ['bukti' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf')];

    $this->actingAs($mhs[1])->post(route('mahasiswa.tagihan-susulan.bukti', $a), $bukti())->assertNotFound();
    $this->actingAs($mhs[0])->post(route('mahasiswa.tagihan-susulan.bukti', $a), $bukti())->assertSessionHas('success');
    expect($a->fresh()->status)->toBe(TagihanSusulan::MENUNGGU);
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.tolak', $a), ['alasan' => 'Buram'])->assertSessionHas('success');
    $this->actingAs($mhs[0])->post(route('mahasiswa.tagihan-susulan.bukti', $a), $bukti())->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.lunas', $a))->assertSessionHas('success');
    expect($a->fresh()->status)->toBe(TagihanSusulan::LUNAS);

    $this->actingAs($mhs[0])->get(route('berkas.bukti-susulan', $a))->assertOk();
    $this->actingAs($mhs[1])->get(route('berkas.bukti-susulan', $a))->assertForbidden();

    $this->travelTo('2025-10-11 08:00:00');
    $this->actingAs($mhs[1])->post(route('mahasiswa.tagihan-susulan.bukti', $b), $bukti())->assertSessionHas('error', 'Batas bayar susulan sudah lewat.');
    $this->actingAs($mhs[1])->get(route('mahasiswa.info-biaya-kuliah'))
        ->assertInertia(fn ($page) => $page->where('tagihanSusulan.0.status', 'gugur')->where('tagihanSusulan.0.boleh_unggah', false));
});

it('cancels the bill of a student who sat the main exam, and flags a paid one', function () {
    [$kelas, $mhs, , $admin] = susulanDisetujui();
    jenisBiayaSusulan();
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);
    [$a, $b] = TagihanSusulan::orderBy('id')->get();
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.lunas', $b));

    // Keduanya ternyata tercatat hadir di pertemuan UTS (tatap muka).
    $pertemuanUts = Pertemuan::where('kelas_id', $kelas->id)->where('jenis', 'uts')->first();
    foreach ($mhs as $m) {
        PresensiMahasiswa::create(['pertemuan_id' => $pertemuanUts->id, 'mahasiswa_id' => $m->mahasiswaProfile->id, 'status' => PresensiMahasiswa::HADIR, 'metode' => 'manual']);
    }

    $this->actingAs($mhs[0])->post(route('mahasiswa.tagihan-susulan.bukti', $a), ['bukti' => UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')])
        ->assertSessionHas('error', 'Anda sudah mengikuti ujian utama, jadi tagihan susulan ini dibatalkan.');
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.lunas', $a))->assertSessionHas('error');

    $baris = collect($this->actingAs($admin)->get(route('admin.tagihan-susulan.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))->inertiaProps('tagihan.data'))->keyBy('id');
    expect($baris[$a->id])->toMatchArray(['status' => 'dibatalkan', 'ikut_ujian_utama' => true])
        ->and($baris[$b->id])->toMatchArray(['status' => 'lunas', 'ikut_ujian_utama' => true]);

    // Susulan tidak ikut tagihan semester.
    $this->actingAs($admin)->post(route('admin.tagihan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'paksa' => true])
        ->assertSessionHas('error', 'Belum ada jenis biaya aktif. Isi dulu di menu Jenis Biaya.');
});

/**
 * Susulan disetujui untuk kedua mahasiswa; tagihan terbit, hanya mahasiswa pertama yang lunas.
 *
 * @return array{0: KelasKuliah, 1: list<User>, 2: Ujian, 3: User}
 */
function susulanLunas(string $mode = 'tatap_muka'): array
{
    [$kelas, $mhs, $ujian, $admin] = susulanDisetujui($mode);
    jenisBiayaSusulan();
    test()->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);
    test()->actingAs($admin)->post(route('admin.tagihan-susulan.lunas', TagihanSusulan::where('mahasiswa_id', $mhs[0]->mahasiswaProfile->id)->first()));

    return [$kelas, $mhs, $ujian, $admin];
}

function isianJadwalSusulan(KelasKuliah $kelas, array $ubah = []): array
{
    return ['kelas_id' => $kelas->id, 'jenis' => 'uts_susulan', 'mode' => 'online_berkas', 'tanggal' => '2025-10-13', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'status' => 'terbit', ...$ubah];
}

it('lets admin schedule a susulan only for classes with paid applicants, inside its window', function () {
    [$kelas, $mhs, $ujian, $admin] = susulanDisetujui();

    $this->actingAs($admin)->post(route('admin.ujian.store'), isianJadwalSusulan($kelas))
        ->assertSessionHasErrors(['kelas_id' => 'Belum ada pemohon susulan kelas ini yang pengajuannya disetujui dan tagihannya lunas.']);

    jenisBiayaSusulan();
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.lunas', TagihanSusulan::first()));
    $kelas->tahunAkademik->update(['batas_input_nilai' => '2025-10-20']);

    $this->actingAs($admin)->get(route('admin.ujian.index', ['tahun_akademik_id' => $kelas->tahun_akademik_id]))
        ->assertInertia(fn ($page) => $page->where('susulanSiap.uts', 1)->where('susulanSiap.uas', 0));
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianJadwalSusulan($kelas, ['tanggal' => '2025-10-05']))->assertSessionHasErrors('tanggal');
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianJadwalSusulan($kelas, ['tanggal' => '2025-10-21']))->assertSessionHasErrors('tanggal');
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianJadwalSusulan($kelas))->assertSessionHasNoErrors();

    $susulan = Ujian::where('jenis', 'uts_susulan')->first();
    expect($susulan->pertemuan())->toBeNull()->and($susulan->labelJenis())->toBe('UTS Susulan');
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianJadwalSusulan($kelas, ['tanggal' => '2025-10-14']))->assertSessionHasErrors('jenis');
});

it('shows the susulan only to paid applicants and keeps attendance unchanged', function () {
    [$kelas, $mhs, $ujian, $admin] = susulanLunas();
    $susulan = Ujian::create(isianJadwalSusulan($kelas));
    $this->travelTo('2025-10-13 10:00:00');

    $this->actingAs($mhs[0])->get(route('mahasiswa.ujian'))
        ->assertInertia(fn ($page) => $page->where('ujians', fn ($u) => collect($u)->contains('jenis', 'uts_susulan')
            && collect($u)->firstWhere('jenis', 'uts')['terdaftar_susulan'] === true));
    $this->actingAs($mhs[0])->get(route('mahasiswa.ujian.show', $susulan))->assertInertia(fn ($page) => $page->where('bolehIkut', true));
    $this->actingAs($mhs[0])->get(route('mahasiswa.ujian.kartu', ['jenis' => 'uts_susulan']))->assertOk();
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.kumpulkan', $susulan), ['jawaban' => [UploadedFile::fake()->create('j.pdf', 10, 'application/pdf')]])
        ->assertSessionHas('success');

    // Belum lunas: tidak melihat jadwal susulan sama sekali.
    $this->actingAs($mhs[1])->get(route('mahasiswa.ujian'))
        ->assertInertia(fn ($page) => $page->where('ujians', fn ($u) => ! collect($u)->contains('jenis', 'uts_susulan')));
    $this->actingAs($mhs[1])->get(route('mahasiswa.ujian.show', $susulan))->assertNotFound();

    expect(PresensiMahasiswa::whereHas('pertemuan', fn ($q) => $q->where('kelas_id', $kelas->id)->where('jenis', 'uts'))->count())->toBe(0);

    // Dosen hanya melihat peserta susulan, bisa menilai, dan mencetak daftar hadir.
    $dosen = $kelas->dosen->user;
    $this->travelTo('2025-10-13 12:00:00');
    $this->actingAs($dosen)->get(route('dosen.ujian.show', $susulan))
        ->assertInertia(fn ($page) => $page->has('peserta', 1)->where('peserta.0.mahasiswa_id', $mhs[0]->mahasiswaProfile->id));
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$susulan, $mhs[0]->mahasiswaProfile]), ['nilai' => 80])->assertSessionHas('success');
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$susulan, $mhs[1]->mahasiswaProfile]), ['nilai' => 80])->assertNotFound();
    $this->actingAs($dosen)->get(route('dosen.ujian.daftar-hadir', $susulan))->assertOk();
});

it('blocks the online main exam for approved applicants', function () {
    [$kelas, $mhs, $ujian] = kelasSusulan('online_berkas');
    $this->travelTo('2025-10-05 09:00:00');
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $ujian), isianSusulan());
    $this->actingAs(User::factory()->admin()->create())->post(route('admin.ujian-susulan.setujui', PengajuanSusulan::first()));

    $this->travelTo('2025-10-06 14:00:00');
    $berkas = fn () => ['jawaban' => [UploadedFile::fake()->create('j.pdf', 10, 'application/pdf')]];
    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.kumpulkan', $ujian), $berkas())
        ->assertSessionHasErrors(['jawaban' => 'Anda terdaftar ujian susulan untuk ujian ini. Ikuti jadwal ujian susulannya.']);
    $this->actingAs($mhs[1])->post(route('mahasiswa.ujian.kumpulkan', $ujian), $berkas())->assertSessionHas('success');
});

it('drops the susulan right of a paid applicant who is recorded at the main exam', function () {
    [$kelas, $mhs] = susulanLunas();
    $susulan = Ujian::create(isianJadwalSusulan($kelas));
    $pertemuanUts = Pertemuan::where('kelas_id', $kelas->id)->where('jenis', 'uts')->first();
    PresensiMahasiswa::create(['pertemuan_id' => $pertemuanUts->id, 'mahasiswa_id' => $mhs[0]->mahasiswaProfile->id, 'status' => PresensiMahasiswa::HADIR, 'metode' => 'manual']);
    $this->travelTo('2025-10-13 10:00:00');

    $this->actingAs($mhs[0])->get(route('mahasiswa.ujian.show', $susulan))->assertNotFound();
    $this->actingAs($kelas->dosen->user)->get(route('dosen.ujian.show', $susulan))->assertInertia(fn ($page) => $page->has('peserta', 0));
});

it('creates draft susulan schedules for all ready classes at once', function () {
    [$kelas, , , $admin] = susulanLunas();
    $isian = ['tahun_akademik_id' => $kelas->tahun_akademik_id, 'jenis' => 'uts_susulan', 'mode' => 'online_berkas', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00'];

    $this->actingAs($admin)->post(route('admin.ujian.susulan-massal'), [...$isian, 'tanggal' => '2025-10-01'])
        ->assertSessionHas('success', '0 jadwal susulan dibuat sebagai draf. 1 kelas dilewati karena tanggalnya sebelum ujian utama atau sesudah batas input nilai kelas.');
    $this->actingAs($admin)->post(route('admin.ujian.susulan-massal'), [...$isian, 'tanggal' => '2025-10-13'])
        ->assertSessionHas('success', '1 jadwal susulan dibuat sebagai draf.');
    expect(Ujian::where('jenis', 'uts_susulan')->where('status', 'draf')->count())->toBe(1);
});

/**
 * Kelas dengan UAS terbit 15 Des 2025; pengajuan UAS susulan mahasiswa pertama disetujui.
 *
 * @return array{0: KelasKuliah, 1: list<User>, 2: Ujian, 3: User}
 */
function uasDisusul(): array
{
    [$kelas, $mhs] = kelasSusulan();
    $uas = Ujian::create([
        'kelas_id' => $kelas->id, 'jenis' => 'uas', 'mode' => 'online_berkas', 'tanggal' => '2025-12-15', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'status' => 'terbit',
    ]);
    $admin = User::factory()->admin()->create();
    test()->travelTo('2025-12-14 09:00:00');
    test()->actingAs($mhs[0])->post(route('mahasiswa.ujian.susulan', $uas), isianSusulan());
    test()->actingAs($admin)->post(route('admin.ujian-susulan.setujui', PengajuanSusulan::first()));

    return [$kelas->fresh(), $mhs, $uas, $admin];
}

it('holds grade finalization until the approved UAS susulan is done or lapsed', function () {
    [$kelas, $mhs, , $admin] = uasDisusul();
    $dosen = $kelas->dosen->user;
    $this->travelTo('2025-12-16 09:00:00');

    // Belum ditagih: tertahan.
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('error');
    $this->actingAs($dosen)->get(route('dosen.kelas-kuliah.show', $kelas))->assertInertia(fn ($page) => $page->where('statusNilai.susulan_tertunda', 1));

    // Lunas tetapi susulan belum dijadwalkan: masih tertahan; setelah susulan selesai: boleh.
    jenisBiayaSusulan();
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.lunas', TagihanSusulan::first()));
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('error');
    Ujian::create(['kelas_id' => $kelas->id, 'jenis' => 'uas_susulan', 'mode' => 'online_berkas', 'tanggal' => '2025-12-18', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'status' => 'terbit']);
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('error');
    $this->travelTo('2025-12-18 12:00:00');
    $this->actingAs($dosen)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('success');
});

it('does not hold finalization for a lapsed susulan bill', function () {
    [$kelas, , , $admin] = uasDisusul();
    jenisBiayaSusulan();
    $this->travelTo('2025-12-16 09:00:00');
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);

    $this->travelTo('2025-12-20 09:00:00');
    $this->actingAs($kelas->dosen->user)->post(route('dosen.kelas-kuliah.finalisasi-nilai', $kelas))->assertSessionHas('success');
});

it('counts sitting the UAS susulan as sitting the UAS for remidi proposals', function () {
    [$kelas, $mhs] = uasDisusul();
    $susulan = Ujian::create(['kelas_id' => $kelas->id, 'jenis' => 'uas_susulan', 'mode' => 'online_berkas', 'tanggal' => '2025-12-18', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00', 'status' => 'terbit']);
    Krs::query()->where('kelas_id', $kelas->id)->update(['nilai' => 'E']);
    UjianJawaban::create(['ujian_id' => $susulan->id, 'mahasiswa_id' => $mhs[0]->mahasiswaProfile->id, 'berkas' => ['a.pdf'], 'dikumpulkan_at' => '2025-12-18 10:00:00']);

    $usulan = collect(UsulanRemidi::susun($kelas)['mahasiswa'])->keyBy('mahasiswa_id');
    expect($usulan[$mhs[0]->mahasiswaProfile->id]['diusulkan'])->toBeTrue()
        ->and($usulan[$mhs[1]->mahasiswaProfile->id]['diusulkan'])->toBeFalse();
});

it('reminds students and lecturers about susulan on the dashboard', function () {
    [$kelas, $mhs, , $admin] = susulanDisetujui();
    jenisBiayaSusulan();
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.terbitkan'), ['tahun_akademik_id' => $kelas->tahun_akademik_id]);
    $this->actingAs($admin)->post(route('admin.tagihan-susulan.lunas', TagihanSusulan::where('mahasiswa_id', $mhs[0]->mahasiswaProfile->id)->first()));
    Ujian::create(isianJadwalSusulan($kelas));

    $this->actingAs($mhs[0])->get(route('mahasiswa.dashboard'))
        ->assertInertia(fn ($page) => $page->has('susulanMahasiswa.ujian', 1)->has('susulanMahasiswa.tagihan', 0));
    $this->actingAs($mhs[1])->get(route('mahasiswa.dashboard'))
        ->assertInertia(fn ($page) => $page->has('susulanMahasiswa.tagihan', 1)->where('susulanMahasiswa.tagihan.0.batas_bayar', '2025-10-10')->has('susulanMahasiswa.ujian', 0));

    $dosen = $kelas->dosen->user;
    $this->actingAs($dosen)->get(route('dosen.dashboard'))->assertInertia(fn ($page) => $page->has('susulanDosen.siapkan', 1)->has('susulanDosen.nilai', 0));
    $this->travelTo('2025-10-13 12:00:00');
    $this->actingAs($dosen)->get(route('dosen.dashboard'))->assertInertia(fn ($page) => $page->has('susulanDosen.siapkan', 0)->has('susulanDosen.nilai', 1));
});
