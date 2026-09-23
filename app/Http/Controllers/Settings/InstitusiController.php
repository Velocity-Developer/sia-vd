<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PengaturanInstitusi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class InstitusiController extends Controller
{
    /**
     * Show the institution settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Institusi', [
            'institusi' => PengaturanInstitusi::current(),
        ]);
    }

    /**
     * Update the institution settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_pt' => ['required', 'string', 'max:255'],
            'singkatan' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'npsn' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'tahun_berdiri' => ['nullable', 'integer', 'min:1000', 'max:'.date('Y')],
        ], [
            'image' => ':attribute harus berupa gambar.',
            'mimes' => ':attribute harus berformat jpg, jpeg, png, atau webp.',
            'email' => ':attribute harus berupa alamat email yang valid.',
            'url' => ':attribute harus berupa URL yang valid.',
            'max.numeric' => ':attribute maksimal :max.',
        ], [
            'nama_pt' => 'Nama Perguruan Tinggi',
            'singkatan' => 'Singkatan/Nama Pendek',
            'logo' => 'Logo',
            'npsn' => 'NPSN/Kode Perguruan Tinggi',
            'alamat' => 'Alamat',
            'telepon' => 'Nomor Telepon',
            'email' => 'Email Resmi',
            'website' => 'Website',
            'tahun_berdiri' => 'Tahun Berdiri',
        ]);

        $institusi = PengaturanInstitusi::current();

        if ($request->hasFile('logo')) {
            if ($institusi->logo) {
                Storage::disk('public')->delete($institusi->logo);
            }
            $data['logo'] = $request->file('logo')->store('institusi', 'public');
        } else {
            unset($data['logo']);
        }

        $data['updated_by'] = $request->user()->id;
        $institusi->update($data);

        return back()->with('success', 'Pengaturan institusi berhasil diperbarui.');
    }
}
