<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
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
use Throwable;

class KelasKuliahController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $tahunAkademikId = $this->filterTahunAkademikId($request);
        $mataKuliahId = $request->integer('mata_kuliah_id') ?: null;
        $dosenId = $request->integer('dosen_id') ?: null;

        $kelasKuliahs = KelasKuliah::with(['tahunAkademik', 'dosen.user', 'mataKuliah.prodi', 'jadwals.ruang'])
            ->when($tahunAkademikId !== null, fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId))
            ->when($mataKuliahId !== null, fn ($query) => $query->where('matkul_id', $mataKuliahId))
            ->when($dosenId !== null, fn ($query) => $query->where('dosen_id', $dosenId))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('kode_kelas', 'like', "%{$search}%")->orWhereHas('tahunAkademik', fn ($q) => $q->where('tahun', 'like', "%{$search}%")->orWhere('semester', 'like', "%{$search}%"))->orWhereHas('mataKuliah', fn ($q) => $q->where('kode_matkul', 'like', "%{$search}%")->orWhere('nama_matkul', 'like', "%{$search}%"))))
            ->orderBy('kode_kelas')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/KelasKuliah', [
            'kelasKuliahs' => $kelasKuliahs,
            'search' => $search,
            'tahunAkademiks' => $this->tahunAkademiks(),
            'tahunAkademikId' => $tahunAkademikId,
            'mataKuliahId' => $mataKuliahId,
            'mataKuliahOptions' => $this->mataKuliahOptions($tahunAkademikId),
            'dosenId' => $dosenId,
            'dosenOptions' => $this->dosenOptions($tahunAkademikId),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/KelasKuliahForm', [
            'kelasKuliah' => null,
            'dosens' => $this->dosens(),
            'matkulGroups' => $this->matkulGroups(),
            'tahunAkademiks' => TahunAkademik::orderByDesc('tahun')->orderBy('semester')->get(),
        ]);
    }

    public function show(KelasKuliah $kelasKuliah): Response
    {
        $kelasKuliah->load(['tahunAkademik', 'dosen.user', 'mataKuliah.prodi.fakultas', 'jadwals.ruang', 'materis.uploader:id,name', 'tugas.uploader:id,name', 'quizzes.uploader:id,name', 'krs.mahasiswa.user', 'krs.mahasiswa.prodi']);

        return Inertia::render('Admin/KelasKuliahShow', [
            'kelasKuliah' => $kelasKuliah,
            'otherClasses' => KelasKuliah::with('mataKuliah')->whereKeyNot($kelasKuliah->id)->orderBy('kode_kelas')->get(),
            'skalaNilai' => SkalaNilai::huruf(),
            'nilaiTerkunci' => false,
        ]);
    }

    public function updateGrade(Request $request, KelasKuliah $kelasKuliah, Krs $krs): RedirectResponse
    {
        abort_if($krs->kelas_id !== $kelasKuliah->id, 404);
        $krs->update($request->validate(['nilai' => ['nullable', Rule::in(SkalaNilai::huruf())]]));

        return back()->with('success', 'Nilai berhasil diperbarui.');
    }

    /**
     * Batalkan KRS yang salah input. KRS yang sudah bernilai tidak bisa dibatalkan agar riwayat nilai tetap utuh.
     */
    public function destroyKrs(KelasKuliah $kelasKuliah, Krs $krs): RedirectResponse
    {
        abort_if($krs->kelas_id !== $kelasKuliah->id, 404);

        if (filled($krs->nilai)) {
            return back()->with('error', 'KRS yang sudah memiliki nilai tidak dapat dibatalkan. Kosongkan nilainya terlebih dahulu.');
        }

        $krs->cancel();

        return back()->with('success', 'KRS mahasiswa berhasil dibatalkan.');
    }

    public function edit(KelasKuliah $kelasKuliah): Response
    {
        return Inertia::render('Admin/KelasKuliahForm', [
            'kelasKuliah' => $kelasKuliah,
            'dosens' => $this->dosens(),
            'matkulGroups' => $this->matkulGroups(),
            'tahunAkademiks' => TahunAkademik::orderByDesc('tahun')->orderBy('semester')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new KelasKuliah);

        return to_route('admin.kelas-kuliah.index')->with('success', 'Kelas Kuliah berhasil ditambahkan.');
    }

    public function update(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $this->save($request, $kelasKuliah);

        return to_route('admin.kelas-kuliah.index')->with('success', 'Kelas Kuliah berhasil diperbarui.');
    }

    public function destroy(KelasKuliah $kelasKuliah): RedirectResponse
    {
        if ($kelasKuliah->krs()->exists()) {
            return to_route('admin.kelas-kuliah.index')->with('error', 'Kelas Kuliah tidak dapat dihapus karena sudah memiliki KRS mahasiswa.');
        }

        try {
            $kelasKuliah->delete();
        } catch (Throwable) {
            return to_route('admin.kelas-kuliah.index')->with('error', 'Kelas Kuliah gagal dihapus.');
        }

        return to_route('admin.kelas-kuliah.index')->with('success', 'Kelas Kuliah berhasil dihapus.');
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
    private function mataKuliahOptions(?int $tahunAkademikId): array
    {
        return MataKuliah::whereHas('kelasKuliah', fn ($query) => $query->when($tahunAkademikId !== null, fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId)))
            ->orderBy('kode_matkul')
            ->get(['id', 'kode_matkul', 'nama_matkul'])
            ->map(fn (MataKuliah $mataKuliah): array => [
                'id' => $mataKuliah->id,
                'name' => $mataKuliah->kode_matkul.' — '.$mataKuliah->nama_matkul,
            ])->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function dosenOptions(?int $tahunAkademikId): array
    {
        return DosenProfile::with('user:id,name')
            ->whereHas('kelasKuliah', fn ($query) => $query->when($tahunAkademikId !== null, fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId)))
            ->orderBy('nidn')
            ->get()
            ->map(fn (DosenProfile $dosen): array => [
                'id' => $dosen->id,
                'name' => ($dosen->user?->name ?? 'Tanpa nama').' — '.$dosen->nidn,
            ])->all();
    }

    private function dosens(): array
    {
        return DosenProfile::with('user:id,name')->orderBy('nidn')->get()->map(fn (DosenProfile $d): array => [
            'id' => $d->id,
            'name' => ($d->user?->name ?? '-').' — '.$d->nidn,
        ])->all();
    }

    /**
     * @return array<int, array{label: string, options: array<int, array{id: int, name: string}>}>
     */
    private function matkulGroups(): array
    {
        $mataKuliahs = MataKuliah::with('prodi:id,nama_prodi')->orderBy('kode_matkul')->get(['id', 'kode_matkul', 'nama_matkul', 'prodi_id']);
        $groups = [];
        foreach ($mataKuliahs as $mk) {
            $label = $mk->prodi?->nama_prodi ?? 'Program Studi Lainnya';
            $groups[$label][] = ['id' => $mk->id, 'name' => $mk->kode_matkul.' — '.$mk->nama_matkul];
        }

        $result = [];
        foreach ($groups as $label => $options) {
            $result[] = ['label' => $label, 'options' => $options];
        }

        return $result;
    }

    private function save(Request $request, KelasKuliah $model): void
    {
        $data = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:50', Rule::unique('kelas_kuliah', 'kode_kelas')->ignore($model)],
            'tahun_akademik_id' => ['required', 'exists:tahun_akademik,id'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:500'],
            'dosen_id' => ['required', 'exists:dosen_profiles,id'],
            'matkul_id' => ['required', 'exists:mata_kuliahs,id'],
        ], $this->messages(), $this->attributes());
        $model->fill($data)->save();
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'unique' => ':attribute sudah digunakan.',
            'integer' => ':attribute harus berupa angka.',
            'min' => ':attribute minimal :min.',
            'max.string' => ':attribute maksimal :max karakter.',
            'max.integer' => ':attribute maksimal :max.',
            'exists' => ':attribute tidak ditemukan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'kode_kelas' => 'Kode Kelas',
            'tahun_akademik_id' => 'Tahun Akademik',
            'kapasitas' => 'Kapasitas',
            'dosen_id' => 'Dosen',
            'matkul_id' => 'Mata Kuliah',
        ];
    }
}
