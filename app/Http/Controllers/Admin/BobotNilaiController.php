<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BobotNilai;
use App\Models\ProgramStudi;
use App\Models\SkalaNilai;
use App\ValidasiSkalaNilai;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bobot nilai huruf per prodi. Aturannya sama dengan Skala Nilai di Pengaturan Akademik. Nilai KRS mata kuliah
 * prodi yang punya Bobot Nilai dihitung dengan bobot ini; prodi yang belum diatur memakai Skala Nilai umum.
 */
class BobotNilaiController extends Controller
{
    private const KOLOM = ['huruf', 'bobot', 'angka_minimal', 'lulus', 'boleh_diulang'];

    public function index(Request $request): Response
    {
        $prodi = ProgramStudi::query()->withCount('bobotNilai')->orderBy('nama_prodi')->get(['id', 'kode_prodi', 'nama_prodi', 'jenjang']);
        $terpilih = $prodi->firstWhere('id', $request->integer('prodi')) ?? $prodi->first();

        $bobot = $terpilih
            ? BobotNilai::query()->whereBelongsTo($terpilih, 'programStudi')->orderByDesc('bobot')->orderBy('huruf')->get(self::KOLOM)
            : collect();
        $belumDiatur = $terpilih !== null && $bobot->isEmpty();
        $dipakai = $terpilih ? SkalaNilai::jumlahDipakai($terpilih->id) : [];

        return Inertia::render('Admin/BobotNilai', [
            'prodi' => $prodi->map(fn (ProgramStudi $item): array => [
                'id' => $item->id,
                'nama' => trim(($item->jenjang ? $item->jenjang.' ' : '').$item->nama_prodi),
                'kode' => $item->kode_prodi,
                'jumlah' => $item->bobot_nilai_count,
            ]),
            'prodiId' => $terpilih?->id,
            'belumDiatur' => $belumDiatur,
            'bobotNilai' => ($belumDiatur ? SkalaNilai::query()->orderByDesc('bobot')->orderBy('huruf')->get(self::KOLOM) : $bobot)
                ->map(fn (Model $nilai): array => [...$nilai->toArray(), 'dipakai' => $dipakai[$nilai->huruf] ?? 0]),
        ]);
    }

    public function update(Request $request, ProgramStudi $programStudi): RedirectResponse
    {
        $baris = ValidasiSkalaNilai::validasi($request, 'bobot_nilai');

        // Huruf yang sudah dipakai di KRS mata kuliah prodi ini tidak boleh hilang, agar nilai lama tetap punya bobot.
        $hilang = $this->hurufHilang($programStudi, collect($baris)->pluck('huruf')->all());
        if ($hilang !== []) {
            throw ValidationException::withMessages([
                'bobot_nilai' => 'Nilai '.implode(', ', $hilang).' masih dipakai di KRS mata kuliah prodi ini sehingga tidak bisa dihapus.',
            ]);
        }

        DB::transaction(function () use ($programStudi, $baris): void {
            $programStudi->bobotNilai()->delete();

            foreach ($baris as $row) {
                $programStudi->bobotNilai()->create(ValidasiSkalaNilai::kolom($row));
            }
        });

        return to_route('admin.bobot-nilai.index', ['prodi' => $programStudi->id])
            ->with('success', "Bobot nilai {$programStudi->nama_prodi} berhasil disimpan.");
    }

    public function destroy(ProgramStudi $programStudi): RedirectResponse
    {
        // Setelah dihapus prodi kembali memakai Skala Nilai umum, jadi huruf yang sudah dipakai harus ada di sana.
        $hilang = $this->hurufHilang($programStudi, SkalaNilai::query()->pluck('huruf')->all());
        if ($hilang !== []) {
            return to_route('admin.bobot-nilai.index', ['prodi' => $programStudi->id])
                ->with('error', 'Bobot nilai tidak bisa dihapus: nilai '.implode(', ', $hilang).' dipakai di KRS prodi ini tetapi tidak ada di Skala Nilai umum.');
        }

        $programStudi->bobotNilai()->delete();

        return to_route('admin.bobot-nilai.index', ['prodi' => $programStudi->id])
            ->with('success', "Bobot nilai {$programStudi->nama_prodi} dihapus; prodi ini kembali memakai Skala Nilai umum.");
    }

    /**
     * Huruf yang dipakai KRS mata kuliah prodi ini tetapi tidak ada di daftar huruf baru.
     *
     * @param  list<string>  $huruf
     * @return list<string>
     */
    private function hurufHilang(ProgramStudi $programStudi, array $huruf): array
    {
        return array_values(array_diff(array_keys(SkalaNilai::jumlahDipakai($programStudi->id)), $huruf));
    }
}
