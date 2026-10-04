<?php

namespace App;

use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanAkademik;
use App\Models\PengaturanInstitusi;
use App\Models\Pertemuan;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Kartu UTS dan Kartu UAS (PDF format contoh klien) dari KRS mahasiswa (yang sudah disetujui bila verifikasi KRS aktif). Satu sumber untuk admin (KRS → Cetak
 * Kartu Ujian) dan mahasiswa (Jadwal Ujian), dengan syarat yang sama: KRS disetujui, dan bila syarat kehadiran
 * diberlakukan, semua mata kuliah memenuhi persentase minimal (atau mendapat dispensasi).
 */
class KartuUjian
{
    /**
     * Alasan kartu belum bisa dicetak, atau null bila bisa.
     */
    public static function alasanTidakBisa(MahasiswaProfile $mahasiswa, TahunAkademik $tahun, string $jenis): ?string
    {
        // KRS wajib sudah disetujui selama verifikasi KRS dinyalakan; tanpa verifikasi cukup ada kelas di KRS.
        $alasan = KrsSemester::verifikasiAktif()
            ? KartuStudiTetap::alasanTidakBisa($mahasiswa, $tahun)
            : (self::kelas($mahasiswa, $tahun)->isEmpty() ? 'KRS belum berisi kelas.' : null);
        if ($alasan !== null) {
            return 'Kartu '.strtoupper($jenis).' belum bisa dicetak: '.lcfirst($alasan);
        }

        $kurang = self::syaratKurang(collect([$mahasiswa->id]), $tahun, $jenis)->get($mahasiswa->id, []);

        return $kurang === [] ? null : 'Kartu '.strtoupper($jenis).' belum bisa dicetak: kehadiran di bawah '
            .PengaturanAkademik::untukProdi($mahasiswa->prodi_id)->min_kehadiran_ujian.'% pada '.implode(', ', $kurang).'. Dispensasi diberikan bagian akademik/prodi lewat menu Presensi.';
    }

    public static function pdf(MahasiswaProfile $mahasiswa, TahunAkademik $tahun, string $jenis, bool $unduh = false): Response
    {
        $mahasiswa->muatPengesahan();
        $krs = self::kelas($mahasiswa, $tahun);
        $institusi = PengaturanInstitusi::current();
        $nama = 'kartu-'.$jenis.'-'.$mahasiswa->nim.'-'.Str::slug($tahun->label()).'.pdf';

        $pdf = Pdf::loadView('pdf.kartu-ujian-krs', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'mahasiswa' => $mahasiswa,
            'tahunAkademik' => $tahun,
            'jenis' => $jenis,
            'krs' => $krs,
            'tanggalUjian' => self::tanggalUjian($krs, $jenis),
        ]);

