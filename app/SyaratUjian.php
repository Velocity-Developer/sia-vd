<?php

namespace App;

use App\Models\DispensasiUjian;
use App\Models\KelasKuliah;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use Illuminate\Support\Collection;

/**
 * Syarat kehadiran untuk mengikuti UTS/UAS (tatap muka).
 *
 * UTS memakai pertemuan kuliah sebelum pertemuan UTS; UAS memakai seluruh pertemuan kuliah. Hanya
 * pertemuan yang sudah selesai yang dihitung, jadi sebelum ujian angkanya masih sementara. Mahasiswa
 * yang belum punya pertemuan terhitung, atau yang mendapat dispensasi, dianggap memenuhi syarat.
 */
class SyaratUjian
{
    /**
     * @param  list<int>|null  $mahasiswaIds  batasi ke mahasiswa tertentu (null = semua peserta KRS)
     * @return array{aktif: bool, min: int, jadwal: array{uts: ?Pertemuan, uas: ?Pertemuan}, peserta: Collection<int, array{uts: ?array<string, mixed>, uas: ?array<string, mixed>}>}
     */
    public static function untukKelas(KelasKuliah $kelas, ?array $mahasiswaIds = null): array
    {
        $pertemuan = $kelas->pertemuans()->with('ruang:id,kode_ruang,nama_ruang')->orderBy('pertemuan_ke')->get();
        $pesertaIds = $mahasiswaIds ?? $kelas->krs()->pluck('mahasiswa_id')->all();

        $presensi = PresensiMahasiswa::query()
            ->whereIn('pertemuan_id', self::pertemuanDihitung($pertemuan)->pluck('id'))
            ->whereIn('mahasiswa_id', $pesertaIds)
            ->get(['pertemuan_id', 'mahasiswa_id', 'status'])
            ->groupBy('mahasiswa_id');

        $dispensasi = DispensasiUjian::query()
            ->where('kelas_id', $kelas->id)
            ->whereIn('mahasiswa_id', $pesertaIds)
            ->with('pemberi:id,name')
            ->get()
            ->groupBy('mahasiswa_id');

        return self::hitung($pertemuan, $presensi, $dispensasi, $pesertaIds, PengaturanAkademik::current());
    }

    /**
     * Syarat ujian seorang mahasiswa di banyak kelas sekaligus dengan jumlah query tetap (dipakai halaman
     * Presensi mahasiswa). Pertemuan tiap kelas diberikan pemanggil (sudah dimuat, termasuk relasi ruang).
     *
     * @param  Collection<int, Collection<int, Pertemuan>>  $pertemuanPerKelas  kelas_id => pertemuan kelas itu
     * @return Collection<int, array{aktif: bool, min: int, jadwal: array{uts: ?Pertemuan, uas: ?Pertemuan}, peserta: Collection<int, array<string, mixed>>}>
     */
    public static function untukMahasiswa(int $mahasiswaId, Collection $pertemuanPerKelas, PengaturanAkademik $pengaturan): Collection
    {
        $kelasPertemuan = $pertemuanPerKelas->flatten()->pluck('kelas_id', 'id');

        $presensi = PresensiMahasiswa::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->whereIn('pertemuan_id', $pertemuanPerKelas->flatMap(fn (Collection $p) => self::pertemuanDihitung($p)->pluck('id')))
            ->get(['pertemuan_id', 'mahasiswa_id', 'status'])
            ->groupBy(fn (PresensiMahasiswa $baris) => $kelasPertemuan[$baris->pertemuan_id]);

        $dispensasi = DispensasiUjian::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->whereIn('kelas_id', $pertemuanPerKelas->keys())
            ->with('pemberi:id,name')
            ->get()
            ->groupBy('kelas_id');

        return $pertemuanPerKelas->map(fn (Collection $pertemuan, int $kelasId): array => self::hitung(
            $pertemuan,
            $presensi->get($kelasId, collect())->groupBy('mahasiswa_id'),
            $dispensasi->get($kelasId, collect())->groupBy('mahasiswa_id'),
            [$mahasiswaId],
            $pengaturan,
        ));
    }

