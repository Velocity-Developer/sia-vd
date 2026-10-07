<?php

namespace App\Http\Controllers;

use App\Feature;
use App\Models\TahunAkademik;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kalender akademik publik, disusun dari tanggal-tanggal Tahun Akademik yang aktif.
 */
class KalenderAkademikController extends Controller
{
    public function __invoke(): Response
    {
        $tahun = TahunAkademik::aktif();

        return Inertia::render('Portal/KalenderAkademik', [
            'tahunAkademik' => $tahun?->label(),
            'kegiatan' => $tahun ? $this->kegiatan($tahun) : [],
        ]);
    }

    /**
     * @return list<array{kegiatan: string, mulai: string, selesai: ?string}>
     */
    private function kegiatan(TahunAkademik $tahun): array
    {
        $baris = [
            ['Pengisian KRS', $tahun->tanggal_krs_awal, $tahun->tanggal_krs_akhir],
            ['Batas revisi KRS', $tahun->tanggal_revisi_krs_akhir, null],
            ['Perkuliahan', $tahun->tanggal_mulai, $tahun->tanggal_akhir],
            ['Pengajuan cuti akademik', $tahun->tanggal_cuti_awal, $tahun->tanggal_cuti_akhir],
            ['Batas input nilai', $tahun->batas_input_nilai, null],
            // Tagihan remidi hanya ada selama fitur keuangan aktif.
            ['Batas pembayaran remidi', Feature::aktif('keuangan') ? $tahun->batas_bayar_remidi : null, null],
            ['Batas input nilai remidi', $tahun->batas_input_nilai_remidi, null],
        ];

        $tanggal = fn ($nilai): ?string => $nilai === null ? null : Carbon::parse($nilai)->toDateString();

        return collect($baris)
            ->filter(fn (array $b): bool => $b[1] !== null)
            ->map(fn (array $b): array => ['kegiatan' => $b[0], 'mulai' => $tanggal($b[1]), 'selesai' => $tanggal($b[2])])
            ->sortBy('mulai')
            ->values()
            ->all();
    }
}
