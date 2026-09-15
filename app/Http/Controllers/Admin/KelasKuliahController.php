<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MataKuliah;
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
        $kelasKuliahs = KelasKuliah::with(['tahunAkademik', 'dosen.user', 'mataKuliah.prodi', 'jadwals.ruang'])
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('kode_kelas', 'like', "%{$search}%")->orWhereHas('tahunAkademik', fn ($q) => $q->where('tahun', 'like', "%{$search}%")->orWhere('semester', 'like', "%{$search}%"))->orWhereHas('mataKuliah', fn ($q) => $q->where('kode_matkul', 'like', "%{$search}%")->orWhere('nama_matkul', 'like', "%{$search}%"))))
            ->orderBy('kode_kelas')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/KelasKuliah', ['kelasKuliahs' => $kelasKuliahs, 'search' => $search]);
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
        ]);
    }

    public function updateGrade(Request $request, KelasKuliah $kelasKuliah, Krs $krs): RedirectResponse
    {
        abort_if($krs->kelas_id !== $kelasKuliah->id, 404);
        $krs->update($request->validate(['nilai' => ['required', Rule::in(['A', 'B', 'C', 'D', 'E'])]]));

        return back()->with('success', 'Nilai berhasil diperbarui.');
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
        try {
            $kelasKuliah->delete();
        } catch (Throwable) {
            return to_route('admin.kelas-kuliah.index')->with('error', 'Kelas Kuliah gagal dihapus.');
        }

        return to_route('admin.kelas-kuliah.index')->with('success', 'Kelas Kuliah berhasil dihapus.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
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
