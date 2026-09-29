<?php

use App\Models\Krs;
use App\Models\Pendadaran;
use App\Models\PengajuanAkademik;
use App\Models\PengaturanAkademik;
use App\Models\PeriodeWisuda;
use App\Models\Ruang;
use App\Models\TahunAkademik;
use App\Models\TugasAkhir;
use App\Models\User;
use App\Models\Wisuda;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Mahasiswa yang lulus pendadaran 6 Okt 2025: Skripsi (3 SKS) bernilai A dan satu mata kuliah lain (3 SKS) bernilai B.
 *
 * @return array{0: User, 1: Krs}
 */
function mahasiswaLulusPendadaran(string $nilaiSkripsi = 'A'): array
{
    $kelasTa = createMateriKelasKuliah();
    $kelasTa->mataKuliah->update(['nama_matkul' => 'Skripsi', 'tugas_akhir' => true]);
    $mhs = User::factory()->mahasiswa()->create(['name' => 'Rina Kusuma']);
    $m = $mhs->mahasiswaProfile;
    $m->update(['tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2003-04-05', 'status' => 'Aktif']);
    $krsTa = Krs::create(['mahasiswa_id' => $m->id, 'kelas_id' => $kelasTa->id, 'status' => 'Aktif', 'nilai' => $nilaiSkripsi]);

    $lalu = TahunAkademik::firstOrCreate(['tahun' => '2024/2025', 'semester' => 'Genap'], ['tanggal_mulai' => '2025-02-01', 'tanggal_akhir' => '2025-07-31', 'tanggal_krs_awal' => '2025-02-01', 'tanggal_krs_akhir' => '2025-02-14', 'status' => false]);
    Krs::create(['mahasiswa_id' => $m->id, 'kelas_id' => createMateriKelasKuliah($lalu)->id, 'status' => 'Aktif', 'nilai' => 'B']);
    PengaturanAkademik::current()->update(['min_sks_pendadaran' => 3]);

    $dosen = [User::factory()->dosen()->create(), User::factory()->dosen()->create(), User::factory()->dosen()->create()];
    $ta = TugasAkhir::create(['mahasiswa_id' => $m->id, 'judul' => 'Sistem Tracer Study', 'bidang' => 'SI', 'pembimbing_1_id' => $dosen[0]->dosenProfile->id, 'status' => TugasAkhir::SELESAI, 'selesai_at' => now()]);
    $daftar = PengajuanAkademik::create(['mahasiswa_id' => $m->id, 'jenis' => 'pendadaran', 'tugas_akhir_id' => $ta->id, 'isian' => ['judul' => $ta->judul], 'lampiran' => [], 'status' => 'disetujui']);
    Pendadaran::create([
        'pengajuan_id' => $daftar->id, 'tugas_akhir_id' => $ta->id, 'mahasiswa_id' => $m->id, 'tanggal' => '2025-10-06', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00',
        'ruang_id' => Ruang::firstOrCreate(['kode_ruang' => 'R-W'], ['nama_ruang' => 'Ruang W', 'kapasitas' => 10])->id,
        'penguji_1_id' => $dosen[0]->dosenProfile->id, 'penguji_2_id' => $dosen[1]->dosenProfile->id, 'penguji_3_id' => $dosen[2]->dosenProfile->id,
        'status' => Pendadaran::SELESAI, 'hasil' => 'lulus', 'nilai_akhir' => 85, 'huruf' => 'A',
    ]);

    return [$mhs, $krsTa];
}

function periodeWisuda(array $ubah = []): PeriodeWisuda
{
    return PeriodeWisuda::create(['nama' => 'Wisuda I 2025', 'tanggal_acara' => '2025-11-20', 'tempat' => 'Aula', 'batas_daftar' => '2025-11-01', ...$ubah]);
}

function isianWisuda(PeriodeWisuda $periode, array $ubah = []): array
{
    return [
        'periode_wisuda_id' => $periode->id,
        'nama_ijazah' => 'Rina Kusuma',
        'tempat_lahir' => 'Bandung',
        'tanggal_lahir' => '2003-04-05',
        'ukuran_toga' => 'M',
        'pas_foto' => UploadedFile::fake()->image('foto.jpg'),
        'naskah_final' => UploadedFile::fake()->create('naskah.pdf', 500, 'application/pdf'),
        'bebas_pinjam' => UploadedFile::fake()->create('bebas.pdf', 100, 'application/pdf'),
        'bukti_bayar' => UploadedFile::fake()->image('bayar.png'),
        ...$ubah,
    ];
}

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo('2025-10-20 09:00:00');
});

