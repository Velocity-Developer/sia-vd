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
        $pengaturan = PengaturanAkademik::current();
        $pertemuan = $kelas->pertemuans()->with('ruang:id,kode_ruang,nama_ruang')->orderBy('pertemuan_ke')->get();
        $jadwal = [
            'uts' => $pertemuan->firstWhere('jenis', Pertemuan::UTS),
            'uas' => $pertemuan->firstWhere('jenis', Pertemuan::UAS),
        ];

        $pesertaIds = $mahasiswaIds ?? $kelas->krs()->pluck('mahasiswa_id')->all();

        $baris = PresensiMahasiswa::query()
            ->whereIn('pertemuan_id', $pertemuan->where('jenis', Pertemuan::KULIAH)->where('status', Pertemuan::SELESAI)->pluck('id'))
            ->whereIn('mahasiswa_id', $pesertaIds)
            ->get(['pertemuan_id', 'mahasiswa_id', 'status'])
            ->groupBy('mahasiswa_id');

        $nomorPertemuan = $pertemuan->pluck('pertemuan_ke', 'id');

        $dispensasi = DispensasiUjian::query()
            ->where('kelas_id', $kelas->id)
            ->whereIn('mahasiswa_id', $pesertaIds)
            ->with('pemberi:id,name')
            ->get()
            ->groupBy('mahasiswa_id');

        $hitung = function (Collection $presensi, ?Pertemuan $ujian, string $jenis, Collection $dispensasiMahasiswa) use ($nomorPertemuan, $pengaturan): ?array {
            if ($ujian === null) {
                return null;
            }

            // UTS hanya dari pertemuan sebelum UTS; UAS dari semua pertemuan kuliah.
            $dasar = $jenis === Pertemuan::UTS
                ? $presensi->filter(fn ($p): bool => $nomorPertemuan[$p->pertemuan_id] < $ujian->pertemuan_ke)
                : $presensi;
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
            'uts' => $hitung($baris->get($id, collect()), $jadwal['uts'], Pertemuan::UTS, $dispensasi->get($id, collect())),
            'uas' => $hitung($baris->get($id, collect()), $jadwal['uas'], Pertemuan::UAS, $dispensasi->get($id, collect())),
        ]]);

        return [
            'aktif' => $pengaturan->syarat_ujian_aktif,
            'min' => $pengaturan->min_kehadiran_ujian,
            'jadwal' => $jadwal,
            'peserta' => $peserta,
        ];
    }
}
