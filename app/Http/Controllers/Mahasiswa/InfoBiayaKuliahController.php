<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\JenisBiaya;
use App\Models\MahasiswaProfile;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InfoBiayaKuliahController extends Controller
{
    /**
     * Tagihan semester berjalan beserta riwayat semester sebelumnya milik mahasiswa yang masuk.
     */
    public function index(Request $request): Response
    {
        $mahasiswa = $request->user()->mahasiswaProfile?->loadMissing('prodi');

        $tagihan = TagihanSemester::query()
            ->with(['items', 'tahunAkademik'])
            ->where('mahasiswa_id', $mahasiswa?->id)
            ->get()
            ->sortByDesc(fn (TagihanSemester $item) => [$item->tahunAkademik?->tahun, $item->tahunAkademik?->semester])
            ->values();

        $tahunAktif = TahunAkademik::query()->where('status', true)->first();
        $berjalan = $tagihan->firstWhere('tahun_akademik_id', $tahunAktif?->id);

        return Inertia::render('Mahasiswa/InfoBiayaKuliah', [
            'semesterBerjalan' => $berjalan ? $this->bentuk($berjalan) : null,
            'dasar' => $berjalan && $mahasiswa ? $this->dasarPerhitungan($mahasiswa, $berjalan, $tahunAktif) : null,
            'tahunAktif' => $tahunAktif ? $tahunAktif->tahun.' '.$tahunAktif->semester : null,
            'riwayat' => $tagihan
                ->filter(fn (TagihanSemester $item) => $item->tahun_akademik_id !== $tahunAktif?->id)
                ->map(fn (TagihanSemester $item) => $this->bentuk($item))
                ->values(),
        ]);
    }

    /**
     * Asal-usul angka tagihan: tarif per SKS yang berlaku untuk mahasiswa ini, kuota SKS-nya,
     * dan berapa SKS yang masih perlu diambil agar jatah yang sudah dibayar tidak terbuang.
     *
     * @return array<string, mixed>|null
     */
    private function dasarPerhitungan(MahasiswaProfile $mahasiswa, TagihanSemester $tagihan, ?TahunAkademik $tahunAktif): ?array
    {
        $perSks = $tagihan->items->firstWhere('cara_hitung', JenisBiaya::PER_SKS);

        if ($perSks === null) {
            return null;
        }

        $ips = $mahasiswa->ipsSemesterSebelum($tahunAktif);
        $sksDiambil = $tahunAktif === null ? 0 : TagihanSemester::sksDiambil($mahasiswa->id, $tahunAktif->id);

        return [
            'tarif_per_sks' => $perSks->nominal_satuan,
            'kuota_sks' => $perSks->jumlah,
            'prodi' => $mahasiswa->prodi?->nama_prodi,
            'angkatan' => $mahasiswa->angkatan,
            'ips' => $ips['ips'] ?? null,
            'ips_tahun_akademik' => isset($ips['tahun_akademik'])
                ? $ips['tahun_akademik']->tahun.' '.$ips['tahun_akademik']->semester
                : null,
            'sks_diambil' => $sksDiambil,
            'sisa_sks' => max($perSks->jumlah - $sksDiambil, 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function bentuk(TagihanSemester $tagihan): array
    {
        return [
            'id' => $tagihan->id,
            'tahun_akademik' => $tagihan->tahunAkademik ? $tagihan->tahunAkademik->tahun.' '.$tagihan->tahunAkademik->semester : '-',
            'status' => $tagihan->status,
            'total' => $tagihan->total,
            'tanggal_lunas' => $tagihan->tanggal_lunas?->toDateString(),
            'items' => $tagihan->items->map(fn ($item): array => [
                'nama' => $item->nama,
                'cara_hitung' => $item->cara_hitung,
                'nominal_satuan' => $item->nominal_satuan,
                'jumlah' => $item->jumlah,
                'subtotal' => $item->subtotal,
            ])->all(),
        ];
    }
}