    /**
     * @param  Collection<int, Pertemuan>  $pertemuan
     * @return Collection<int, Pertemuan>
     */
    private static function pertemuanDihitung(Collection $pertemuan): Collection
    {
        return $pertemuan->where('jenis', Pertemuan::KULIAH)->where('status', Pertemuan::SELESAI);
    }

    /**
     * @param  Collection<int, Pertemuan>  $pertemuan  seluruh pertemuan satu kelas
     * @param  Collection<int, Collection<int, PresensiMahasiswa>>  $presensi  mahasiswa_id => presensi di pertemuan yang dihitung
     * @param  Collection<int, Collection<int, DispensasiUjian>>  $dispensasi  mahasiswa_id => dispensasi di kelas itu
     * @param  list<int>  $pesertaIds
     * @return array{aktif: bool, min: int, jadwal: array{uts: ?Pertemuan, uas: ?Pertemuan}, peserta: Collection<int, array{uts: ?array<string, mixed>, uas: ?array<string, mixed>}>}
     */
    private static function hitung(Collection $pertemuan, Collection $presensi, Collection $dispensasi, array $pesertaIds, PengaturanAkademik $pengaturan): array
    {
        $jadwal = [
            'uts' => $pertemuan->firstWhere('jenis', Pertemuan::UTS),
            'uas' => $pertemuan->firstWhere('jenis', Pertemuan::UAS),
        ];
        $nomorPertemuan = $pertemuan->pluck('pertemuan_ke', 'id');

        $hitung = function (Collection $presensiMahasiswa, ?Pertemuan $ujian, string $jenis, Collection $dispensasiMahasiswa) use ($nomorPertemuan, $pengaturan): ?array {
            if ($ujian === null) {
                return null;
            }

            // UTS hanya dari pertemuan sebelum UTS; UAS dari semua pertemuan kuliah.
            $dasar = $jenis === Pertemuan::UTS
                ? $presensiMahasiswa->filter(fn ($p): bool => $nomorPertemuan[$p->pertemuan_id] < $ujian->pertemuan_ke)
                : $presensiMahasiswa;
            $hadir = $dasar->whereIn('status', PresensiMahasiswa::DIHITUNG_HADIR)->count();
            $dihitung = $dasar->count();
            $persen = $dihitung > 0 ? round($hadir / $dihitung * 100, 1) : null;
            $disp = $dispensasiMahasiswa->firstWhere('jenis', $jenis);

            return [
                'hadir' => $hadir,
                'dihitung' => $dihitung,
                'persen' => $persen,
                'dispensasi' => $disp ? ['id' => $disp->id, 'alasan' => $disp->alasan, 'oleh' => $disp->pemberi?->name] : null,
                // null = syarat belum diberlakukan (sakelar di Pengaturan Akademik mati).
                'memenuhi' => $pengaturan->syarat_ujian_aktif
                    ? ($disp !== null || $persen === null || $persen >= $pengaturan->min_kehadiran_ujian)
                    : null,
            ];
        };

        $peserta = collect($pesertaIds)->mapWithKeys(fn (int $id): array => [$id => [
            'uts' => $hitung($presensi->get($id, collect()), $jadwal['uts'], Pertemuan::UTS, $dispensasi->get($id, collect())),
            'uas' => $hitung($presensi->get($id, collect()), $jadwal['uas'], Pertemuan::UAS, $dispensasi->get($id, collect())),
        ]]);

        return [
            'aktif' => $pengaturan->syarat_ujian_aktif,
            'min' => $pengaturan->min_kehadiran_ujian,
            'jadwal' => $jadwal,
            'peserta' => $peserta,
        ];
    }
}
