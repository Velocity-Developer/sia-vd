<?php

namespace App\Models;

use App\Models\Concerns\SerializesDatesInAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class KelasKuliah extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'kelas_kuliah';

    protected $fillable = ['kode_kelas', 'tahun_akademik_id', 'kapasitas', 'jumlah_pertemuan', 'dosen_id', 'matkul_id'];

    /**
     * Kelas baru tanpa jumlah pertemuan memakai bawaan di Pengaturan Akademik.
     */
    protected static function booted(): void
    {
        static::creating(function (self $kelas): void {
            $kelas->jumlah_pertemuan ??= PengaturanAkademik::current()->jumlah_pertemuan;
        });
    }

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
            'jumlah_pertemuan' => 'integer',
        ];
    }

    public function tahunAkademik(): BelongsTo
    {
        return $this->belongsTo(TahunAkademik::class, 'tahun_akademik_id');
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(DosenProfile::class, 'dosen_id');
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class, 'matkul_id');
    }

    public function jadwals(): HasMany
    {
        return $this->hasMany(Jadwal::class, 'kelas_id');
    }

    public function materis(): HasMany
    {
        return $this->hasMany(Materi::class, 'kelas_id');
    }

    public function tugas(): HasMany
    {
        return $this->hasMany(Tugas::class, 'kelas_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class, 'kelas_id');
    }

    public function krs(): HasMany
    {
        return $this->hasMany(Krs::class, 'kelas_id');
    }

    public function pertemuans(): HasMany
    {
        return $this->hasMany(Pertemuan::class, 'kelas_id');
    }

    /**
     * Ubah jumlah pertemuan kelas dengan tetap menjaga posisi ujian: UAS selalu di pertemuan terakhir, dan
     * UTS yang ikut terbuang dipindah ke tengah. Pertemuan yang sudah berjalan, sudah punya presensi, atau
     * sudah punya pengajuan izin tidak dihapus; permintaan seperti itu ditolak dengan pesan yang jelas.
     * Bila kelas sudah punya pertemuan, pertemuan tambahan langsung dibuat dari jadwal mingguan.
     */
    public function ubahJumlahPertemuan(int $jumlah, string $field = 'jumlah_pertemuan'): void
    {
        $lama = $this->jumlah_pertemuan;
        $pertemuan = $this->pertemuans()->withCount(['presensiMahasiswas', 'pengajuanIzins'])->get()->keyBy('pertemuan_ke');
        $bebas = fn (Pertemuan $p): bool => $p->status === Pertemuan::DIJADWALKAN && $p->presensi_mahasiswas_count === 0 && $p->pengajuan_izins_count === 0;
        $tolak = fn (string $pesan) => throw ValidationException::withMessages([$field => $pesan]);

        $dibuang = $pertemuan->filter(fn (Pertemuan $p): bool => $p->pertemuan_ke > $jumlah);
        $berjalan = $dibuang->whereIn('status', [Pertemuan::BERLANGSUNG, Pertemuan::SELESAI])->max('pertemuan_ke');
        $adaData = $dibuang->filter(fn (Pertemuan $p): bool => $p->presensi_mahasiswas_count > 0 || $p->pengajuan_izins_count > 0)->max('pertemuan_ke');

        if ($berjalan !== null) {
            $tolak("Pertemuan ke-{$berjalan} sudah berjalan, jadi jumlah pertemuan minimal {$berjalan}.");
        }

        if ($adaData !== null) {
            $tolak("Pertemuan ke-{$adaData} sudah punya presensi atau pengajuan izin mahasiswa, jadi jumlah pertemuan minimal {$adaData}.");
        }

        // Pindahkan UTS/UAS yang ikut terbuang ke posisi barunya (UAS terakhir, UTS di tengah).
        foreach ([Pertemuan::UAS => $jumlah, Pertemuan::UTS => intdiv($jumlah, 2)] as $jenis => $nomor) {
            $ujian = $pertemuan->firstWhere('jenis', $jenis);

            if ($ujian === null || $ujian->pertemuan_ke <= $jumlah || $jumlah < 4) {
                continue;
            }

            $tujuan = $pertemuan->get($nomor);

            if ($tujuan !== null && ($tujuan->jenis !== Pertemuan::KULIAH || ! $bebas($tujuan))) {
                $tolak(strtoupper($jenis)." tidak bisa dipindah ke pertemuan ke-{$nomor} karena pertemuan itu sudah berjalan atau bukan pertemuan kuliah.");
            }

            $tujuan?->update(['jenis' => $jenis]);
        }

        // Saat jumlah bertambah, UAS lama yang belum berjalan menjadi kuliah biasa; UAS baru dibuat di nomor terakhir.
        $uas = $pertemuan->firstWhere('jenis', Pertemuan::UAS);
        if ($jumlah > $lama && $uas !== null && $uas->pertemuan_ke < $jumlah && $bebas($uas)) {
            $uas->update(['jenis' => Pertemuan::KULIAH]);
        }

        $this->pertemuans()->whereIn('id', $dibuang->pluck('id'))->delete();
        $this->update(['jumlah_pertemuan' => $jumlah]);

        if ($jumlah > $lama && $pertemuan->isNotEmpty()) {
            try {
                Pertemuan::generateUntuk($this->fresh());
            } catch (ValidationException) {
                // Kelas tanpa jadwal mingguan: pertemuan tambahan dibuat nanti lewat tombol "Buat pertemuan".
            }
        }
    }

    public function dispensasiUjians(): HasMany
    {
        return $this->hasMany(DispensasiUjian::class, 'kelas_id');
    }
}
