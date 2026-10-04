<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FilterKrs;
use App\Http\Controllers\Controller;
use App\KartuStudiTetap;
use App\KartuUjian;
use App\LingkupProdi;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cetak KST (Kartu Studi Tetap) dan Kartu Ujian (UTS/UAS) per mahasiswa. Keduanya hanya dari KRS yang
 * sudah disetujui. Kartu ujian (App\KartuUjian, sama dengan yang dicetak mahasiswa) mengikuti syarat kehadiran di
 * Pengaturan Akademik bila diberlakukan.
 */
class CetakKrsController extends Controller
{
    use FilterKrs;

    public function kst(Request $request): Response
    {
        return $this->daftar($request, 'kst');
    }

    public function kartuUjian(Request $request): Response
    {
        return $this->daftar($request, 'kartu');
    }

    public function cetakKst(Request $request, MahasiswaProfile $mahasiswa): HttpResponse
    {
        return KartuStudiTetap::pdf($mahasiswa, $this->tahunCetak($request, $mahasiswa));
    }

    public function cetakKartuUjian(Request $request, MahasiswaProfile $mahasiswa): HttpResponse|RedirectResponse
    {
        $tahun = $this->tahunCetak($request, $mahasiswa);
        $jenis = $this->jenis($request);

        if (($alasan = KartuUjian::alasanTidakBisa($mahasiswa, $tahun, $jenis)) !== null) {
            return back()->with('error', $mahasiswa->user?->name.' — '.$alasan);
        }

        return KartuUjian::pdf($mahasiswa, $tahun, $jenis);
    }

    /**
     * Daftar mahasiswa dengan KRS disetujui pada tahun akademik terpilih.
     */
    private function daftar(Request $request, string $mode): Response
    {
        [$tahun, $tahunAkademiks] = $this->tahunKrs($request);
        $tahunId = (int) $tahun?->id;
        $filter = [
            'tahun_akademik_id' => $tahun?->id,
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'search' => $request->string('search')->trim()->toString(),
            'jenis' => $mode === 'kartu' ? $this->jenis($request) : null,
        ];

        $mahasiswa = $this->hanyaKrsDisetujui($this->mahasiswaKrs($tahunId, $filter['prodi_id'], $filter['search']), $tahunId)
            ->paginate(25, ['id', 'user_id', 'nim', 'prodi_id', 'angkatan'])
            ->withQueryString();

        $ids = $mahasiswa->getCollection()->pluck('id');
        $sks = $this->sksPerMahasiswa($ids->all(), $tahunId);
        $kurang = $mode === 'kartu' && $tahun !== null ? KartuUjian::syaratKurang($ids, $tahun, $filter['jenis']) : collect();

        $mahasiswa->through(fn (MahasiswaProfile $m): array => [
            'id' => $m->id,
            'nim' => $m->nim,
            'nama' => $m->user?->name,
            'prodi' => $this->namaProdi($m->prodi),
            'angkatan' => $m->angkatan,
            'sks' => (int) ($sks[$m->id] ?? 0),
            'syarat_kurang' => $kurang->get($m->id, []),
        ]);

        // Syarat ujian diatur per prodi; angka di judul kolom mengikuti prodi yang disaring (atau pengaturan umum).
        $syarat = PengaturanAkademik::untukProdi(LingkupProdi::id() ?? $filter['prodi_id']);

        return Inertia::render('Admin/CetakKrs', [
            'mode' => $mode,
            'mahasiswa' => $mahasiswa,
            'filter' => $filter,
            ...$this->opsiFilterKrs($tahunAkademiks),
            'syaratUjian' => $mode === 'kartu' && $syarat->syarat_ujian_aktif ? $syarat->min_kehadiran_ujian : null,
        ]);
    }

    private function jenis(Request $request): string
    {
        return in_array($request->query('jenis'), Ujian::JENIS, true) ? $request->query('jenis') : Pertemuan::UTS;
    }

    /**
     * Tahun akademik yang dicetak; KRS mahasiswa pada tahun itu wajib sudah disetujui.
     */
    private function tahunCetak(Request $request, MahasiswaProfile $mahasiswa): TahunAkademik
    {
        $tahun = $request->filled('tahun_akademik_id') ? TahunAkademik::find($request->integer('tahun_akademik_id')) : TahunAkademik::aktif();
        abort_if($tahun === null, 404);
        abort_if(($alasan = KartuStudiTetap::alasanTidakBisa($mahasiswa, $tahun)) !== null, 404, $alasan ?? '');

        return $tahun;
    }
}
