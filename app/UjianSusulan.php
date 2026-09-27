<?php

namespace App;

use App\Models\KelasKuliah;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanSusulan;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\QuizAttempt;
use App\Models\TagihanSusulan;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aturan ujian susulan UTS/UAS: siapa yang dianggap mengikuti ujian utama dan kapan pengajuan boleh dikirim.
 */
class UjianSusulan
{
    /**
     * Mahasiswa yang mengikuti ujian utama. Lembar soal: sudah memulai; unggah berkas: mengumpulkan;
     * tatap muka: tercatat hadir/terlambat di pertemuan UTS/UAS atau nilai ujiannya sudah diisi.
     *
     * @return Collection<int, int> id mahasiswa
     */
    public static function pesertaUjianUtama(Ujian $ujian): Collection
    {
        $peserta = match ($ujian->mode) {
            Ujian::ONLINE_SOAL => QuizAttempt::query()->whereHas('quiz', fn ($q) => $q->where('ujian_id', $ujian->id))->pluck('mahasiswa_id'),
            Ujian::ONLINE_BERKAS => UjianJawaban::query()->where('ujian_id', $ujian->id)->whereNotNull('berkas')->pluck('mahasiswa_id'),
            default => UjianJawaban::query()->where('ujian_id', $ujian->id)->whereNotNull('nilai')->pluck('mahasiswa_id')
                ->merge(PresensiMahasiswa::query()
                    ->whereIn('pertemuan_id', Pertemuan::query()->where('kelas_id', $ujian->kelas_id)->where('jenis', $ujian->jenis)->select('id'))
                    ->whereIn('status', PresensiMahasiswa::DIHITUNG_HADIR)
                    ->pluck('mahasiswa_id')),
        };

        return $peserta->unique()->values();
    }

    public static function ikutUjianUtama(Ujian $ujian, int $mahasiswaId): bool
    {
        return self::pesertaUjianUtama($ujian)->contains($mahasiswaId);
    }

    /**
     * Pengajuan dibuka sejak jadwal ujian terbit sampai akhir hari ke-N sesudah tanggal ujian.
     */
    public static function batasPengajuan(Ujian $ujian): Carbon
    {
        return $ujian->tanggal->copy()->addDays(PengaturanAkademik::current()->batas_pengajuan_susulan_hari)->endOfDay();
    }

    /**
     * Alasan pengajuan susulan ditolak, atau null bila boleh.
     */
    public static function alasanTidakBolehAjukan(Ujian $ujian, MahasiswaProfile $mahasiswa): ?string
    {
        return match (true) {
            ! in_array($ujian->jenis, Ujian::JENIS, true) || $ujian->status !== Ujian::TERBIT => 'Susulan hanya untuk UTS/UAS yang jadwalnya sudah terbit.',
            ! $ujian->kelasKuliah->krs()->where('mahasiswa_id', $mahasiswa->id)->exists() => 'Anda bukan peserta kelas ini.',
            now()->gt(self::batasPengajuan($ujian)) => 'Batas pengajuan ujian susulan sudah lewat.',
            PengajuanSusulan::query()->where('ujian_id', $ujian->id)->where('mahasiswa_id', $mahasiswa->id)->aktif()->exists() => 'Anda sudah punya pengajuan susulan untuk ujian ini.',
            self::ikutUjianUtama($ujian, $mahasiswa->id) => 'Anda sudah mengikuti ujian ini.',
            default => null,
        };
    }

    /**
     * Peserta ujian susulan: pengajuan disetujui untuk ujian utamanya, tagihan lunas, dan tidak ikut ujian utama.
     *
     * @return Collection<int, int> id mahasiswa
     */
    public static function pesertaSusulan(Ujian $susulan): Collection
    {
        $utama = $susulan->ujianUtama();

        if ($utama === null) {
            return collect();
        }

        return self::pemohonLunas($utama)->diff(self::pesertaUjianUtama($utama))->values();
    }

    /**
     * Pemohon susulan yang disetujui dan tagihannya lunas untuk satu ujian utama.
     *
     * @return Collection<int, int> id mahasiswa
     */
    public static function pemohonLunas(Ujian $utama): Collection
    {
        return PengajuanSusulan::query()
            ->where('ujian_id', $utama->id)
            ->where('status', PengajuanSusulan::DISETUJUI)
            ->whereHas('tagihan', fn ($q) => $q->where('status', TagihanSusulan::LUNAS))
            ->pluck('mahasiswa_id');
    }

    /**
     * Ujian utama terbit (UTS/UAS) di tahun akademik ini yang punya pemohon lunas tetapi belum punya jadwal susulan.
     *
     * @return Collection<int, Ujian>
     */
    public static function ujianUtamaSiapSusulan(?int $tahunAkademikId, ?string $jenis = null): Collection
    {
        return Ujian::query()
            ->whereIn('jenis', $jenis ? [$jenis] : Ujian::JENIS)
            ->terbit()
            ->whereHas('kelasKuliah', fn ($q) => $q->where('tahun_akademik_id', $tahunAkademikId))
            ->whereHas('pengajuanSusulan', fn ($q) => $q->where('status', PengajuanSusulan::DISETUJUI)->whereHas('tagihan', fn ($t) => $t->where('status', TagihanSusulan::LUNAS)))
            ->with(['kelasKuliah:id,kode_kelas,matkul_id,tahun_akademik_id', 'kelasKuliah.mataKuliah:id,nama_matkul'])
            ->get()
            ->filter(fn (Ujian $u): bool => ! Ujian::query()->where('kelas_id', $u->kelas_id)->where('jenis', Ujian::jenisSusulanUntuk($u->jenis))->exists()
                && self::pemohonLunas($u)->diff(self::pesertaUjianUtama($u))->isNotEmpty())
            ->values();
    }

    /**
     * Pemohon UAS susulan kelas ini yang susulannya masih berjalan, sehingga nilai kelas belum boleh difinalisasi:
     * pengajuan disetujui dan tidak ikut UAS utama, lalu tagihannya belum terbit, belum lunas tetapi belum lewat
     * batas bayar, atau sudah lunas tetapi UAS susulannya belum dijadwalkan/belum selesai. Yang gugur tidak dihitung.
     *
     * @return Collection<int, int> id mahasiswa
     */
    public static function uasSusulanTertunda(KelasKuliah $kelas): Collection
    {
        $uas = $kelas->ujianTerbit(Pertemuan::UAS);

        if ($uas === null) {
            return collect();
        }

        $ikutUtama = self::pesertaUjianUtama($uas)->flip();
        $susulan = $kelas->ujianTerbit(Ujian::UAS_SUSULAN);
        $susulanSelesai = $susulan !== null && $susulan->sudahSelesai();

        return PengajuanSusulan::query()
            ->where('ujian_id', $uas->id)
            ->where('status', PengajuanSusulan::DISETUJUI)
            ->with('tagihan')
            ->get()
            ->reject(fn (PengajuanSusulan $p): bool => $ikutUtama->has($p->mahasiswa_id))
            ->filter(function (PengajuanSusulan $p) use ($susulanSelesai): bool {
                $tagihan = $p->tagihan;

                return match (true) {
                    $tagihan === null => true,
                    $tagihan->status === TagihanSusulan::LUNAS => ! $susulanSelesai,
                    default => ! $tagihan->gugur(),
                };
            })
            ->pluck('mahasiswa_id')
            ->values();
    }
}
