<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BatasSks;
use App\Models\Krs;
use App\Models\PengaturanAkademik;
use App\Models\SkalaNilai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanAkademikController extends Controller
{
    public function index(): Response
    {
        $dipakai = $this->jumlahNilaiDipakai();

        return Inertia::render('Admin/PengaturanAkademik', [
            'maksSksTanpaIps' => PengaturanAkademik::current()->maks_sks_tanpa_ips,
            'batasSks' => BatasSks::query()->orderByDesc('ips_minimal')->get(['ips_minimal', 'maks_sks']),
            'skalaNilai' => SkalaNilai::query()->orderByDesc('bobot')->orderBy('huruf')->get(['huruf', 'bobot', 'lulus', 'boleh_diulang'])
                ->map(fn (SkalaNilai $nilai): array => [...$nilai->toArray(), 'dipakai' => $dipakai[$nilai->huruf] ?? 0]),
        ]);
    }

    public function updateBatasSks(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'maks_sks_tanpa_ips' => ['required', 'integer', 'min:1', 'max:40'],
            'batas_sks' => ['required', 'array', 'min:1'],
            'batas_sks.*.ips_minimal' => ['required', 'numeric', 'min:0', 'max:4', 'distinct'],
            'batas_sks.*.maks_sks' => ['required', 'integer', 'min:1', 'max:40'],
        ], [
            'distinct' => 'IPS minimal tidak boleh sama di dua baris.',
            'max' => ':attribute maksimal :max.',
        ], [
            'maks_sks_tanpa_ips' => 'Maks SKS tanpa IPS',
            'batas_sks.*.ips_minimal' => 'IPS minimal',
            'batas_sks.*.maks_sks' => 'Maks SKS',
        ]);

        if (! collect($data['batas_sks'])->contains(fn (array $row): bool => (float) $row['ips_minimal'] === 0.0)) {
            throw ValidationException::withMessages([
                'batas_sks' => 'Harus ada baris dengan IPS minimal 0 agar semua IPS mendapat batas SKS.',
            ]);
        }

        DB::transaction(function () use ($data, $request): void {
            PengaturanAkademik::current()->update(['maks_sks_tanpa_ips' => $data['maks_sks_tanpa_ips'], 'updated_by' => $request->user()->id]);
            BatasSks::query()->delete();

            foreach ($data['batas_sks'] as $row) {
                BatasSks::create(['ips_minimal' => round((float) $row['ips_minimal'], 2), 'maks_sks' => $row['maks_sks']]);
            }
        });

        return back()->with('success', 'Batas SKS berhasil disimpan.');
    }

    public function updateSkalaNilai(Request $request): RedirectResponse
    {
        $request->merge([
            'skala_nilai' => collect($request->input('skala_nilai', []))
                ->map(fn ($row): array => is_array($row) ? [...$row, 'huruf' => strtoupper(trim((string) ($row['huruf'] ?? '')))] : [])
                ->all(),
        ]);

        $data = $request->validate([
            'skala_nilai' => ['required', 'array', 'min:1'],
            'skala_nilai.*.huruf' => ['required', 'string', 'max:2', 'regex:/^[A-Z][+-]?$/', 'distinct'],
            'skala_nilai.*.bobot' => ['required', 'numeric', 'min:0', 'max:4'],
            'skala_nilai.*.lulus' => ['required', 'boolean'],
            'skala_nilai.*.boleh_diulang' => ['required', 'boolean'],
        ], [
            'distinct' => 'Huruf nilai tidak boleh sama di dua baris.',
            'regex' => 'Huruf nilai berupa satu huruf, boleh diikuti + atau - (mis. A, B+, A-).',
            'max' => ':attribute maksimal :max.',
        ], [
            'skala_nilai.*.huruf' => 'Huruf',
            'skala_nilai.*.bobot' => 'Bobot',
        ]);

        // Huruf yang sudah dipakai di nilai mahasiswa tidak boleh hilang, agar nilai lama tetap punya bobot.
        $hilang = collect($this->jumlahNilaiDipakai())->keys()->diff(collect($data['skala_nilai'])->pluck('huruf'));

        if ($hilang->isNotEmpty()) {
            throw ValidationException::withMessages([
                'skala_nilai' => 'Nilai '.$hilang->implode(', ').' masih dipakai di KRS mahasiswa sehingga tidak bisa dihapus.',
            ]);
        }

        DB::transaction(function () use ($data): void {
            SkalaNilai::query()->delete();

            foreach ($data['skala_nilai'] as $row) {
                SkalaNilai::create([
                    'huruf' => $row['huruf'],
                    'bobot' => round((float) $row['bobot'], 2),
                    'lulus' => (bool) $row['lulus'],
                    'boleh_diulang' => (bool) $row['boleh_diulang'],
                ]);
            }
        });

        return back()->with('success', 'Skala nilai berhasil disimpan.');
    }

    /**
     * @return array<string, int> huruf => jumlah KRS yang memakai nilai tersebut
     */
    private function jumlahNilaiDipakai(): array
    {
        return Krs::query()
            ->whereNotNull('nilai')
            ->selectRaw('UPPER(nilai) as huruf, COUNT(*) as jumlah')
            ->groupByRaw('UPPER(nilai)')
            ->pluck('jumlah', 'huruf')
            ->map(fn ($jumlah): int => (int) $jumlah)
            ->all();
    }
}
