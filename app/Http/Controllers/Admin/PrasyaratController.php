<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataKuliah;
use App\Models\MataKuliahPrasyarat;
use App\Models\ProgramStudi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pasangan mata kuliah dan prasyaratnya. Aturannya sama dengan isian Prasyarat di form Mata Kuliah: prasyarat harus
 * dari prodi yang sama dan semester sebelumnya, sehingga prasyarat tidak mungkin melingkar.
 */
class PrasyaratController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $programStudiId = $request->integer('program_studi_id') ?: null;
        $prasyarat = MataKuliahPrasyarat::query()
            ->select('mata_kuliah_prasyarat.*')
            ->join('mata_kuliahs as mk', 'mk.id', '=', 'mata_kuliah_prasyarat.mata_kuliah_id')
            ->join('mata_kuliahs as ps', 'ps.id', '=', 'mata_kuliah_prasyarat.prasyarat_id')
            ->with(['mataKuliah:id,kode_matkul,nama_matkul,semester,prodi_id', 'mataKuliah.prodi:id,nama_prodi,jenjang', 'prasyarat:id,kode_matkul,nama_matkul,semester'])
            ->when($programStudiId !== null, fn ($query) => $query->where('mk.prodi_id', $programStudiId))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('mk.kode_matkul', 'like', "%{$search}%")->orWhere('mk.nama_matkul', 'like', "%{$search}%")
                ->orWhere('ps.kode_matkul', 'like', "%{$search}%")->orWhere('ps.nama_matkul', 'like', "%{$search}%")))
            ->orderBy('mk.semester')
            ->orderBy('mk.kode_matkul')
            ->orderBy('ps.kode_matkul')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Prasyarat', [
            'prasyarat' => $prasyarat,
            'search' => $search,
            'programStudis' => ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang']),
            'programStudiId' => $programStudiId,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PrasyaratForm', ['prasyarat' => null, ...$this->pilihan()]);
    }

    public function edit(MataKuliahPrasyarat $prasyarat): Response
    {
        return Inertia::render('Admin/PrasyaratForm', ['prasyarat' => $prasyarat->only(['id', 'mata_kuliah_id', 'prasyarat_id']), ...$this->pilihan()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new MataKuliahPrasyarat);

        return to_route('admin.prasyarat.index')->with('success', 'Prasyarat berhasil ditambahkan.');
    }

    public function update(Request $request, MataKuliahPrasyarat $prasyarat): RedirectResponse
    {
        $this->save($request, $prasyarat);

        return to_route('admin.prasyarat.index')->with('success', 'Prasyarat berhasil diperbarui.');
    }

    public function destroy(MataKuliahPrasyarat $prasyarat): RedirectResponse
    {
        $prasyarat->delete();

        return to_route('admin.prasyarat.index')->with('success', 'Prasyarat berhasil dihapus.');
    }

    private function save(Request $request, MataKuliahPrasyarat $model): void
    {
        $data = $request->validate([
            'mata_kuliah_id' => ['required', 'integer', Rule::exists('mata_kuliahs', 'id')],
            'prasyarat_id' => [
                'required', 'integer', 'different:mata_kuliah_id', Rule::exists('mata_kuliahs', 'id'),
                Rule::unique('mata_kuliah_prasyarat', 'prasyarat_id')->where('mata_kuliah_id', $request->integer('mata_kuliah_id'))->ignore($model),
            ],
        ], [
            'exists' => ':attribute tidak ditemukan.',
            'different' => 'Mata kuliah tidak bisa menjadi prasyarat dirinya sendiri.',
            'prasyarat_id.unique' => 'Prasyarat ini sudah terdaftar untuk mata kuliah tersebut.',
        ], [
            'mata_kuliah_id' => 'Mata Kuliah',
            'prasyarat_id' => 'Mata Kuliah Prasyarat',
        ]);

        $mataKuliah = MataKuliah::query()->findOrFail($data['mata_kuliah_id']);
        $prasyarat = MataKuliah::query()->findOrFail($data['prasyarat_id']);

        if ($prasyarat->prodi_id !== $mataKuliah->prodi_id) {
            throw ValidationException::withMessages(['prasyarat_id' => 'Prasyarat harus mata kuliah dari program studi yang sama.']);
        }
        if ($prasyarat->semester >= $mataKuliah->semester) {
            throw ValidationException::withMessages([
                'prasyarat_id' => "Prasyarat harus dari semester sebelum semester {$mataKuliah->semester}: {$prasyarat->nama_matkul} (smt {$prasyarat->semester}).",
            ]);
        }

        $model->fill($data)->save();
    }

    /**
     * Pilihan mata kuliah beserta nama prodinya; daftar prasyarat disaring di halaman (prodi sama, semester lebih kecil).
     *
     * @return array{mataKuliahs: list<array{id: int, kode_matkul: string, nama_matkul: string, semester: int, prodi_id: int, prodi: string}>}
     */
    private function pilihan(): array
    {
        return [
            'mataKuliahs' => MataKuliah::query()
                ->with('prodi:id,nama_prodi,jenjang')
                ->orderBy('semester')
                ->orderBy('kode_matkul')
                ->get(['id', 'kode_matkul', 'nama_matkul', 'semester', 'prodi_id'])
                ->map(fn (MataKuliah $mk): array => [
                    'id' => $mk->id,
                    'kode_matkul' => $mk->kode_matkul,
                    'nama_matkul' => $mk->nama_matkul,
                    'semester' => $mk->semester,
                    'prodi_id' => $mk->prodi_id,
                    'prodi' => trim(($mk->prodi?->jenjang ? $mk->prodi->jenjang.' ' : '').($mk->prodi?->nama_prodi ?? '')),
                ])
                ->all(),
        ];
    }
}
