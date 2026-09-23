<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class MataKuliahController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $programStudiId = $request->integer('program_studi_id') ?: null;
        $mataKuliahs = MataKuliah::with(['prodi.fakultas'])
            ->when($programStudiId !== null, fn ($query) => $query->where('prodi_id', $programStudiId))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('kode_matkul', 'like', "%{$search}%")->orWhere('nama_matkul', 'like', "%{$search}%")))
            ->orderBy('kode_matkul')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/MataKuliah', ['mataKuliahs' => $mataKuliahs, 'search' => $search, 'programStudis' => $this->programStudis(), 'programStudiId' => $programStudiId]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/MataKuliahForm', ['mataKuliah' => null, 'programStudis' => $this->programStudis()]);
    }

    public function show(MataKuliah $mataKuliah): Response
    {
        $mataKuliah->load(['prodi.fakultas']);

        return Inertia::render('Admin/MataKuliahShow', ['mataKuliah' => $mataKuliah]);
    }

    public function edit(MataKuliah $mataKuliah): Response
    {
        return Inertia::render('Admin/MataKuliahForm', ['mataKuliah' => $mataKuliah, 'programStudis' => $this->programStudis()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new MataKuliah);

        return to_route('admin.mata-kuliah.index')->with('success', 'Mata Kuliah berhasil ditambahkan.');
    }

    public function update(Request $request, MataKuliah $mataKuliah): RedirectResponse
    {
        $this->save($request, $mataKuliah);

        return to_route('admin.mata-kuliah.index')->with('success', 'Mata Kuliah berhasil diperbarui.');
    }

    public function destroy(MataKuliah $mataKuliah): RedirectResponse
    {
        if ($mataKuliah->kelasKuliah()->exists()) {
            return to_route('admin.mata-kuliah.index')->with('error', 'Mata Kuliah tidak dapat dihapus karena masih dipakai oleh kelas kuliah.');
        }

        try {
            $mataKuliah->delete();
        } catch (Throwable) {
            return to_route('admin.mata-kuliah.index')->with('error', 'Mata Kuliah gagal dihapus.');
        }

        return to_route('admin.mata-kuliah.index')->with('success', 'Mata Kuliah berhasil dihapus.');
    }

    /**
     * @return array<int, array{id: int, nama_prodi: string, jenjang: string, nama_fakultas: string}>
     */
    private function programStudis(): array
    {
        return ProgramStudi::with('fakultas:id,nama_fakultas')->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang', 'fakultas_id'])->map(fn (ProgramStudi $prodi): array => [
            'id' => $prodi->id,
            'nama_prodi' => $prodi->nama_prodi,
            'jenjang' => $prodi->jenjang,
            'nama_fakultas' => $prodi->fakultas?->nama_fakultas ?? 'Fakultas Lainnya',
        ])->all();
    }

    private function save(Request $request, MataKuliah $model): void
    {
        $data = $request->validate([
            'kode_matkul' => ['required', 'string', Rule::unique('mata_kuliahs', 'kode_matkul')->ignore($model)],
            'nama_matkul' => ['required', 'string', 'max:255'],
            'sks' => ['required', 'integer', 'min:1', 'max:6'],
            'semester' => ['required', 'integer', 'min:1', 'max:14'],
            'jenis' => ['required', 'in:Wajib,Pilihan'],
            'prodi_id' => ['required', 'exists:program_studis,id'],
        ], $this->messages(), $this->attributes());
        $model->fill($data)->save();
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'in' => ':attribute tidak valid.',
            'exists' => ':attribute tidak ditemukan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'kode_matkul' => 'Kode Mata Kuliah',
            'nama_matkul' => 'Nama Mata Kuliah',
            'sks' => 'SKS',
            'semester' => 'Semester',
            'jenis' => 'Jenis',
            'prodi_id' => 'Program Studi',
        ];
    }
}
