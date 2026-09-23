<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JenisBiaya;
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

class TagihanController extends Controller
{
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
            ->when($status === TagihanSemester::LUNAS, fn (Builder $query) => $query->whereHas('tagihan', fn (Builder $q) => $q->where('status', TagihanSemester::LUNAS)))
            ->when($status === TagihanSemester::BELUM_BAYAR, fn (Builder $query) => $query->whereDoesntHave('tagihan', fn (Builder $q) => $q->where('status', TagihanSemester::LUNAS)))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('nim', 'like', "%{$search}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))))
            ->orderBy('nim')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MahasiswaProfile $mahasiswa): array => $this->baris($mahasiswa));

        return Inertia::render('Admin/Tagihan', [
            'daftar' => $daftar,
            'ringkasan' => $this->ringkasan($tahunAkademik?->id),
            'filter' => [
                'tahun_akademik_id' => $tahunAkademik?->id,
                'prodi_id' => $prodiId,
                'angkatan' => $angkatan,
                'status' => $status ?: 'all',
                'search' => $search,
            ],
            'tahunAkademikOptions' => TahunAkademik::query()->orderByDesc('tahun')->orderBy('semester')->get()
                ->map(fn (TahunAkademik $ta): array => ['id' => $ta->id, 'name' => $ta->tahun.' '.$ta->semester])->all(),
            'prodiOptions' => ProgramStudi::query()->orderBy('nama_prodi')->get()
                ->map(fn (ProgramStudi $prodi): array => ['id' => $prodi->id, 'name' => $prodi->jenjang.' '.$prodi->nama_prodi])->all(),
            'angkatanOptions' => MahasiswaProfile::query()->whereNotNull('angkatan')->distinct()->orderByDesc('angkatan')->pluck('angkatan')->all(),
            'adaJenisBiaya' => JenisBiaya::query()->where('aktif', true)->exists(),
        ]);
    }

    /**
     * Terbitkan tagihan untuk semua mahasiswa aktif pada satu tahun akademik.
     * Tagihan yang sudah lunas tidak disentuh agar nominalnya tidak berubah setelah dibayar.
     */
    public function terbitkan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
        ], attributes: ['tahun_akademik_id' => 'Tahun akademik']);

        $jenisBiaya = JenisBiaya::query()->where('aktif', true)->with('tarif')->orderBy('urutan')->get();

        if ($jenisBiaya->isEmpty()) {
            return back()->with('error', 'Belum ada jenis biaya aktif. Isi dulu di menu Jenis Biaya.');
        }

        $jumlah = 0;

        DB::transaction(function () use ($data, $jenisBiaya, $request, &$jumlah): void {
            MahasiswaProfile::query()->where('status', 'Aktif')->chunkById(100, function ($mahasiswas) use ($data, $jenisBiaya, $request, &$jumlah): void {
                foreach ($mahasiswas as $mahasiswa) {
                    $tagihan = TagihanSemester::query()->firstOrNew([
                        'mahasiswa_id' => $mahasiswa->id,
                        'tahun_akademik_id' => $data['tahun_akademik_id'],
                    ]);

                    if ($tagihan->exists && $tagihan->lunas()) {
                        continue;
                    }

                    $tagihan->fill(['status' => TagihanSemester::BELUM_BAYAR, 'diubah_oleh' => $request->user()->id])->save();
                    $tagihan->susunRincian($mahasiswa, $jenisBiaya);
                    $jumlah++;
                }
            });
        });

        return back()->with('success', $jumlah.' tagihan diterbitkan atau dihitung ulang.');
    }

    /**
     * Ubah status pembayaran satu mahasiswa pada satu tahun akademik.
     */
    public function ubahStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mahasiswa_id' => ['required', 'integer', Rule::exists('mahasiswa_profiles', 'id')],
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'status' => ['required', Rule::in([TagihanSemester::BELUM_BAYAR, TagihanSemester::LUNAS])],
        ], attributes: ['status' => 'Status pembayaran']);

        $mahasiswa = MahasiswaProfile::query()->findOrFail($data['mahasiswa_id']);

        $tagihan = TagihanSemester::query()->firstOrNew([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $data['tahun_akademik_id'],
        ]);

        // Menandai lunas tanpa tagihan terbit tetap dibolehkan (mis. mahasiswa bayar sebelum
        // tagihan disusun); rinciannya menyusul saat tagihan diterbitkan.
        $tagihan->fill([
            'status' => $data['status'],
            'tanggal_lunas' => $data['status'] === TagihanSemester::LUNAS ? now()->toDateString() : null,
            'diubah_oleh' => $request->user()->id,
        ])->save();

        return back()->with('success', 'Status pembayaran '.$mahasiswa->user?->name.' diperbarui.');
    }

    /**
     * Rincian tagihan satu mahasiswa untuk ditampilkan di dialog.
     */
    public function rincian(Request $request, MahasiswaProfile $mahasiswa): Response
    {
        $tahunAkademik = $this->tahunAkademikTerpilih($request);

        $tagihan = TagihanSemester::query()
            ->with('items')
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
            'tagihan' => $tagihan,
            'sks' => $tahunAkademik ? TagihanSemester::sksDiambil($mahasiswa->id, $tahunAkademik->id) : 0,
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
     * Simpan rincian tagihan yang diketik admin. Total dihitung ulang dari rinciannya.
     */
    public function simpanRincian(Request $request, MahasiswaProfile $mahasiswa): RedirectResponse
    {
        $data = $request->validate([
            'tahun_akademik_id' => ['required', 'integer', Rule::exists('tahun_akademik', 'id')],
            'items' => ['array'],
            'items.*.nama' => ['required', 'string', 'max:255'],
            'items.*.subtotal' => ['required', 'integer', 'min:0', 'max:9999999999'],
        ], attributes: [
            'items.*.nama' => 'Nama komponen',
            'items.*.subtotal' => 'Nominal',
        ]);

        $tagihan = TagihanSemester::query()->firstOrNew([
            'mahasiswa_id' => $mahasiswa->id,
            'tahun_akademik_id' => $data['tahun_akademik_id'],
        ]);

        DB::transaction(function () use ($data, $request, $tagihan): void {
            $tagihan->fill(['diubah_oleh' => $request->user()->id])->save();
            $tagihan->items()->delete();

            $total = 0;

            foreach ($data['items'] ?? [] as $item) {
                $total += $item['subtotal'];
                $tagihan->items()->create([
                    'nama' => $item['nama'],
                    'cara_hitung' => JenisBiaya::TETAP,
                    'nominal_satuan' => $item['subtotal'],
                    'jumlah' => 1,
                    'subtotal' => $item['subtotal'],
                ]);
            }

            $tagihan->forceFill(['total' => $total])->save();
        });

        return back()->with('success', 'Rincian tagihan disimpan.');
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
            ->with(['tagihan' => fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId)->with('editor:id,name')])
            ->with(['krsSemester' => fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId)])
            ->whereHas('user');
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(MahasiswaProfile $mahasiswa): array
    {
        $tagihan = $mahasiswa->tagihan->first();

        return [
            'id' => $mahasiswa->id,
            'nama' => $mahasiswa->user?->name,
            'nim' => $mahasiswa->nim,
            'prodi' => $mahasiswa->prodi?->nama_prodi,
            'angkatan' => $mahasiswa->angkatan,
            'semester' => $mahasiswa->semester,
            'status' => $tagihan?->status ?? TagihanSemester::BELUM_BAYAR,
            'total' => $tagihan?->total ?? 0,
            'ada_tagihan' => (bool) $tagihan,
            'tanggal_lunas' => $tagihan?->tanggal_lunas?->toDateString(),
            'diubah_oleh' => $tagihan?->editor?->name,
            'diubah_pada' => $tagihan?->updated_at?->toDateTimeString(),
            'krs_tersimpan' => $mahasiswa->krsSemester->isNotEmpty(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function ringkasan(?int $tahunAkademikId): array
    {
        $total = MahasiswaProfile::query()->where('status', 'Aktif')->whereHas('user')->count();
        $lunas = TagihanSemester::query()
            ->where('tahun_akademik_id', $tahunAkademikId)
            ->where('status', TagihanSemester::LUNAS)
            ->whereHas('mahasiswa', fn (Builder $query) => $query->where('status', 'Aktif'))
            ->count();
        $terbit = TagihanSemester::query()
            ->where('tahun_akademik_id', $tahunAkademikId)
            ->whereHas('mahasiswa', fn (Builder $query) => $query->where('status', 'Aktif'))
            ->count();

        return ['total' => $total, 'lunas' => $lunas, 'belum_bayar' => $total - $lunas, 'terbit' => $terbit];
    }
}
