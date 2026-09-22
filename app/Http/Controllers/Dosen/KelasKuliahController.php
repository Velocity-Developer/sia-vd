<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MataKuliah;
use App\Models\SkalaNilai;
use App\Models\TahunAkademik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KelasKuliahController extends Controller
{
    public function index(Request $request): Response
    {
        $dosenProfileId = $request->user()?->dosenProfile?->id;

        abort_if($dosenProfileId === null, 403);

        $search = $request->string('search')->trim()->toString();
        $tahunAkademikId = $this->filterTahunAkademikId($request);
        $mataKuliahId = $request->integer('mata_kuliah_id') ?: null;

        $kelasKuliahs = KelasKuliah::with(['mataKuliah.prodi', 'tahunAkademik', 'jadwals.ruang'])
            ->where('dosen_id', $dosenProfileId)
            ->when($tahunAkademikId !== null, fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId))
            ->when($mataKuliahId !== null, fn ($query) => $query->where('matkul_id', $mataKuliahId))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('kode_kelas', 'like', "%{$search}%")->orWhereHas('tahunAkademik', fn ($q) => $q->where('tahun', 'like', "%{$search}%")->orWhere('semester', 'like', "%{$search}%"))->orWhereHas('mataKuliah', fn ($q) => $q->where('kode_matkul', 'like', "%{$search}%")->orWhere('nama_matkul', 'like', "%{$search}%"))))
            ->orderBy('kode_kelas')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Dosen/KelasKuliah', [
            'kelasKuliahs' => $kelasKuliahs,
            'search' => $search,
            'tahunAkademiks' => $this->tahunAkademiks(),
            'tahunAkademikId' => $tahunAkademikId,
            'mataKuliahId' => $mataKuliahId,
            'mataKuliahOptions' => $this->mataKuliahOptions($dosenProfileId, $tahunAkademikId),
        ]);
    }

    /**
     * Filter tahun akademik default ke tahun akademik yang sedang aktif.
     */
    private function filterTahunAkademikId(Request $request): ?int
    {
        if ($request->has('tahun_akademik_id')) {
            return $request->integer('tahun_akademik_id') ?: null;
        }

        return TahunAkademik::where('status', true)->value('id');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function tahunAkademiks(): array
    {
        return TahunAkademik::orderByDesc('tahun')->orderBy('semester')->get()
            ->map(fn (TahunAkademik $tahunAkademik): array => [
                'id' => $tahunAkademik->id,
                'name' => $tahunAkademik->tahun.' '.$tahunAkademik->semester,
            ])->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function mataKuliahOptions(int $dosenProfileId, ?int $tahunAkademikId): array
    {
        return MataKuliah::whereHas('kelasKuliah', fn ($query) => $query
            ->where('dosen_id', $dosenProfileId)
            ->when($tahunAkademikId !== null, fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId)))
            ->orderBy('kode_matkul')
            ->get(['id', 'kode_matkul', 'nama_matkul'])
            ->map(fn (MataKuliah $mataKuliah): array => [
                'id' => $mataKuliah->id,
                'name' => $mataKuliah->kode_matkul.' — '.$mataKuliah->nama_matkul,
            ])->all();
    }

    public function updateGrade(Request $request, KelasKuliah $kelasKuliah, Krs $krs): RedirectResponse
    {
        abort_if($kelasKuliah->dosen_id !== $request->user()?->dosenProfile?->id || $krs->kelas_id !== $kelasKuliah->id, 403);

        if ($this->nilaiTerkunci($kelasKuliah)) {
            return back()->with('error', 'Nilai terkunci karena tahun akademik kelas ini sudah tidak aktif. Hubungi admin untuk perubahan nilai.');
        }

        $krs->update($request->validate(['nilai' => ['nullable', Rule::in(SkalaNilai::huruf())]]));

        return back()->with('success', 'Nilai berhasil diperbarui.');
    }

    public function show(Request $request, KelasKuliah $kelasKuliah): Response
    {
        $dosenProfileId = $request->user()?->dosenProfile?->id;

        abort_if($dosenProfileId === null || $kelasKuliah->dosen_id !== $dosenProfileId, 403);

        $kelasKuliah->load([
            'mataKuliah.prodi.fakultas',
            'tahunAkademik',
            'jadwals.ruang',
            'materis.uploader:id,name',
            'tugas.uploader:id,name',
            'quizzes.uploader:id,name',
            'krs.mahasiswa:id,user_id,nim,prodi_id',
            'krs.mahasiswa.user:id,name',
            'krs.mahasiswa.prodi:id,nama_prodi',
        ]);

        return Inertia::render('Dosen/KelasKuliahShow', [
            'kelasKuliah' => $kelasKuliah,
            'otherClasses' => KelasKuliah::with('mataKuliah')->where('dosen_id', $dosenProfileId)->whereKeyNot($kelasKuliah->id)->orderBy('kode_kelas')->get(),
            'skalaNilai' => SkalaNilai::huruf(),
            'nilaiTerkunci' => $this->nilaiTerkunci($kelasKuliah),
        ]);
    }

    /**
     * Dosen hanya bisa mengubah nilai selama tahun akademik kelas masih aktif; setelah itu hanya admin.
     */
    private function nilaiTerkunci(KelasKuliah $kelasKuliah): bool
    {
        return $kelasKuliah->loadMissing('tahunAkademik')->tahunAkademik?->status !== true;
    }
}
