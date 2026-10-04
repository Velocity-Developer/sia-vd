<?php

namespace App\Http\Controllers\Concerns;

use App\Models\KelasKuliah;
use App\NilaiSemester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Simpan tabel nilai per komponen satu kelas (Nilai Semester admin dan halaman kelas dosen/admin).
 * Pemanggil memeriksa akses dan kunci nilai lebih dulu.
 */
trait SimpanNilaiKomponen
{
    protected function simpanNilaiKomponen(Request $request, KelasKuliah $kelas): RedirectResponse
    {
        if (NilaiSemester::kkm($kelas)) {
            return back()->with('error', 'Nilai mata kuliah KKM diisi di Penilaian → Nilai KKM.');
        }
        if ($kelas->tugasAkhir() && ! NilaiSemester::langsung($kelas)) {
            return back()->with('error', 'Nilai TA/Skripsi terisi otomatis dari hasil pendadaran.');
        }

        $komponen = NilaiSemester::komponenKelas($kelas);
        if (! NilaiSemester::persenLengkap($komponen)) {
            return back()->with('error', 'Komponen nilai belum diatur atau jumlah persennya belum 100% (Penilaian → Tambah Komponen Nilai).');
        }
        if (! NilaiSemester::skalaSiap($kelas->loadMissing('mataKuliah')->mataKuliah?->prodi_id)) {
            return back()->with('error', 'Angka minimal huruf belum diatur (Bobot Nilai prodi atau Skala Nilai di Pengaturan Akademik), jadi nilai akhir tidak bisa diubah menjadi huruf.');
        }

        $data = $request->validate([
            'nilai' => ['present', 'array'],
            'nilai.*' => ['array'],
            'nilai.*.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'nilai.*.*.numeric' => 'Nilai harus berupa angka.',
            'nilai.*.*.min' => 'Nilai paling kecil 0.',
            'nilai.*.*.max' => 'Nilai paling besar 100.',
        ]);

        $berubah = NilaiSemester::simpan($kelas, $data['nilai'], $komponen);

        return back()->with('success', "Nilai disimpan. {$berubah} nilai akhir mahasiswa diperbarui.");
    }
}