        return $unduh ? $pdf->download($nama) : $pdf->stream($nama);
    }

    /**
     * Mata kuliah yang kehadirannya belum memenuhi syarat ujian (tanpa dispensasi), per mahasiswa.
     * Kosong bila syarat kehadiran tidak diberlakukan.
     *
     * @param  Collection<int, int>  $mahasiswaIds
     * @return Collection<int, list<string>> mahasiswa_id => nama mata kuliah (persen hadir)
     */
    public static function syaratKurang(Collection $mahasiswaIds, TahunAkademik $tahun, string $jenis): Collection
    {
        if ($mahasiswaIds->isEmpty() || ! in_array($jenis, Ujian::JENIS, true)) {
            return collect();
        }

        // Syarat ujian berlaku per prodi mahasiswa (Akademik → Konfigurasi → Syarat Ujian & Remedial).
        $prodiMahasiswa = MahasiswaProfile::query()->withoutGlobalScope('lingkup-prodi')->whereKey($mahasiswaIds)->pluck('prodi_id', 'id');
        $pengaturanProdi = $prodiMahasiswa->unique()->mapWithKeys(fn ($prodiId): array => [(int) $prodiId => PengaturanAkademik::untukProdi($prodiId === null ? null : (int) $prodiId)]);
        $mahasiswaIds = $mahasiswaIds->filter(fn (int $id): bool => $pengaturanProdi[(int) ($prodiMahasiswa[$id] ?? 0)]?->syarat_ujian_aktif ?? false)->values();
        if ($mahasiswaIds->isEmpty()) {
            return collect();
        }

        $kelasPerMahasiswa = Krs::query()
            ->whereIn('mahasiswa_id', $mahasiswaIds)
            ->whereHas('kelasKuliah', fn ($q) => $q->where('tahun_akademik_id', $tahun->id))
            ->get(['mahasiswa_id', 'kelas_id'])
            ->groupBy('mahasiswa_id');
        $kelas = KelasKuliah::query()->whereIn('id', $kelasPerMahasiswa->flatten()->pluck('kelas_id')->unique())
            ->with('mataKuliah:id,nama_matkul')->get(['id', 'matkul_id'])->keyBy('id');
        $pertemuan = Pertemuan::query()->whereIn('kelas_id', $kelas->keys())->get()->groupBy('kelas_id');

        return $kelasPerMahasiswa->map(function (Collection $krs, int $mahasiswaId) use ($pertemuan, $pengaturanProdi, $prodiMahasiswa, $kelas, $jenis): array {
            $syarat = SyaratUjian::untukMahasiswa(
                $mahasiswaId,
                $krs->mapWithKeys(fn (Krs $k): array => [$k->kelas_id => $pertemuan->get($k->kelas_id, collect())]),
                $pengaturanProdi[(int) ($prodiMahasiswa[$mahasiswaId] ?? 0)],
            );

            return $syarat
                ->filter(fn (array $s): bool => ($s['peserta'][$mahasiswaId][$jenis]['memenuhi'] ?? null) === false)
                ->map(fn (array $s, int $kelasId): string => ($kelas[$kelasId]?->mataKuliah?->nama_matkul ?? '-')
                    .' ('.$s['peserta'][$mahasiswaId][$jenis]['persen'].'%)')
                ->values()
                ->all();
        })->filter();
    }

    /**
     * @return Collection<int, Krs>
     */
    private static function kelas(MahasiswaProfile $mahasiswa, TahunAkademik $tahun): Collection
    {
        return $mahasiswa->krs()
            ->whereHas('kelasKuliah', fn ($q) => $q->where('tahun_akademik_id', $tahun->id))
            ->with([
                'kelasKuliah:id,matkul_id,dosen_id,kode_kelas,kapasitas,tahun_akademik_id',
                'kelasKuliah.mataKuliah:id,prodi_id,kode_matkul,nama_matkul,sks,semester,jenis',
                'kelasKuliah.dosen:id,user_id',
                'kelasKuliah.dosen.user:id,name',
                'kelasKuliah.jadwals' => fn ($q) => $q->with('ruang:id,kode_ruang')->orderBy('jam_mulai'),
            ])
            ->get()
            ->sortBy(fn (Krs $k): string => (string) $k->kelasKuliah?->mataKuliah?->kode_matkul)
            ->values();
    }

    /**
     * Tanggal UTS/UAS terbit per kelas, untuk kolom Tanggal di kartu (kosong bila belum dijadwalkan).
     *
     * @param  Collection<int, Krs>  $krs
     * @return array<int, string> kelas_id => tanggal
     */
    private static function tanggalUjian(Collection $krs, string $jenis): array
    {
        return Ujian::query()->terbit()
            ->whereIn('kelas_id', $krs->pluck('kelas_id'))
            ->where('jenis', $jenis)
            ->orderBy('tanggal')
            ->get(['kelas_id', 'tanggal'])
            ->unique('kelas_id')
            ->mapWithKeys(fn (Ujian $u): array => [$u->kelas_id => $u->tanggal->translatedFormat('d/m/Y')])
            ->all();
    }
}
