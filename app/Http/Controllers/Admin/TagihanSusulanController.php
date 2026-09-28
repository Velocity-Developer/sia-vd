<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\VerifikasiBuktiBayar;
use App\Http\Controllers\Controller;
use App\Models\JenisBiaya;
use App\Models\PengajuanSusulan;
use App\Models\PengaturanAkademik;
use App\Models\TagihanSusulan;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use App\UjianSusulan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tagihan ujian susulan: diterbitkan massal dari pengajuan yang disetujui, lalu admin memverifikasi bukti bayar.
 */
class TagihanSusulanController extends Controller
{
    use VerifikasiBuktiBayar;

    /** @var array<int, Collection<int, int>> id ujian => (id mahasiswa => indeks) peserta ujian utama */
    private array $pesertaUtama = [];

    public function index(Request $request): Response
    {
        $tahunAkademik = $this->tahunAkademikTerpilih($request);
        $taId = $tahunAkademik?->id;
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();

        $tagihan = TagihanSusulan::query()
            ->with([
                'mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name', 'kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul',
                'ujian:id,kelas_id,jenis,mode,tanggal', 'verifikator:id,name',
                'kelasKuliah.ujians' => fn ($q) => $q->whereIn('jenis', Ujian::JENIS_SUSULAN)->select(['id', 'kelas_id', 'jenis', 'tanggal', 'jam_mulai', 'jam_akhir', 'status']),
            ])
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $taId))
            ->when(in_array($status, [TagihanSusulan::BELUM_BAYAR, TagihanSusulan::MENUNGGU, TagihanSusulan::LUNAS, TagihanSusulan::DITOLAK], true), fn (Builder $q) => $q->where('status', $status))
            ->when($search !== '', fn (Builder $q) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('nim', 'like', "%{$search}%")->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))))
            ->orderByRaw('status = ? desc', [TagihanSusulan::MENUNGGU])
            ->orderBy('bukti_diunggah_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (TagihanSusulan $t): array {
                $ikut = $this->ikutUtama($t->ujian, $t->mahasiswa_id);
                $jadwal = $t->kelasKuliah?->ujians->firstWhere('jenis', Ujian::jenisSusulanUntuk($t->ujian->jenis));

                return [
                    'id' => $t->id,
                    'nama' => $t->mahasiswa?->user?->name,
                    'nim' => $t->mahasiswa?->nim,
                    'kelas' => $t->kelasKuliah?->kode_kelas,
                    'matkul' => $t->kelasKuliah?->mataKuliah?->nama_matkul,
                    'jenis' => $t->ujian->jenis,
                    'total' => $t->total,
                    'rincian' => $t->rincian,
                    'status' => $t->statusSusulan($ikut),
                    'ikut_ujian_utama' => $ikut,
                    'batas_bayar' => $t->batas_bayar->toDateString(),
                    'ada_bukti' => $t->bukti !== null,
                    'bukti_diunggah_at' => $t->bukti_diunggah_at?->toIso8601String(),
                    'alasan_tolak' => $t->alasan_tolak,
                    'diverifikasi_oleh' => $t->verifikator?->name,
                    'diverifikasi_at' => $t->diverifikasi_at?->toIso8601String(),
                    'ujian_susulan' => $jadwal ? ['tanggal' => $jadwal->tanggal->toDateString(), 'lewat' => $jadwal->sudahMulai()] : null,
                ];
            });

        return Inertia::render('Admin/TagihanSusulan', [
            'tagihan' => $tagihan,
            'ringkasan' => $this->ringkasan($taId),
            'filter' => ['tahun_akademik_id' => $taId, 'status' => $status ?: 'all', 'search' => $search],
            'batasBayarHari' => PengaturanAkademik::current()->batas_bayar_susulan_hari,
            'adaJenisBiaya' => JenisBiaya::query()->where('aktif', true)->where('kategori', JenisBiaya::SUSULAN)->exists(),
            'tahunAkademikOptions' => TahunAkademik::query()->orderByDesc('tanggal_mulai')->get()
                ->map(fn (TahunAkademik $ta): array => ['id' => $ta->id, 'name' => $ta->tahun.' '.$ta->semester])->all(),
        ]);
    }

    /**
     * Terbitkan tagihan untuk pengajuan yang disetujui dan belum ditagih. Yang ternyata ikut ujian utama dilewati.
     */
    public function terbitkan(Request $request): RedirectResponse
    {
        $data = $request->validate(['tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')]]);
        $jenisBiaya = JenisBiaya::query()->where('aktif', true)->where('kategori', JenisBiaya::SUSULAN)->with('tarif')->orderBy('urutan')->get();

        if ($jenisBiaya->isEmpty()) {
            return back()->with('error', 'Belum ada jenis biaya kategori Susulan yang aktif. Isi dulu di menu Jenis Biaya.');
        }

        $batas = today()->addDays(PengaturanAkademik::current()->batas_bayar_susulan_hari);
        $pengajuan = $this->belumDitagih($data['tahun_akademik_id']);

        DB::transaction(function () use ($pengajuan, $jenisBiaya, $batas, $request): void {
            foreach ($pengajuan as $p) {
                $hitung = TagihanSusulan::hitung($p->mahasiswa, (int) $p->ujian->kelasKuliah->mataKuliah?->sks, $jenisBiaya);

                TagihanSusulan::query()->create([
                    'pengajuan_susulan_id' => $p->id,
                    'mahasiswa_id' => $p->mahasiswa_id,
                    'ujian_id' => $p->ujian_id,
                    'kelas_id' => $p->ujian->kelas_id,
                    ...$hitung,
                    // Tanpa tarif yang berlaku (nominal 0) susulan gratis.
                    'status' => $hitung['total'] > 0 ? TagihanSusulan::BELUM_BAYAR : TagihanSusulan::LUNAS,
                    'batas_bayar' => $batas,
                    'diterbitkan_oleh' => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', $pengajuan->isEmpty()
            ? 'Tidak ada pengajuan baru yang perlu ditagih.'
            : "{$pengajuan->count()} tagihan susulan diterbitkan, batas bayar {$batas->translatedFormat('d M Y')}.");
    }

    public function lunas(Request $request, TagihanSusulan $tagihanSusulan): RedirectResponse
    {
        return $this->prosesTandaiLunas($request, $tagihanSusulan, 'Tagihan susulan ditandai lunas.', $this->laranganIkutUtama($tagihanSusulan));
    }

    public function tolak(Request $request, TagihanSusulan $tagihanSusulan): RedirectResponse
    {
        return $this->prosesTolakBukti($request, $tagihanSusulan);
    }

    private function laranganIkutUtama(TagihanSusulan $tagihan): ?string
    {
        return UjianSusulan::ikutUjianUtama($tagihan->ujian, $tagihan->mahasiswa_id)
            ? 'Mahasiswa ini sudah mengikuti ujian utama, jadi tagihan susulannya dibatalkan.'
            : null;
    }

    private function ikutUtama(Ujian $ujian, int $mahasiswaId): bool
    {
        $this->pesertaUtama[$ujian->id] ??= UjianSusulan::pesertaUjianUtama($ujian)->flip();

        return $this->pesertaUtama[$ujian->id]->has($mahasiswaId);
    }

    /**
     * Pengajuan disetujui di tahun akademik ini yang belum ditagih dan mahasiswanya belum ikut ujian utama.
     *
     * @return Collection<int, PengajuanSusulan>
     */
    private function belumDitagih(?int $taId): Collection
    {
        return PengajuanSusulan::query()
            ->where('status', PengajuanSusulan::DISETUJUI)
            ->whereDoesntHave('tagihan')
            ->whereHas('ujian.kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $taId))
            ->with(['mahasiswa:id,prodi_id,angkatan', 'ujian:id,kelas_id,jenis,mode', 'ujian.kelasKuliah:id,matkul_id', 'ujian.kelasKuliah.mataKuliah:id,sks'])
            ->get()
            ->reject(fn (PengajuanSusulan $p): bool => $this->ikutUtama($p->ujian, $p->mahasiswa_id))
            ->values();
    }

    /**
     * @return array<string, int>
     */
    private function ringkasan(?int $taId): array
    {
        $perStatus = TagihanSusulan::query()
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $taId))
            ->selectRaw('status, count(*) as jumlah')->groupBy('status')->pluck('jumlah', 'status');

        return [
            'belum_ditagih' => $this->belumDitagih($taId)->count(),
            'belum_bayar' => (int) (($perStatus[TagihanSusulan::BELUM_BAYAR] ?? 0) + ($perStatus[TagihanSusulan::DITOLAK] ?? 0)),
            'menunggu' => (int) ($perStatus[TagihanSusulan::MENUNGGU] ?? 0),
            'lunas' => (int) ($perStatus[TagihanSusulan::LUNAS] ?? 0),
        ];
    }

    private function tahunAkademikTerpilih(Request $request): ?TahunAkademik
    {
        $id = $request->integer('tahun_akademik_id') ?: null;

        return $id
            ? TahunAkademik::query()->find($id)
            : TahunAkademik::query()->where('status', true)->first() ?? TahunAkademik::query()->orderByDesc('id')->first();
    }
}
