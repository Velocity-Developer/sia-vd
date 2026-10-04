<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\LingkupProdi;
use App\Models\PengaturanAkademik;
use App\Models\ProgramStudi;
use App\Models\SkalaNilai;
use App\Models\SyaratUjianProdi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Syarat kehadiran ujian (UTS/UAS) dan batas huruf remedial. Pengaturan umum berlaku bagi semua prodi; prodi yang
 * diatur sendiri memakai pengaturannya (PengaturanAkademik::untukProdi()). Akun Prodi hanya mengatur prodinya.
 */
class SyaratUjianController extends Controller
{
    public const UMUM = 'umum';

    public function index(Request $request): Response
    {
        $prodi = ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'kode_prodi', 'nama_prodi', 'jenjang']);
        $diatur = SyaratUjianProdi::query()->get()->keyBy('prodi_id');
        $bolehUmum = ! LingkupProdi::aktif();

        $pilihan = (string) $request->query('prodi', '');
        $terpilih = $prodi->firstWhere('id', (int) $pilihan);
        if ($terpilih === null && ! ($bolehUmum && ($pilihan === self::UMUM || $pilihan === ''))) {
            $terpilih = $prodi->first();
        }

        $milikProdi = $terpilih === null ? null : $diatur->get($terpilih->id);
        $umum = PengaturanAkademik::current();
        $nilai = $milikProdi ?? $umum;

        return Inertia::render('Admin/SyaratUjian', [
            'bolehUmum' => $bolehUmum,
            'prodi' => $prodi->map(fn (ProgramStudi $item): array => [
                'id' => $item->id,
                'nama' => trim(($item->jenjang ? $item->jenjang.' ' : '').$item->nama_prodi),
                'kode' => $item->kode_prodi,
                'diatur' => $diatur->has($item->id),
            ]),
            'prodiId' => $terpilih?->id,
            'belumDiatur' => $terpilih !== null && $milikProdi === null,
            'pengaturan' => [
                'syarat_ujian_aktif' => (bool) $nilai->syarat_ujian_aktif,
                'min_kehadiran_ujian' => (int) $nilai->min_kehadiran_ujian,
                'izin_sakit_dihitung_hadir' => (bool) $nilai->izin_sakit_dihitung_hadir,
                'huruf_maks_remidi' => $nilai->huruf_maks_remidi,
            ],
            'hurufOptions' => SkalaNilai::huruf($terpilih?->id),
        ]);
    }

    public function updateUmum(Request $request): RedirectResponse
    {
        PengaturanAkademik::current()->update([...$this->validasi($request, null), 'updated_by' => $request->user()->id]);

        return to_route('admin.syarat-ujian.index', ['prodi' => self::UMUM])
            ->with('success', 'Pengaturan umum syarat ujian & remedial disimpan.');
    }

    public function update(Request $request, ProgramStudi $programStudi): RedirectResponse
    {
        SyaratUjianProdi::query()->updateOrCreate(['prodi_id' => $programStudi->id], $this->validasi($request, $programStudi->id));

        return to_route('admin.syarat-ujian.index', ['prodi' => $programStudi->id])
            ->with('success', "Syarat ujian & remedial {$programStudi->nama_prodi} disimpan.");
    }

    public function destroy(ProgramStudi $programStudi): RedirectResponse
    {
        SyaratUjianProdi::query()->where('prodi_id', $programStudi->id)->delete();

        return to_route('admin.syarat-ujian.index', ['prodi' => $programStudi->id])
            ->with('success', "Pengaturan {$programStudi->nama_prodi} dihapus; prodi ini kembali memakai pengaturan umum.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?int $prodiId): array
    {
        $data = $request->validate([
            'syarat_ujian_aktif' => ['required', 'boolean'],
            'min_kehadiran_ujian' => ['required', 'integer', 'min:0', 'max:100'],
            'izin_sakit_dihitung_hadir' => ['required', 'boolean'],
            'huruf_maks_remidi' => ['nullable', Rule::in(SkalaNilai::huruf($prodiId))],
        ], attributes: [
            'syarat_ujian_aktif' => 'Syarat kehadiran ujian',
            'min_kehadiran_ujian' => 'Minimal kehadiran',
            'izin_sakit_dihitung_hadir' => 'Izin & sakit dihitung hadir',
            'huruf_maks_remidi' => 'Huruf maksimal setelah remidi',
        ]);
        $data['huruf_maks_remidi'] ??= null;

        return $data;
    }
}
