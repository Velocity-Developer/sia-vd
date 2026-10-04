<?php

namespace App\Http\Controllers\Admin;

use App\AmbilKelasKrs;
use App\Http\Controllers\Concerns\FilterKrs;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\PengaturanAkademik;
use App\Models\TahunAkademik;
use App\TawaranKrs;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Input KRS oleh admin atas nama mahasiswa. Aturan isi KRS sama dengan mahasiswa (tawaran, prasyarat,
 * bentrok, kapasitas, batas SKS), tetapi admin tidak terikat periode KRS maupun kunci KRS.
 */
class InputKrsController extends Controller
{
    use FilterKrs;

    public function index(Request $request): Response
    {
        [$tahun, $tahunAkademiks] = $this->tahunKrs($request);
        $prodiId = $request->integer('prodi_id') ?: null;
        $mahasiswa = MahasiswaProfile::query()
            ->with(['user:id,name', 'prodi:id,nama_prodi,jenjang', 'dosenWali:id,user_id', 'dosenWali.user:id,name'])
            ->find($request->integer('mahasiswa_id'));

        return Inertia::render('Admin/InputKrs', [
            'filter' => ['tahun_akademik_id' => $tahun?->id, 'prodi_id' => $prodiId, 'mahasiswa_id' => $mahasiswa?->id],
            ...$this->opsiFilterKrs($tahunAkademiks),
            // Mahasiswa aktif, ditambah yang sedang dipilih walau statusnya lain (agar tetap tampil di pilihan).
            'mahasiswaOptions' => MahasiswaProfile::query()
                ->where(fn (Builder $q) => $q->aktif()->when($mahasiswa, fn (Builder $q, MahasiswaProfile $m) => $q->orWhere('id', $m->id)))
                ->when($prodiId, fn (Builder $q, int $prodi) => $q->where('prodi_id', $prodi))
                ->with('user:id,name')
                ->orderBy('nim')
                ->get(['id', 'user_id', 'nim'])
                ->map(fn (MahasiswaProfile $m): array => ['id' => $m->id, 'name' => trim(($m->nim ?? 'Tanpa NIM').' — '.$m->user?->name)]),
            'krs' => $tahun === null || $mahasiswa === null ? null : $this->dataKrs($mahasiswa, $tahun),
        ]);
    }

    public function store(Request $request, MahasiswaProfile $mahasiswa, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $kelasKuliah->loadMissing('mataKuliah', 'tahunAkademik');
        abort_unless($kelasKuliah->mataKuliah?->prodi_id === $mahasiswa->prodi_id, 404);

        if (! $mahasiswa->isAktif()) {
            return back()->with('error', 'Status mahasiswa ('.($mahasiswa->status ?? 'belum diisi').') tidak memungkinkan pengisian KRS.');
        }

        $error = AmbilKelasKrs::ambil($mahasiswa, $kelasKuliah);

        return $error === null
            ? back()->with('success', $kelasKuliah->mataKuliah->nama_matkul.' (kelas '.$kelasKuliah->kode_kelas.') ditambahkan ke KRS.')
            : back()->with('error', $error);
    }

    public function destroy(Krs $krs): RedirectResponse
    {
        if (filled($krs->nilai)) {
            return back()->with('error', 'Kelas yang sudah memiliki nilai tidak dapat dikeluarkan dari KRS.');
        }

        $krs->loadMissing('kelasKuliah.mataKuliah:id,nama_matkul');
        $krs->cancel();

        return back()->with('success', $krs->kelasKuliah?->mataKuliah?->nama_matkul.' dikeluarkan dari KRS.');
    }

