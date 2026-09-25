<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanAkademik;
use App\Models\PengaturanInstitusi;
use App\Models\Pertemuan;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use App\SyaratUjian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UjianController extends Controller
{
    /**
     * Jadwal ujian (yang sudah terbit) dari kelas-kelas di KRS mahasiswa, beserta status syarat kehadirannya.
     */
    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $tahunAkademiks = TahunAkademik::query()
            ->whereHas('kelasKuliahs.krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'tahun', 'semester', 'status']);
        $tahun = $tahunAkademiks->firstWhere('id', $request->integer('tahun_akademik_id'))
            ?? $tahunAkademiks->firstWhere('status', true)
            ?? $tahunAkademiks->first();

        return Inertia::render('Mahasiswa/Ujian', [
            'ujians' => $this->daftarUjian($mahasiswa, $tahun?->id),
            'tahunAkademikId' => $tahun?->id,
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->tahun.' '.$t->semester]),
        ]);
    }

    /**
     * Kartu ujian (PDF) untuk UTS atau UAS di tahun akademik yang dipilih.
     */
    public function kartu(Request $request): HttpResponse
    {
        $mahasiswa = $this->mahasiswa($request)->loadMissing('user:id,name', 'prodi:id,nama_prodi,jenjang');
        $jenis = $request->query('jenis') === Pertemuan::UAS ? Pertemuan::UAS : Pertemuan::UTS;
        $tahun = TahunAkademik::find($request->integer('tahun_akademik_id')) ?? TahunAkademik::where('status', true)->first();
        abort_if($tahun === null, 404);

        $ujians = $this->daftarUjian($mahasiswa, $tahun->id)->where('jenis', $jenis)->values();
        abort_if($ujians->isEmpty(), 404, 'Belum ada jadwal '.strtoupper($jenis).' yang terbit.');
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView('pdf.kartu-ujian', [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'mahasiswa' => $mahasiswa,
            'tahun' => $tahun,
            'jenis' => $jenis,
            'ujians' => $ujians,
            'syaratAktif' => PengaturanAkademik::current()->syarat_ujian_aktif,
        ])->download('kartu-'.$jenis.'-'.$mahasiswa->nim.'-'.Str::slug($tahun->tahun.'-'.$tahun->semester).'.pdf');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function daftarUjian(MahasiswaProfile $mahasiswa, ?int $tahunId): Collection
    {
        $kelas = KelasKuliah::query()
            ->where('tahun_akademik_id', $tahunId)
            ->whereHas('krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))
            ->with(['mataKuliah:id,kode_matkul,nama_matkul,sks', 'ujians' => fn ($q) => $q->terbit()->with('ruang:id,kode_ruang,nama_ruang')])
            ->get(['id', 'kode_kelas', 'matkul_id']);

        $pertemuan = Pertemuan::query()->whereIn('kelas_id', $kelas->pluck('id'))->get()->groupBy('kelas_id');
        $syarat = SyaratUjian::untukMahasiswa(
            $mahasiswa->id,
            $kelas->mapWithKeys(fn (KelasKuliah $k): array => [$k->id => $pertemuan->get($k->id, collect())]),
            PengaturanAkademik::current(),
        );

        return $kelas->flatMap(fn (KelasKuliah $k) => $k->ujians->map(fn (Ujian $u): array => [
            'id' => $u->id,
            'jenis' => $u->jenis,
            'mode' => $u->mode,
            'label_mode' => $u->labelMode(),
            'tanggal' => $u->tanggal->toDateString(),
            'jam_mulai' => $u->jam_mulai,
            'jam_akhir' => $u->jam_akhir,
            'ruang' => $u->ruang ? $u->ruang->kode_ruang.' — '.$u->ruang->nama_ruang : null,
            'pengawas' => $u->pengawas,
            'petunjuk' => $u->petunjuk,
            'kode_kelas' => $k->kode_kelas,
            'kode_matkul' => $k->mataKuliah?->kode_matkul,
            'nama_matkul' => $k->mataKuliah?->nama_matkul,
            'sks' => $k->mataKuliah?->sks,
            'syarat' => $syarat[$k->id]['peserta'][$mahasiswa->id][$u->jenis] ?? null,
        ]))->sortBy(fn (array $u): string => $u['tanggal'].$u['jam_mulai'])->values();
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }
}
