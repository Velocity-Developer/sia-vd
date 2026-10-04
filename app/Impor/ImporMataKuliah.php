<?php

namespace App\Impor;

use App\Models\MataKuliah;
use App\Models\ProgramStudi;
use Illuminate\Validation\Rule;

class ImporMataKuliah extends Impor
{
    private const JENIS_PENILAIAN = ['Reguler' => MataKuliah::REGULER, 'TA' => MataKuliah::TUGAS_AKHIR, 'PPL' => MataKuliah::PPL, 'KKM' => MataKuliah::KKM];

    public function judul(): string
    {
        return 'Mata Kuliah';
    }

    public function izin(): string
    {
        return 'admin.mata-kuliah';
    }

    public function kolom(): array
    {
        return [
            'kode_mk' => ['Kode MK', true, 'KEP101', 'Unik.'],
            'nama_mk' => ['Nama MK', true, 'Anatomi Fisiologi', ''],
            'sks' => ['SKS', true, '3', '1–6.'],
            'semester' => ['Semester', true, '1', '1–14.'],
            'jenis' => ['Jenis', false, 'Wajib', 'Wajib atau Pilihan. Kosong = Wajib.'],
            'jenis_penilaian' => ['Jenis Penilaian', false, 'Reguler', 'Reguler, TA, PPL, atau KKM. Kosong = Reguler.'],
            'kode_prodi' => ['Kode Prodi', true, 'S1-KEP', 'Lihat lembar Program Studi.'],
        ];
    }

    protected function unikDalamBerkas(): array
    {
        return ['kode_mk'];
    }

    public function referensi(): array
    {
        return [
            'Program Studi' => ProgramStudi::query()->orderBy('nama_prodi')->get(['kode_prodi', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): string => "{$p->kode_prodi} = ".trim($p->jenjang.' '.$p->nama_prodi))->all(),
        ];
    }

    protected function normalisasi(array $data): array
    {
        $data = parent::normalisasi($data);
        $data['jenis'] = self::pilihan($data['jenis'] ?? null, ['Wajib', 'Pilihan']) ?? 'Wajib';
        $penilaian = self::pilihan($data['jenis_penilaian'] ?? null, array_keys(self::JENIS_PENILAIAN)) ?? 'Reguler';
        $data['jenis_penilaian'] = self::JENIS_PENILAIAN[$penilaian] ?? $penilaian;
        $data['prodi_id'] = isset($data['kode_prodi']) ? ProgramStudi::query()->where('kode_prodi', $data['kode_prodi'])->value('id') : null;

        return $data;
    }

    protected function aturan(array $data): array
    {
        return [
            'kode_mk' => ['required', 'string', 'max:50', Rule::unique('mata_kuliahs', 'kode_matkul')],
            'nama_mk' => ['required', 'string', 'max:255'],
            'sks' => ['required', 'integer', 'min:1', 'max:6'],
            'semester' => ['required', 'integer', 'min:1', 'max:14'],
            'jenis' => ['required', Rule::in(['Wajib', 'Pilihan'])],
            'jenis_penilaian' => ['required', Rule::in(array_values(self::JENIS_PENILAIAN))],
            'kode_prodi' => ['required', 'string'],
            'prodi_id' => [Rule::requiredIf($data['kode_prodi'] !== null)],
        ];
    }

    public function simpan(array $data, array $opsi): void
    {
        MataKuliah::query()->create([
            'kode_matkul' => $data['kode_mk'],
            'nama_matkul' => $data['nama_mk'],
            'sks' => (int) $data['sks'],
            'semester' => (int) $data['semester'],
            'jenis' => $data['jenis'],
            'jenis_penilaian' => $data['jenis_penilaian'],
            'prodi_id' => $data['prodi_id'],
        ]);
    }
}
