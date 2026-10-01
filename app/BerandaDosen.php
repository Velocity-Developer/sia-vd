<?php

namespace App;

use App\Models\DosenProfile;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanAkademik;
use App\Models\PengumpulanTugas;
use App\Models\Pertemuan;
use App\Models\QuizAttempt;
use App\Models\TahunAkademik;
use App\Models\TugasAkhir;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Isi Beranda dosen selain pengingat per fitur (presensi, remidi, susulan, tugas akhir): profil & angka,
 * pekerjaan yang menunggu dinilai, batas input nilai yang mendekat, dan kelas yang diampu di tahun akademik aktif.
 */
class BerandaDosen
{
    /** Batas input nilai mulai diingatkan sekian hari sebelumnya. */
    private const HARI_BATAS_NILAI = 14;

    /**
     * @return array{nama: string, nidn: string|null, prodi: string|null, jabatan: string|null, kelas_diampu: int, mahasiswa_diajar: int, mahasiswa_wali: int, bimbingan_ta: int}
     */
    public static function ringkasan(DosenProfile $dosen, ?TahunAkademik $tahunAkademik): array
    {
        $dosen->loadMissing(['user:id,name', 'prodi:id,jenjang,nama_prodi']);
        $kelasIds = self::kelasAktif($dosen, $tahunAkademik)->pluck('id');

        return [
            'nama' => $dosen->user->name,
            'nidn' => $dosen->nidn,
            'prodi' => $dosen->prodi ? trim($dosen->prodi->jenjang.' '.$dosen->prodi->nama_prodi) : null,
            'jabatan' => $dosen->jabatan_fungsional,
            'kelas_diampu' => $kelasIds->count(),
            'mahasiswa_diajar' => Krs::query()->whereIn('kelas_id', $kelasIds)->distinct()->count('mahasiswa_id'),
            'mahasiswa_wali' => MahasiswaProfile::query()->where('dosen_wali_id', $dosen->id)->where('status', 'Aktif')->count(),
            'bimbingan_ta' => TugasAkhir::query()->dibimbing($dosen->id)->where('status', TugasAkhir::BERJALAN)->count(),
        ];
    }

    /**
     * Kelas diampu di tahun akademik aktif beserta jumlah peserta dan kemajuan pertemuan kuliah.
     *
     * @return list<array{id: int, kode_kelas: string, matkul: string|null, sks: int|null, peserta: int, pertemuan_selesai: int, jumlah_pertemuan: int, nilai_final: bool}>
     */
    public static function kelas(DosenProfile $dosen, TahunAkademik $tahunAkademik): array
    {
        $bawaan = PengaturanAkademik::current()->jumlah_pertemuan;

        return self::kelasAktif($dosen, $tahunAkademik)
            ->map(fn (KelasKuliah $k): array => [
                'id' => $k->id,
                'kode_kelas' => $k->kode_kelas,
                'matkul' => $k->mataKuliah?->nama_matkul,
                'sks' => $k->mataKuliah?->sks,
                'peserta' => (int) $k->krs_count,
                'pertemuan_selesai' => (int) $k->pertemuan_selesai,
                'jumlah_pertemuan' => (int) ($k->jumlah_pertemuan ?? $bawaan),
                'nilai_final' => $k->nilaiFinal(),
            ])->values()->all();
    }

