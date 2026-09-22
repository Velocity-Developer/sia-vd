<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanInstitusi;
use App\Models\TahunAkademik;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class HasilStudiController extends Controller
{
    public function transkrip(Request $request): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        $krs = Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereNotNull('nilai')
            ->with('kelasKuliah.mataKuliah', 'kelasKuliah.tahunAkademik')
            ->get();
        $bobotNilai = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0];
        $transkrip = $krs->filter(fn (Krs $item): bool => isset($bobotNilai[strtoupper((string) $item->nilai)]) && ($item->kelasKuliah?->mataKuliah?->sks ?? 0) > 0)
            ->map(fn (Krs $item): array => [
                'id' => $item->id,
                'kode' => $item->kelasKuliah->mataKuliah->kode_matkul,
                'nama' => $item->kelasKuliah->mataKuliah->nama_matkul,
                'jenis' => $item->kelasKuliah->mataKuliah->jenis,
                'sks' => $item->kelasKuliah->mataKuliah->sks,
                'nilai' => strtoupper($item->nilai),
                'bobot' => $bobotNilai[strtoupper($item->nilai)],
                'mutu' => $item->kelasKuliah->mataKuliah->sks * $bobotNilai[strtoupper($item->nilai)],
            ])->values();
        $totalSks = $transkrip->sum('sks');
        $totalMutu = $transkrip->sum('mutu');

        return Inertia::render('Mahasiswa/TranskripNilai', [
            'transkrip' => $transkrip,
            'ringkasan' => ['totalMatkul' => $transkrip->count(), 'totalSks' => $totalSks, 'totalMutu' => $totalMutu, 'ipk' => $totalSks > 0 ? round($totalMutu / $totalSks, 2) : null],
        ]);
    }

    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $data = $this->dataKhs($mahasiswa, $request->integer('tahun_akademik_id'));

        return Inertia::render('Mahasiswa/HasilStudi', [
            'krs' => $data['krs'],
            'tahunAkademiks' => $data['tahunAkademiks'],
            'tahunAkademikTerpilih' => $data['tahunAkademik']?->id,
            'ringkasan' => $data['ringkasan'],
        ]);
    }

    public function downloadKhs(Request $request): HttpResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $data = $this->dataKhs($mahasiswa, $request->integer('tahun_akademik_id'));
        $institusi = PengaturanInstitusi::current();

        $pdf = Pdf::loadView('pdf.khs', [
            'institusi' => $institusi,
            'logoSrc' => $this->logoSrc($institusi),
            'kontak' => array_values(array_filter([
                $institusi->alamat,
                $institusi->telepon ? "Telp. {$institusi->telepon}" : null,
                $institusi->email,
                $institusi->website,
            ])),
            'mahasiswa' => $mahasiswa->loadMissing('user', 'prodi', 'dosenWali.user'),
            'tahunAkademik' => $data['tahunAkademik'],
            'krs' => $data['krs'],
            'ringkasan' => $data['ringkasan'],
        ]);

        $tahun = str_replace('/', '-', (string) $data['tahunAkademik']?->tahun);

        return $pdf->download("khs-{$mahasiswa->nim}-{$tahun}-{$data['tahunAkademik']?->semester}.pdf");
    }

    /**
     * Ubah logo institusi menjadi data URI agar bisa dirender DomPDF tanpa akses remote.
     */
    private function logoSrc(PengaturanInstitusi $institusi): ?string
    {
        if ($institusi->logo === null) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($institusi->logo)) {
            return null;
        }

        $mime = $disk->mimeType($institusi->logo) ?: 'image/png';

        return "data:{$mime};base64,".base64_encode($disk->get($institusi->logo));
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }

    /**
     * @return array{krs: Collection<int, Krs>, tahunAkademiks: Collection<int, TahunAkademik>, tahunAkademik: TahunAkademik|null, ringkasan: array{totalSks: int, totalSksDinilai: int, totalMutu: int, ip: float|null}}
     */
    private function dataKhs(MahasiswaProfile $mahasiswa, ?int $tahunAkademikId): array
    {
        $tahunAkademik = TahunAkademik::query()
            ->orderByDesc('tahun')
            ->orderByDesc('semester')
            ->get(['id', 'tahun', 'semester', 'status']);
        $tahunAkademikAktif = $tahunAkademik->firstWhere('status', true) ?? $tahunAkademik->first();
        $tahunAkademikTerpilih = $tahunAkademikId ? $tahunAkademik->firstWhere('id', $tahunAkademikId) : $tahunAkademikAktif;
        $tahunAkademikTerpilih ??= $tahunAkademikAktif;

        $krs = Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->when($tahunAkademikTerpilih, fn ($query) => $query->whereHas('kelasKuliah', fn ($kelas) => $kelas->where('tahun_akademik_id', $tahunAkademikTerpilih->id)))
            ->with(['kelasKuliah.mataKuliah', 'kelasKuliah.tahunAkademik'])
            ->get();
        $bobotNilai = ['A' => 4, 'B' => 3, 'C' => 2, 'D' => 1, 'E' => 0];
        $krsDinilai = $krs->filter(fn (Krs $item): bool => isset($bobotNilai[strtoupper((string) $item->nilai)]) && ($item->kelasKuliah?->mataKuliah?->sks ?? 0) > 0);
        $totalSksDinilai = $krsDinilai->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks);
        $totalMutu = $krsDinilai->sum(fn (Krs $item): int => $item->kelasKuliah->mataKuliah->sks * $bobotNilai[strtoupper($item->nilai)]);

        return [
            'krs' => $krs,
            'tahunAkademiks' => $tahunAkademik,
            'tahunAkademik' => $tahunAkademikTerpilih,
            'ringkasan' => [
                'totalSks' => $krs->sum(fn (Krs $item): int => $item->kelasKuliah?->mataKuliah?->sks ?? 0),
                'totalSksDinilai' => $totalSksDinilai,
                'totalMutu' => $totalMutu,
                'ip' => $totalSksDinilai > 0 ? round($totalMutu / $totalSksDinilai, 2) : null,
            ],
        ];
    }
}
