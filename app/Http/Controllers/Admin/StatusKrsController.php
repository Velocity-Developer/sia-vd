<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FilterKrs;
use App\Http\Controllers\Controller;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\TahunAkademik;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Status KRS per mahasiswa: Ya = KRS final (disetujui & terkunci), Tidak = belum final / dibuka agar
 * mahasiswa bisa mengubahnya. Mengubah ke Ya menyetujui KRS; ke Tidak membuka kunci seperti "Kembalikan untuk Revisi".
 */
class StatusKrsController extends Controller
{
    use FilterKrs;

    public function index(Request $request): Response
    {
        [$tahun, $tahunAkademiks] = $this->tahunKrs($request);
        $filter = [
            'tahun_akademik_id' => $tahun?->id,
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'status' => in_array($request->query('status'), ['ya', 'tidak'], true) ? $request->query('status') : 'semua',
            'search' => $request->string('search')->trim()->toString(),
        ];
        $tahunId = (int) $tahun?->id;
        $disetujui = fn (Builder $k) => $k->where('tahun_akademik_id', $tahunId)->where('status', KrsSemester::DISETUJUI);

        $mahasiswa = $this->mahasiswaKrs($tahunId, $filter['prodi_id'], $filter['search'])
            ->when($filter['status'] === 'ya', fn (Builder $q) => $q->whereHas('krsSemester', $disetujui))
            ->when($filter['status'] === 'tidak', fn (Builder $q) => $q->whereDoesntHave('krsSemester', $disetujui))
            ->with(['krsSemester' => fn ($q) => $q->where('tahun_akademik_id', $tahunId)->with('verifikator:id,name')])
            ->paginate(25, ['id', 'user_id', 'nim', 'prodi_id'])
            ->withQueryString();

        $sks = $this->sksPerMahasiswa($mahasiswa->getCollection()->pluck('id')->all(), $tahunId);
        $mahasiswa->through(function (MahasiswaProfile $m) use ($sks, $tahun): array {
            $kunci = $m->krsSemester->first()?->setRelation('tahunAkademik', $tahun);

            return [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama' => $m->user?->name,
                'prodi' => $this->namaProdi($m->prodi),
                'sks' => (int) ($sks[$m->id] ?? 0),
                'final' => $kunci?->status === KrsSemester::DISETUJUI,
                'status_krs' => $kunci?->status,
                'catatan_revisi' => $kunci?->catatan_revisi,
                'batas_revisi' => $kunci?->status === KrsSemester::PERLU_REVISI ? $kunci->ringkasan()['batas_revisi'] : null,
                'diubah_oleh' => $kunci?->verifikator?->name,
                'diubah_pada' => $kunci?->diverifikasi_pada?->toIso8601String(),
            ];
        });

        return Inertia::render('Admin/StatusKrs', [
            'mahasiswa' => $mahasiswa,
            'filter' => $filter,
            ...$this->opsiFilterKrs($tahunAkademiks),
            'masaRevisiBerjalan' => $tahun !== null && KrsSemester::masaRevisiBerjalan($tahun),
            'batasRevisi' => $tahun === null ? null : KrsSemester::batasRevisi($tahun)?->toDateString(),
        ]);
    }

    /**
     * Status Ya: KRS disetujui dan terkunci.
     */
    public function ya(Request $request, MahasiswaProfile $mahasiswa): RedirectResponse
    {
        $tahun = $this->tahunDari($request);
        $adaKelas = $mahasiswa->krs()->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $tahun->id))->exists();

        if (! $adaKelas) {
            return back()->with('error', 'KRS '.$mahasiswa->user?->name.' belum berisi kelas, jadi belum bisa dijadikan final.');
        }

        $kunci = KrsSemester::untuk($mahasiswa->id, $tahun->id);

        if ($kunci?->status === KrsSemester::DISETUJUI) {
            return back()->with('error', 'KRS '.$mahasiswa->user?->name.' sudah berstatus Ya.');
        }

        // KRS yang belum pernah disimpan mahasiswa dicatat tersimpan sekarang.
        ($kunci ?? KrsSemester::simpan($mahasiswa->id, $tahun->id))->setujui($request->user()->id);

        return back()->with('success', 'Status KRS '.$mahasiswa->user?->name.' diubah menjadi Ya (disetujui & terkunci).');
    }

    /**
     * Status Tidak: KRS dibuka agar bisa diubah mahasiswa sampai akhir masa revisi atau tanggal yang diisi.
     */
    public function tidak(Request $request, MahasiswaProfile $mahasiswa): RedirectResponse
    {
        $tahun = $this->tahunDari($request);
        $data = $request->validate(KrsSemester::ATURAN_BUKA_KUNCI, attributes: KrsSemester::ATRIBUT_BUKA_KUNCI);

        if (($galat = KrsSemester::bukaKunci($mahasiswa->id, $tahun, $data['catatan'] ?? null, $data['dibuka_sampai'] ?? null, $request->user()->id)) !== null) {
            return back()->with('error', $galat);
        }

        return back()->with('success', 'Status KRS '.$mahasiswa->user?->name.' diubah menjadi Tidak. '.KrsSemester::pesanDibuka($data['dibuka_sampai'] ?? null, $tahun));
    }

    private function tahunDari(Request $request): TahunAkademik
    {
        $request->validate(['tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')]]);

        return TahunAkademik::findOrFail($request->integer('tahun_akademik_id'));
    }
}
