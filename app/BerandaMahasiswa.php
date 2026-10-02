<?php

namespace App;

use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use App\Models\Tugas;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Isi Beranda mahasiswa selain pengingat per fitur: ringkasan studi, pengingat KRS & tagihan semester,
 * kuliah hari ini, dan tugas yang tenggatnya dekat. Semua dihitung untuk tahun akademik aktif.
 */
class BerandaMahasiswa
{
    /** Tugas ditampilkan bila tenggatnya dalam sekian hari ke depan. */
    private const HARI_TUGAS = 7;

    /**
     * @return array{nama: string, nim: string|null, prodi: string|null, angkatan: int|null, status: string|null, dosen_wali: string|null,
     *     ipk: float|null, sks_lulus: int, sks_semester: int, krs_tersimpan: bool}
     */
    public static function ringkasan(MahasiswaProfile $mahasiswa, ?TahunAkademik $tahunAkademik): array
    {
        $mahasiswa->loadMissing(['user:id,name', 'prodi:id,jenjang,nama_prodi', 'dosenWali.user:id,name']);
        $transkrip = Transkrip::ringkasan($mahasiswa->id);

        return [
            'nama' => $mahasiswa->user->name,
            'nim' => $mahasiswa->nim,
            'prodi' => $mahasiswa->prodi ? trim($mahasiswa->prodi->jenjang.' '.$mahasiswa->prodi->nama_prodi) : null,
            'angkatan' => $mahasiswa->angkatan,
            'status' => $mahasiswa->status,
            'dosen_wali' => $mahasiswa->dosenWali?->user?->name,
            'ipk' => $transkrip['ipk'],
            // SKS diakui (pindahan) ikut dihitung; transkrip & IPK tetap hanya dari mata kuliah di sini.
            'sks_lulus' => $transkrip['sks_lulus'] + $mahasiswa->sksDiakui(),
            'sks_semester' => $tahunAkademik === null ? 0 : (int) self::krsSemester($mahasiswa, $tahunAkademik)
                ->sum(fn (Krs $krs): int => $krs->kelasKuliah?->mataKuliah?->sks ?? 0),
            'krs_tersimpan' => $tahunAkademik !== null && KrsSemester::tersimpan($mahasiswa->id, $tahunAkademik->id),
        ];
    }

    /**
     * Pengingat KRS dan tagihan semester aktif.
     *
     * @return array{pesan: list<array{teks: string, penting: bool}>, tautan: string}|null
     */
    public static function pengingatSemester(MahasiswaProfile $mahasiswa, ?TahunAkademik $tahunAkademik, bool $lihatKrs, bool $lihatTagihan): ?array
    {
        if ($tahunAkademik === null || ! $mahasiswa->isAktif()) {
            return null;
        }

        $pesan = [];
        $tautan = null;
        $tagihan = TagihanSemester::query()->where('mahasiswa_id', $mahasiswa->id)->where('tahun_akademik_id', $tahunAkademik->id)->first();
        $krsTerkunci = PengaturanAkademik::current()->kunciKrsBerlaku() && $tagihan !== null && ! $tagihan->lunas();

        if ($lihatTagihan && $tagihan !== null) {
            $rupiah = 'Rp '.number_format($tagihan->total, 0, ',', '.');
            $teks = match ($tagihan->status) {
                TagihanSemester::BELUM_BAYAR => "Tagihan semester {$tahunAkademik->label()} sebesar {$rupiah} belum dibayar. Unggah bukti bayar di Biaya Kuliah.",
                TagihanSemester::DITOLAK => 'Bukti bayar tagihan semester ditolak'.($tagihan->alasan_tolak ? ': '.$tagihan->alasan_tolak : '').'. Unggah ulang bukti bayar.',
                TagihanSemester::MENUNGGU => 'Bukti bayar tagihan semester sedang diverifikasi admin.',
                default => null,
            };
            if ($teks !== null) {
                $pesan[] = ['teks' => $teks, 'penting' => $tagihan->status !== TagihanSemester::MENUNGGU];
                $tautan = route('mahasiswa.info-biaya-kuliah');
            }
        }

        $kunci = $lihatKrs ? KrsSemester::untuk($mahasiswa->id, $tahunAkademik->id)?->setRelation('tahunAkademik', $tahunAkademik) : null;

        if ($lihatKrs && $kunci === null && $tahunAkademik->periodeKrsAktif()) {
            $batas = Carbon::parse($tahunAkademik->tanggal_krs_akhir)->translatedFormat('d F Y');
            $pesan[] = $krsTerkunci
                ? ['teks' => "Masa KRS berakhir {$batas}. KRS baru bisa diisi setelah tagihan semester lunas.", 'penting' => true]
                : ['teks' => "Masa KRS dibuka sampai {$batas}. KRS Anda belum disimpan.", 'penting' => true];
            $tautan ??= route('mahasiswa.krs');
        } elseif ($kunci?->bisaDirevisi()) {
            $batas = Carbon::parse($kunci->ringkasan()['batas_revisi'])->translatedFormat('d F Y');
            $pesan[] = ['teks' => 'KRS Anda perlu direvisi'.($kunci->catatan_revisi ? ': '.$kunci->catatan_revisi : '').'. Perbaiki dan '.(KrsSemester::verifikasiAktif() ? 'ajukan' : 'simpan')." lagi paling lambat {$batas}.", 'penting' => true];
            $tautan ??= route('mahasiswa.krs');
        } elseif ($kunci?->status === KrsSemester::DIAJUKAN) {
            $pesan[] = ['teks' => 'KRS Anda sedang menunggu verifikasi admin.', 'penting' => false];
            $tautan ??= route('mahasiswa.krs');
        }

        return $pesan === [] ? null : ['pesan' => $pesan, 'tautan' => $tautan];
    }

