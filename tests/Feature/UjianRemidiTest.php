<?php

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Pertemuan;
use App\Models\Quiz;
use App\Models\RemidiPeserta;
use App\Models\Ruang;
use App\Models\TagihanRemidi;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Kelas final dengan tiga mahasiswa bernilai E: [0] peserta lunas, [1] peserta belum bayar, [2] bukan peserta.
 * Batas bayar 12 Jan 2026, batas input nilai remidi 25 Jan 2026.
 *
 * @return array{0: KelasKuliah, 1: list<User>}
 */
function kelasUjianRemidi(): array
{
    $kelas = createMateriKelasKuliah();
    $mhs = [];
    foreach (range(0, 2) as $i) {
        $mhs[] = $user = User::factory()->mahasiswa()->create();
        Krs::create(['mahasiswa_id' => $user->mahasiswaProfile->id, 'kelas_id' => $kelas->id, 'nilai' => 'E']);

        if ($i < 2) {
            $peserta = RemidiPeserta::create(['kelas_id' => $kelas->id, 'mahasiswa_id' => $user->mahasiswaProfile->id, 'nilai_awal' => 'E', 'diusulkan' => true]);
            TagihanRemidi::create([
                'remidi_peserta_id' => $peserta->id, 'mahasiswa_id' => $user->mahasiswaProfile->id, 'kelas_id' => $kelas->id,
                'rincian' => [], 'total' => 100_000, 'status' => $i === 0 ? TagihanRemidi::LUNAS : TagihanRemidi::BELUM_BAYAR,
            ]);
        }
    }
    $kelas->update(['nilai_final_at' => '2026-01-05 10:00:00', 'remidi_dikunci_at' => '2026-01-06 10:00:00']);
    $kelas->tahunAkademik->update(['batas_bayar_remidi' => '2026-01-12', 'batas_input_nilai_remidi' => '2026-01-25']);

    return [$kelas->fresh(), $mhs];
}

function isianRemidi(KelasKuliah $kelas, array $ubah = []): array
{
    return [
        'kelas_id' => $kelas->id, 'jenis' => 'remidi', 'mode' => 'tatap_muka', 'tanggal' => '2026-01-15', 'jam_mulai' => '09:00', 'jam_akhir' => '11:00',
        'ruang_id' => Ruang::firstOrCreate(['kode_ruang' => 'R-REM'], ['nama_ruang' => 'Ruang Remidi', 'kapasitas' => 30])->id,
        'status' => 'terbit', ...$ubah,
    ];
}

function ujianRemidi(KelasKuliah $kelas, array $ubah = []): Ujian
{
    return Ujian::create(isianRemidi($kelas, ['ruang_id' => null, 'mode' => 'online_berkas', ...$ubah]));
}

it('lets admin schedule one remidi per class inside the remidi window', function () {
    [$kelas] = kelasUjianRemidi();
    $admin = User::factory()->admin()->create();
    $this->travelTo('2026-01-10 10:00:00');

    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidi($kelas, ['tanggal' => '2026-01-12']))->assertSessionHasErrors('tanggal');
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidi($kelas, ['tanggal' => '2026-01-26']))->assertSessionHasErrors('tanggal');
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidi($kelas))->assertSessionHasNoErrors();

    $ujian = Ujian::where('kelas_id', $kelas->id)->where('jenis', 'remidi')->first();
    expect($ujian)->not->toBeNull()->and($ujian->pertemuan())->toBeNull();

    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidi($kelas, ['tanggal' => '2026-01-16']))->assertSessionHasErrors('jenis');
});

it('refuses remidi schedules for classes that are not ready', function () {
    [$kelas] = kelasUjianRemidi();
    $admin = User::factory()->admin()->create();

    $kelas->update(['remidi_dikunci_at' => null]);
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidi($kelas))->assertSessionHasErrors(['kelas_id' => 'Daftar remidi kelas ini belum dikunci dosen.']);

    $kelas->update(['remidi_dikunci_at' => now()]);
    TagihanRemidi::query()->update(['status' => TagihanRemidi::BELUM_BAYAR]);
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidi($kelas))->assertSessionHasErrors(['kelas_id' => 'Belum ada peserta remidi kelas ini yang tagihannya lunas.']);

    $kelas->tahunAkademik->update(['batas_input_nilai_remidi' => null]);
    $this->actingAs($admin)->post(route('admin.ujian.store'), isianRemidi($kelas))->assertSessionHasErrors('kelas_id');

    expect(Ujian::count())->toBe(0);
});

it('shows the remidi only to paid participants', function () {
    [$kelas, $mhs] = kelasUjianRemidi();
    $ujian = ujianRemidi($kelas);
    $this->travelTo('2026-01-14 10:00:00');

    $this->actingAs($mhs[0])->get(route('mahasiswa.ujian'))
        ->assertInertia(fn ($page) => $page->has('ujians', 1)->where('ujians.0.jenis', 'remidi'));
    $this->actingAs($mhs[0])->get(route('mahasiswa.ujian.show', $ujian))->assertOk();
    $this->actingAs($mhs[0])->get(route('mahasiswa.ujian.kartu', ['jenis' => 'remidi']))->assertOk()->assertHeader('content-type', 'application/pdf');

    foreach ([$mhs[1], $mhs[2]] as $bukanPeserta) {
        $this->actingAs($bukanPeserta)->get(route('mahasiswa.ujian'))->assertInertia(fn ($page) => $page->has('ujians', 0));
        $this->actingAs($bukanPeserta)->get(route('mahasiswa.ujian.show', $ujian))->assertNotFound();
        $this->actingAs($bukanPeserta)->get(route('mahasiswa.ujian.kartu', ['jenis' => 'remidi']))->assertNotFound();
    }
});

