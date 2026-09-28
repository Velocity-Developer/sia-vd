<?php

namespace App;

use App\Models\MahasiswaProfile;
use App\Models\PengajuanIzin;
use App\Models\Pertemuan;
use App\Models\RiwayatJadwalPertemuan;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Pengingat Beranda mahasiswa: pertemuan mendatang di kelas yang diambil yang baru saja dijadwal ulang.
 * Pengajuan izin tetap menempel ke pertemuan yang dipindah, jadi mahasiswa yang sudah mengajukan izin
 * diberi tahu tanggal barunya.
 */
class PengingatJadwalPertemuan
{
    /** Perubahan jadwal diingatkan selama sekian hari sejak diubah. */
    public const HARI_DIINGATKAN = 14;

    /**
     * @return array{pesan: list<array{teks: string, penting: bool}>, tautan: string}|null
     */
    public static function untukMahasiswa(MahasiswaProfile $mahasiswa): ?array
    {
        $pertemuan = Pertemuan::query()
            ->whereDate('tanggal', '>=', today())
            ->where('status', Pertemuan::DIJADWALKAN)
            ->whereHas('kelasKuliah', fn (Builder $kelas) => $kelas
                ->whereHas('tahunAkademik', fn (Builder $q) => $q->where('status', true))
                ->whereHas('krs', fn (Builder $q) => $q->where('mahasiswa_id', $mahasiswa->id)))
            ->whereHas('riwayatJadwal', fn (Builder $q) => $q->where('created_at', '>=', now()->subDays(self::HARI_DIINGATKAN)))
            ->with(['kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul', 'ruang:id,kode_ruang', 'riwayatJadwal'])
            ->orderBy('tanggal')->orderBy('jam_mulai')
            ->get();

        if ($pertemuan->isEmpty()) {
            return null;
        }

        $izin = PengajuanIzin::query()->where('mahasiswa_id', $mahasiswa->id)->whereIn('pertemuan_id', $pertemuan->pluck('id'))->pluck('pertemuan_id')->flip();

        $pesan = $pertemuan->map(function (Pertemuan $p) use ($izin): array {
            /** @var RiwayatJadwalPertemuan $terakhir */
            $terakhir = $p->riwayatJadwal->first();
            $teks = ($p->kelasKuliah?->mataKuliah?->nama_matkul ?? $p->kelasKuliah?->kode_kelas).' pertemuan ke-'.$p->pertemuan_ke
                .' dipindah dari '.$terakhir->tanggal_lama->translatedFormat('l, d F').' ke '.$p->tanggal->translatedFormat('l, d F Y')
                .' pukul '.substr($p->jam_mulai, 0, 5).($p->ruang ? ' di '.$p->ruang->kode_ruang : '').' ('.$terakhir->alasan.').';

            return $izin->has($p->id)
                ? ['teks' => $teks.' Pengajuan izin Anda tetap berlaku untuk tanggal baru ini.', 'penting' => true]
                : ['teks' => $teks, 'penting' => false];
        })->values()->all();

        return ['pesan' => $pesan, 'tautan' => route('mahasiswa.presensi')];
    }
}
