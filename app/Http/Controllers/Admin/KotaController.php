<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BadanHukum;
use App\Models\Kota;
use App\Models\PengaturanInstitusi;
use App\Models\ProgramStudi;
use App\Models\Provinsi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class KotaController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $provinsiId = $request->integer('provinsi_id') ?: null;
        $kotas = Kota::query()
            ->with('provinsi:id,kode,nama')
            ->when($provinsiId !== null, fn ($query) => $query->where('provinsi_id', $provinsiId))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('kode', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%")))
            ->orderBy('kode')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Kota', [
            'kotas' => $kotas,
            'search' => $search,
            'provinsis' => $this->provinsiOptions(),
            'provinsiId' => $provinsiId,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/KotaForm', ['kota' => null, 'provinsis' => $this->provinsiOptions()]);
    }

    public function edit(Kota $kota): Response
    {
        return Inertia::render('Admin/KotaForm', ['kota' => $kota, 'provinsis' => $this->provinsiOptions()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Kota);

        return to_route('admin.kota.index')->with('success', 'Kota/kabupaten berhasil ditambahkan.');
    }

    public function update(Request $request, Kota $kota): RedirectResponse
    {
        $this->save($request, $kota);

        return to_route('admin.kota.index')->with('success', 'Kota/kabupaten berhasil diperbarui.');
    }

    public function destroy(Kota $kota): RedirectResponse
    {
        if (BadanHukum::query()->where('kota_id', $kota->id)->exists() || PengaturanInstitusi::query()->where('kota_id', $kota->id)->exists() || ProgramStudi::query()->where('kota_id', $kota->id)->exists()) {
            return to_route('admin.kota.index')->with('error', 'Kota/kabupaten tidak dapat dihapus karena masih dipakai di data badan hukum, perguruan tinggi, atau program studi.');
        }

        try {
            $kota->delete();
        } catch (Throwable) {
            return to_route('admin.kota.index')->with('error', 'Kota/kabupaten gagal dihapus.');
        }

        return to_route('admin.kota.index')->with('success', 'Kota/kabupaten berhasil dihapus.');
    }

    /**
     * @return list<array{id: int, kode: string, nama: string}>
     */
    private function provinsiOptions(): array
    {
        return Provinsi::query()->orderBy('nama')->get(['id', 'kode', 'nama'])->toArray();
    }

    private function save(Request $request, Kota $model): void
    {
        $data = $request->validate([
            'provinsi_id' => ['required', 'integer', 'exists:provinsis,id'],
            'kode' => ['required', 'string', 'max:20', Rule::unique('kotas', 'kode')->ignore($model)],
            'nama' => ['required', 'string', 'max:255', Rule::unique('kotas', 'nama')->where('provinsi_id', $request->integer('provinsi_id'))->ignore($model)],
        ], [
            'nama.unique' => 'Nama kota/kabupaten ini sudah ada di provinsi yang dipilih.',
        ], ['provinsi_id' => 'Provinsi', 'kode' => 'Kode', 'nama' => 'Nama Kota/Kabupaten']);
        $model->fill($data)->save();
    }
}
