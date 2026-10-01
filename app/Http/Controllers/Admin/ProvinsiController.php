<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BadanHukum;
use App\Models\PengaturanInstitusi;
use App\Models\ProgramStudi;
use App\Models\Provinsi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProvinsiController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $provinsis = Provinsi::query()
            ->withCount('kotas')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('kode', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%")))
            ->orderBy('kode')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Provinsi', ['provinsis' => $provinsis, 'search' => $search]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ProvinsiForm', ['provinsi' => null]);
    }

    public function edit(Provinsi $provinsi): Response
    {
        return Inertia::render('Admin/ProvinsiForm', ['provinsi' => $provinsi]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Provinsi);

        return to_route('admin.provinsi.index')->with('success', 'Provinsi berhasil ditambahkan.');
    }

    public function update(Request $request, Provinsi $provinsi): RedirectResponse
    {
        $this->save($request, $provinsi);

        return to_route('admin.provinsi.index')->with('success', 'Provinsi berhasil diperbarui.');
    }

    public function destroy(Provinsi $provinsi): RedirectResponse
    {
        if ($provinsi->kotas()->exists()) {
            return to_route('admin.provinsi.index')->with('error', 'Provinsi tidak dapat dihapus karena masih memiliki data kota/kabupaten.');
        }

        if (BadanHukum::query()->where('provinsi_id', $provinsi->id)->exists() || PengaturanInstitusi::query()->where('provinsi_id', $provinsi->id)->exists() || ProgramStudi::query()->where('provinsi_id', $provinsi->id)->exists()) {
            return to_route('admin.provinsi.index')->with('error', 'Provinsi tidak dapat dihapus karena masih dipakai di data badan hukum, perguruan tinggi, atau program studi.');
        }

        try {
            $provinsi->delete();
        } catch (Throwable) {
            return to_route('admin.provinsi.index')->with('error', 'Provinsi gagal dihapus.');
        }

        return to_route('admin.provinsi.index')->with('success', 'Provinsi berhasil dihapus.');
    }

    private function save(Request $request, Provinsi $model): void
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('provinsis', 'kode')->ignore($model)],
            'nama' => ['required', 'string', 'max:255', Rule::unique('provinsis', 'nama')->ignore($model)],
        ], [], ['kode' => 'Kode', 'nama' => 'Nama Provinsi']);
        $model->fill($data)->save();
    }
}
