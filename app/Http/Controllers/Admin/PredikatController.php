<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Predikat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Predikat kelulusan per rentang IPK. Dipakai saat SKL terbit; predikat wisuda yang sudah terbit tidak berubah.
 */
class PredikatController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Predikat', [
            'predikat' => Predikat::query()->orderByDesc('bobot_minimal')->get(['id', 'nama', 'bobot_minimal', 'bobot_maksimal']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PredikatForm', ['predikat' => null]);
    }

    public function edit(Predikat $predikat): Response
    {
        return Inertia::render('Admin/PredikatForm', ['predikat' => $predikat->only(['id', 'nama', 'bobot_minimal', 'bobot_maksimal'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new Predikat);

        return to_route('admin.predikat.index')->with('success', 'Predikat berhasil ditambahkan.');
    }

    public function update(Request $request, Predikat $predikat): RedirectResponse
    {
        $this->save($request, $predikat);

        return to_route('admin.predikat.index')->with('success', 'Predikat berhasil diperbarui.');
    }

    public function destroy(Predikat $predikat): RedirectResponse
    {
        $predikat->delete();

        return to_route('admin.predikat.index')->with('success', 'Predikat berhasil dihapus.');
    }

    private function save(Request $request, Predikat $model): void
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:60', Rule::unique('predikats', 'nama')->ignore($model)],
            'bobot_minimal' => ['required', 'numeric', 'min:0', 'max:4'],
            'bobot_maksimal' => ['required', 'numeric', 'min:0', 'max:4', 'gte:bobot_minimal'],
        ], [
            'gte' => ':attribute tidak boleh lebih kecil dari Bobot Minimal.',
            'max' => ':attribute maksimal :max.',
        ], [
            'nama' => 'Nama Predikat',
            'bobot_minimal' => 'Bobot Minimal',
            'bobot_maksimal' => 'Bobot Maksimal',
        ]);
        $data['bobot_minimal'] = round((float) $data['bobot_minimal'], 2);
        $data['bobot_maksimal'] = round((float) $data['bobot_maksimal'], 2);

        // Rentang tidak boleh beririsan, agar satu IPK hanya punya satu predikat.
        $bentrok = Predikat::query()
            ->whereKeyNot($model->id)
            ->where('bobot_minimal', '<=', $data['bobot_maksimal'])
            ->where('bobot_maksimal', '>=', $data['bobot_minimal'])
            ->orderByDesc('bobot_minimal')
            ->first();
        if ($bentrok !== null) {
            throw ValidationException::withMessages([
                'bobot_minimal' => sprintf('Rentang beririsan dengan predikat %s (%.2f–%.2f).', $bentrok->nama, $bentrok->bobot_minimal, $bentrok->bobot_maksimal),
            ]);
        }

        $model->fill($data)->save();
    }
}
