<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BadanHukum;
use App\Models\Kota;
use App\Models\Provinsi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BadanHukumController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/BadanHukum', [
            'badanHukum' => BadanHukum::current(),
            'provinsis' => Provinsi::query()->orderBy('nama')->get(['id', 'nama']),
            'kotas' => Kota::query()->orderBy('nama')->get(['id', 'provinsi_id', 'nama']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_badan_hukum' => ['required', 'string', 'max:255'],
            'tanggal_berdiri' => ['nullable', 'date'],
            'nomor_akta_terakhir' => ['nullable', 'string', 'max:100'],
            'tanggal_akta_terakhir' => ['nullable', 'date'],
            'nomor_pengesahan' => ['nullable', 'string', 'max:100'],
            'tanggal_pengesahan' => ['nullable', 'date'],
            'alamat_jalan' => ['nullable', 'string', 'max:500'],
            'provinsi_id' => ['nullable', 'integer', 'exists:provinsis,id'],
            // Kota harus berada di provinsi yang dipilih.
            'kota_id' => ['nullable', 'integer', Rule::exists('kotas', 'id')->where('provinsi_id', $request->integer('provinsi_id'))],
            'kode_pos' => ['nullable', 'string', 'max:10'],
            'telepon' => ['nullable', 'string', 'max:30'],
            'faximili' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
        ], [
            'kota_id.exists' => 'Kota/Kabupaten tidak berada di provinsi yang dipilih.',
            'email' => ':attribute harus berupa alamat email yang valid.',
            'url' => ':attribute harus berupa URL yang valid.',
        ], [
            'nama_badan_hukum' => 'Nama Badan Hukum',
            'tanggal_berdiri' => 'Tanggal Berdiri',
            'nomor_akta_terakhir' => 'Nomor Akta Terakhir',
            'tanggal_akta_terakhir' => 'Tanggal Akta Terakhir',
            'nomor_pengesahan' => 'Nomor Pengesahan',
            'tanggal_pengesahan' => 'Tanggal Pengesahan',
            'alamat_jalan' => 'Alamat Jalan',
            'provinsi_id' => 'Provinsi',
            'kota_id' => 'Kota/Kabupaten',
            'kode_pos' => 'Kode Pos',
            'telepon' => 'Telepon',
            'faximili' => 'Faximili',
            'email' => 'Email',
            'website' => 'Website',
        ]);

        $data['updated_by'] = $request->user()->id;
        BadanHukum::current()->update($data);

        return back()->with('success', 'Data badan hukum berhasil diperbarui.');
    }
}
