<?php

namespace App\Impor;

use App\Models\DosenProfile;
use App\Models\KelasKuliah;
use App\Models\MataKuliah;
use App\Models\TahunAkademik;
use Illuminate\Validation\Rule;

/**
 * Impor Kelas Kuliah: satu baris = satu kelas (MK + dosen pengampu) di satu tahun akademik.
 * Jadwal tetap diatur di menu Jadwal Kelas.
 */
class ImporKelasKuliah extends Impor
{
    public function judul(): string
    {
        return 'Kelas Kuliah';
    }

    public function izin(): string
    {
        return 'admin.kelas-kuliah';
    }

    public function kolom(): array
    {
        return [
            'kode_kelas' => ['Kode Kelas', true, 'KEP101-A', 'Unik dalam satu tahun akademik (dipakai juga sebagai rombel).'],
            'tahun_akademik' => ['Tahun Akademik', false, '', 'Contoh: 2025/2026 Ganjil. Kosong = tahun akademik aktif. Lihat lembar Tahun Akademik.'],
            'kode_mk' => ['Kode MK', true, 'KEP101', 'Lihat lembar Mata Kuliah.'],
            'nidn_dosen' => ['NIDN Dosen', false, '0912345601', 'NIDN dosen aktif. Wajib, kecuali MK Tugas Akhir/Skripsi. Lihat lembar Dosen.'],
            'kapasitas' => ['Kapasitas', true, '40', '1–500.'],
            'jumlah_pertemuan' => ['Jumlah Pertemuan', false, '', '1–32. Kosong = bawaan Pengaturan Akademik.'],
        ];
    }

    public function referensi(): array
    {
        return [
            'Tahun Akademik' => TahunAkademik::query()->orderByDesc('tanggal_mulai')->get(['tahun', 'semester', 'status'])
                ->map(fn (TahunAkademik $ta): string => "{$ta->tahun} {$ta->semester}".($ta->status ? ' (aktif)' : ''))->all(),
            'Mata Kuliah' => MataKuliah::query()->with('prodi:id,nama_prodi')->orderBy('kode_matkul')->get(['kode_matkul', 'nama_matkul', 'prodi_id'])
                ->map(fn (MataKuliah $mk): string => "{$mk->kode_matkul} = {$mk->nama_matkul}".($mk->prodi ? " ({$mk->prodi->nama_prodi})" : ''))->all(),
            'Dosen' => DosenProfile::query()->pilihan()->with('user:id,name')->orderBy('nidn')->get(['id', 'user_id', 'nidn'])
                ->map(fn (DosenProfile $d): string => "{$d->nidn} = ".($d->user?->name ?? '-'))->all(),
        ];
    }

    protected function normalisasi(array $data): array
    {
        $data = parent::normalisasi($data);
        $data['tahun_akademik_id'] = self::tahunAkademikId($data['tahun_akademik'] ?? null);
        $matkul = isset($data['kode_mk'])
            ? MataKuliah::query()->where('kode_matkul', $data['kode_mk'])->first(['id', 'tugas_akhir'])
            : null;
        $data['matkul_id'] = $matkul?->id;
        $data['mk_tugas_akhir'] = (bool) $matkul?->tugas_akhir;
        $data['dosen_id'] = isset($data['nidn_dosen'])
            ? DosenProfile::query()->pilihan()->where('nidn', $data['nidn_dosen'])->value('id')
            : null;

        return $data;
    }

    protected function aturan(array $data): array
    {
        return [
            'kode_kelas' => ['required', 'string', 'max:50', Rule::unique('kelas_kuliah', 'kode_kelas')->where('tahun_akademik_id', $data['tahun_akademik_id'])],
            'tahun_akademik_id' => ['required'],
            'kode_mk' => ['required', 'string'],
            'matkul_id' => [Rule::requiredIf($data['kode_mk'] !== null)],
            'nidn_dosen' => [Rule::requiredIf(! $data['mk_tugas_akhir'] && $data['matkul_id'] !== null), 'nullable', 'string'],
            'dosen_id' => [Rule::requiredIf($data['nidn_dosen'] !== null)],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:500'],
            'jumlah_pertemuan' => ['nullable', 'integer', 'min:1', 'max:32'],
        ];
    }

    protected function pesan(): array
    {
        return [
            'kode_kelas.unique' => 'Kode Kelas sudah ada di tahun akademik tersebut.',
            'tahun_akademik_id.required' => 'Tahun Akademik tidak ditemukan (format: 2025/2026 Ganjil), atau kolomnya kosong dan belum ada tahun akademik aktif.',
            'matkul_id.required' => 'Kode MK tidak ditemukan.',
            'nidn_dosen.required' => 'NIDN Dosen wajib diisi untuk mata kuliah selain Tugas Akhir/Skripsi.',
            'dosen_id.required' => 'NIDN Dosen tidak ditemukan atau dosennya tidak aktif.',
        ];
    }

    /**
     * Kode kelas juga harus unik di antara baris berkas untuk tahun akademik yang sama.
     */
    public function periksa(array $baris): array
    {
        $hasil = parent::periksa($baris);
        $taId = [];
        $terpakai = [];
        foreach ($baris as $b) {
            $kode = mb_strtolower(trim((string) ($b['data']['kode_kelas'] ?? '')));
            if ($kode === '') {
                continue;
            }
            $ta = trim((string) ($b['data']['tahun_akademik'] ?? ''));
            $taId[$ta] ??= self::tahunAkademikId($ta === '' ? null : $ta) ?? 'tak-dikenal:'.$ta;
            $kunci = $taId[$ta].'|'.$kode;
            if (isset($terpakai[$kunci])) {
                $hasil['galat'][] = "Baris {$b['baris']}: Kode Kelas sama dengan baris {$terpakai[$kunci]}.";
                $hasil['data'] = [];
            } else {
                $terpakai[$kunci] = $b['baris'];
            }
        }

        return $hasil;
    }

    public function simpan(array $data, array $opsi): void
    {
        KelasKuliah::query()->create([
            'kode_kelas' => $data['kode_kelas'],
            'tahun_akademik_id' => $data['tahun_akademik_id'],
            'matkul_id' => $data['matkul_id'],
            'dosen_id' => $data['dosen_id'],
            'kapasitas' => (int) $data['kapasitas'],
            'jumlah_pertemuan' => isset($data['jumlah_pertemuan']) ? (int) $data['jumlah_pertemuan'] : null,
        ]);
    }

    /**
     * "2025/2026 Ganjil" (juga "2025/2026-Genap", huruf kecil) → id; kosong → tahun akademik aktif.
     */
    private static function tahunAkademikId(?string $nilai): ?int
    {
        if ($nilai === null) {
            return TahunAkademik::aktif()?->id;
        }
        if (! preg_match('#^\s*(\d{4}\s*/\s*\d{4})[\s\-]+(\w+)\s*$#u', $nilai, $m)) {
            return null;
        }

        return TahunAkademik::query()
            ->where('tahun', preg_replace('/\s+/', '', $m[1]))
            ->where('semester', self::pilihan($m[2], TahunAkademik::SEMESTER))
            ->value('id');
    }
}
