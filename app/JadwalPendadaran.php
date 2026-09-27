<?php

namespace App;

use App\Models\DosenProfile;
use App\Models\Jadwal;
use App\Models\Pendadaran;
use App\Models\Pertemuan;
use App\Models\Ujian;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Pemeriksaan bentrok jadwal pendadaran.
 *
 * Ruang tidak boleh dipakai pendadaran lain, perkuliahan, atau ujian di jam yang sama, dan seorang dosen
 * tidak boleh menguji dua pendadaran sekaligus — keduanya menolak penyimpanan. Bentrok dengan jadwal
 * mengajar harian penguji hanya menjadi peringatan.
 */
class JadwalPendadaran
{
    public function __construct(
        private readonly string $tanggal,
        private readonly string $jamMulai,
        private readonly string $jamAkhir,
        private readonly ?int $kecualiId = null,
    ) {}

    public function ruangBentrok(int $ruangId): ?string
    {
        $pendadaran = $this->pendadaranLain()->where('ruang_id', $ruangId)->with('mahasiswa.user:id,name')->first();
        if ($pendadaran !== null) {
            return 'Ruang sudah dipakai pendadaran '.$pendadaran->mahasiswa?->user?->name.' ('.$this->jam($pendadaran).').';
        }

        $pertemuan = $this->pertemuan()->where('ruang_id', $ruangId)->with('kelasKuliah:id,kode_kelas')->first();
        if ($pertemuan !== null) {
            return 'Ruang sudah dipakai kuliah kelas '.$pertemuan->kelasKuliah?->kode_kelas.' ('.$this->jam($pertemuan).').';
        }

        $jadwal = $this->jadwalMingguan()->where('ruang_id', $ruangId)->with('kelasKuliah:id,kode_kelas')->first();
        if ($jadwal !== null) {
            return 'Ruang sudah dipakai kuliah kelas '.$jadwal->kelasKuliah?->kode_kelas.' ('.$this->jam($jadwal).').';
        }

        $ujian = Ujian::query()->whereDate('tanggal', $this->tanggal)->where('mode', Ujian::TATAP_MUKA)->where('ruang_id', $ruangId)
            ->where('jam_mulai', '<', $this->jamAkhir)->where('jam_akhir', '>', $this->jamMulai)
            ->with('kelasKuliah:id,kode_kelas')->first();

        return $ujian === null ? null
            : 'Ruang sudah dipakai ujian kelas '.$ujian->kelasKuliah?->kode_kelas.' ('.$this->jam($ujian).').';
    }

    /**
     * Dosen yang sudah menguji pendadaran lain di jam yang sama.
     *
     * @param  list<int>  $dosenIds
     * @return array<int, string> id dosen => pesan
     */
    public function pengujiBentrok(array $dosenIds): array
    {
        $pesan = [];
        foreach ($this->pendadaranLain()->where(fn (Builder $q) => $q->whereIn('penguji_1_id', $dosenIds)->orWhereIn('penguji_2_id', $dosenIds)->orWhereIn('penguji_3_id', $dosenIds))
            ->with('mahasiswa.user:id,name')->get() as $lain) {
            foreach (array_intersect($lain->pengujiIds(), $dosenIds) as $dosenId) {
                $pesan[$dosenId] ??= 'Dosen ini sudah menguji pendadaran '.$lain->mahasiswa?->user?->name.' ('.$this->jam($lain).').';
            }
        }

        return $pesan;
    }

    /**
     * Peringatan: penguji punya jadwal mengajar di jam yang sama. Tidak menolak penyimpanan.
     *
     * @param  list<int>  $dosenIds
     * @return list<string>
     */
    public function peringatanMengajar(array $dosenIds): array
    {
        $nama = DosenProfile::query()->whereKey($dosenIds)->with('user:id,name')->get()->mapWithKeys(fn (DosenProfile $d): array => [$d->id => $d->user?->name]);
        $pesan = [];

        foreach ($this->pertemuan()->with('kelasKuliah:id,kode_kelas,dosen_id')->get() as $p) {
            $dosenId = $p->dosen_id ?? $p->kelasKuliah?->dosen_id;
            if (in_array($dosenId, $dosenIds, true)) {
                $pesan[] = 'Jadwal penguji '.$nama[$dosenId].' akan bentrok dengan kuliah kelas '.$p->kelasKuliah?->kode_kelas.' ('.$this->jam($p).').';
            }
        }

        foreach ($this->jadwalMingguan()->whereHas('kelasKuliah', fn (Builder $k) => $k->whereIn('dosen_id', $dosenIds))->with('kelasKuliah:id,kode_kelas,dosen_id')->get() as $j) {
            $pesan[] = 'Jadwal penguji '.$nama[$j->kelasKuliah->dosen_id].' akan bentrok dengan kuliah kelas '.$j->kelasKuliah->kode_kelas.' ('.$this->jam($j).').';
        }

        return array_values(array_unique($pesan));
    }

    /**
     * @return Builder<Pendadaran>
     */
    private function pendadaranLain(): Builder
    {
        return Pendadaran::query()->whereDate('tanggal', $this->tanggal)
            ->where('jam_mulai', '<', $this->jamAkhir)->where('jam_akhir', '>', $this->jamMulai)
            ->when($this->kecualiId, fn (Builder $q, int $id) => $q->whereKeyNot($id));
    }

    /**
     * Pertemuan kuliah (terjadwal di tanggal itu) yang jamnya beririsan.
     *
     * @return Builder<Pertemuan>
     */
    private function pertemuan(): Builder
    {
        return Pertemuan::query()->whereDate('tanggal', $this->tanggal)->where('status', '!=', Pertemuan::DIBATALKAN)
            ->where('jam_mulai', '<', $this->jamAkhir)->where('jam_akhir', '>', $this->jamMulai);
    }

    /**
     * Jadwal mingguan kelas yang belum membuat pertemuan, pada tahun akademik yang mencakup tanggal itu.
     *
     * @return Builder<Jadwal>
     */
    private function jadwalMingguan(): Builder
    {
        return Jadwal::query()
            ->overlapping(Pertemuan::NAMA_HARI[Carbon::parse($this->tanggal)->dayOfWeekIso], $this->jamMulai, $this->jamAkhir)
            ->whereHas('kelasKuliah', fn (Builder $k) => $k->whereDoesntHave('pertemuans')
                ->whereHas('tahunAkademik', fn (Builder $t) => $t->whereDate('tanggal_mulai', '<=', $this->tanggal)->whereDate('tanggal_akhir', '>=', $this->tanggal)));
    }

    private function jam(Jadwal|Pendadaran|Pertemuan|Ujian $item): string
    {
        return substr((string) $item->jam_mulai, 0, 5).'–'.substr((string) $item->jam_akhir, 0, 5);
    }
}
