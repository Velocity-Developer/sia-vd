<?php

namespace App;

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanSusulan;
use App\Models\TagihanSusulan;
use App\Models\Ujian;

/**
 * Pengingat ujian susulan untuk Beranda: status pengajuan, tagihan, dan jadwal susulan mahasiswa, serta ujian
 * susulan yang perlu disiapkan atau dinilai dosen.
 */
class PengingatSusulan
{
    /**
     * @return array{pengajuan: list<array<string, mixed>>, tagihan: list<array<string, mixed>>, ujian: list<array<string, mixed>>}
     */
    public static function untukMahasiswa(MahasiswaProfile $mahasiswa, bool $lihatTagihan, bool $lihatUjian): array
    {
        $pengajuan = ! $lihatUjian ? collect() : PengajuanSusulan::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('status', PengajuanSusulan::MENUNGGU)
            ->with(['ujian:id,kelas_id,jenis,mode,tanggal', 'ujian.kelasKuliah:id,matkul_id', 'ujian.kelasKuliah.mataKuliah:id,nama_matkul'])
            ->get()
            ->reject(fn (PengajuanSusulan $p): bool => UjianSusulan::ikutUjianUtama($p->ujian, $mahasiswa->id))
            ->map(fn (PengajuanSusulan $p): array => [
                'id' => $p->id,
                'ujian_id' => $p->ujian_id,
                'judul' => strtoupper($p->ujian->jenis).' '.$p->ujian->kelasKuliah?->mataKuliah?->nama_matkul,
            ]);

        $tagihan = ! $lihatTagihan ? collect() : TagihanSusulan::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereIn('status', [TagihanSusulan::BELUM_BAYAR, TagihanSusulan::DITOLAK, TagihanSusulan::MENUNGGU])
            ->with(['ujian:id,kelas_id,jenis,mode,tanggal', 'kelasKuliah:id,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul'])
            ->get()
            ->filter(fn (TagihanSusulan $t): bool => in_array($t->statusSusulan(UjianSusulan::ikutUjianUtama($t->ujian, $mahasiswa->id)), [TagihanSusulan::BELUM_BAYAR, TagihanSusulan::DITOLAK, TagihanSusulan::MENUNGGU], true))
            ->map(fn (TagihanSusulan $t): array => [
                'id' => $t->id,
                'judul' => 'Susulan '.strtoupper($t->ujian->jenis).' '.$t->kelasKuliah?->mataKuliah?->nama_matkul,
                'total' => $t->total,
                'status' => $t->status,
                'batas_bayar' => $t->batas_bayar->toDateString(),
            ]);

        $ujian = ! $lihatUjian ? collect() : Ujian::query()
            ->whereIn('jenis', Ujian::JENIS_SUSULAN)
            ->terbit()
            ->whereDate('tanggal', '>=', today())
            ->whereHas('kelasKuliah.krs', fn ($q) => $q->where('mahasiswa_id', $mahasiswa->id))
            ->with(['kelasKuliah:id,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul'])
            ->orderBy('tanggal')
            ->get()
            ->filter(fn (Ujian $u): bool => ! $u->sudahSelesai() && $u->termasukPesertaKhusus($mahasiswa->id))
            ->map(fn (Ujian $u): array => [
                'id' => $u->id,
                'judul' => $u->labelJenis().' '.$u->kelasKuliah?->mataKuliah?->nama_matkul,
                'tanggal' => $u->tanggal->toDateString(),
                'jam_mulai' => $u->jam_mulai,
                'jam_akhir' => $u->jam_akhir,
                'label_mode' => $u->labelMode(),
            ]);

        return ['pengajuan' => $pengajuan->values()->all(), 'tagihan' => $tagihan->values()->all(), 'ujian' => $ujian->values()->all()];
    }

    /**
     * Ujian susulan terbit di kelas yang diampu (tahun aktif): yang belum selesai perlu disiapkan soalnya, yang sudah
     * selesai tetapi nilainya belum dirilis perlu dinilai. Hanya yang punya peserta.
     *
     * @return array{siapkan: list<array<string, mixed>>, nilai: list<array<string, mixed>>}
     */
    public static function untukDosen(DosenProfile $dosen): array
    {
        $ujian = Ujian::query()
            ->whereIn('jenis', Ujian::JENIS_SUSULAN)
            ->terbit()
            ->where('nilai_dirilis', false)
            ->whereHas('kelasKuliah', fn ($q) => $q->where('dosen_id', $dosen->id)->whereHas('tahunAkademik', fn ($t) => $t->where('status', true)))
            ->with(['kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul'])
            ->orderBy('tanggal')
            ->get()
            ->filter(fn (Ujian $u): bool => UjianSusulan::pesertaSusulan($u)->isNotEmpty());

        $bentuk = fn (Ujian $u): array => [
            'id' => $u->id,
            'judul' => $u->labelJenis().' '.$u->kelasKuliah?->mataKuliah?->nama_matkul,
            'kode_kelas' => $u->kelasKuliah?->kode_kelas,
            'tanggal' => $u->tanggal->toDateString(),
        ];

        return [
            'siapkan' => $ujian->reject(fn (Ujian $u): bool => $u->sudahSelesai())->map($bentuk)->values()->all(),
            'nilai' => $ujian->filter(fn (Ujian $u): bool => $u->sudahSelesai())->map($bentuk)->values()->all(),
        ];
    }
}
