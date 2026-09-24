<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PengaturanTampilan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TampilanController extends Controller
{
    public function edit(): Response
    {
        $tampilan = PengaturanTampilan::current();

        return Inertia::render('PengaturanSistem/Tampilan', [
            'pengaturan' => [
                ...$tampilan->only(['nama_aplikasi', 'login_judul', 'login_teks', 'login_sorotan', 'sidebar_bawaan']),
                'favicon_url' => PengaturanTampilan::urlBerkas($tampilan->favicon),
                'login_gambar_url' => PengaturanTampilan::urlBerkas($tampilan->login_gambar),
            ],
            'bawaan' => [
                'login_judul' => PengaturanTampilan::LOGIN_JUDUL_BAWAAN,
                'login_teks' => PengaturanTampilan::LOGIN_TEKS_BAWAAN,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama_aplikasi' => ['nullable', 'string', 'max:100'],
            'login_judul' => ['nullable', 'string', 'max:120'],
            'login_teks' => ['nullable', 'string', 'max:300'],
            'login_sorotan' => ['required', 'boolean'],
            'sidebar_bawaan' => ['required', Rule::in(PengaturanTampilan::SIDEBAR)],
            // SVG sengaja tidak diterima: berkas SVG bisa berisi skrip bila dibuka langsung.
            'favicon' => ['nullable', 'file', 'max:512', 'extensions:png,ico,webp', 'mimes:png,ico,webp'],
            'hapus_favicon' => ['boolean'],
            'login_gambar' => ['nullable', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
            'hapus_login_gambar' => ['boolean'],
        ], [
            'extensions' => ':attribute harus berformat png, ico, atau webp.',
            'favicon.mimes' => ':attribute harus berformat png, ico, atau webp.',
            'login_gambar.mimes' => ':attribute harus berformat jpg, jpeg, png, atau webp.',
            'image' => ':attribute harus berupa gambar.',
        ], [
            'nama_aplikasi' => 'Nama di tab browser',
            'login_judul' => 'Judul halaman masuk',
            'login_teks' => 'Teks sambutan',
            'login_sorotan' => 'Daftar fitur',
            'sidebar_bawaan' => 'Sidebar bawaan',
            'favicon' => 'Favicon',
            'login_gambar' => 'Gambar latar',
        ]);

        $tampilan = PengaturanTampilan::current();
        $simpan = [
            ...collect($data)->only(['nama_aplikasi', 'login_judul', 'login_teks', 'login_sorotan', 'sidebar_bawaan'])->all(),
            'updated_by' => $request->user()->id,
        ];

        foreach (['favicon', 'login_gambar'] as $kolom) {
            if ($request->hasFile($kolom) || $request->boolean('hapus_'.$kolom)) {
                if ($tampilan->{$kolom}) {
                    Storage::disk('public')->delete($tampilan->{$kolom});
                }
                $simpan[$kolom] = $request->hasFile($kolom) ? $request->file($kolom)->store('tampilan', 'public') : null;
            }
        }

        $tampilan->update($simpan);

        return back()->with('success', 'Pengaturan tampilan berhasil disimpan.');
    }
}
