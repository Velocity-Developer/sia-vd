<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BadanHukum;
use App\Models\Kota;
use App\Models\PengaturanInstitusi;
use App\Models\Provinsi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PerguruanTinggiController extends Controller
{
    /**
     * Halaman Master → Perguruan Tinggi (dulu tab Institusi di Pengaturan Sistem).
     */
    public function edit(): Response
    {
        return Inertia::render('Admin/PerguruanTinggi', [
            'institusi' => PengaturanInstitusi::current(),
            'badanHukums' => BadanHukum::query()->orderBy('id')->get(['id', 'nama_badan_hukum']),
            'provinsis' => Provinsi::query()->orderBy('nama')->get(['id', 'nama']),
            'kotas' => Kota::query()->orderBy('nama')->get(['id', 'provinsi_id', 'nama']),
            'zonaWaktu' => [
                ['value' => 'Asia/Jakarta', 'label' => 'WIB — Waktu Indonesia Barat (UTC+7)'],
                ['value' => 'Asia/Makassar', 'label' => 'WITA — Waktu Indonesia Tengah (UTC+8)'],
                ['value' => 'Asia/Jayapura', 'label' => 'WIT — Waktu Indonesia Timur (UTC+9)'],
            ],
        ]);
    }

    /**
     * Simpan data perguruan tinggi.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_pt' => ['required', 'string', 'max:255'],
            'badan_hukum_id' => ['nullable', 'integer', 'exists:badan_hukum,id'],
            'singkatan' => ['nullable', 'string', 'max:50'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'npsn' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'alamat_lain' => ['nullable', 'string', 'max:1000'],
            'provinsi_id' => ['nullable', 'integer', 'exists:provinsis,id'],
            // Kota harus berada di provinsi yang dipilih.
            'kota_id' => ['nullable', 'integer', Rule::exists('kotas', 'id')->where('provinsi_id', $request->integer('provinsi_id'))],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'faximili' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'tahun_berdiri' => ['nullable', 'integer', 'min:1000', 'max:'.date('Y')],
            'nomor_akta_terakhir' => ['nullable', 'string', 'max:100'],
            'tanggal_akta_terakhir' => ['nullable', 'date'],
            'nomor_pengesahan' => ['nullable', 'string', 'max:100'],
            'tanggal_pengesahan' => ['nullable', 'date'],
            'akreditasi' => ['nullable', 'string', 'max:50'],
            'zona_waktu' => ['sometimes', 'required', Rule::in(array_keys(PengaturanInstitusi::ZONA_WAKTU))],
        ], [
            'kota_id.exists' => 'Kota/Kabupaten tidak berada di provinsi yang dipilih.',
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
            'badan_hukum_id' => 'Badan Hukum',
            'alamat' => 'Alamat',
            'alamat_lain' => 'Alamat Lain',
            'provinsi_id' => 'Provinsi',
            'kota_id' => 'Kota/Kabupaten',
            'kode_pos' => 'Kode Pos',
            'telepon' => 'Nomor Telepon',
            'faximili' => 'Faximili',
            'nomor_akta_terakhir' => 'Nomor Akta Terakhir',
            'tanggal_akta_terakhir' => 'Tanggal Akta Terakhir',
            'nomor_pengesahan' => 'Nomor Pengesahan',
            'tanggal_pengesahan' => 'Tanggal Pengesahan',
            'akreditasi' => 'Akreditasi',
            'email' => 'Email Resmi',
            'website' => 'Website',
            'tahun_berdiri' => 'Tahun Berdiri',
            'zona_waktu' => 'Zona Waktu',
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

        return back()->with('success', 'Data perguruan tinggi berhasil diperbarui.');
    }
}
