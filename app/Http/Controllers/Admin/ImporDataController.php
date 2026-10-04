<?php

namespace App\Http\Controllers\Admin;

use App\CatatAktivitas;
use App\Excel;
use App\Http\Controllers\Controller;
use App\Impor\Impor;
use App\Models\LogAktivitas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Impor data dari Excel: unduh template, isi, lalu unggah. Semua baris diperiksa dulu; bila ada yang salah tidak ada
 * yang disimpan dan daftar galatnya ditampilkan per baris.
 */
class ImporDataController extends Controller
{
    public function index(Request $request, string $jenis): Response
    {
        $impor = $this->impor($jenis);

        return Inertia::render('Admin/ImporData', [
            'jenis' => $jenis,
            'judul' => $impor->judul(),
            'akun' => $jenis !== 'mata-kuliah',
            'kolom' => collect($impor->kolom())->map(fn (array $k): array => ['judul' => $k[0], 'wajib' => $k[1], 'catatan' => $k[3]])->values(),
            'pilihanJenis' => collect(Impor::JENIS)->keys()
                ->filter(fn (string $j): bool => Gate::allows(Impor::untuk($j)->izin()))
                ->map(fn (string $j): array => ['id' => $j, 'name' => Impor::untuk($j)->judul()])->values(),
            'maksBaris' => Impor::MAKS_BARIS,
            'hasil' => $request->session()->get('impor_hasil'),
        ]);
    }

    public function template(string $jenis): StreamedResponse
    {
        $impor = $this->impor($jenis);
        $kolom = $impor->kolom();
        $petunjuk = collect($kolom)->map(fn (array $k): string => $k[0].($k[1] ? ' (wajib)' : ' (opsional)').($k[3] !== '' ? ': '.$k[3] : ''))
            ->prepend('Isi data mulai baris 2 lembar pertama, satu baris satu data. Baris contoh boleh dihapus atau ditimpa.')
            ->prepend('Judul kolom di baris 1 jangan diubah.')
            ->values()->all();

        return Excel::unduh(
            'template-impor-'.$jenis.'.xlsx',
            array_map(fn (array $k): string => $k[0], array_values($kolom)),
            [array_map(fn (array $k): string => $k[2], array_values($kolom))],
            $impor->judul(),
            ['Petunjuk' => $petunjuk, ...$impor->referensi()],
            template: true,
        );
    }

    public function store(Request $request, string $jenis): RedirectResponse
    {
        $impor = $this->impor($jenis);
        $request->validate([
            'berkas' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'kirim_tautan_sandi' => ['nullable', 'boolean'],
        ], [
            'berkas.required' => 'Pilih berkas Excel.',
            'berkas.mimes' => 'Berkas harus berformat .xlsx atau .xls.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        try {
            $baris = Excel::baca($request->file('berkas')->getRealPath());
        } catch (Throwable) {
            throw ValidationException::withMessages(['berkas' => 'Berkas tidak bisa dibaca. Pastikan memakai template .xlsx dari halaman ini.']);
        }

        if ($baris === []) {
            throw ValidationException::withMessages(['berkas' => 'Berkas tidak berisi data.']);
        }
        // Setiap baris memuat semua judul kolom berkas, jadi baris pertama cukup untuk memeriksa kolom wajib.
        $kolom = $impor->kolom();
        $kurang = array_diff(array_keys(array_filter($kolom, fn (array $k): bool => $k[1])), array_keys($baris[0]['data']));
        if ($kurang !== []) {
            throw ValidationException::withMessages(['berkas' => 'Kolom wajib tidak ditemukan: '.implode(', ', array_map(fn (string $k): string => $kolom[$k][0], $kurang)).'. Pakai template dari halaman ini.']);
        }
        if (count($baris) > Impor::MAKS_BARIS) {
            throw ValidationException::withMessages(['berkas' => 'Maksimal '.Impor::MAKS_BARIS.' baris per berkas; pecah berkasnya.']);
        }

        $hasil = $impor->periksa($baris);
        if ($hasil['galat'] !== []) {
            return back()->with('impor_hasil', ['berhasil' => 0, 'galat' => array_slice($hasil['galat'], 0, 200), 'jumlahGalat' => count($hasil['galat'])])
                ->with('error', 'Tidak ada data yang disimpan: '.count($hasil['galat']).' galat ditemukan. Perbaiki berkasnya lalu unggah lagi.');
        }

        $opsi = ['kirim_tautan_sandi' => $request->boolean('kirim_tautan_sandi')];
        // Satu baris log untuk seluruh impor, bukan satu per data.
        CatatAktivitas::tanpaCatatan(fn () => DB::transaction(function () use ($impor, $hasil, $opsi): void {
            foreach ($hasil['data'] as $data) {
                $impor->simpan($data, $opsi);
            }
        }));
        CatatAktivitas::simpan($request->user(), LogAktivitas::DIBUAT, [
            'objek_tipe' => 'Impor',
            'label' => 'Impor '.$impor->judul().': '.count($hasil['data']).' baris dari '.$request->file('berkas')->getClientOriginalName(),
        ]);

        return back()->with('impor_hasil', ['berhasil' => count($hasil['data']), 'galat' => [], 'jumlahGalat' => 0])
            ->with('success', count($hasil['data']).' '.mb_strtolower($impor->judul()).' berhasil diimpor.');
    }

    private function impor(string $jenis): Impor
    {
        $impor = Impor::untuk($jenis);
        Gate::authorize($impor->izin());

        return $impor;
    }
}
