<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ruang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RuangController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $ruangs = Ruang::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('kode_ruang', 'like', "%{$search}%")->orWhere('nama_ruang', 'like', "%{$search}%")))
            ->orderBy('kode_ruang')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Ruang', ['ruangs' => $ruangs, 'search' => $search]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/RuangForm', ['ruang' => null]);
    }

    public function show(Ruang $ruang): Response
    {
        return Inertia::render('Admin/RuangShow', ['ruang' => $ruang]);
    }

    public function edit(Ruang $ruang): Response
    {
        return Inertia::render('Admin/RuangForm', ['ruang' => $ruang]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Ruang);

        return to_route('admin.ruang.index')->with('success', 'Ruang berhasil ditambahkan.');
    }

    public function update(Request $request, Ruang $ruang): RedirectResponse
    {
        $this->save($request, $ruang);

        return to_route('admin.ruang.index')->with('success', 'Ruang berhasil diperbarui.');
    }

    public function destroy(Ruang $ruang): RedirectResponse
    {
        if ($ruang->jadwals()->exists()) {
            return to_route('admin.ruang.index')->with('error', 'Ruang tidak dapat dihapus karena masih dipakai di jadwal kuliah.');
        }

        try {
            $ruang->delete();
        } catch (Throwable) {
            return to_route('admin.ruang.index')->with('error', 'Ruang gagal dihapus.');
        }

        return to_route('admin.ruang.index')->with('success', 'Ruang berhasil dihapus.');
    }

    private function save(Request $request, Ruang $model): void
    {
        $data = $request->validate([
            'kode_ruang' => ['required', 'string', 'max:50', Rule::unique('ruangs', 'kode_ruang')->ignore($model)],
            'nama_ruang' => ['required', 'string', 'max:255'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:1000'],
            'detail' => ['nullable', 'string'],
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
            'nullable' => ':attribute boleh kosong.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'kode_ruang' => 'Kode Ruang',
            'nama_ruang' => 'Nama Ruang',
            'kapasitas' => 'Kapasitas',
            'detail' => 'Detail',
        ];
    }
}
