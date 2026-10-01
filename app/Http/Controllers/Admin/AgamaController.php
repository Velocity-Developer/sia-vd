<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agama;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AgamaController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $agamas = Agama::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('kode', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%")))
            ->orderBy('kode')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Admin/Agama', ['agamas' => $agamas, 'search' => $search]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/AgamaForm', ['agama' => null]);
    }

    public function edit(Agama $agama): Response
    {
        return Inertia::render('Admin/AgamaForm', ['agama' => $agama]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Agama);

        return to_route('admin.agama.index')->with('success', 'Agama berhasil ditambahkan.');
    }

    public function update(Request $request, Agama $agama): RedirectResponse
    {
        $this->save($request, $agama);

        return to_route('admin.agama.index')->with('success', 'Agama berhasil diperbarui.');
    }

    public function destroy(Agama $agama): RedirectResponse
    {
        try {
            $agama->delete();
        } catch (Throwable) {
            return to_route('admin.agama.index')->with('error', 'Agama gagal dihapus.');
        }

        return to_route('admin.agama.index')->with('success', 'Agama berhasil dihapus.');
    }

    private function save(Request $request, Agama $model): void
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('agamas', 'kode')->ignore($model)],
            'nama' => ['required', 'string', 'max:255', Rule::unique('agamas', 'nama')->ignore($model)],
        ], [], ['kode' => 'Kode', 'nama' => 'Nama Agama']);
        $model->fill($data)->save();
    }
}
