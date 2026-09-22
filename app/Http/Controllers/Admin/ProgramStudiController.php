<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\Fakultas;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProgramStudiController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $fakultasId = $request->integer('fakultas_id') ?: null;
        $programStudis = ProgramStudi::with(['fakultas', 'ketuaProgramStudi.user:id,name'])->when($fakultasId !== null, fn ($query) => $query->where('fakultas_id', $fakultasId))->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('kode_prodi', 'like', "%{$search}%")->orWhere('nama_prodi', 'like', "%{$search}%")))->orderBy('nama_prodi')->paginate(10)->withQueryString();

        return Inertia::render('Admin/ProgramStudi', ['programStudis' => $programStudis, 'search' => $search, 'fakultas' => Fakultas::orderBy('nama_fakultas')->get(['id', 'nama_fakultas']), 'fakultasId' => $fakultasId]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ProgramStudiForm', ['programStudi' => null, 'fakultas' => Fakultas::orderBy('nama_fakultas')->get(['id', 'nama_fakultas']), 'dosen' => $this->dosen()]);
    }

    public function show(ProgramStudi $programStudi): Response
    {
        $programStudi->load(['fakultas.dekan.user:id,name', 'ketuaProgramStudi.user:id,name']);

        return Inertia::render('Admin/ProgramStudiShow', ['programStudi' => $programStudi]);
    }

    public function edit(ProgramStudi $programStudi): Response
    {
        return Inertia::render('Admin/ProgramStudiForm', ['programStudi' => $programStudi, 'fakultas' => Fakultas::orderBy('nama_fakultas')->get(['id', 'nama_fakultas']), 'dosen' => $this->dosen()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new ProgramStudi);

        return to_route('admin.program-studi.index')->with('success', 'Program Studi berhasil ditambahkan.');
    }

    public function update(Request $request, ProgramStudi $programStudi): RedirectResponse
    {
        $this->save($request, $programStudi);

        return to_route('admin.program-studi.index')->with('success', 'Program Studi berhasil diperbarui.');
    }

    public function destroy(ProgramStudi $programStudi): RedirectResponse
    {
        if ($programStudi->mataKuliah()->exists()) {
            return to_route('admin.program-studi.index')->with('error', 'Program Studi tidak dapat dihapus karena masih memiliki mata kuliah.');
        }

        if (MahasiswaProfile::query()->where('prodi_id', $programStudi->id)->exists() || DosenProfile::query()->where('prodi_id', $programStudi->id)->exists()) {
            return to_route('admin.program-studi.index')->with('error', 'Program Studi tidak dapat dihapus karena masih memiliki mahasiswa atau dosen.');
        }

        try {
            $programStudi->delete();
        } catch (Throwable) {
            return to_route('admin.program-studi.index')->with('error', 'Program Studi gagal dihapus.');
        }

        return to_route('admin.program-studi.index')->with('success', 'Program Studi berhasil dihapus.');
    }

    private function dosen(): array
    {
        return DosenProfile::with('user:id,name')->get(['id', 'user_id'])->map(fn (DosenProfile $dosen): array => ['id' => $dosen->id, 'name' => $dosen->user->name])->all();
    }

    private function save(Request $request, ProgramStudi $model): void
    {
        if ($request->input('tanggal_akreditasi_mulai') === '') {
            $request->merge(['tanggal_akreditasi_mulai' => null]);
        }
        if ($request->input('tanggal_akreditasi_akhir') === '') {
            $request->merge(['tanggal_akreditasi_akhir' => null]);
        }

        $data = $request->validate(['fakultas_id' => ['required', 'exists:fakultas,id'], 'kode_prodi' => ['required', 'string', Rule::unique('program_studis')->ignore($model)], 'nama_prodi' => ['required', 'string'], 'jenjang' => ['required', 'string'], 'status_akreditasi' => ['required', 'string'], 'no_sk_akreditasi' => ['nullable', 'string'], 'tanggal_akreditasi_mulai' => ['nullable', 'date'], 'tanggal_akreditasi_akhir' => ['nullable', 'date'], 'kaprodi' => ['required', 'exists:dosen_profiles,id'], 'tahun_berdiri' => ['required', 'integer']], $this->messages(), $this->attributes());
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
            'date' => 'Format :attribute tidak valid.',
            'integer' => ':attribute harus berupa angka.',
            'exists' => ':attribute tidak ditemukan.',
            'max.string' => ':attribute maksimal :max karakter.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'fakultas_id' => 'Fakultas',
            'kode_prodi' => 'Kode Program Studi',
            'nama_prodi' => 'Nama Program Studi',
            'jenjang' => 'Jenjang',
            'status_akreditasi' => 'Status Akreditasi',
            'no_sk_akreditasi' => 'Nomor SK Akreditasi',
            'tanggal_akreditasi_mulai' => 'Tanggal Akreditasi Mulai',
            'tanggal_akreditasi_akhir' => 'Tanggal Akreditasi Akhir',
            'kaprodi' => 'Kaprodi',
            'tahun_berdiri' => 'Tahun Berdiri',
        ];
    }
}
