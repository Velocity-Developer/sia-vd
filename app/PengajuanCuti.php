<?php

namespace App;

use App\Models\MahasiswaProfile;
use App\Models\PengajuanAkademik;
use App\Models\PengaturanAkademik;
use App\Models\TahunAkademik;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aturan pengajuan cuti dan aktif kembali (disimpan sebagai PengajuanAkademik jenis cuti/aktif_kembali).
 *
 * Mahasiswa Aktif memilih semester yang ingin dicutikan di antara tahun akademik yang periode pengajuan cutinya
 * sedang dibuka. Setelah disetujui admin, status menjadi Cuti: langsung bila semester itu sedang aktif, atau saat
 * semester itu diaktifkan. Status kembali Aktif hanya lewat pengajuan aktif kembali yang disetujui admin.
 */
class PengajuanCuti
{
    /**
     * Tahun akademik yang bisa dipilih: periode cutinya dibuka, semesternya belum berakhir, dan belum
     * disetujui cutinya untuk mahasiswa ini.
     *
     * @return Collection<int, TahunAkademik>
     */
    public static function tahunDibuka(?int $mahasiswaId = null): Collection
    {
        $sudahCuti = $mahasiswaId === null ? [] : self::disetujui($mahasiswaId)->pluck('isian.tahun_akademik_id')->all();

        return TahunAkademik::query()->cutiDibuka()->whereDate('tanggal_akhir', '>=', Carbon::today())
            ->orderBy('tanggal_mulai')->get()
            ->reject(fn (TahunAkademik $tahun): bool => in_array($tahun->id, $sudahCuti))
            ->values();
    }

    /**
     * @return Collection<int, PengajuanAkademik>
     */
    public static function disetujui(int $mahasiswaId): Collection
    {
        return PengajuanAkademik::query()->where('mahasiswa_id', $mahasiswaId)->where('jenis', PengajuanAkademik::CUTI)
            ->where('status', PengajuanAkademik::DISETUJUI)->get();
    }

    public static function alasanTidakBolehCuti(MahasiswaProfile $mahasiswa): ?string
    {
        $maks = PengaturanAkademik::current()->maks_cuti;
        $jumlah = self::disetujui($mahasiswa->id)->count();

        return match (true) {
            ! $mahasiswa->isAktif() => 'Hanya mahasiswa berstatus Aktif atau Pindahan yang dapat mengajukan cuti (status Anda: '.($mahasiswa->status ?? 'belum diisi').').',
            $jumlah >= $maks => "Anda sudah mengambil cuti {$jumlah} semester, batas cuti selama studi {$maks} semester.",
            self::tahunDibuka($mahasiswa->id)->isEmpty() => 'Periode pengajuan cuti sedang tidak dibuka.',
            default => null,
        };
    }

    public static function alasanTidakBolehAktifKembali(MahasiswaProfile $mahasiswa): ?string
    {
        return $mahasiswa->status === 'Cuti' ? null : 'Pengajuan aktif kembali hanya untuk mahasiswa berstatus Cuti.';
    }

    /**
     * Terapkan cuti yang disetujui: status Cuti bila semester tujuannya sedang aktif.
     *
     * @return bool status sudah diubah sekarang
     */
    public static function terapkanPengajuan(PengajuanAkademik $pengajuan): bool
    {
        $tahun = TahunAkademik::query()->find($pengajuan->isian['tahun_akademik_id'] ?? 0);
        if (! $tahun?->status) {
            return false;
        }

        return MahasiswaProfile::query()->whereKey($pengajuan->mahasiswa_id)->aktif()->update(['status' => 'Cuti']) > 0;
    }

    /**
     * Semester baru diaktifkan: mahasiswa Aktif yang cutinya disetujui untuk semester itu menjadi Cuti.
     */
    public static function terapkan(TahunAkademik $tahun): int
    {
        $mahasiswaIds = PengajuanAkademik::query()->where('jenis', PengajuanAkademik::CUTI)->where('status', PengajuanAkademik::DISETUJUI)
            ->get(['mahasiswa_id', 'isian'])
            ->filter(fn (PengajuanAkademik $p): bool => (int) ($p->isian['tahun_akademik_id'] ?? 0) === $tahun->id)
            ->pluck('mahasiswa_id');

        return $mahasiswaIds->isEmpty() ? 0
            : MahasiswaProfile::query()->whereKey($mahasiswaIds)->aktif()->update(['status' => 'Cuti']);
    }
}
