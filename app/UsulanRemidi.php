<?php

namespace App;

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Pertemuan;
use App\Models\QuizAttempt;
use App\Models\RemidiPeserta;
use App\Models\SkalaNilai;
use App\Models\TagihanRemidi;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use Illuminate\Support\Collection;

/**
 * Usulan otomatis peserta remidi: huruf akhir bertanda tidak lulus atau boleh diulang, dan ikut UAS.
 * Dosen tetap bisa menambah atau mencoret sebelum daftar dikunci.
 */
class UsulanRemidi
{
    public static function hurufRemidi(?string $nilai): bool
    {
        return filled($nilai) && (! SkalaNilai::lulus($nilai) || SkalaNilai::bolehDiulang($nilai));
    }

    /**
     * Mahasiswa yang ikut UAS kelas ini, atau null bila kelas tidak punya UAS terbit (semua dianggap ikut).
     * Lembar soal: sudah mulai mengerjakan; unggah berkas: mengumpulkan; tatap muka: nilai UAS terisi.
     *
     * @return Collection<int, int>|null
     */
    public static function pesertaUas(KelasKuliah $kelas): ?Collection
    {
        $uas = Ujian::query()->where('kelas_id', $kelas->id)->where('jenis', Pertemuan::UAS)->terbit()->first(['id', 'mode']);

        if ($uas === null) {
            return null;
        }

        return match ($uas->mode) {
            Ujian::ONLINE_SOAL => QuizAttempt::query()->whereHas('quiz', fn ($q) => $q->where('ujian_id', $uas->id))->pluck('mahasiswa_id'),
            Ujian::ONLINE_BERKAS => UjianJawaban::query()->where('ujian_id', $uas->id)->whereNotNull('berkas')->pluck('mahasiswa_id'),
            default => UjianJawaban::query()->where('ujian_id', $uas->id)->whereNotNull('nilai')->pluck('mahasiswa_id'),
        };
    }

    /**
     * Semua mahasiswa kelas beserta status usulan dan pilihan saat ini. Sebelum pernah disimpan, pilihan = usulan.
     *
     * @return array{ada_uas: bool, mahasiswa: list<array<string, mixed>>}
     */
    public static function susun(KelasKuliah $kelas): array
    {
        $kelas->loadMissing(['krs.mahasiswa:id,user_id,nim', 'krs.mahasiswa.user:id,name']);
        $pesertaUas = self::pesertaUas($kelas)?->flip();
        $tersimpan = RemidiPeserta::query()->where('kelas_id', $kelas->id)->pluck('mahasiswa_id')->flip();

        $mahasiswa = $kelas->krs
            ->sortBy(fn (Krs $krs): string => (string) $krs->mahasiswa?->nim)
            ->map(function (Krs $krs) use ($pesertaUas, $tersimpan): array {
                $ikutUas = $pesertaUas === null ? null : $pesertaUas->has($krs->mahasiswa_id);
                $diusulkan = self::hurufRemidi($krs->nilai) && $ikutUas !== false;

                return [
                    'mahasiswa_id' => $krs->mahasiswa_id,
                    'nama' => $krs->mahasiswa?->user?->name,
                    'nim' => $krs->mahasiswa?->nim,
                    'nilai' => $krs->nilai,
                    'huruf_remidi' => self::hurufRemidi($krs->nilai),
                    'ikut_uas' => $ikutUas,
                    'diusulkan' => $diusulkan,
                    'terpilih' => $tersimpan->isEmpty() ? $diusulkan : $tersimpan->has($krs->mahasiswa_id),
                ];
            })
            ->values()
            ->all();

        // Setelah daftar dikunci: status tagihan dan nilai ujian remidi tiap peserta.
        if ($kelas->remidi_dikunci_at !== null) {
            $kelas->loadMissing('tahunAkademik');
            $tagihan = TagihanRemidi::query()->where('kelas_id', $kelas->id)->get()->keyBy('mahasiswa_id')
                ->each(fn (TagihanRemidi $t) => $t->setRelation('kelasKuliah', $kelas));
            $nilaiRemidi = $kelas->ujianRemidi()?->nilaiPeserta() ?? collect();
            $nilaiAwal = RemidiPeserta::query()->where('kelas_id', $kelas->id)->pluck('nilai_awal', 'mahasiswa_id');

            $mahasiswa = array_map(fn (array $m): array => [
                ...$m,
                'nilai_awal' => $nilaiAwal[$m['mahasiswa_id']] ?? null,
                'tagihan' => $tagihan->get($m['mahasiswa_id'])?->statusTampil(),
                'nilai_remidi' => $nilaiRemidi->get($m['mahasiswa_id']),
            ], $mahasiswa);
        }

        return ['ada_uas' => $pesertaUas !== null, 'mahasiswa' => $mahasiswa];
    }
}
