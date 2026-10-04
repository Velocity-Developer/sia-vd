<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\InformasiPmb;
use App\Models\PengaturanPmb;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman publik Informasi PMB (tanpa login): isi dari admin ditambah jadwal periode yang sedang dibuka.
 */
class InformasiController extends Controller
{
    public function __invoke(): Response
    {
        $informasi = InformasiPmb::current();
        $periode = PengaturanPmb::aktif();

        return Inertia::render('Pmb/Informasi', [
            'judul' => $informasi->judul,
            'pengantar' => $informasi->pengantar,
            'bagian' => collect(InformasiPmb::BAGIAN)
                ->map(fn (string $judul, string $kolom): array => ['judul' => $judul, 'isi' => $informasi->{$kolom}])
                ->filter(fn (array $b): bool => filled($b['isi']))
                ->values(),
            'periode' => $periode ? [
                ...$periode->only(['kode', 'tahun_angkatan', 'tanggal_buka', 'tanggal_tutup', 'tanggal_usm_mulai', 'tanggal_usm_selesai', 'tanggal_her', 'biaya_pendaftaran', 'tanggal_pembayaran_mulai', 'tanggal_pembayaran_selesai']),
                'penuh' => $periode->pendaftar()->count() >= $periode->kapasitas,
            ] : null,
        ]);
    }
}
