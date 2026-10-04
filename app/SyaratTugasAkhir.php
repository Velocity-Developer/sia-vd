<?php

namespace App;

use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\PeriodeWisuda;
use App\Models\TugasAkhir;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Daftar syarat tiap tahap tugas akhir. Form pengajuan hanya aktif bila semua syarat terpenuhi;
 * bukti bayar diunggah di form, jadi tidak termasuk syarat.
 */
class SyaratTugasAkhir
{
    /**
     * @return list<array{label: string, terpenuhi: bool, keterangan: ?string}>
     */
    public static function pengajuanTa(MahasiswaProfile $mahasiswa): array
    {
        $matkul = self::matkulTaDiambil($mahasiswa);

        return [
            [
                'label' => 'Mengambil mata kuliah TA/Skripsi di semester aktif',
                'terpenuhi' => $matkul !== null,
                'keterangan' => $matkul?->nama_matkul ?? 'Mata kuliah TA/Skripsi belum ada di KRS semester ini.',
            ],
        ];
    }

    /**
     * Syarat mendaftar pendadaran. Mata kuliah TA/Skripsi tidak ikut dihitung SKS-nya dan boleh belum dinilai.
     *
     * @return list<array{label: string, terpenuhi: bool, keterangan: ?string}>
     */
    public static function pendadaran(MahasiswaProfile $mahasiswa): array
    {
        $tugasAkhir = TugasAkhir::milik($mahasiswa->id);
        $matkul = self::matkulTaDiambil($mahasiswa);

        return [
            [
                'label' => 'Tugas akhir sudah disahkan',
                'terpenuhi' => $tugasAkhir?->status === TugasAkhir::BERJALAN,
                'keterangan' => $tugasAkhir === null ? 'Ajukan tugas akhir terlebih dahulu.' : null,
            ],
            [
                'label' => 'Mengambil mata kuliah TA/Skripsi di semester aktif',
                'terpenuhi' => $matkul !== null,
                'keterangan' => $matkul?->nama_matkul ?? 'Mata kuliah TA/Skripsi belum ada di KRS semester ini.',
            ],
            ...self::nilai($mahasiswa, false),
        ];
    }

    /**
     * Syarat mendaftar wisuda (selain surat bebas pustaka dan surat keterangan lunas yang diunggah di form). Kali ini nilai TA/Skripsi juga harus ada.
     *
     * @return list<array{label: string, terpenuhi: bool, keterangan: ?string}>
     */
    public static function wisuda(MahasiswaProfile $mahasiswa): array
    {
        $tugasAkhir = TugasAkhir::milik($mahasiswa->id);
        $periode = PeriodeWisuda::query()->dibuka()->orderBy('tanggal_acara')->get()->filter(fn (PeriodeWisuda $p): bool => $p->bisaDidaftar());

        return [
            [
                'label' => Feature::aktif('pendadaran') ? 'Lulus pendadaran (termasuk revisi yang sudah disahkan)' : 'Nilai TA/Skripsi lulus',
                'terpenuhi' => $tugasAkhir?->status === TugasAkhir::SELESAI,
                'keterangan' => null,
            ],
            ...self::nilai($mahasiswa, true),
            // Transkrip dan SKL hanya memakai nilai yang sudah divalidasi.
            (function () use ($mahasiswa): array {
                $belum = Krs::query()->where('mahasiswa_id', $mahasiswa->id)->whereNotNull('nilai')->whereNull('nilai_divalidasi_at')->count();

                return [
                    'label' => 'Semua nilai sudah divalidasi',
                    'terpenuhi' => $belum === 0,
                    'keterangan' => $belum > 0 ? "{$belum} nilai mata kuliah masih menunggu validasi admin/prodi." : null,
                ];
            })(),
            // Naskah final = naskah TA yang diunggah di Pengajuan Judul & Upload TA (tidak diunggah ulang di form wisuda).
            [
                'label' => 'Naskah TA sudah diunggah',
                'terpenuhi' => $tugasAkhir?->naskah !== null,
                'keterangan' => $tugasAkhir?->naskah === null ? 'Unggah di menu Pengajuan Judul & Upload TA.' : null,
            ],
            [
                'label' => 'Ada periode wisuda yang dibuka dan kuotanya tersisa',
                'terpenuhi' => $periode->isNotEmpty(),
                'keterangan' => $periode->isEmpty() ? 'Belum ada periode wisuda yang menerima pendaftaran.' : $periode->pluck('nama')->join(', '),
            ],
        ];
    }