    /**
     * Pertemuan hari ini di kelas yang diambil, beserta presensi mahasiswa bila sudah tercatat.
     *
     * @return list<array{id: int, matkul: string|null, kode_kelas: string|null, pertemuan_ke: int, jenis: string, jam_mulai: string, jam_akhir: string, ruang: string|null, status: string, terlewat: bool, presensi: string|null}>
     */
    public static function kuliahHariIni(MahasiswaProfile $mahasiswa, TahunAkademik $tahunAkademik): array
    {
        $pertemuan = Pertemuan::query()
            ->whereIn('kelas_id', self::krsSemester($mahasiswa, $tahunAkademik)->pluck('kelas_id'))
            ->whereDate('tanggal', today())
            ->with(['kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul', 'ruang:id,kode_ruang'])
            ->orderBy('jam_mulai')
            ->get();
        $presensi = PresensiMahasiswa::query()->where('mahasiswa_id', $mahasiswa->id)->whereIn('pertemuan_id', $pertemuan->pluck('id'))
            ->pluck('status', 'pertemuan_id');

        return $pertemuan->map(fn (Pertemuan $p): array => [
            'id' => $p->id,
            'matkul' => $p->kelasKuliah?->mataKuliah?->nama_matkul,
            'kode_kelas' => $p->kelasKuliah?->kode_kelas,
            'pertemuan_ke' => $p->pertemuan_ke,
            'jenis' => $p->jenis,
            'jam_mulai' => $p->jam_mulai,
            'jam_akhir' => $p->jam_akhir,
            'ruang' => $p->ruang?->kode_ruang,
            'status' => $p->status,
            'terlewat' => $p->terlewat(),
            'presensi' => $presensi[$p->id] ?? null,
        ])->all();
    }

    /**
     * Tugas bertenggat dalam beberapa hari ke depan yang belum dikumpulkan.
     *
     * @return list<array{id: int, judul: string, matkul: string|null, tenggat: string}>
     */
    public static function tugasMendatang(MahasiswaProfile $mahasiswa, TahunAkademik $tahunAkademik): array
    {
        return Tugas::query()
            ->whereIn('kelas_id', self::krsSemester($mahasiswa, $tahunAkademik)->pluck('kelas_id'))
            ->whereBetween('tenggat_waktu', [now(), now()->addDays(self::HARI_TUGAS)->endOfDay()])
            ->whereDoesntHave('pengumpulanTugas', fn ($q) => $q->where('mahasiswa_id', $mahasiswa->id))
            ->with(['kelasKuliah:id,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul'])
            ->orderBy('tenggat_waktu')
            ->limit(6)
            ->get(['id', 'judul_tugas', 'kelas_id', 'tenggat_waktu'])
            ->map(fn (Tugas $t): array => [
                'id' => $t->id,
                'judul' => $t->judul_tugas,
                'matkul' => $t->kelasKuliah?->mataKuliah?->nama_matkul,
                'tenggat' => $t->tenggat_waktu->toIso8601String(),
            ])->all();
    }

    /**
     * KRS mahasiswa di tahun akademik aktif.
     *
     * @return Collection<int, Krs>
     */
    private static function krsSemester(MahasiswaProfile $mahasiswa, TahunAkademik $tahunAkademik): Collection
    {
        return Krs::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereHas('kelasKuliah', fn ($q) => $q->where('tahun_akademik_id', $tahunAkademik->id))
            ->with('kelasKuliah:id,matkul_id', 'kelasKuliah.mataKuliah:id,sks')
            ->get(['id', 'kelas_id']);
    }
}