it('opens the graduation step only after passing the defence and with an open period', function () {
    [$mhs, $krsTa] = mahasiswaLulusPendadaran();
    $ta = TugasAkhir::sole();

    $ta->update(['status' => TugasAkhir::BERJALAN]);
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page->where('pendaftaranWisuda.keadaan', 'terkunci'));
    $ta->update(['status' => TugasAkhir::SELESAI]);

    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranWisuda.keadaan', 'belum_memenuhi')
        ->where('pendaftaranWisuda.syarat.4.keterangan', 'Belum ada periode wisuda yang menerima pendaftaran.')
        ->where('pendaftaranWisuda.dataIjazah', ['nama_ijazah' => 'Rina Kusuma', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2003-04-05']));

    // Periode lewat batas daftar tidak dihitung.
    periodeWisuda(['nama' => 'Lama', 'tanggal_acara' => '2025-10-19', 'batas_daftar' => '2025-10-10']);
    $periode = periodeWisuda();
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranWisuda.keadaan', 'baru')
        ->has('pendaftaranWisuda.periodeOptions', 1)
        ->where('pendaftaranWisuda.periodeOptions.0.id', $periode->id));

    // Nilai Skripsi sekarang juga harus ada.
    $krsTa->update(['nilai' => null]);
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranWisuda.keadaan', 'belum_memenuhi')
        ->where('pendaftaranWisuda.syarat.3.keterangan', 'Belum dinilai: Skripsi.'));
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode))
        ->assertSessionHasErrors(['judul' => 'Anda belum memenuhi syarat pendaftaran wisuda.']);
});

it('registers for graduation and waits for the admin', function () {
    [$mhs] = mahasiswaLulusPendadaran();
    $periode = periodeWisuda();

    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode, ['ukuran_toga' => 'XXXL', 'pas_foto' => UploadedFile::fake()->create('foto.pdf', 10, 'application/pdf')]))
        ->assertSessionHasErrors(['ukuran_toga', 'pas_foto']);
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode, ['bukti_bayar' => null]))->assertSessionHasErrors('bukti_bayar');
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode))->assertSessionHas('success');

    $p = PengajuanAkademik::where('jenis', 'wisuda')->sole();
    expect($p->status)->toBe(PengajuanAkademik::MENUNGGU)
        ->and($p->isian['periode_wisuda_id'])->toBe($periode->id)
        ->and(array_keys($p->lampiran))->toBe(['pas_foto', 'naskah_final', 'bebas_pinjam', 'bukti_bayar']);
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode))
        ->assertSessionHasErrors(['judul' => 'Pendaftaran Anda masih menunggu diproses admin.']);
});

it('flags corrected diploma data and approves into the participant list', function () {
    [$mhs] = mahasiswaLulusPendadaran();
    $admin = User::factory()->admin()->create();
    $periode = periodeWisuda();
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode, ['nama_ijazah' => 'Rina Kusumawati']));
    $p = PengajuanAkademik::where('jenis', 'wisuda')->sole();

    $this->actingAs($admin)->get(route('admin.pengajuan-akademik.index', ['jenis' => 'wisuda']))->assertInertia(fn ($page) => $page
        ->where('pengajuan.data.0.periode_wisuda', 'Wisuda I 2025')
        ->where('pengajuan.data.0.koreksi', ['nama_ijazah']));

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p))->assertSessionHas('success');
    $w = Wisuda::sole();
    expect($w->periode_wisuda_id)->toBe($periode->id)->and($w->nomor_skl)->toBeNull()->and($p->fresh()->status)->toBe(PengajuanAkademik::DISETUJUI);
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p))->assertSessionHas('error');

    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranWisuda.keadaan', 'terdaftar')
        ->where('pendaftaranWisuda.wisuda.periode.nama', 'Wisuda I 2025'));
    $this->actingAs($admin)->get(route('admin.periode-wisuda.show', $periode))->assertInertia(fn ($page) => $page
        ->component('Admin/PeriodeWisudaShow')
        ->where('peserta.0.nama', 'Rina Kusumawati')
        ->where('peserta.0.ukuran_toga', 'M'));
});

it('respects the period quota', function () {
    [$mhs] = mahasiswaLulusPendadaran();
    [$mhs2] = mahasiswaLulusPendadaran();
    $admin = User::factory()->admin()->create();
    $periode = periodeWisuda(['kuota' => 1]);

    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode));
    $this->actingAs($mhs2)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode));
    [$p1, $p2] = PengajuanAkademik::where('jenis', 'wisuda')->orderBy('id')->get();

    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p1))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p2))->assertSessionHas('error');
    expect(Wisuda::count())->toBe(1)->and($periode->sisaKuota())->toBe(0);

    // Periode penuh tidak lagi bisa dipilih.
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.perbaikan', $p2), ['catatan' => 'Pilih periode lain']);
    $this->actingAs($mhs2)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode))
        ->assertSessionHasErrors(['judul' => 'Anda belum memenuhi syarat pendaftaran wisuda.']);
    $lain = periodeWisuda(['nama' => 'Wisuda II 2025', 'tanggal_acara' => '2025-12-20', 'batas_daftar' => '2025-12-01']);
    $this->actingAs($mhs2)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), ['periode_wisuda_id' => $periode->id] + isianWisuda($lain))
        ->assertSessionHasErrors(['periode_wisuda_id' => 'Periode wisuda ini sudah ditutup atau kuotanya penuh.']);
    $this->actingAs($mhs2)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($lain))->assertSessionHas('success');
});

