<?php

namespace App\Http\Controllers\Mahasiswa;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanAkademik;
use App\Models\PengaturanInstitusi;
use App\Models\Pertemuan;
use App\Models\RemidiPeserta;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\SyaratUjian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UjianController extends Controller
{
    /**
     * Jadwal ujian (yang sudah terbit) dari kelas-kelas di KRS mahasiswa, beserta status syarat kehadirannya.
     */
    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $tahunAkademiks = TahunAkademik::query()
            ->whereHas('kelasKuliahs.krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'tahun', 'semester', 'status']);
        $tahun = $tahunAkademiks->firstWhere('id', $request->integer('tahun_akademik_id'))
            ?? $tahunAkademiks->firstWhere('status', true)
            ?? $tahunAkademiks->first();

        return Inertia::render('Mahasiswa/Ujian', [
            'ujians' => $this->daftarUjian($mahasiswa, $tahun?->id),
            'tahunAkademikId' => $tahun?->id,
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->tahun.' '.$t->semester]),
        ]);
    }

    /**
     * Detail satu ujian untuk mahasiswa. Berkas soal baru dikirim saat ujian dimulai dan hanya untuk
     * mahasiswa yang boleh ikut.
     */
    public function show(Request $request, Ujian $ujian): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $this->pastikanPeserta($ujian, $mahasiswa);
        $ujian->load(['ruang:id,kode_ruang,nama_ruang', 'kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul']);
        $bolehIkut = $ujian->bolehIkut($mahasiswa->id);
        $jawaban = $ujian->jawabans()->where('mahasiswa_id', $mahasiswa->id)->first();
        $quiz = $ujian->mode === Ujian::ONLINE_SOAL ? $ujian->quiz()->withCount('questions')->withSum('questions', 'points')->first() : null;
        $attempt = $quiz?->attempts()->where('mahasiswa_id', $mahasiswa->id)->first();
        $attempt?->setRelation('quiz', $quiz)->closeIfExpired();

        return Inertia::render('Mahasiswa/UjianShow', [
            'ujian' => [
                ...$ujian->only(['id', 'jenis', 'mode', 'jam_mulai', 'jam_akhir', 'pengawas', 'petunjuk']),
                'label_jenis' => $ujian->labelJenis(),
                'tanggal' => $ujian->tanggal->toDateString(),
                'label_mode' => $ujian->labelMode(),
                'ruang' => $ujian->ruang ? $ujian->ruang->kode_ruang.' — '.$ujian->ruang->nama_ruang : null,
                'kode_kelas' => $ujian->kelasKuliah?->kode_kelas,
                'nama_matkul' => $ujian->kelasKuliah?->mataKuliah?->nama_matkul,
                // Nama berkas soal hanya dikirim setelah ujian dimulai.
                'soal' => $bolehIkut && $ujian->sudahMulai() ? array_map(fn (string $p): string => basename($p), $ujian->soal_berkas ?? []) : [],
            ],
            'bolehIkut' => $bolehIkut,
            'sudahMulai' => $ujian->sudahMulai(),
            'sudahSelesai' => $ujian->sudahSelesai(),
            // Hitung mundur memakai jam server.
            'detikSampaiMulai' => $ujian->sudahMulai() ? null : (int) now()->diffInSeconds($ujian->mulaiAt(), true),
            'detikSampaiSelesai' => $ujian->sedangBerlangsung() ? (int) now()->diffInSeconds($ujian->akhirAt(), true) : null,
            'jawaban' => $jawaban === null ? null : [
                'nama_berkas' => array_map(fn (string $p): string => basename($p), $jawaban->berkas ?? []),
                'dikumpulkan_at' => $jawaban->dikumpulkan_at?->toIso8601String(),
                'nilai' => $ujian->nilai_dirilis ? $jawaban->nilai : null,
                'catatan_dosen' => $ujian->nilai_dirilis ? $jawaban->catatan_dosen : null,
            ],
            'nilaiDirilis' => $ujian->nilai_dirilis,
            'lembarSoal' => $quiz === null ? null : [
                'id' => $quiz->id,
                'siap' => $quiz->questions_count > 0,
                'jumlah_soal' => $quiz->questions_count,
                'total_poin' => (int) $quiz->questions_sum_points,
                'waktu_pengerjaan' => $quiz->waktu_pengerjaan,
            ],
            'pengerjaan' => $attempt === null ? null : [
                'selesai' => $attempt->submitted_at !== null,
                'selesai_at' => $attempt->submitted_at?->toIso8601String(),
                'skor' => $ujian->nilai_dirilis ? $attempt->score : null,
                'nilai' => $ujian->nilai_dirilis ? Ujian::nilaiDariSkor($attempt->score, (int) $quiz->questions_sum_points) : null,
            ],
        ]);
    }

    /**
     * Kumpulkan (atau ganti) jawaban berkas. Hanya selama jam ujian; lewat batas ditolak total.
     */
    public function kumpulkan(Request $request, Ujian $ujian): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $this->pastikanPeserta($ujian, $mahasiswa);
        abort_unless($ujian->mode === Ujian::ONLINE_BERKAS, 404);

        $request->validate([
            'jawaban' => ['required', 'array', 'min:1', 'max:5'],
            'jawaban.*' => ['file', 'max:20480', ...AllowedUpload::rules()],
        ], [
            'jawaban.*.extensions' => AllowedUpload::message(),
            'jawaban.*.mimes' => AllowedUpload::messageIsi(),
            'jawaban.*.max' => 'Ukuran berkas jawaban maksimal 20 MB.',
            'jawaban.max' => 'Maksimal 5 berkas jawaban.',
        ], ['jawaban' => 'Berkas jawaban', 'jawaban.*' => 'Berkas jawaban']);

        // Waktu diperiksa sesudah unggahan diterima server, jadi berkas yang selesai terunggah lewat jam selesai ikut ditolak.
        if (! $ujian->sudahMulai()) {
            throw ValidationException::withMessages(['jawaban' => 'Ujian belum dimulai.']);
        }

        if ($ujian->sudahSelesai()) {
            throw ValidationException::withMessages(['jawaban' => 'Waktu ujian sudah habis. Jawaban tidak bisa dikumpulkan lagi.']);
        }

        if (! $ujian->bolehIkut($mahasiswa->id)) {
            throw ValidationException::withMessages(['jawaban' => $ujian->remidi() ? 'Anda bukan peserta remidi yang tagihannya lunas.' : 'Anda belum memenuhi syarat kehadiran untuk mengikuti ujian ini.']);
        }

        $berkas = collect($request->file('jawaban'))->map(fn ($f): string => $f->storeAs(
            'ujian/'.$ujian->id.'/jawaban',
            $mahasiswa->nim.'-'.Str::lower(Str::random(8)).'.'.strtolower($f->getClientOriginalExtension()),
            AllowedUpload::DISK,
        ))->all();

        DB::transaction(function () use ($ujian, $mahasiswa, $berkas): void {
            $lama = $ujian->jawabans()->where('mahasiswa_id', $mahasiswa->id)->lockForUpdate()->first();

            if ($lama !== null) {
                Storage::disk(AllowedUpload::DISK)->delete($lama->berkas ?? []);
            }

            UjianJawaban::updateOrCreate(
                ['ujian_id' => $ujian->id, 'mahasiswa_id' => $mahasiswa->id],
                ['berkas' => $berkas, 'dikumpulkan_at' => now()],
            );
            $ujian->catatHadir($mahasiswa->id);
        });

        return back()->with('success', 'Jawaban terkumpul pukul '.now()->format('H.i').'. Anda masih bisa menggantinya sampai waktu ujian habis.');
    }

    /**
     * Kartu ujian (PDF) untuk UTS atau UAS di tahun akademik yang dipilih.
     */
    public function kartu(Request $request): HttpResponse
    {
        $mahasiswa = $this->mahasiswa($request)->loadMissing('user:id,name', 'prodi:id,nama_prodi,jenjang');
        $jenis = in_array($request->query('jenis'), Ujian::SEMUA_JENIS, true) ? $request->query('jenis') : Pertemuan::UTS;
        $tahun = TahunAkademik::find($request->integer('tahun_akademik_id')) ?? TahunAkademik::where('status', true)->first();
        abort_if($tahun === null, 404);

        $ujians = $this->daftarUjian($mahasiswa, $tahun->id)->where('jenis', $jenis)->values();
        abort_if($ujians->isEmpty(), 404, 'Belum ada jadwal '.($jenis === Ujian::REMIDI ? 'remidi' : strtoupper($jenis)).' yang terbit.');
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.kartu-ujian', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'mahasiswa' => $mahasiswa,
            'tahun' => $tahun,
            'jenis' => $jenis,
            'ujians' => $ujians,
            'syaratAktif' => $jenis !== Ujian::REMIDI && PengaturanAkademik::current()->syarat_ujian_aktif,
        ])->download('kartu-'.$jenis.'-'.$mahasiswa->nim.'-'.Str::slug($tahun->tahun.'-'.$tahun->semester).'.pdf');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function daftarUjian(MahasiswaProfile $mahasiswa, ?int $tahunId): Collection
    {
        $kelas = KelasKuliah::query()
            ->where('tahun_akademik_id', $tahunId)
            ->whereHas('krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))
            ->with(['mataKuliah:id,kode_matkul,nama_matkul,sks', 'ujians' => fn ($q) => $q->terbit()->with('ruang:id,kode_ruang,nama_ruang')])
            ->get(['id', 'kode_kelas', 'matkul_id']);

        // Jadwal remidi hanya tampil bagi peserta remidi yang tagihannya lunas.
        $kelasRemidi = RemidiPeserta::query()->where('mahasiswa_id', $mahasiswa->id)->whereIn('kelas_id', $kelas->pluck('id'))->lunas()->pluck('kelas_id')->flip();
        $kelas->each(fn (KelasKuliah $k) => $k->setRelation('ujians', $k->ujians->reject(fn (Ujian $u): bool => $u->remidi() && ! $kelasRemidi->has($k->id))->values()));

        $pertemuan = Pertemuan::query()->whereIn('kelas_id', $kelas->pluck('id'))->get()->groupBy('kelas_id');
        $syarat = SyaratUjian::untukMahasiswa(
            $mahasiswa->id,
            $kelas->mapWithKeys(fn (KelasKuliah $k): array => [$k->id => $pertemuan->get($k->id, collect())]),
            PengaturanAkademik::current(),
        );

        return $kelas->flatMap(fn (KelasKuliah $k) => $k->ujians->map(fn (Ujian $u): array => [
            'id' => $u->id,
            'jenis' => $u->jenis,
            'label_jenis' => $u->labelJenis(),
            'mode' => $u->mode,
            'label_mode' => $u->labelMode(),
            'tanggal' => $u->tanggal->toDateString(),
            'jam_mulai' => $u->jam_mulai,
            'jam_akhir' => $u->jam_akhir,
            'ruang' => $u->ruang ? $u->ruang->kode_ruang.' — '.$u->ruang->nama_ruang : null,
            'pengawas' => $u->pengawas,
            'petunjuk' => $u->petunjuk,
            'kode_kelas' => $k->kode_kelas,
            'kode_matkul' => $k->mataKuliah?->kode_matkul,
            'nama_matkul' => $k->mataKuliah?->nama_matkul,
            'sks' => $k->mataKuliah?->sks,
            'syarat' => $syarat[$k->id]['peserta'][$mahasiswa->id][$u->jenis] ?? null,
        ]))->sortBy(fn (array $u): string => $u['tanggal'].$u['jam_mulai'])->values();
    }

    /**
     * Hanya peserta kelas yang bisa membuka ujian yang sudah terbit.
     */
    private function pastikanPeserta(Ujian $ujian, MahasiswaProfile $mahasiswa): void
    {
        abort_unless($ujian->status === Ujian::TERBIT && $ujian->kelasKuliah->krs()->where('mahasiswa_id', $mahasiswa->id)->exists(), 404);
        // Ujian remidi tidak terlihat sama sekali oleh yang bukan peserta lunas.
        abort_if($ujian->remidi() && ! $ujian->bolehIkut($mahasiswa->id), 404);
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }
}
