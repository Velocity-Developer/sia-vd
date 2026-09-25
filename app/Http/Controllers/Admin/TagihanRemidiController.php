<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JenisBiaya;
use App\Models\KelasKuliah;
use App\Models\RemidiPeserta;
use App\Models\TagihanRemidi;
use App\Models\TahunAkademik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tagihan remidi: diterbitkan massal dari daftar remidi yang sudah dikunci dosen, lalu admin memverifikasi bukti bayar.
 */
class TagihanRemidiController extends Controller
{
    public function index(Request $request): Response
    {
        $tahunAkademik = $this->tahunAkademikTerpilih($request);
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();
        $taId = $tahunAkademik?->id;

        $tagihan = TagihanRemidi::query()
            ->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name', 'kelasKuliah:id,kode_kelas,matkul_id,tahun_akademik_id', 'kelasKuliah.mataKuliah:id,nama_matkul,sks', 'verifikator:id,name'])
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $taId))
            ->when(in_array($status, [TagihanRemidi::BELUM_BAYAR, TagihanRemidi::MENUNGGU, TagihanRemidi::LUNAS, TagihanRemidi::DITOLAK], true), fn (Builder $q) => $q->where('status', $status))
            ->when($search !== '', fn (Builder $q) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('nim', 'like', "%{$search}%")->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))))
            // Yang menunggu verifikasi tampil paling atas.
            ->orderByRaw('status = ? desc', [TagihanRemidi::MENUNGGU])
            ->orderBy('bukti_diunggah_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString()
            ->through(function (TagihanRemidi $t) use ($tahunAkademik): array {
                $t->kelasKuliah->setRelation('tahunAkademik', $tahunAkademik);

                return [
                    'id' => $t->id,
                    'nama' => $t->mahasiswa?->user?->name,
                    'nim' => $t->mahasiswa?->nim,
                    'kelas' => $t->kelasKuliah?->kode_kelas,
                    'matkul' => $t->kelasKuliah?->mataKuliah?->nama_matkul,
                    'total' => $t->total,
                    'rincian' => $t->rincian,
                    'status' => $t->statusTampil(),
                    'ada_bukti' => $t->bukti !== null,
                    'bukti_diunggah_at' => $t->bukti_diunggah_at?->toIso8601String(),
                    'alasan_tolak' => $t->alasan_tolak,
                    'diverifikasi_oleh' => $t->verifikator?->name,
                    'diverifikasi_at' => $t->diverifikasi_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Admin/TagihanRemidi', [
            'tagihan' => $tagihan,
            'ringkasan' => $this->ringkasan($tahunAkademik),
            'filter' => ['tahun_akademik_id' => $taId, 'status' => $status ?: 'all', 'search' => $search],
            'batasBayar' => $tahunAkademik?->batas_bayar_remidi?->toDateString(),
            'batasLewat' => $tahunAkademik?->batas_bayar_remidi?->copy()->endOfDay()->isPast() ?? false,
            'adaJenisBiaya' => JenisBiaya::query()->where('aktif', true)->where('kategori', JenisBiaya::REMIDI)->exists(),
            'tahunAkademikOptions' => TahunAkademik::query()->orderByDesc('tahun')->orderBy('semester')->get()
                ->map(fn (TahunAkademik $ta): array => ['id' => $ta->id, 'name' => $ta->tahun.' '.$ta->semester])->all(),
        ]);
    }

    /**
     * Terbitkan tagihan untuk semua peserta remidi (daftar dikunci) di tahun akademik yang belum punya tagihan.
     * Tagihan yang sudah ada tidak diubah.
     */
    public function terbitkan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
        ], attributes: ['tahun_akademik_id' => 'Tahun akademik']);
        $tahunAkademik = TahunAkademik::query()->findOrFail($data['tahun_akademik_id']);

        if ($tahunAkademik->batas_bayar_remidi === null) {
            return back()->with('error', 'Isi dulu Batas Bayar Remidi di menu Tahun Akademik.');
        }

        if ($tahunAkademik->batas_bayar_remidi->copy()->endOfDay()->isPast()) {
            return back()->with('error', 'Batas bayar remidi tahun akademik ini sudah lewat. Perpanjang dulu di menu Tahun Akademik.');
        }

        $jenisBiaya = JenisBiaya::query()->where('aktif', true)->where('kategori', JenisBiaya::REMIDI)->with('tarif')->orderBy('urutan')->get();

        if ($jenisBiaya->isEmpty()) {
            return back()->with('error', 'Belum ada jenis biaya kategori Remidi yang aktif. Isi dulu di menu Jenis Biaya.');
        }

        $peserta = RemidiPeserta::query()
            ->whereDoesntHave('tagihan')
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $tahunAkademik->id)->whereNotNull('remidi_dikunci_at'))
            ->with(['mahasiswa:id,prodi_id,angkatan', 'kelasKuliah:id,matkul_id', 'kelasKuliah.mataKuliah:id,sks'])
            ->get();

        DB::transaction(function () use ($peserta, $jenisBiaya, $request): void {
            foreach ($peserta as $p) {
                $hitung = TagihanRemidi::hitung($p->mahasiswa, (int) $p->kelasKuliah->mataKuliah?->sks, $jenisBiaya);

                TagihanRemidi::query()->create([
                    'remidi_peserta_id' => $p->id,
                    'mahasiswa_id' => $p->mahasiswa_id,
                    'kelas_id' => $p->kelas_id,
                    ...$hitung,
                    // Tanpa tarif yang berlaku (nominal 0) remidi gratis bagi mahasiswa itu.
                    'status' => $hitung['total'] > 0 ? TagihanRemidi::BELUM_BAYAR : TagihanRemidi::LUNAS,
                    'diterbitkan_oleh' => $request->user()->id,
                ]);
            }
        });

        return back()->with('success', $peserta->isEmpty() ? 'Tidak ada peserta baru yang perlu ditagih.' : "{$peserta->count()} tagihan remidi diterbitkan.");
    }

    /**
     * Tandai lunas: dari bukti yang diunggah, atau pembayaran langsung tanpa bukti di sistem.
     */
    public function lunas(Request $request, TagihanRemidi $tagihanRemidi): RedirectResponse
    {
        if ($tagihanRemidi->status === TagihanRemidi::LUNAS) {
            return back()->with('error', 'Tagihan ini sudah lunas.');
        }

        $tagihanRemidi->update([
            'status' => TagihanRemidi::LUNAS,
            'alasan_tolak' => null,
            'diverifikasi_oleh' => $request->user()->id,
            'diverifikasi_at' => now(),
        ]);

        return back()->with('success', 'Tagihan remidi ditandai lunas.');
    }

    public function tolak(Request $request, TagihanRemidi $tagihanRemidi): RedirectResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']], attributes: ['alasan' => 'Alasan']);

        if ($tagihanRemidi->status !== TagihanRemidi::MENUNGGU) {
            return back()->with('error', 'Hanya bukti yang menunggu verifikasi yang bisa ditolak.');
        }

        $tagihanRemidi->update([
            'status' => TagihanRemidi::DITOLAK,
            'alasan_tolak' => $data['alasan'],
            'diverifikasi_oleh' => $request->user()->id,
            'diverifikasi_at' => now(),
        ]);

        return back()->with('success', 'Bukti bayar ditolak. Mahasiswa bisa mengunggah ulang sebelum batas bayar.');
    }

    /**
     * @return array<string, int>
     */
    private function ringkasan(?TahunAkademik $tahunAkademik): array
    {
        $taId = $tahunAkademik?->id;
        $kelas = KelasKuliah::query()->where('tahun_akademik_id', $taId);
        $perStatus = TagihanRemidi::query()
            ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $taId))
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return [
            'kelas_dikunci' => (clone $kelas)->whereNotNull('remidi_dikunci_at')->count(),
            // Kelas yang nilainya sudah final tapi daftar remidinya belum dikunci dosen.
            'kelas_belum_kunci' => (clone $kelas)->whereNull('remidi_dikunci_at')
                ->when(! ($tahunAkademik?->batas_input_nilai?->copy()->endOfDay()->isPast() ?? false), fn (Builder $q) => $q->where(fn (Builder $w) => $w
                    ->whereNotNull('nilai_final_at')->orWhere('nilai_dibuka_sampai', '<', now()->toDateString())))
                ->count(),
            'belum_ditagih' => RemidiPeserta::query()->whereDoesntHave('tagihan')
                ->whereHas('kelasKuliah', fn (Builder $q) => $q->where('tahun_akademik_id', $taId)->whereNotNull('remidi_dikunci_at'))->count(),
            'belum_bayar' => (int) ($perStatus[TagihanRemidi::BELUM_BAYAR] ?? 0),
            'menunggu' => (int) ($perStatus[TagihanRemidi::MENUNGGU] ?? 0),
            'ditolak' => (int) ($perStatus[TagihanRemidi::DITOLAK] ?? 0),
            'lunas' => (int) ($perStatus[TagihanRemidi::LUNAS] ?? 0),
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