it('manages graduation periods', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.periode-wisuda.store'), ['nama' => 'X', 'tanggal_acara' => '2025-11-01', 'batas_daftar' => '2025-11-05'])
        ->assertSessionHasErrors('batas_daftar');
    $this->actingAs($admin)->post(route('admin.periode-wisuda.store'), ['nama' => 'Wisuda I', 'tanggal_acara' => '2025-11-20', 'batas_daftar' => '2025-11-01', 'kuota' => 2])
        ->assertSessionHas('success');
    $periode = PeriodeWisuda::sole();
    expect($periode->tempat)->toBeNull()->and($periode->kuota)->toBe(2);

    [$mhs] = mahasiswaLulusPendadaran();
    $this->actingAs($mhs)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode));
    $this->actingAs($admin)->delete(route('admin.periode-wisuda.destroy', $periode))->assertSessionHas('error');
    $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', PengajuanAkademik::where('jenis', 'wisuda')->sole()));

    $this->actingAs($admin)->put(route('admin.periode-wisuda.update', $periode), ['nama' => 'Wisuda I', 'tanggal_acara' => '2025-11-20', 'batas_daftar' => '2025-11-01', 'kuota' => 0])
        ->assertSessionHasErrors('kuota');
    $this->actingAs($admin)->get(route('admin.periode-wisuda.index'))->assertInertia(fn ($page) => $page
        ->component('Admin/PeriodeWisuda')
        ->where('periode.0.jumlah_peserta', 1)
        ->where('periode.0.dibuka', true));
    $this->actingAs(User::factory()->dosen()->create())->get(route('admin.periode-wisuda.index'))->assertForbidden();
});

it('issues the graduation letter and marks the student as graduated', function () {
    [$mhs] = mahasiswaLulusPendadaran();
    [$mhs2] = mahasiswaLulusPendadaran('B');
    $admin = User::factory()->admin()->create();
    $periode = periodeWisuda();
    foreach ([$mhs, $mhs2] as $m) {
        $this->actingAs($m)->post(route('mahasiswa.tugas-akhir.ajukan-wisuda'), isianWisuda($periode));
    }
    PengajuanAkademik::where('jenis', 'wisuda')->get()->each(fn ($p) => $this->actingAs($admin)->post(route('admin.pengajuan-akademik.setujui', $p)));
    [$w1, $w2] = Wisuda::orderBy('id')->get();

    $this->actingAs($mhs)->get(route('berkas.skl', $w1))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.wisuda.skl', $w1))->assertSessionHas('success');
    $w1->refresh();
    expect($w1->nomor_skl)->toBe('001/SKL/X/2025')
        ->and($w1->ipk)->toBe(3.5)
        ->and($w1->total_sks)->toBe(6)
        ->and($w1->predikat)->toBe('Sangat Memuaskan')
        ->and($w1->tanggal_lulus->toDateString())->toBe('2025-10-06')
        ->and($mhs->mahasiswaProfile->fresh()->status)->toBe('Lulus')
        ->and($mhs2->mahasiswaProfile->fresh()->status)->toBe('Aktif');
    $this->actingAs($admin)->post(route('admin.wisuda.skl', $w1))->assertSessionHas('error', 'SKL mahasiswa ini sudah terbit.');

    $this->actingAs($mhs)->get(route('berkas.skl', $w1))->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($admin)->get(route('berkas.skl', $w1))->assertOk();
    $this->actingAs($mhs2)->get(route('berkas.skl', $w1))->assertForbidden();
    $this->actingAs($mhs)->get(route('mahasiswa.tugas-akhir'))->assertInertia(fn ($page) => $page
        ->where('pendaftaranWisuda.wisuda.nomor_skl', '001/SKL/X/2025')
        ->where('pendaftaranWisuda.wisuda.predikat', 'Sangat Memuaskan'));

    // Massal: hanya yang belum punya SKL.
    $this->actingAs($admin)->post(route('admin.periode-wisuda.skl-massal', $periode))->assertSessionHas('success', '1 SKL terbit; status mahasiswanya menjadi Lulus.');
    expect($w2->fresh()->nomor_skl)->toBe('002/SKL/X/2025')->and($w2->fresh()->ipk)->toBe(3.0)->and($w2->fresh()->predikat)->toBe('Memuaskan')
        ->and($mhs2->mahasiswaProfile->fresh()->status)->toBe('Lulus');

    $this->actingAs($admin)->get(route('admin.periode-wisuda.cetak', $periode))->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('grades the predicate from the GPA', function (float $ipk, string $predikat) {
    expect(Wisuda::predikat($ipk))->toBe($predikat);
})->with([
    [3.51, 'Dengan Pujian (Cum Laude)'],
    [3.50, 'Sangat Memuaskan'],
    [3.01, 'Sangat Memuaskan'],
    [3.00, 'Memuaskan'],
    [2.76, 'Memuaskan'],
    [2.75, 'Cukup'],
]);
