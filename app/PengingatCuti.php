<?php

namespace App;

use App\Models\MahasiswaProfile;
use App\Models\PengajuanAkademik;
use App\Models\TahunAkademik;
use Illuminate\Support\Collection;

/**
 * Pengingat cuti dan aktif kembali untuk Beranda.
 *
 * Status Cuti tidak berakhir sendiri: mahasiswa tetap Cuti sampai pengajuan aktif kembali disetujui. Karena itu
 * mahasiswa (dan admin) diingatkan bila semester yang sedang aktif bukan semester cuti yang disetujui.
 */
class PengingatCuti
{
    /** Keputusan admin (ditolak/disetujui) masih ditampilkan selama sekian hari sesudah diproses. */
    private const HARI_TAMPIL_KEPUTUSAN = 14;

    /**
     * @return array{pesan: list<array{teks: string, penting: bool}>, tautan: string}|null
     */
    public static function untukMahasiswa(MahasiswaProfile $mahasiswa): ?array
    {
        $namaTahun = TahunAkademik::query()->get()->keyBy('id');
        $semester = fn (?PengajuanAkademik $p): string => ($t = $namaTahun->get($p?->isian['tahun_akademik_id'] ?? 0)) ? ' semester '.$t->label() : '';
        $pesan = [];
        $cutiBerakhir = self::cutiBerakhir($mahasiswa);

        $cuti = PengajuanAkademik::terakhir($mahasiswa->id, PengajuanAkademik::CUTI);
        $pesan[] = match (true) {
            $cuti?->sedangDiproses() === true => ['teks' => 'Pengajuan cuti'.$semester($cuti).' menunggu keputusan admin.', 'penting' => false],
            $cuti?->status === PengajuanAkademik::PERLU_PERBAIKAN => ['teks' => 'Pengajuan cuti'.$semester($cuti).' diminta perbaikan: '.$cuti->catatan, 'penting' => true],
            $cuti?->status === PengajuanAkademik::DITOLAK && self::baruDiproses($cuti) => ['teks' => 'Pengajuan cuti'.$semester($cuti).' ditolak'.($cuti->catatan ? ': '.$cuti->catatan : '.'), 'penting' => true],
            $cuti?->status === PengajuanAkademik::DISETUJUI && ! $cutiBerakhir => self::pesanCutiDisetujui($mahasiswa, $namaTahun->get($cuti->isian['tahun_akademik_id'] ?? 0)),
            default => null,
        };

        $aktifKembali = PengajuanAkademik::terakhir($mahasiswa->id, PengajuanAkademik::AKTIF_KEMBALI);
        $pesan[] = match (true) {
            $aktifKembali?->sedangDiproses() === true => ['teks' => 'Pengajuan aktif kembali menunggu keputusan admin.', 'penting' => false],
            $aktifKembali?->status === PengajuanAkademik::PERLU_PERBAIKAN => ['teks' => 'Pengajuan aktif kembali diminta perbaikan: '.$aktifKembali->catatan, 'penting' => true],
            $aktifKembali?->status === PengajuanAkademik::DITOLAK && self::baruDiproses($aktifKembali) => ['teks' => 'Pengajuan aktif kembali ditolak'.($aktifKembali->catatan ? ': '.$aktifKembali->catatan : '.'), 'penting' => true],
            $aktifKembali?->status === PengajuanAkademik::DISETUJUI && self::baruDiproses($aktifKembali) && $mahasiswa->status === 'Aktif' => ['teks' => 'Pengajuan aktif kembali disetujui. Status Anda kembali Aktif.', 'penting' => false],
            default => null,
        };

        // Semester cuti sudah lewat tetapi belum mengajukan aktif kembali (pengajuan yang sedang diproses sudah diingatkan di atas).
        if ($cutiBerakhir && ! in_array($aktifKembali?->status, [...PengajuanAkademik::SEDANG_DIPROSES, PengajuanAkademik::PERLU_PERBAIKAN], true)) {
            $terakhir = $namaTahun->only(self::semesterCuti($mahasiswa->id)->all())->sortByDesc('tanggal_mulai')->first();
            $pesan[] = ['teks' => ($terakhir ? 'Semester cuti Anda ('.$terakhir->label().') sudah berakhir' : 'Status Anda masih Cuti')
                .'. Ajukan aktif kembali agar dapat mengisi KRS dan mengikuti perkuliahan.', 'penting' => true];
        }

        $pesan = array_values(array_filter($pesan));

        return $pesan === [] ? null : ['pesan' => $pesan, 'tautan' => route('mahasiswa.pengajuan-cuti')];
    }