    /**
     * Pengumpulan tugas dan jawaban esai quiz (bukan lembar ujian, yang diingatkan lewat halaman ujian) yang belum dinilai.
     *
     * @return list<array{jenis: string, judul: string, kelas: string, jumlah: int, tautan: string}>
     */
    public static function perluDinilai(DosenProfile $dosen, TahunAkademik $tahunAkademik): array
    {
        $kelas = self::kelasAktif($dosen, $tahunAkademik)->keyBy('id');
        $label = fn (int $kelasId): string => trim(($kelas[$kelasId]->mataKuliah?->nama_matkul ?? '').' · '.$kelas[$kelasId]->kode_kelas, ' ·');

        $tugas = ! Feature::aktif('tugas') ? collect() : PengumpulanTugas::query()
            ->whereNull('nilai')
            ->join('tugas', 'tugas.id', '=', 'pengumpulan_tugas.tugas_id')
            ->whereIn('tugas.kelas_id', $kelas->keys())
            ->groupBy('tugas.id', 'tugas.judul_tugas', 'tugas.kelas_id')
            ->selectRaw('tugas.id, tugas.judul_tugas as judul, tugas.kelas_id, count(*) as jumlah')
            ->get()
            ->map(fn ($t): array => ['jenis' => 'Tugas', 'judul' => $t->judul, 'kelas' => $label($t->kelas_id), 'jumlah' => (int) $t->jumlah,
                'tautan' => route('dosen.kelas-kuliah.tugas.show', [$t->kelas_id, $t->id])]);

        $quiz = ! Feature::aktif('quiz') ? collect() : QuizAttempt::query()
            ->whereNotNull('quiz_attempts.submitted_at')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->whereNull('quizzes.ujian_id')
            ->whereIn('quizzes.kelas_id', $kelas->keys())
            ->whereHas('answers', fn (Builder $a) => $a->whereNull('point')->whereHas('question', fn (Builder $q) => $q->where('question_type', 'essay')))
            ->groupBy('quizzes.id', 'quizzes.nama_quiz', 'quizzes.kelas_id')
            ->selectRaw('quizzes.id, quizzes.nama_quiz as judul, quizzes.kelas_id, count(*) as jumlah')
            ->get()
            ->map(fn ($q): array => ['jenis' => 'Quiz esai', 'judul' => $q->judul, 'kelas' => $label($q->kelas_id), 'jumlah' => (int) $q->jumlah,
                'tautan' => route('dosen.kelas-kuliah.quiz.show', [$q->kelas_id, $q->id])]);

        return $tugas->concat($quiz)->sortByDesc('jumlah')->values()->all();
    }

    /**
     * Kelas yang nilainya belum final padahal batas input nilainya tinggal sedikit.
     *
     * @return array{pesan: list<array{teks: string, penting: bool}>, tautan: string}|null
     */
    public static function pengingatNilai(DosenProfile $dosen, TahunAkademik $tahunAkademik): ?array
    {
        $pesan = self::kelasAktif($dosen, $tahunAkademik)
            ->reject(fn (KelasKuliah $k): bool => $k->nilaiFinal() || $k->batasInputNilai() === null
                || $k->batasInputNilai()->gt(today()->addDays(self::HARI_BATAS_NILAI)))
            ->sortBy(fn (KelasKuliah $k) => $k->batasInputNilai())
            ->map(function (KelasKuliah $k): array {
                $sisa = (int) today()->diffInDays($k->batasInputNilai()->copy()->startOfDay());

                return [
                    'teks' => ($k->mataKuliah?->nama_matkul ?? 'Kelas').' · '.$k->kode_kelas.': batas input nilai '
                        .$k->batasInputNilai()->translatedFormat('d F Y').($sisa === 0 ? ' (hari ini)' : " ({$sisa} hari lagi)").'. Isi nilai lalu finalisasi.',
                    'penting' => $sisa <= 3,
                ];
            })->values()->all();

        return $pesan === [] ? null : ['pesan' => $pesan, 'tautan' => route('dosen.kelas-kuliah.index')];
    }

    /**
     * @return Collection<int, KelasKuliah>
     */
    private static function kelasAktif(DosenProfile $dosen, ?TahunAkademik $tahunAkademik): Collection
    {
        if ($tahunAkademik === null) {
            return collect();
        }

        return KelasKuliah::query()
            ->where('dosen_id', $dosen->id)
            ->where('tahun_akademik_id', $tahunAkademik->id)
            ->with(['mataKuliah:id,nama_matkul,sks', 'tahunAkademik'])
            ->withCount(['krs', 'pertemuans as pertemuan_selesai' => fn (Builder $q) => $q->where('jenis', Pertemuan::KULIAH)->where('status', Pertemuan::SELESAI)])
            ->orderBy('kode_kelas')
            ->get();
    }
}
