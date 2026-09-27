<?php

namespace App;

use App\Models\MahasiswaProfile;
use App\Models\PengajuanSusulan;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\QuizAttempt;
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
}
