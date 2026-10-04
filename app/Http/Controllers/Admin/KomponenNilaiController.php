<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KomponenNilai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Komponen nilai global beserta persen bobotnya (jumlah 100%) dan sumbernya: diisi manual, atau Kehadiran yang
 * diambil otomatis dari presensi (paling banyak satu). Dipakai Nilai Semester untuk menghitung nilai akhir.
 * Mengubah persen tidak menghitung ulang nilai yang sudah tersimpan; nilai akhir kelas ikut berubah saat disimpan lagi.
 */
class KomponenNilaiController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/KomponenNilai', [
            'komponen' => KomponenNilai::query()->withCount('nilai')->orderBy('urutan')->orderBy('id')->get()
                ->map(fn (KomponenNilai $k): array => ['id' => $k->id, 'nama' => $k->nama, 'persen' => $k->persen, 'sumber' => $k->sumber, 'dipakai' => $k->nilai_count]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'komponen' => ['required', 'array', 'min:1'],
            'komponen.*.id' => ['nullable', 'integer', Rule::exists('komponen_nilais', 'id')],
            'komponen.*.nama' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'komponen.*.persen' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'komponen.*.sumber' => ['required', Rule::in(KomponenNilai::SUMBER)],
        ], [
            'komponen.required' => 'Tambahkan minimal satu komponen nilai.',
            'komponen.min' => 'Tambahkan minimal satu komponen nilai.',
            'komponen.*.nama.distinct' => 'Nama komponen tidak boleh sama.',
        ], [
            'komponen.*.nama' => 'Nama komponen',
            'komponen.*.persen' => 'Persen',
            'komponen.*.sumber' => 'Sumber nilai',
        ]);

        $baris = collect($data['komponen'])->map(fn (array $row): array => [
            'id' => isset($row['id']) ? (int) $row['id'] : null,
            'nama' => trim($row['nama']),
            'persen' => round((float) $row['persen'], 2),
            'sumber' => $row['sumber'],
        ]);

        $total = round($baris->sum('persen'), 2);
        if (abs($total - 100) >= 0.005) {
            throw ValidationException::withMessages(['komponen' => "Jumlah persen semua komponen harus 100% (sekarang {$total}%)."]);
        }

        if ($baris->where('sumber', KomponenNilai::KEHADIRAN)->count() > 1) {
            throw ValidationException::withMessages(['komponen' => 'Hanya satu komponen yang boleh diambil otomatis dari kehadiran.']);
        }

        // Komponen yang sudah berisi nilai mahasiswa tidak boleh dihapus, agar nilai akhirnya tetap bisa dijelaskan.
        $dihapus = KomponenNilai::query()->whereNotIn('id', $baris->pluck('id')->filter()->all())->withCount('nilai')->get();
        $dipakai = $dihapus->filter(fn (KomponenNilai $k): bool => $k->nilai_count > 0);
        if ($dipakai->isNotEmpty()) {
            throw ValidationException::withMessages([
                'komponen' => 'Komponen '.$dipakai->pluck('nama')->implode(', ').' sudah berisi nilai mahasiswa sehingga tidak bisa dihapus.',
            ]);
        }

        DB::transaction(function () use ($baris, $dihapus): void {
            KomponenNilai::query()->whereKey($dihapus->modelKeys())->delete();

            // Nama sementara dulu agar menukar nama antarkomponen tidak bentrok dengan indeks unik.
            foreach ($baris->pluck('id')->filter() as $id) {
                KomponenNilai::query()->whereKey($id)->update(['nama' => '~'.$id]);
            }

            foreach ($baris->values() as $urutan => $row) {
                KomponenNilai::query()->updateOrCreate(['id' => $row['id']], ['nama' => $row['nama'], 'persen' => $row['persen'], 'sumber' => $row['sumber'], 'urutan' => $urutan]);
            }
        });

        return to_route('admin.komponen-nilai.index')->with('success', 'Komponen nilai berhasil disimpan.');
    }
}