    /**
     * Simpan KRS hasil input admin: diajukan (menunggu verifikasi) atau langsung disetujui.
     */
    public function simpan(Request $request, MahasiswaProfile $mahasiswa): RedirectResponse
    {
        $data = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'setujui' => ['boolean'],
        ]);

        $adaKelas = $mahasiswa->krs()->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $data['tahun_akademik_id']))->exists();

        if (! $adaKelas) {
            return back()->with('error', 'Tambahkan minimal satu kelas sebelum menyimpan KRS.');
        }

        $kunci = KrsSemester::simpan($mahasiswa->id, $data['tahun_akademik_id']);

        if ($request->boolean('setujui')) {
            $kunci->setujui($request->user()->id);
        }

        return back()->with('success', $kunci->status === KrsSemester::DISETUJUI
            ? 'KRS disimpan dan disetujui.'
            : 'KRS disimpan dan menunggu verifikasi.');
    }

    /**
     * @return array<string, mixed>
     */
    private function dataKrs(MahasiswaProfile $mahasiswa, TahunAkademik $tahun): array
    {
        $semuaKrs = AmbilKelasKrs::semuaKrs($mahasiswa);
        $tawaran = new TawaranKrs($mahasiswa, $tahun, $semuaKrs);
        $diambil = $this->kelasKrs($mahasiswa, $tahun->id);
        $ips = $mahasiswa->ipsSemesterSebelum($tahun, $semuaKrs);
        $kunci = KrsSemester::untuk($mahasiswa->id, $tahun->id)?->setRelation('tahunAkademik', $tahun);

        $ditawarkan = KelasKuliah::query()
            ->where('tahun_akademik_id', $tahun->id)
            ->whereNotIn('id', $diambil->pluck('kelas_id'))
            ->whereHas('mataKuliah', fn (Builder $q) => $q->where('prodi_id', $mahasiswa->prodi_id))
            ->with([
                'mataKuliah:id,prodi_id,kode_matkul,nama_matkul,sks,semester,jenis,tugas_akhir',
                'mataKuliah.prasyarat:mata_kuliahs.id,nama_matkul',
                'dosen:id,user_id',
                'dosen.user:id,name',
                'jadwals' => fn ($q) => $q->with('ruang:id,kode_ruang')->orderBy('jam_mulai'),
            ])
            ->withCount('krs')
            ->orderBy('kode_kelas')
            ->get()
            ->filter(fn (KelasKuliah $k): bool => $k->mataKuliah !== null && $tawaran->jenis($k->mataKuliah) !== null)
            // Mata kuliah yang sudah diambil di kelas lain tidak ditawarkan lagi.
            ->reject(fn (KelasKuliah $k): bool => $diambil->contains(fn (Krs $krs): bool => $krs->kelasKuliah?->matkul_id === $k->matkul_id))
            ->sortBy(fn (KelasKuliah $k): string => sprintf('%02d', $k->mataKuliah->semester).$k->mataKuliah->kode_matkul.$k->kode_kelas)
            ->values();

        return [
            'mahasiswa' => [
                'id' => $mahasiswa->id,
                'user_id' => $mahasiswa->user_id,
                'nama' => $mahasiswa->user?->name,
                'nim' => $mahasiswa->nim,
                'prodi' => $this->namaProdi($mahasiswa->prodi),
                'angkatan' => $mahasiswa->angkatan,
                'semester' => $tawaran->semester(),
                'status' => $mahasiswa->status,
                'boleh_krs' => $mahasiswa->isAktif(),
                'dosen_pa' => $mahasiswa->dosenWali?->user?->name,
            ],
            'tahun_akademik' => $tahun->label(),
            'status' => $kunci?->ringkasan(),
            'sks_diambil' => $diambil->sum(fn (Krs $k): int => (int) ($k->kelasKuliah?->mataKuliah?->sks ?? 0)),
            'maks_sks' => PengaturanAkademik::maksSksUntuk($ips['ips'] ?? null, $mahasiswa->prodi_id),
            'ips_sebelumnya' => $ips === null ? null : ['ips' => $ips['ips'], 'tahun_akademik' => $ips['tahun_akademik']->label()],
            'verifikasi_aktif' => KrsSemester::verifikasiAktif(),
            'diambil' => $diambil->map(fn (Krs $k): array => [
                'id' => $k->id,
                ...$this->kelasRingkas($k->kelasKuliah),
                'nilai' => $k->nilai,
            ]),
            'ditawarkan' => $ditawarkan->map(fn (KelasKuliah $k): array => [
                ...$this->kelasRingkas($k),
                'label' => $tawaran->label($k->mataKuliah),
                'terkunci' => $tawaran->alasanPrasyarat($k->mataKuliah),
                'terisi' => $k->krs_count,
                'kapasitas' => $k->mataKuliah->tugas_akhir ? null : $k->kapasitas,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kelasRingkas(?KelasKuliah $kelas): array
    {
        /** @var MataKuliah|null $mk */
        $mk = $kelas?->mataKuliah;

        return [
            'kelas_id' => $kelas?->id,
            'kode_kelas' => $kelas?->kode_kelas,
            'kode_matkul' => $mk?->kode_matkul,
            'nama_matkul' => $mk?->nama_matkul,
            'semester_matkul' => $mk?->semester,
            'sks' => (int) ($mk?->sks ?? 0),
            'dosen' => $kelas?->dosen?->user?->name,
            'jadwal' => $kelas?->jadwals
                ->map(fn ($j): string => $j->hari.', '.substr($j->jam_mulai, 0, 5).'–'.substr($j->jam_akhir, 0, 5).($j->ruang ? ' ('.$j->ruang->kode_ruang.')' : ''))
                ->all() ?? [],
        ];
    }
}
