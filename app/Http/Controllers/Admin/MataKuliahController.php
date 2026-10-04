<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        return Inertia::render('Admin/MataKuliahForm', ['mataKuliah' => null, 'programStudis' => $this->programStudis(), 'pilihanPrasyarat' => $this->pilihanPrasyarat(), 'jenisPenilaian' => MataKuliah::JENIS_PENILAIAN]);
    }

    public function show(MataKuliah $mataKuliah): Response
    {
        $mataKuliah->load([
            'prodi.fakultas',
            'prasyarat' => fn ($query) => $query->orderBy('semester')->orderBy('kode_matkul'),
            'menjadiPrasyarat' => fn ($query) => $query->orderBy('semester')->orderBy('kode_matkul'),
        ]);

        return Inertia::render('Admin/MataKuliahShow', ['mataKuliah' => $mataKuliah]);
    }

    public function edit(MataKuliah $mataKuliah): Response
    {
        return Inertia::render('Admin/MataKuliahForm', [
            'mataKuliah' => $mataKuliah->toArray() + ['prasyarat_ids' => $mataKuliah->prasyarat()->pluck('mata_kuliahs.id')->all()],
            'programStudis' => $this->programStudis(),
            'pilihanPrasyarat' => $this->pilihanPrasyarat(),
            'jenisPenilaian' => MataKuliah::JENIS_PENILAIAN,
        ]);
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

    /**
     * @return array<int, array{id: int, kode_matkul: string, nama_matkul: string, semester: int, prodi_id: int}>
     */
    private function pilihanPrasyarat(): array
    {
        return MataKuliah::query()->orderBy('semester')->orderBy('kode_matkul')->get(['id', 'kode_matkul', 'nama_matkul', 'semester', 'prodi_id'])->all();
    }

    private function save(Request $request, MataKuliah $model): void
    {
        $data = $request->validate([
            'kode_matkul' => ['required', 'string', Rule::unique('mata_kuliahs', 'kode_matkul')->ignore($model)],
            'nama_matkul' => ['required', 'string', 'max:255'],
            'sks' => ['required', 'integer', 'min:1', 'max:6'],
            'semester' => ['required', 'integer', 'min:1', 'max:14'],
            'jenis' => ['required', 'in:Wajib,Pilihan'],
            'jenis_penilaian' => ['required', Rule::in(array_keys(MataKuliah::JENIS_PENILAIAN))],
            'prodi_id' => ['required', 'exists:program_studis,id'],
            'prasyarat_ids' => ['array'],
            'prasyarat_ids.*' => ['integer', 'distinct', Rule::exists('mata_kuliahs', 'id')->where('prodi_id', $request->integer('prodi_id')), Rule::notIn(array_filter([$model->id]))],
        ], $this->messages(), $this->attributes());
        $prasyaratIds = $data['prasyarat_ids'] ?? [];
        unset($data['prasyarat_ids']);
        $this->pastikanUrutanPrasyarat($model, $data, $prasyaratIds);

        DB::transaction(function () use ($model, $data, $prasyaratIds): void {
            $model->fill($data)->save();
            $model->prasyarat()->sync($prasyaratIds);
        });
    }

    /**
     * Prasyarat harus dari semester lebih kecil, dan mata kuliah yang mensyaratkan mata kuliah ini harus
     * dari semester lebih besar dan prodi yang sama. Dengan begitu prasyarat tidak mungkin melingkar.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $prasyaratIds
     */
    private function pastikanUrutanPrasyarat(MataKuliah $model, array $data, array $prasyaratIds): void
    {
        $semester = (int) $data['semester'];
        $terlaluTinggi = MataKuliah::query()->whereKey($prasyaratIds)->where('semester', '>=', $semester)->orderBy('kode_matkul')->get();

        if ($terlaluTinggi->isNotEmpty()) {
            throw ValidationException::withMessages([
                'prasyarat_ids' => 'Prasyarat harus dari semester sebelum semester '.$semester.': '.$terlaluTinggi->map(fn (MataKuliah $mk): string => "{$mk->nama_matkul} (smt {$mk->semester})")->implode(', ').'.',
            ]);
        }

        if (! $model->exists) {
            return;
        }

        $pensyarat = $model->menjadiPrasyarat()->orderBy('kode_matkul')->get();
        $bentrokSemester = $pensyarat->filter(fn (MataKuliah $mk): bool => $mk->semester <= $semester);

        if ($bentrokSemester->isNotEmpty()) {
            throw ValidationException::withMessages([
                'semester' => 'Semester harus lebih kecil dari mata kuliah yang mensyaratkannya: '.$bentrokSemester->map(fn (MataKuliah $mk): string => "{$mk->nama_matkul} (smt {$mk->semester})")->implode(', ').'.',
            ]);
        }

        if ($pensyarat->contains(fn (MataKuliah $mk): bool => $mk->prodi_id !== (int) $data['prodi_id'])) {
            throw ValidationException::withMessages([
                'prodi_id' => 'Program studi tidak bisa diubah karena mata kuliah ini menjadi prasyarat mata kuliah lain.',
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'in' => ':attribute tidak valid.',
            'prasyarat_ids.*.exists' => 'Prasyarat harus mata kuliah dari program studi yang sama.',
            'prasyarat_ids.*.not_in' => 'Mata kuliah tidak bisa menjadi prasyarat dirinya sendiri.',
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
            'jenis_penilaian' => 'Jenis penilaian',
            'prodi_id' => 'Program Studi',
            'prasyarat_ids' => 'Prasyarat',
        ];
    }
}
