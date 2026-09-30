<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\VerifikasiBuktiBayar;
use App\Http\Controllers\Controller;
use App\Models\JenisBiaya;
use App\Models\Krs;
use App\Models\KrsSemester;
use App\Models\MahasiswaProfile;
use App\Models\ProgramStudi;
use App\Models\TagihanSemester;
use App\Models\TahunAkademik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tagihan semester: admin menerbitkan, mahasiswa mengunggah bukti bayar, admin menandai lunas atau menolak buktinya.
 */
class TagihanController extends Controller
{
    use VerifikasiBuktiBayar;

    /** Status filter untuk mahasiswa aktif yang belum punya tagihan pada tahun akademik terpilih. */
    private const BELUM_TERBIT = 'belum_terbit';

    /**
     * Daftar tagihan semester: seluruh mahasiswa aktif, beserta tagihannya pada tahun akademik terpilih.
     */
    public function index(Request $request): Response
    {
        $tahunAkademik = $this->tahunAkademikTerpilih($request);
        $prodiId = $request->integer('prodi_id') ?: null;
        $angkatan = $request->integer('angkatan') ?: null;
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();

        $daftar = $this->kueriMahasiswa($tahunAkademik?->id)
            ->when($prodiId, fn (Builder $query) => $query->where('prodi_id', $prodiId))
            ->when($angkatan, fn (Builder $query) => $query->where('angkatan', $angkatan))
            ->when($status === self::BELUM_TERBIT, fn (Builder $query) => $query->whereDoesntHave('tagihan', fn (Builder $q) => $q->where('tahun_akademik_id', $tahunAkademik?->id)))
            ->when(
                in_array($status, [TagihanSemester::BELUM_BAYAR, TagihanSemester::MENUNGGU, TagihanSemester::DITOLAK, TagihanSemester::LUNAS], true),
                fn (Builder $query) => $query->whereHas('tagihan', fn (Builder $q) => $q->where('tahun_akademik_id', $tahunAkademik?->id)->where('status', $status)),
            )
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('nim', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))))
            ->orderBy('nim')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MahasiswaProfile $mahasiswa): array => $this->baris($mahasiswa, $tahunAkademik));

        return Inertia::render('Admin/Tagihan', [
            'daftar' => $daftar,
            'ringkasan' => TagihanSemester::ringkasan($tahunAkademik?->id),
            'filter' => [
                'tahun_akademik_id' => $tahunAkademik?->id,
                'prodi_id' => $prodiId,
                'angkatan' => $angkatan,
                'status' => $status ?: 'all',
                'search' => $search,
            ],
            'tahunAkademikOptions' => TahunAkademik::query()->orderByDesc('tanggal_mulai')->get()
                ->map(fn (TahunAkademik $ta): array => ['id' => $ta->id, 'name' => $ta->tahun.' '.$ta->semester])->all(),
            'prodiOptions' => ProgramStudi::query()->orderBy('nama_prodi')->get()
                ->map(fn (ProgramStudi $prodi): array => ['id' => $prodi->id, 'name' => $prodi->jenjang.' '.$prodi->nama_prodi])->all(),
            'angkatanOptions' => MahasiswaProfile::query()->whereNotNull('angkatan')->distinct()->orderByDesc('angkatan')->pluck('angkatan')->all(),
            'adaJenisBiaya' => JenisBiaya::query()->where('aktif', true)->where('kategori', JenisBiaya::SEMESTER)->exists(),
        ]);
    }

    /**
     * Terbitkan tagihan untuk semua mahasiswa aktif pada satu tahun akademik.
     * Tagihan yang sudah ada hanya dihitung ulang bila belum dibayar, belum ada bukti, dan rinciannya
     * tidak diketik admin. Mahasiswa tanpa tarif yang cocok (total nol) tidak ditagih.
     */
    public function terbitkan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
        ], attributes: ['tahun_akademik_id' => 'Tahun akademik']);

        $jenisBiaya = JenisBiaya::query()->where('aktif', true)->where('kategori', JenisBiaya::SEMESTER)->with('tarif')->orderBy('urutan')->get();

        if ($jenisBiaya->isEmpty()) {
            return back()->with('error', 'Belum ada jenis biaya aktif. Isi dulu di menu Jenis Biaya.');
        }

        // Biaya per SKS memakai kuota SKS, dan kuota itu ditentukan IPS semester sebelumnya.
        // Menerbitkan tagihan sebelum nilai lengkap membuat sebagian mahasiswa memakai kuota
        // "tanpa IPS" yang lebih kecil, jadi admin diperingatkan lebih dulu.
        $nilaiBelumLengkap = $this->nilaiBelumLengkap($data['tahun_akademik_id']);

        if ($nilaiBelumLengkap > 0 && ! $request->boolean('paksa')) {
            return back()->with('tagihan_konfirmasi', "Masih ada {$nilaiBelumLengkap} nilai semester sebelumnya yang belum diisi. Kuota SKS sebagian mahasiswa akan memakai angka \"tanpa IPS\". Terbitkan sekarang, atau lengkapi nilainya dulu.");
        }

        $tahunAkademik = TahunAkademik::query()->findOrFail($data['tahun_akademik_id']);
        $hasil = ['baru' => 0, 'ulang' => 0, 'dilewati' => 0, 'tanpa_tarif' => 0];

        DB::transaction(function () use ($tahunAkademik, $jenisBiaya, $request, &$hasil): void {
            MahasiswaProfile::query()
                ->where('status', 'Aktif')
                ->with(['tagihan' => fn ($query) => $query->where('tahun_akademik_id', $tahunAkademik->id)])
                ->chunkById(100, function ($mahasiswas) use ($tahunAkademik, $jenisBiaya, $request, &$hasil): void {
                    foreach ($mahasiswas as $mahasiswa) {
                        $tagihan = $mahasiswa->tagihan->first();

                        if ($tagihan !== null && ! $tagihan->bolehDihitungUlang()) {
                            $hasil['dilewati']++;

                            continue;
                        }

                        $rincian = TagihanSemester::hitungRincian($mahasiswa, $jenisBiaya, $tahunAkademik);

                        if (array_sum(array_column($rincian, 'subtotal')) === 0) {
                            // Tagihan lama yang kini tanpa tarif ikut dihapus; belum ada bukti yang menempel.
                            $tagihan?->delete();
                            $hasil['tanpa_tarif']++;

                            continue;
                        }

                        $hasil[$tagihan === null ? 'baru' : 'ulang']++;
                        $tagihan ??= new TagihanSemester([
                            'mahasiswa_id' => $mahasiswa->id,
                            'tahun_akademik_id' => $tahunAkademik->id,
                            'status' => TagihanSemester::BELUM_BAYAR,
                        ]);
                        $tagihan->fill(['diubah_oleh' => $request->user()->id])->save();
                        $tagihan->gantiRincian($rincian, false);
                    }
                });
        });

        $pesan = "{$hasil['baru']} tagihan baru diterbitkan, {$hasil['ulang']} dihitung ulang.";

        if ($hasil['dilewati'] > 0) {
            $pesan .= " {$hasil['dilewati']} tidak disentuh karena sudah lunas, sudah ada bukti bayar, atau rinciannya diketik manual.";
        }

        if ($hasil['tanpa_tarif'] > 0) {
            $pesan .= " {$hasil['tanpa_tarif']} mahasiswa tanpa tarif yang cocok, jadi tidak ditagih.";
        }

        return back()->with('success', $pesan);
    }

    /**
     * Tandai lunas, dengan atau tanpa bukti (mis. dibayar di loket). Hanya untuk tagihan yang sudah terbit.
     */
    public function lunas(Request $request, TagihanSemester $tagihanSemester): RedirectResponse
    {
        $tagihanSemester->diubah_oleh = $request->user()->id;

        return $this->prosesTandaiLunas($request, $tagihanSemester, 'Tagihan '.$tagihanSemester->mahasiswa?->user?->name.' ditandai lunas.');
    }

    public function tolak(Request $request, TagihanSemester $tagihanSemester): RedirectResponse
    {
        $tagihanSemester->diubah_oleh = $request->user()->id;

        return $this->prosesTolakBukti($request, $tagihanSemester);
    }

    /**
     * Batalkan status lunas, mis. salah tandai. Bila ada bukti, tagihan kembali menunggu verifikasi.
     */
    public function batalLunas(Request $request, TagihanSemester $tagihanSemester): RedirectResponse
    {
        if (! $tagihanSemester->lunas()) {
            return back()->with('error', 'Tagihan ini memang belum lunas.');
        }

        $tagihanSemester->update([
            'status' => $tagihanSemester->bukti === null ? TagihanSemester::BELUM_BAYAR : TagihanSemester::MENUNGGU,
            'diverifikasi_oleh' => null,
            'diverifikasi_at' => null,
            'diubah_oleh' => $request->user()->id,
        ]);

        return back()->with('success', 'Status lunas '.$tagihanSemester->mahasiswa?->user?->name.' dibatalkan.');
    }

    /**
     * Admin mengunggah bukti yang diserahkan mahasiswa (mis. kuitansi loket); tagihan langsung lunas.
     */
    public function unggahBukti(Request $request, TagihanSemester $tagihanSemester): RedirectResponse
    {
        $tagihanSemester->diubah_oleh = $request->user()->id;
        $respons = $this->prosesUnggahBukti($request, $tagihanSemester, 'semester', 'bukti-bayar');

        if ($tagihanSemester->status !== TagihanSemester::MENUNGGU) {
            return $respons;
        }

        $tagihanSemester->update([
            'status' => TagihanSemester::LUNAS,
            'diverifikasi_oleh' => $request->user()->id,
            'diverifikasi_at' => now(),
        ]);

        return back()->with('success', 'Bukti bayar disimpan dan tagihan ditandai lunas.');
    }

    /**
     * Rincian tagihan satu mahasiswa untuk ditampilkan di dialog.
     */
    public function rincian(Request $request, MahasiswaProfile $mahasiswa): Response
    {
        $tahunAkademik = $this->tahunAkademikTerpilih($request);

        $tagihan = TagihanSemester::query()
            ->with(['items', 'verifikator:id,name'])
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $tahunAkademik?->id)
            ->first();

        return Inertia::render('Admin/TagihanRincian', [
            'mahasiswa' => [
                'id' => $mahasiswa->id,
                'nama' => $mahasiswa->user?->name,
                'nim' => $mahasiswa->nim,
                'prodi' => $mahasiswa->prodi?->nama_prodi,
                'angkatan' => $mahasiswa->angkatan,
            ],
            'tahunAkademik' => $tahunAkademik ? $tahunAkademik->tahun.' '.$tahunAkademik->semester : null,
            'tagihan' => $tagihan ? [
                'id' => $tagihan->id,
                'status' => $tagihan->status,
                'total' => $tagihan->total,
                'rincian_manual' => $tagihan->rincian_manual,
                'tanggal_lunas' => $tagihan->tanggal_lunas?->toDateString(),
                'ada_bukti' => $tagihan->bukti !== null,
                'bukti_diunggah_at' => $tagihan->bukti_diunggah_at?->toIso8601String(),
                'alasan_tolak' => $tagihan->alasan_tolak,
                'diverifikasi_oleh' => $tagihan->verifikator?->name,
                'diverifikasi_at' => $tagihan->diverifikasi_at?->toIso8601String(),
                'items' => $tagihan->items->map(fn ($item): array => $item->only(['nama', 'cara_hitung', 'nominal_satuan', 'jumlah', 'subtotal']))->all(),
            ] : null,
            'sks' => $tahunAkademik ? TagihanSemester::sksDiambil($mahasiswa->id, $tahunAkademik->id) : 0,
            'kuota' => $tahunAkademik ? TagihanSemester::kuotaSks($mahasiswa, $tahunAkademik) : 0,
            'krsTersimpan' => $tahunAkademik !== null && KrsSemester::tersimpan($mahasiswa->id, $tahunAkademik->id),
            'tahunAkademikId' => $tahunAkademik?->id,
        ]);
    }

    /**
     * Buka kunci KRS mahasiswa agar bisa memperbaiki pilihan kelasnya sendiri.
     * Dipakai untuk kasus salah ambil mata kuliah, yang tidak bisa ditolong form pindah kelas.
     */
    public function bukaKunciKrs(Request $request, MahasiswaProfile $mahasiswa): RedirectResponse
    {
        $data = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
        ], attributes: ['tahun_akademik_id' => 'Tahun akademik']);

        $dihapus = KrsSemester::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->where('tahun_akademik_id', $data['tahun_akademik_id'])
            ->delete();

        if ($dihapus === 0) {
            return back()->with('error', 'KRS mahasiswa ini memang belum dikunci.');
        }

        return back()->with('success', 'Kunci KRS '.$mahasiswa->user?->name.' dibuka. Mahasiswa bisa mengubah KRS selama periode masih berjalan.');
    }

    /**
     * Simpan rincian tagihan yang diketik admin. Total dihitung ulang dari rinciannya, dan tagihan ditandai
     * manual agar tidak ditimpa saat diterbitkan ulang. Tagihan yang belum terbit ikut dibuat.
     */
    public function simpanRincian(Request $request, MahasiswaProfile $mahasiswa): RedirectResponse
    {
        $data = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.nama' => ['required', 'string', 'max:255'],
            'items.*.subtotal' => ['required', 'integer', 'min:0', 'max:9999999999'],
        ], [
            'items.required' => 'Isi minimal satu komponen tagihan.',
            'items.min' => 'Isi minimal satu komponen tagihan.',
        ], [
            'items.*.nama' => 'Nama komponen',
            'items.*.subtotal' => 'Nominal',
        ]);

        if (array_sum(array_column($data['items'], 'subtotal')) === 0) {
            return back()->with('error', 'Total tagihan tidak boleh nol. Untuk membebaskan biaya, tandai lunas tagihannya.');
        }

        $tagihan = TagihanSemester::query()->firstOrNew(
            ['mahasiswa_id' => $mahasiswa->id, 'tahun_akademik_id' => $data['tahun_akademik_id']],
            ['status' => TagihanSemester::BELUM_BAYAR],
        );

        // Tagihan hanya untuk mahasiswa Aktif (Cuti, Lulus, dst. tidak ditagih); yang sudah terbit tetap bisa diubah.
        if (! $tagihan->exists && $mahasiswa->status !== 'Aktif') {
            return back()->with('error', 'Mahasiswa berstatus '.$mahasiswa->status.' tidak ditagih.');
        }

        if ($tagihan->lunas()) {
            return back()->with('error', 'Tagihan yang sudah lunas tidak bisa diubah rinciannya. Batalkan status lunasnya dulu.');
        }

        DB::transaction(function () use ($data, $request, $tagihan): void {
            $tagihan->fill(['diubah_oleh' => $request->user()->id])->save();
            $tagihan->gantiRincian(array_map(fn (array $item): array => [
                'nama' => $item['nama'],
                'cara_hitung' => JenisBiaya::TETAP,
                'nominal_satuan' => $item['subtotal'],
                'jumlah' => 1,
                'subtotal' => $item['subtotal'],
            ], $data['items']), true);
        });

        return back()->with('success', 'Rincian tagihan disimpan. Rincian ini tidak ditimpa saat tagihan diterbitkan ulang.');
    }

    /**
     * Jumlah baris KRS tanpa nilai pada semester sebelum tahun akademik terpilih.
     */
    private function nilaiBelumLengkap(int $tahunAkademikId): int
    {
        $tahunAkademik = TahunAkademik::query()->find($tahunAkademikId);

        if ($tahunAkademik?->tanggal_mulai === null) {
            return 0;
        }

        $sebelumnya = TahunAkademik::query()
            ->whereNotNull('tanggal_mulai')
            ->where('tanggal_mulai', '<', $tahunAkademik->tanggal_mulai)
            ->orderByDesc('tanggal_mulai')
            ->first();

        if ($sebelumnya === null) {
            return 0;
        }

        return Krs::query()
            ->whereNull('nilai')
            ->whereHas('kelasKuliah', fn (Builder $query) => $query->where('tahun_akademik_id', $sebelumnya->id))
            // TA/Skripsi yang belum dinilai memang berlanjut ke semester berikutnya, bukan nilai yang tertinggal.
            ->whereHas('kelasKuliah.mataKuliah', fn (Builder $query) => $query->where('tugas_akhir', false))
            ->whereHas('mahasiswa', fn (Builder $query) => $query->where('status', 'Aktif'))
            ->count();
    }

    private function tahunAkademikTerpilih(Request $request): ?TahunAkademik
    {
        $id = $request->integer('tahun_akademik_id') ?: null;

        return $id
            ? TahunAkademik::query()->find($id)
            : TahunAkademik::query()->where('status', true)->first() ?? TahunAkademik::query()->orderByDesc('id')->first();
    }

    private function kueriMahasiswa(?int $tahunAkademikId): Builder
    {
        return MahasiswaProfile::query()
            ->where('status', 'Aktif')
            ->with(['user:id,name', 'prodi:id,nama_prodi,jenjang'])
            ->with(['tagihan' => fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId)->with(['editor:id,name', 'verifikator:id,name'])])
            ->with(['krsSemester' => fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId)])
            ->whereHas('user');
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(MahasiswaProfile $mahasiswa, ?TahunAkademik $tahunAkademik): array
    {
        $tagihan = $mahasiswa->tagihan->first();

        return [
            'id' => $mahasiswa->id,
            'nama' => $mahasiswa->user?->name,
            'nim' => $mahasiswa->nim,
            'prodi' => $mahasiswa->prodi?->nama_prodi,
            'angkatan' => $mahasiswa->angkatan,
            'semester' => $mahasiswa->semesterPada($tahunAkademik),
            'tagihan_id' => $tagihan?->id,
            'status' => $tagihan?->status ?? self::BELUM_TERBIT,
            'total' => $tagihan?->total ?? 0,
            'rincian_manual' => (bool) $tagihan?->rincian_manual,
            'tanggal_lunas' => $tagihan?->tanggal_lunas?->toDateString(),
            'ada_bukti' => $tagihan?->bukti !== null,
            'bukti_diunggah_at' => $tagihan?->bukti_diunggah_at?->toIso8601String(),
            'alasan_tolak' => $tagihan?->alasan_tolak,
            'diverifikasi_oleh' => $tagihan?->verifikator?->name,
            'diubah_oleh' => $tagihan?->editor?->name,
            'diubah_pada' => $tagihan?->updated_at?->toDateTimeString(),
            'krs_tersimpan' => $mahasiswa->krsSemester->isNotEmpty(),
        ];
    }
}
