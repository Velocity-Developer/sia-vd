<?php

namespace App;

use App\Models\DosenProfile;
use App\Models\KelasKuliah;
use App\Models\MahasiswaProfile;
use App\Models\RemidiPeserta;
use App\Models\TagihanRemidi;
use App\Models\Ujian;

/**
 * Pengingat remidi untuk Beranda: tagihan dan jadwal remidi mahasiswa, serta langkah remidi yang menunggu dosen.
 */
class PengingatRemidi
{
    /**
     * @return array{tagihan: list<array<string, mixed>>, ujian: list<array<string, mixed>>}
     */
    public static function untukMahasiswa(MahasiswaProfile $mahasiswa, bool $lihatTagihan, bool $lihatUjian): array
    {
        $tagihan = ! $lihatTagihan ? collect() : TagihanRemidi::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereIn('status', [TagihanRemidi::BELUM_BAYAR, TagihanRemidi::DITOLAK, TagihanRemidi::MENUNGGU])
            ->with(['kelasKuliah:id,matkul_id,tahun_akademik_id', 'kelasKuliah.mataKuliah:id,nama_matkul', 'kelasKuliah.tahunAkademik'])
            ->get()
            ->reject(fn (TagihanRemidi $t): bool => $t->gugur())
            ->map(fn (TagihanRemidi $t): array => [
                'id' => $t->id,
                'matkul' => $t->kelasKuliah?->mataKuliah?->nama_matkul,
                'total' => $t->total,
                'status' => $t->status,
                'batas_bayar' => $t->batasBayar()?->toDateString(),
            ]);

        // Jadwal remidi yang belum selesai bagi peserta yang sudah lunas.
        $ujian = ! $lihatUjian ? collect() : Ujian::query()
            ->where('jenis', Ujian::REMIDI)
            ->terbit()
            ->whereIn('kelas_id', RemidiPeserta::query()->where('mahasiswa_id', $mahasiswa->id)->lunas()->select('kelas_id'))
            ->whereDate('tanggal', '>=', today())
            ->with(['kelasKuliah:id,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul'])
            ->orderBy('tanggal')
            ->get()
            ->reject(fn (Ujian $u): bool => $u->sudahSelesai())
            ->map(fn (Ujian $u): array => [
                'id' => $u->id,
                'matkul' => $u->kelasKuliah?->mataKuliah?->nama_matkul,
                'tanggal' => $u->tanggal->toDateString(),
                'jam_mulai' => $u->jam_mulai,
                'jam_akhir' => $u->jam_akhir,
                'label_mode' => $u->labelMode(),
            ]);

        return ['tagihan' => $tagihan->values()->all(), 'ujian' => $ujian->values()->all()];
    }

    /**
     * Kelas diampu di tahun aktif yang daftar remidinya perlu dikunci, dan yang remidinya selesai tetapi belum difinalisasi.
     *
     * @return array{kunci_daftar: list<array<string, mixed>>, isi_nilai: list<array<string, mixed>>}
     */
    public static function untukDosen(DosenProfile $dosen): array
    {
        $kelas = KelasKuliah::query()
            ->where('dosen_id', $dosen->id)
            ->whereHas('tahunAkademik', fn ($q) => $q->where('status', true))
            ->whereHas('krs')
            ->where(fn ($q) => $q->whereNull('remidi_dikunci_at')->orWhereNull('remidi_final_at'))
            ->with(['tahunAkademik', 'mataKuliah:id,nama_matkul', 'ujians' => fn ($q) => $q->where('jenis', Ujian::REMIDI)->terbit()])
            ->get(['id', 'kode_kelas', 'matkul_id', 'tahun_akademik_id', 'nilai_final_at', 'nilai_dibuka_sampai', 'remidi_dikunci_at', 'remidi_final_at']);

        $bentuk = fn (KelasKuliah $k): array => ['id' => $k->id, 'kode_kelas' => $k->kode_kelas, 'matkul' => $k->mataKuliah?->nama_matkul];

        return [
            'kunci_daftar' => $kelas->filter(fn (KelasKuliah $k): bool => $k->remidi_dikunci_at === null && $k->nilaiFinal())->map($bentuk)->values()->all(),
            'isi_nilai' => $kelas->filter(function (KelasKuliah $k): bool {
                $ujian = $k->ujians->first();

                return $k->remidi_dikunci_at !== null && $ujian?->sudahSelesai() && $k->jendelaRemidiTerbuka();
            })->map($bentuk)->values()->all(),
        ];
    }
}