it('accepts remidi answer files only from paid participants', function () {
    Storage::fake('local');
    [$kelas, $mhs] = kelasUjianRemidi();
    $ujian = ujianRemidi($kelas);
    $this->travelTo('2026-01-15 10:00:00');
    $berkas = fn () => ['jawaban' => [UploadedFile::fake()->create('jawab.pdf', 50, 'application/pdf')]];

    $this->actingAs($mhs[0])->post(route('mahasiswa.ujian.kumpulkan', $ujian), $berkas())->assertSessionHas('success');
    $this->actingAs($mhs[1])->post(route('mahasiswa.ujian.kumpulkan', $ujian), $berkas())->assertNotFound();

    expect(UjianJawaban::where('ujian_id', $ujian->id)->count())->toBe(1);
    // Remidi tidak menyentuh presensi.
    expect(Pertemuan::where('kelas_id', $kelas->id)->count())->toBe(0);
});

it('lets only paid participants start the remidi question sheet', function () {
    [$kelas, $mhs] = kelasUjianRemidi();
    $ujian = ujianRemidi($kelas, ['mode' => 'online_soal']);
    $quiz = Quiz::create(['nama_quiz' => 'Remidi', 'tenggat_waktu' => $ujian->akhirAt(), 'uploaded_by' => $kelas->dosen->user_id, 'kelas_id' => $kelas->id, 'ujian_id' => $ujian->id]);
    $quiz->questions()->create(['question_text' => 'Satu', 'question_type' => 'essay', 'points' => 10]);
    $this->travelTo('2026-01-15 10:00:00');

    $this->actingAs($mhs[1])->get(route('mahasiswa.quiz.show', $quiz))->assertNotFound();
    $this->actingAs($mhs[1])->post(route('mahasiswa.quiz.start', $quiz))->assertSessionHas('error', 'Anda bukan peserta remidi yang tagihannya lunas.');
    $this->actingAs($mhs[0])->post(route('mahasiswa.quiz.start', $quiz))->assertSessionHas('success');
});

it('lets the lecturer prepare and grade the remidi although class grades are final, until the remidi deadline', function () {
    [$kelas, $mhs] = kelasUjianRemidi();
    $ujian = ujianRemidi($kelas, ['mode' => 'tatap_muka', 'ruang_id' => Ruang::firstOrCreate(['kode_ruang' => 'R-REM'], ['nama_ruang' => 'R', 'kapasitas' => 30])->id]);
    $dosen = $kelas->dosen->user;

    $this->travelTo('2026-01-15 12:00:00');
    $this->actingAs($dosen)->get(route('dosen.ujian.show', $ujian))
        ->assertInertia(fn ($page) => $page->has('peserta', 1)->where('peserta.0.mahasiswa_id', $mhs[0]->mahasiswaProfile->id)->where('terkunci', false));
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $mhs[0]->mahasiswaProfile]), ['nilai' => 72])->assertSessionHas('success');
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $mhs[1]->mahasiswaProfile]), ['nilai' => 72])->assertNotFound();
    $this->actingAs($dosen)->get(route('dosen.ujian.daftar-hadir', $ujian))->assertOk()->assertHeader('content-type', 'application/pdf');

    // Nilai tugas/huruf akhir biasa tetap terkunci karena kelas sudah final.
    $krs = Krs::where('kelas_id', $kelas->id)->first();
    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.krs.nilai', [$kelas, $krs]), ['nilai' => 'C'])->assertSessionHas('error');

    $this->travelTo('2026-01-26 08:00:00');
    $this->actingAs($dosen)->put(route('dosen.ujian.nilai', [$ujian, $mhs[0]->mahasiswaProfile]), ['nilai' => 90])->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->put(route('admin.ujian.nilai', [$ujian, $mhs[0]->mahasiswaProfile]), ['nilai' => 90])->assertSessionHas('success');
    expect((float) UjianJawaban::where('ujian_id', $ujian->id)->value('nilai'))->toBe(90.0);
});

it('lets the lecturer edit remidi questions on a finalized class before it starts', function () {
    [$kelas] = kelasUjianRemidi();
    $ujian = ujianRemidi($kelas, ['mode' => 'online_soal']);
    $dosen = $kelas->dosen->user;
    $this->travelTo('2026-01-14 10:00:00');

    $this->actingAs($dosen)->post(route('dosen.ujian.lembar-soal', $ujian))->assertRedirect();
    $quiz = $ujian->fresh()->quiz;
    expect($quiz->nama_quiz)->toStartWith('Remidi');
    $soal = $quiz->questions()->create(['question_text' => 'Lama', 'question_type' => 'essay', 'points' => 10]);

    $this->actingAs($dosen)->put(route('dosen.kelas-kuliah.quiz.questions.update', [$kelas, $quiz, $soal]), ['question_text' => 'Baru', 'question_type' => 'essay', 'points' => 20])
        ->assertRedirect();
    expect($soal->fresh()->question_text)->toBe('Baru');
});
