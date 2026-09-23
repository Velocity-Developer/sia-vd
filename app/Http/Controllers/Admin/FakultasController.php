<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\Fakultas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FakultasController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $fakultas = Fakultas::with('dekan.user:id,name')->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('kode_fakultas', 'like', "%{$search}%")->orWhere('nama_fakultas', 'like', "%{$search}%")))->orderBy('nama_fakultas')->paginate(10)->withQueryString();

        return Inertia::render('Admin/Fakultas', ['fakultas' => $fakultas, 'search' => $search]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/FakultasForm', ['fakultas' => null, 'dosen' => $this->dosen()]);
    }

    public function show(Fakultas $fakulta): Response
    {
        $fakulta->load(['dekan.user:id,name', 'programStudis.ketuaProgramStudi.user:id,name']);

        return Inertia::render('Admin/FakultasShow', ['fakultas' => $fakulta]);
    }

    public function edit(Fakultas $fakulta): Response
    {
        return Inertia::render('Admin/FakultasForm', ['fakultas' => $fakulta, 'dosen' => $this->dosen()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Fakultas);

        return to_route('admin.fakultas.index')->with('success', 'Fakultas berhasil ditambahkan.');
    }

    public function update(Request $request, Fakultas $fakulta): RedirectResponse
    {
        $this->save($request, $fakulta);

        return to_route('admin.fakultas.index')->with('success', 'Fakultas berhasil diperbarui.');
    }

    public function destroy(Fakultas $fakulta): RedirectResponse
    {
        if ($fakulta->programStudis()->exists()) {
            return to_route('admin.fakultas.index')->with('error', 'Fakultas tidak dapat dihapus karena masih memiliki program studi.');
        }

        try {
            $fakulta->delete();
        } catch (Throwable) {
            return to_route('admin.fakultas.index')->with('error', 'Fakultas gagal dihapus.');
        }

        return to_route('admin.fakultas.index')->with('success', 'Fakultas berhasil dihapus.');
    }

    private function dosen(): array
    {
        return DosenProfile::with('user:id,name')->get(['id', 'user_id'])->map(fn (DosenProfile $dosen): array => ['id' => $dosen->id, 'name' => $dosen->user->name])->all();
    }

    private function save(Request $request, Fakultas $fakulta): void
    {
        $data = $request->validate([
            'kode_fakultas' => ['required', 'string', Rule::unique('fakultas')->ignore($fakulta)],
            'nama_fakultas' => ['required', 'string', 'max:255', Rule::unique('fakultas', 'nama_fakultas')->ignore($fakulta->id)],
            'dekan_id' => ['required', 'exists:dosen_profiles,id'],
            'tanggal_berdiri' => ['required', 'date'],
            'no_telp' => ['required', 'string'],
            'email' => ['required', 'email'],
        ], $this->messages(), $this->attributes());
        $fakulta->fill($data)->save();
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'email' => 'Format :attribute tidak valid.',
            'date' => 'Format :attribute tidak valid.',
            'exists' => ':attribute tidak ditemukan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'kode_fakultas' => 'Kode Fakultas',
            'nama_fakultas' => 'Nama Fakultas',
            'dekan_id' => 'Dekan',
            'tanggal_berdiri' => 'Tanggal Berdiri',
            'no_telp' => 'Nomor Telepon',
            'email' => 'Email',
        ];
    }
}
