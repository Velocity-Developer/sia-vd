<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengajuanAkademik;
use App\Models\PengaturanInstitusi;
use App\Models\PeriodeWisuda;
use App\Models\Wisuda;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Periode wisuda, daftar mahasiswa wisuda per periode, dan penerbitan SKL.
 */
class PeriodeWisudaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/PeriodeWisuda', [
            'periode' => PeriodeWisuda::query()->withCount(['wisuda', 'wisuda as skl_terbit_count' => fn ($q) => $q->whereNotNull('nomor_skl')])
                ->orderByDesc('tanggal_acara')->get()
                ->map(fn (PeriodeWisuda $p): array => [...$p->ringkas(), 'dibuka' => $p->dibuka(), 'jumlah_peserta' => $p->wisuda_count, 'jumlah_skl' => $p->skl_terbit_count]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        PeriodeWisuda::query()->create($this->validasi($request));

        return back()->with('success', 'Periode wisuda dibuat.');
    }

    public function update(Request $request, PeriodeWisuda $periodeWisuda): RedirectResponse
    {
        $data = $this->validasi($request);
        if ($data['kuota'] !== null && $data['kuota'] < $periodeWisuda->wisuda()->count()) {
            return back()->withErrors(['kuota' => 'Kuota tidak boleh lebih kecil dari jumlah peserta yang sudah disetujui.']);
        }
        $periodeWisuda->update($data);

        return back()->with('success', 'Periode wisuda diperbarui.');
    }

    public function destroy(PeriodeWisuda $periodeWisuda): RedirectResponse
    {
        $dipilih = PengajuanAkademik::query()->where('jenis', PengajuanAkademik::WISUDA)->whereIn('status', PengajuanAkademik::SEDANG_DIPROSES)
            ->get(['isian'])->contains(fn (PengajuanAkademik $p): bool => (int) ($p->isian['periode_wisuda_id'] ?? 0) === $periodeWisuda->id);
        if ($periodeWisuda->wisuda()->exists() || $dipilih) {
            return back()->with('error', 'Periode ini sudah punya pendaftar sehingga tidak bisa dihapus.');
        }
        $periodeWisuda->delete();

        return back()->with('success', 'Periode wisuda dihapus.');
    }

    /**
     * Daftar mahasiswa wisuda satu periode.
     */
    public function show(PeriodeWisuda $periodeWisuda): Response
    {
        return Inertia::render('Admin/PeriodeWisudaShow', [
            'periode' => [...$periodeWisuda->ringkas(), 'dibuka' => $periodeWisuda->dibuka()],
            'peserta' => $this->peserta($periodeWisuda)->map(fn (Wisuda $w): array => [
                'id' => $w->id,
                'nama' => $w->pengajuan?->isian['nama_ijazah'] ?? $w->mahasiswa?->user?->name,
                'nim' => $w->mahasiswa?->nim,
                'prodi' => $w->mahasiswa?->prodi ? $w->mahasiswa->prodi->jenjang.' '.$w->mahasiswa->prodi->nama_prodi : null,
                'judul' => $w->tugasAkhir?->judul,
                'ukuran_toga' => $w->pengajuan?->isian['ukuran_toga'] ?? null,
                'pengajuan_id' => $w->pengajuan_id,
                'status_mahasiswa' => $w->mahasiswa?->status,
                'nomor_skl' => $w->nomor_skl,
                'skl_terbit_at' => $w->skl_terbit_at?->toIso8601String(),
                'ipk' => $w->ipk,
                'predikat' => $w->predikat,
            ]),
        ]);
    }

    /**
     * Terbitkan SKL satu peserta; status mahasiswa menjadi Lulus.
     */
    public function skl(Request $request, Wisuda $wisuda): RedirectResponse
    {
        if ($wisuda->nomor_skl !== null) {
            return back()->with('error', 'SKL mahasiswa ini sudah terbit.');
        }
        $wisuda->terbitkanSkl($request->user()->id);

        return back()->with('success', 'SKL terbit dengan nomor '.$wisuda->nomor_skl.'; status mahasiswa menjadi Lulus.');
    }

    /**
     * Terbitkan SKL seluruh peserta periode yang belum punya SKL.
     */
    public function sklMassal(Request $request, PeriodeWisuda $periodeWisuda): RedirectResponse
    {
        $belum = $periodeWisuda->wisuda()->whereNull('nomor_skl')->orderBy('id')->get();
        $belum->each(fn (Wisuda $w) => $w->terbitkanSkl($request->user()->id));

        return back()->with('success', $belum->isEmpty() ? 'Semua peserta sudah punya SKL.' : $belum->count().' SKL terbit; status mahasiswanya menjadi Lulus.');
    }

    /**
     * Daftar mahasiswa wisuda (PDF) untuk dicetak.
     */
    public function cetak(PeriodeWisuda $periodeWisuda): HttpResponse
    {
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.peserta-wisuda', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'periode' => $periodeWisuda,
            'peserta' => $this->peserta($periodeWisuda),
        ])->download('peserta-wisuda-'.Str::slug($periodeWisuda->nama).'.pdf');
    }

    /**
     * @return Collection<int, Wisuda>
     */
    private function peserta(PeriodeWisuda $periode): Collection
    {
        return $periode->wisuda()
            ->with(['mahasiswa:id,user_id,nim,prodi_id,status', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'tugasAkhir:id,judul', 'pengajuan:id,isian'])
            ->get()
            ->sortBy(fn (Wisuda $w): string => $w->mahasiswa?->nim ?? '')
            ->values();
    }

    /**
     * @return array{nama: string, tanggal_acara: string, tempat: ?string, batas_daftar: string, kuota: ?int}
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'tanggal_acara' => ['required', 'date_format:Y-m-d'],
            'tempat' => ['nullable', 'string', 'max:200'],
            'batas_daftar' => ['required', 'date_format:Y-m-d', 'before_or_equal:tanggal_acara'],
            'kuota' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ], ['batas_daftar.before_or_equal' => 'Batas daftar tidak boleh sesudah tanggal acara.'], [
            'nama' => 'Nama periode',
            'tanggal_acara' => 'Tanggal acara',
            'tempat' => 'Tempat',
            'batas_daftar' => 'Batas daftar',
            'kuota' => 'Kuota',
        ]) + ['tempat' => null, 'kuota' => null];
    }
}
