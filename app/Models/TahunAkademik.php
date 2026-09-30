<?php

namespace App\Models;

use App\Feature;
use App\Models\Concerns\SerializesDatesInAppTimezone;
use App\PengajuanCuti;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TahunAkademik extends Model
{
    use SerializesDatesInAppTimezone;

    protected $table = 'tahun_akademik';

    public const SEMESTER = ['Ganjil', 'Genap'];

    protected $fillable = ['tahun', 'semester', 'tanggal_mulai', 'tanggal_akhir', 'tanggal_krs_awal', 'tanggal_krs_akhir', 'tanggal_cuti_awal', 'tanggal_cuti_akhir', 'batas_input_nilai', 'batas_bayar_remidi', 'batas_input_nilai_remidi', 'status'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_akhir' => 'date',
            'tanggal_cuti_awal' => 'date:Y-m-d',
            'tanggal_cuti_akhir' => 'date:Y-m-d',
            'batas_input_nilai' => 'date:Y-m-d',
            'batas_bayar_remidi' => 'date:Y-m-d',
            'batas_input_nilai_remidi' => 'date:Y-m-d',
            'status' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Cuti yang disetujui untuk semester ini baru mengubah status mahasiswa saat semesternya aktif.
        static::saved(function (self $tahun): void {
            if ($tahun->status && ($tahun->wasRecentlyCreated || $tahun->wasChanged('status'))) {
                PengajuanCuti::terapkan($tahun);
            }
        });
    }

    public static function aktif(): ?self
    {
        return self::query()->where('status', true)->first();
    }

    /**
     * Tahun pertama dari teks tahun akademik ("2026/2027" menjadi 2026).
     */
    public function tahunAwal(): ?int
    {
        return preg_match('/^(\d{4})\//', (string) $this->tahun, $cocok) === 1 ? (int) $cocok[1] : null;
    }

    public function kelasKuliahs(): HasMany
    {
        return $this->hasMany(KelasKuliah::class, 'tahun_akademik_id');
    }

    /**
     * Periode pengisian KRS sedang berjalan. Tanggal kosong berarti periode dianggap tertutup.
     */
    public function periodeKrsAktif(): bool
    {
        if ($this->tanggal_krs_awal === null || $this->tanggal_krs_akhir === null) {
            return false;
        }

        return Carbon::today()->between(
            Carbon::parse($this->tanggal_krs_awal)->startOfDay(),
            Carbon::parse($this->tanggal_krs_akhir)->endOfDay(),
        );
    }

    /**
     * Periode pengajuan cuti untuk semester ini sedang dibuka (tanggal kosong = tertutup).
     *
     * @param  Builder<self>  $query
     */
    public function scopeCutiDibuka(Builder $query): void
    {
        $query->whereDate('tanggal_cuti_awal', '<=', Carbon::today())->whereDate('tanggal_cuti_akhir', '>=', Carbon::today());
    }

    public function label(): string
    {
        return $this->tahun.' '.$this->semester;
    }

    /**
     * Nilai remidi (dan huruf akhir peserta remidi) masih boleh diisi dosen sampai akhir hari batas.
     */
    public function batasNilaiRemidiLewat(): bool
    {
        return $this->batas_input_nilai_remidi?->copy()->endOfDay()->isPast() ?? false;
    }

    /**
     * Ujian remidi dijadwalkan sesudah tanggal ini: batas bayar remidi selama fitur keuangan aktif, atau
     * batas input nilai (tanggal akhir semester bila kosong) bila remidi tanpa tagihan.
     *
     * @return array{tanggal: ?Carbon, label: string}
     */
    public function awalRemidi(): array
    {
        return match (true) {
            Feature::aktif('keuangan') => ['tanggal' => $this->batas_bayar_remidi, 'label' => 'batas bayar remidi'],
            $this->batas_input_nilai !== null => ['tanggal' => $this->batas_input_nilai, 'label' => 'batas input nilai'],
            default => ['tanggal' => $this->tanggal_akhir, 'label' => 'tanggal akhir semester'],
        };
    }

    /**
     * Pesan bila batas remidi di Tahun Akademik belum lengkap, atau null bila remidi sudah bisa dijadwalkan.
     */
    public function batasRemidiBelumLengkap(): ?string
    {
        return match (true) {
            Feature::aktif('keuangan') && ($this->batas_bayar_remidi === null || $this->batas_input_nilai_remidi === null) => 'Isi dulu Batas Bayar Remidi dan Batas Input Nilai Remidi di menu Tahun Akademik.',
            $this->batas_input_nilai_remidi === null => 'Isi dulu Batas Input Nilai Remidi di menu Tahun Akademik.',
            default => null,
        };
    }
}