    /**
     * Syarat nilai dari transkrip: SKS minimal (di luar TA), tidak ada nilai tidak lulus, dan semua mata kuliah sudah dinilai.
     * Mata kuliah TA/Skripsi boleh belum dinilai kecuali $termasukTa.
     *
     * @return list<array{label: string, terpenuhi: bool, keterangan: ?string}>
     */
    public static function nilai(MahasiswaProfile $mahasiswa, bool $termasukTa): array
    {
        $mahasiswa->loadMissing('prodi');
        $minSks = $mahasiswa->minSksPendadaran();
        $diakui = $mahasiswa->sksDiakui();
        $semua = Transkrip::krs($mahasiswa->id);
        $tanpaTa = $semua->reject(fn (Krs $k): bool => (bool) $k->kelasKuliah?->mataKuliah?->tugas_akhir);
        $dicek = $termasukTa ? $semua : $tanpaTa;

        $sks = Transkrip::terbaik($tanpaTa)->sum(fn (Krs $k): int => $k->kelasKuliah->mataKuliah->sks) + $diakui;
        // Huruf tidak lulus mengikuti skala nilai prodi mata kuliahnya (Bobot Nilai), bukan selalu E.
        $tidakLulus = Transkrip::terbaik($dicek)->reject(fn (Krs $k): bool => $k->nilaiLulus());
        $belumDinilai = Transkrip::belumDinilai($dicek);
        $nama = fn ($daftar): string => $daftar->map(fn (Krs $k): string => $k->kelasKuliah->mataKuliah->nama_matkul)->sort()->join(', ');

        return [
            [
                'label' => "Menempuh minimal {$minSks} SKS (di luar TA/Skripsi)",
                'terpenuhi' => $sks >= $minSks,
                'keterangan' => "Sudah {$sks} SKS bernilai".($diakui > 0 ? " (termasuk {$diakui} SKS diakui)." : '.'),
            ],
            [
                'label' => 'Tidak ada nilai tidak lulus',
                'terpenuhi' => $tidakLulus->isEmpty(),
                'keterangan' => $tidakLulus->isEmpty() ? null : 'Nilai tidak lulus: '.$tidakLulus
                    ->map(fn (Krs $k): string => $k->kelasKuliah->mataKuliah->nama_matkul.' ('.strtoupper((string) $k->nilai).')')->sort()->join(', ').'.',
            ],
            [
                'label' => 'Semua mata kuliah sudah dinilai',
                'terpenuhi' => $belumDinilai->isEmpty(),
                'keterangan' => $belumDinilai->isEmpty() ? null : 'Belum dinilai: '.$nama($belumDinilai).'.',
            ],
        ];
    }

    /**
     * @param  list<array{terpenuhi: bool}>  $syarat
     */
    public static function terpenuhi(array $syarat): bool
    {
        return collect($syarat)->every(fn (array $s): bool => $s['terpenuhi']);
    }

    /**
     * Mata kuliah bertanda TA/Skripsi yang ada di KRS mahasiswa pada tahun akademik aktif.
     */
    public static function matkulTaDiambil(MahasiswaProfile $mahasiswa): ?MataKuliah
    {
        return Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereHas('kelasKuliah', fn (Builder $q) => $q
                ->whereHas('tahunAkademik', fn (Builder $t) => $t->where('status', true))
                ->whereHas('mataKuliah', fn (Builder $m) => $m->where('tugas_akhir', true)))
            ->with('kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul')
            ->first()
            ?->kelasKuliah?->mataKuliah;
    }
}
