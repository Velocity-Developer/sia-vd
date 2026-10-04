<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InformasiPmb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Isi halaman publik Informasi PMB: pengantar, syarat, jadwal tes, biaya, dan kontak. Teks biasa; baris baru dipertahankan.
 */
class InformasiPmbController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/InformasiPmb', [
            'informasi' => InformasiPmb::current()->only(['judul', 'pengantar', ...array_keys(InformasiPmb::BAGIAN)]),
            'bagian' => InformasiPmb::BAGIAN,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $teks = ['nullable', 'string', 'max:5000'];
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:150'],
            'pengantar' => $teks,
            ...array_fill_keys(array_keys(InformasiPmb::BAGIAN), $teks),
        ], attributes: ['judul' => 'Judul', 'pengantar' => 'Pengantar', ...InformasiPmb::BAGIAN]);

        InformasiPmb::current()->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Informasi PMB disimpan.');
    }
}