    /**
     * Mahasiswa berstatus Cuti yang semester cutinya sudah berakhir: ada semester aktif dan itu bukan semester cuti
     * yang disetujui, atau tidak ada semester aktif dan semua semester cutinya sudah lewat.
     */
    public static function cutiBerakhir(MahasiswaProfile $mahasiswa): bool
    {
        return $mahasiswa->status === 'Cuti' && self::semesterCutiSudahLewat(self::semesterCuti($mahasiswa->id), TahunAkademik::aktif());
    }

    /**
     * Jumlah mahasiswa Cuti yang semester cutinya sudah berakhir dan belum mengajukan aktif kembali (untuk dashboard admin).
     */
    public static function jumlahCutiBerakhir(): int
    {
        $aktif = TahunAkademik::aktif();
        $mahasiswaIds = MahasiswaProfile::query()->where('status', 'Cuti')->whereHas('user')->pluck('id');
        if ($mahasiswaIds->isEmpty()) {
            return 0;
        }

        $semesterCuti = PengajuanAkademik::query()->whereIn('mahasiswa_id', $mahasiswaIds)->where('jenis', PengajuanAkademik::CUTI)
            ->where('status', PengajuanAkademik::DISETUJUI)->get(['mahasiswa_id', 'isian'])
            ->groupBy('mahasiswa_id')->map(fn (Collection $p): Collection => $p->map(fn (PengajuanAkademik $x): int => (int) ($x->isian['tahun_akademik_id'] ?? 0)));
        $sedangAjukan = PengajuanAkademik::query()->whereIn('mahasiswa_id', $mahasiswaIds)->where('jenis', PengajuanAkademik::AKTIF_KEMBALI)
            ->whereIn('status', [...PengajuanAkademik::SEDANG_DIPROSES, PengajuanAkademik::PERLU_PERBAIKAN])->pluck('mahasiswa_id')->flip();

        return $mahasiswaIds->reject(fn (int $id): bool => $sedangAjukan->has($id))
            ->filter(fn (int $id): bool => self::semesterCutiSudahLewat($semesterCuti->get($id, collect()), $aktif))
            ->count();
    }

    /**
     * Id tahun akademik yang cutinya disetujui.
     *
     * @return Collection<int, int>
     */
    private static function semesterCuti(int $mahasiswaId): Collection
    {
        return PengajuanCuti::disetujui($mahasiswaId)->map(fn (PengajuanAkademik $p): int => (int) ($p->isian['tahun_akademik_id'] ?? 0));
    }

    /**
     * Status Cuti yang diubah manual (tanpa pengajuan) juga dianggap berakhir begitu ada semester aktif.
     *
     * @param  Collection<int, int>  $semesterCuti
     */
    private static function semesterCutiSudahLewat(Collection $semesterCuti, ?TahunAkademik $aktif): bool
    {
        if ($aktif !== null) {
            return ! $semesterCuti->contains($aktif->id);
        }

        return $semesterCuti->isNotEmpty() && TahunAkademik::query()->whereKey($semesterCuti->all())
            ->whereDate('tanggal_akhir', '>=', today())->doesntExist();
    }

    /**
     * @return array{teks: string, penting: bool}|null
     */
    private static function pesanCutiDisetujui(MahasiswaProfile $mahasiswa, ?TahunAkademik $tahun): ?array
    {
        if ($tahun === null || $tahun->tanggal_akhir?->lt(today())) {
            return null;
        }

        return $tahun->status && $mahasiswa->status === 'Cuti'
            ? ['teks' => 'Anda sedang cuti pada semester '.$tahun->label().' (sampai '.$tahun->tanggal_akhir?->translatedFormat('d F Y').').', 'penting' => false]
            : ['teks' => 'Cuti semester '.$tahun->label().' disetujui. Status Anda menjadi Cuti saat semester itu dimulai.', 'penting' => false];
    }

    private static function baruDiproses(PengajuanAkademik $pengajuan): bool
    {
        return $pengajuan->diproses_at?->gte(now()->subDays(self::HARI_TAMPIL_KEPUTUSAN)) === true;
    }
}
