<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\KartuStudiTetap;
use App\KartuUjian;
use App\Models\MahasiswaProfile;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu mahasiswa Cetak KST dan Cetak Kartu Ujian (UTS & UAS): menampilkan apakah kartu sudah bisa dicetak beserta
 * alasannya, lalu mengunduh lewat rute yang sudah ada (mahasiswa.krs.kst dan mahasiswa.ujian.kartu).
 */
class CetakKartuController extends Controller
{
    public function kst(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        [$tahun, $opsi] = $this->tahun($request, $mahasiswa);

        return Inertia::render('Mahasiswa/CetakKst', [
            'tahunAkademikOptions' => $opsi,
            'tahunAkademikId' => $tahun?->id,
            'alasan' => $tahun === null ? 'Belum ada tahun akademik.' : KartuStudiTetap::alasanTidakBisa($mahasiswa, $tahun),
        ]);
    }

    public function kartuUjian(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        [$tahun, $opsi] = $this->tahun($request, $mahasiswa);

        return Inertia::render('Mahasiswa/CetakKartuUjian', [
            'tahunAkademikOptions' => $opsi,
            'tahunAkademikId' => $tahun?->id,
            'kartu' => collect(Ujian::JENIS)->map(fn (string $jenis): array => [
                'jenis' => $jenis,
                'label' => 'Kartu '.strtoupper($jenis),
                'alasan' => $tahun === null ? 'Belum ada tahun akademik.' : KartuUjian::alasanTidakBisa($mahasiswa, $tahun, $jenis),
            ])->all(),
        ]);
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }

    /**
     * Tahun akademik yang dipilih (bawaan: yang aktif) dan pilihan tahun: tahun aktif ditambah tahun yang pernah ada KRS-nya.
     *
     * @return array{0: ?TahunAkademik, 1: Collection<int, array{id: int, name: string}>}
     */
    private function tahun(Request $request, MahasiswaProfile $mahasiswa): array
    {
        $tahun = TahunAkademik::query()
            ->where(fn ($q) => $q->where('status', true)
                ->orWhereHas('kelasKuliahs.krs', fn ($k) => $k->where('mahasiswa_id', $mahasiswa->id)))
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'tahun', 'semester', 'status']);
        $terpilih = $tahun->firstWhere('id', $request->integer('tahun_akademik_id')) ?? $tahun->firstWhere('status', true) ?? $tahun->first();

        return [$terpilih, $tahun->map(fn (TahunAkademik $ta): array => ['id' => $ta->id, 'name' => $ta->label()])->values()];
    }
}
