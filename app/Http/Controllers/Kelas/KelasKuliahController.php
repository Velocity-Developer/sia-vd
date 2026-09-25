<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MataKuliah;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\SkalaNilai;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use App\UsulanRemidi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class KelasKuliahController extends Controller
{
    use KontenKelas;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $tahunAkademikId = $this->filterTahunAkademikId($request);
        $mataKuliahId = $request->integer('mata_kuliah_id') ?: null;
        $dosenId = $this->peran() === 'dosen' ? $request->user()->dosenProfile?->id : ($request->integer('dosen_id') ?: null);

        $kelasKuliahs = KelasKuliah::with(['tahunAkademik:id,tahun,semester', 'dosen:id,user_id,nidn', 'dosen.user:id,name', 'mataKuliah:id,kode_matkul,nama_matkul,prodi_id', 'mataKuliah.prodi:id,nama_prodi', 'jadwals:id,kelas_id,hari,jam_mulai,jam_akhir,ruang_id', 'jadwals.ruang:id,kode_ruang,nama_ruang'])
            ->when($tahunAkademikId !== null, fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId))
            ->when($mataKuliahId !== null, fn ($query) => $query->where('matkul_id', $mataKuliahId))
            ->when($dosenId !== null, fn ($query) => $query->where('dosen_id', $dosenId))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('kode_kelas', 'like', "%{$search}%")->orWhereHas('tahunAkademik', fn ($q) => $q->where('tahun', 'like', "%{$search}%")->orWhere('semester', 'like', "%{$search}%"))->orWhereHas('mataKuliah', fn ($q) => $q->where('kode_matkul', 'like', "%{$search}%")->orWhere('nama_matkul', 'like', "%{$search}%"))))
            ->orderBy('kode_kelas')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Kelas/KelasKuliahIndex', [
            'peran' => $this->peran(),
            'kelasKuliahs' => $kelasKuliahs,
            'search' => $search,
            'tahunAkademiks' => $this->tahunAkademiks(),
            'tahunAkademikId' => $tahunAkademikId,
            'mataKuliahId' => $mataKuliahId,
            'mataKuliahOptions' => $this->mataKuliahOptions($tahunAkademikId, $this->peran() === 'dosen' ? $dosenId : null),
            'dosenId' => $this->peran() === 'admin' ? $dosenId : null,
            'dosenOptions' => $this->peran() === 'admin' ? $this->dosenOptions($tahunAkademikId) : [],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/KelasKuliahForm', [
            'kelasKuliah' => null,
            'jumlahPertemuanBawaan' => PengaturanAkademik::current()->jumlah_pertemuan,
            'dosens' => $this->dosens(),
            'matkulGroups' => $this->matkulGroups(),
            'tahunAkademiks' => TahunAkademik::orderByDesc('tahun')->orderBy('semester')->get(),
        ]);
    }

    public function show(KelasKuliah $kelasKuliah): Response
    {
        $this->pastikanAksesKelas($kelasKuliah);
        $kelasKuliah->load(['tahunAkademik', 'dosen.user', 'mataKuliah.prodi.fakultas', 'jadwals.ruang', 'materis.uploader:id,name', 'tugas.uploader:id,name', 'quizzes.uploader:id,name', 'krs.mahasiswa:id,user_id,nim,prodi_id', 'krs.mahasiswa.user:id,name', 'krs.mahasiswa.prodi:id,nama_prodi']);

        return Inertia::render('Kelas/KelasKuliahShow', [
            'peran' => $this->peran(),
            'kelasKuliah' => $kelasKuliah,
            // Target duplikasi: kelas lain di tahun akademik yang sama, hanya kolom yang ditampilkan di modal.
            'otherClasses' => KelasKuliah::query()
                ->where('tahun_akademik_id', $kelasKuliah->tahun_akademik_id)
                ->whereKeyNot($kelasKuliah->id)
                ->with('mataKuliah:id,nama_matkul')
                ->orderBy('kode_kelas')
                ->get(['id', 'kode_kelas', 'matkul_id'])
                ->map(fn (KelasKuliah $kelas): array => ['id' => $kelas->id, 'kode_kelas' => $kelas->kode_kelas, 'nama_matkul' => $kelas->mataKuliah?->nama_matkul]),
            'skalaNilai' => SkalaNilai::huruf(),
            'nilaiTerkunci' => $this->nilaiTerkunci($kelasKuliah),
            'statusNilai' => $this->statusNilai($kelasKuliah),
            // Peserta remidi yang huruf akhirnya boleh diubah dosen walau kelas final, dan huruf yang boleh dipilih.
            'remidiTerbuka' => $this->nilaiTerkunci($kelasKuliah) && ! $this->tahunAkademikTerkunci($kelasKuliah) ? $kelasKuliah->mahasiswaRemidiTerbuka() : [],
            'hurufRemidi' => SkalaNilai::hurufSampai(PengaturanAkademik::current()->huruf_maks_remidi),
            'remidi' => $kelasKuliah->nilaiFinal() ? [
                ...$this->infoUjianRemidi($kelasKuliah),
                'dikunci_at' => $kelasKuliah->remidi_dikunci_at?->toIso8601String(),
                'dikunci_oleh' => $kelasKuliah->remidi_dikunci_at !== null ? $kelasKuliah->remidiDikunciOleh()->value('name') : null,
                ...UsulanRemidi::susun($kelasKuliah),
            ] : null,
        ]);
    }

    /**
     * Kunci nilai kelas: setelah difinalisasi dosen tidak bisa lagi mengubah nilai apa pun di kelas ini.
     */
    public function finalisasiNilai(KelasKuliah $kelasKuliah): RedirectResponse
    {
        $this->pastikanAksesKelas($kelasKuliah);

        if (($pesan = $this->pesanNilaiTerkunci($kelasKuliah)) !== null || $kelasKuliah->nilai_final_at !== null) {
            return back()->with('error', $pesan ?? 'Nilai kelas ini sudah difinalisasi.');
        }

        $uas = Ujian::query()->where('kelas_id', $kelasKuliah->id)->where('jenis', Pertemuan::UAS)->terbit()->first();

        if ($uas !== null && ! $uas->sudahSelesai()) {
            return back()->with('error', 'Nilai baru bisa difinalisasi setelah UAS kelas ini selesai.');
        }

        $kelasKuliah->finalisasiNilai(request()->user());

        return back()->with('success', 'Nilai kelas berhasil difinalisasi dan kini terkunci.');
    }

    /**
     * Admin membuka kembali kunci nilai. Bila batas input nilai tahun akademik sudah lewat, dosen perlu batas baru.
     */
    public function bukaKunciNilai(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $batasTahun = $kelasKuliah->loadMissing('tahunAkademik')->tahunAkademik?->batas_input_nilai;
        $perluBatasBaru = $batasTahun !== null && $batasTahun->copy()->endOfDay()->isPast();

        $validated = $request->validate(
            ['sampai' => [$perluBatasBaru ? 'required' : 'nullable', 'date', 'after_or_equal:today']],
            ['sampai.required' => 'Batas input nilai tahun akademik sudah lewat, isi tanggal batas baru untuk kelas ini.'],
            ['sampai' => 'batas baru'],
        );

        $kelasKuliah->bukaKunciNilai($validated['sampai'] ?? null);

        return back()->with('success', 'Kunci nilai kelas dibuka. Dosen bisa mengubah nilai kembali.');
    }

    public function updateGrade(Request $request, KelasKuliah $kelasKuliah, Krs $krs): RedirectResponse
    {
        abort_if($krs->kelas_id !== $kelasKuliah->id, 404);
        $this->pastikanAksesKelas($kelasKuliah);

        $pesan = $this->pesanNilaiTerkunci($kelasKuliah);
        // Nilai kelas sudah final, tetapi huruf akhir peserta remidi yang lunas dibuka setelah ujian remidi selesai.
        $remidi = $pesan !== null && ! $this->tahunAkademikTerkunci($kelasKuliah)
            && in_array($krs->mahasiswa_id, $kelasKuliah->mahasiswaRemidiTerbuka(), true);

        if ($pesan !== null && ! $remidi) {
            return back()->with('error', $pesan);
        }

        $maks = $remidi ? PengaturanAkademik::current()->huruf_maks_remidi : null;
        $krs->update($request->validate(
            ['nilai' => [$remidi ? 'required' : 'nullable', Rule::in(SkalaNilai::hurufSampai($maks))]],
            ['nilai.in' => $maks !== null ? "Huruf akhir setelah remidi paling tinggi {$maks}." : 'Huruf nilai tidak dikenal.', 'nilai.required' => 'Pilih huruf akhir setelah remidi.'],
        ));

        return back()->with('success', 'Nilai berhasil diperbarui.');
    }

    /**
     * Batalkan KRS yang salah input. KRS yang sudah bernilai tidak bisa dibatalkan agar riwayat nilai tetap utuh.
     */
    public function destroyKrs(KelasKuliah $kelasKuliah, Krs $krs): RedirectResponse
    {
        abort_if($krs->kelas_id !== $kelasKuliah->id, 404);

        if (filled($krs->nilai)) {
            return back()->with('error', 'KRS yang sudah memiliki nilai tidak dapat dibatalkan. Kosongkan nilainya terlebih dahulu.');
        }

        $krs->cancel();

        return back()->with('success', 'KRS mahasiswa berhasil dibatalkan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function infoUjianRemidi(KelasKuliah $kelas): array
    {
        $ujian = $kelas->ujianRemidi();

        return [
            'ujian' => $ujian === null ? null : [
                'id' => $ujian->id,
                'tanggal' => $ujian->tanggal->toDateString(),
                'jam_mulai' => $ujian->jam_mulai,
                'jam_akhir' => $ujian->jam_akhir,
                'selesai' => $ujian->sudahSelesai(),
            ],
            'final_at' => $kelas->remidi_final_at?->toIso8601String(),
            'batas_nilai' => $kelas->tahunAkademik?->batas_input_nilai_remidi?->toDateString(),
            'jendela_terbuka' => $kelas->jendelaRemidiTerbuka(),
            'huruf_maks' => PengaturanAkademik::current()->huruf_maks_remidi,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusNilai(KelasKuliah $kelas): array
    {
        $uas = Ujian::query()->where('kelas_id', $kelas->id)->where('jenis', Pertemuan::UAS)->terbit()->first(['id', 'kelas_id', 'tanggal', 'jam_mulai', 'jam_akhir']);
        $batasTahun = $kelas->tahunAkademik?->batas_input_nilai;

        return [
            'final' => $kelas->nilaiFinal(),
            'final_at' => $kelas->nilai_final_at?->toIso8601String(),
            'final_oleh' => $kelas->nilai_final_at !== null ? $kelas->finalOleh()->value('name') : null,
            'batas' => $kelas->batasInputNilai()?->toDateString(),
            'batas_tahun_lewat' => $batasTahun !== null && $batasTahun->copy()->endOfDay()->isPast(),
            'uas_belum_selesai' => $uas !== null && ! $uas->sudahSelesai(),
            'tanpa_nilai' => $kelas->krs->whereNull('nilai')->count(),
        ];
    }

    public function edit(KelasKuliah $kelasKuliah): Response
    {
        return Inertia::render('Admin/KelasKuliahForm', [
            'kelasKuliah' => $kelasKuliah,
            'dosens' => $this->dosens(),
            'matkulGroups' => $this->matkulGroups(),
            'tahunAkademiks' => TahunAkademik::orderByDesc('tahun')->orderBy('semester')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->save($request, new KelasKuliah);

        return to_route($this->rute('kelas-kuliah.index'))->with('success', 'Kelas Kuliah berhasil ditambahkan.');
    }

    public function update(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $this->save($request, $kelasKuliah);

        return to_route($this->rute('kelas-kuliah.index'))->with('success', 'Kelas Kuliah berhasil diperbarui.');
    }

    public function destroy(KelasKuliah $kelasKuliah): RedirectResponse
    {
        if ($kelasKuliah->krs()->exists()) {
            return to_route($this->rute('kelas-kuliah.index'))->with('error', 'Kelas Kuliah tidak dapat dihapus karena sudah memiliki KRS mahasiswa.');
        }

        // Pertemuan yang belum pernah dipakai ikut terhapus; kelas dengan riwayat presensi dipertahankan.
        $adaPresensi = $kelasKuliah->pertemuans()
            ->where(fn ($query) => $query->whereIn('status', [Pertemuan::BERLANGSUNG, Pertemuan::SELESAI])->orWhereHas('presensiMahasiswas')->orWhereHas('pengajuanIzins'))
            ->exists();

        if ($adaPresensi || $kelasKuliah->dispensasiUjians()->exists()) {
            return to_route($this->rute('kelas-kuliah.index'))->with('error', 'Kelas Kuliah tidak dapat dihapus karena sudah memiliki data presensi (pertemuan berjalan, presensi, pengajuan izin, atau dispensasi).');
        }

        try {
            DB::transaction(function () use ($kelasKuliah): void {
                $kelasKuliah->pertemuans()->delete();
                $kelasKuliah->ujians()->delete();
                $kelasKuliah->delete();
            });
        } catch (Throwable) {
            return to_route($this->rute('kelas-kuliah.index'))->with('error', 'Kelas Kuliah gagal dihapus.');
        }

        return to_route($this->rute('kelas-kuliah.index'))->with('success', 'Kelas Kuliah berhasil dihapus.');
    }

    /**
     * Filter tahun akademik default ke tahun akademik yang sedang aktif.
     */
    private function filterTahunAkademikId(Request $request): ?int
    {
        if ($request->has('tahun_akademik_id')) {
            return $request->integer('tahun_akademik_id') ?: null;
        }

        return TahunAkademik::where('status', true)->value('id');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function tahunAkademiks(): array
    {
        return TahunAkademik::orderByDesc('tahun')->orderBy('semester')->get()
            ->map(fn (TahunAkademik $tahunAkademik): array => [
                'id' => $tahunAkademik->id,
                'name' => $tahunAkademik->tahun.' '.$tahunAkademik->semester,
            ])->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function mataKuliahOptions(?int $tahunAkademikId, ?int $dosenId = null): array
    {
        // Dosen hanya melihat mata kuliah dari kelas yang diampunya.
        return MataKuliah::whereHas('kelasKuliah', fn ($query) => $query
            ->when($tahunAkademikId !== null, fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId))
            ->when($dosenId !== null, fn ($query) => $query->where('dosen_id', $dosenId)))
            ->orderBy('kode_matkul')
            ->get(['id', 'kode_matkul', 'nama_matkul'])
            ->map(fn (MataKuliah $mataKuliah): array => [
                'id' => $mataKuliah->id,
                'name' => $mataKuliah->kode_matkul.' — '.$mataKuliah->nama_matkul,
            ])->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function dosenOptions(?int $tahunAkademikId): array
    {
        return DosenProfile::with('user:id,name')
            ->whereHas('kelasKuliah', fn ($query) => $query->when($tahunAkademikId !== null, fn ($query) => $query->where('tahun_akademik_id', $tahunAkademikId)))
            ->orderBy('nidn')
            ->get()
            ->map(fn (DosenProfile $dosen): array => [
                'id' => $dosen->id,
                'name' => ($dosen->user?->name ?? 'Tanpa nama').' — '.$dosen->nidn,
            ])->all();
    }

    private function dosens(): array
    {
        return DosenProfile::with('user:id,name')->orderBy('nidn')->get()->map(fn (DosenProfile $d): array => [
            'id' => $d->id,
            'name' => ($d->user?->name ?? '-').' — '.$d->nidn,
        ])->all();
    }

    /**
     * @return array<int, array{label: string, options: array<int, array{id: int, name: string}>}>
     */
    private function matkulGroups(): array
    {
        $mataKuliahs = MataKuliah::with('prodi:id,nama_prodi')->orderBy('kode_matkul')->get(['id', 'kode_matkul', 'nama_matkul', 'prodi_id']);
        $groups = [];
        foreach ($mataKuliahs as $mk) {
            $label = $mk->prodi?->nama_prodi ?? 'Program Studi Lainnya';
            $groups[$label][] = ['id' => $mk->id, 'name' => $mk->kode_matkul.' — '.$mk->nama_matkul];
        }

        $result = [];
        foreach ($groups as $label => $options) {
            $result[] = ['label' => $label, 'options' => $options];
        }

        return $result;
    }

    private function save(Request $request, KelasKuliah $model): void
    {
        $data = $request->validate([
            'kode_kelas' => ['required', 'string', 'max:50', Rule::unique('kelas_kuliah', 'kode_kelas')->where('tahun_akademik_id', $request->input('tahun_akademik_id'))->ignore($model)],
            'tahun_akademik_id' => ['required', 'exists:tahun_akademik,id'],
            'kapasitas' => ['required', 'integer', 'min:1', 'max:500'],
            'jumlah_pertemuan' => ['nullable', 'integer', 'min:1', 'max:32'],
            'dosen_id' => ['required', 'exists:dosen_profiles,id'],
            'matkul_id' => ['required', 'exists:mata_kuliahs,id'],
        ], $this->messages(), $this->attributes());

        // Mata kuliah dan tahun akademik ikut menentukan isi KHS/transkrip, jadi tidak boleh berubah
        // setelah ada mahasiswa yang mengambil kelas ini.
        if ($model->exists && $model->krs()->exists()) {
            foreach (['matkul_id' => 'Mata Kuliah', 'tahun_akademik_id' => 'Tahun Akademik'] as $kolom => $label) {
                if ((int) $data[$kolom] !== (int) $model->{$kolom}) {
                    throw ValidationException::withMessages([
                        $kolom => $label.' tidak dapat diubah karena kelas ini sudah memiliki KRS mahasiswa.',
                    ]);
                }
            }
        }

        // Jumlah pertemuan disimpan terpisah agar pertemuan berlebih ikut dirapikan (dan ditolak bila sudah berjalan).
        // Bila dikosongkan, kelas baru memakai bawaan Pengaturan Akademik dan kelas lama tidak berubah.
        $jumlahPertemuan = isset($data['jumlah_pertemuan']) ? (int) $data['jumlah_pertemuan'] : null;
        unset($data['jumlah_pertemuan']);

        DB::transaction(function () use ($model, $data, $jumlahPertemuan): void {
            if (! $model->exists) {
                $model->fill([...$data, 'jumlah_pertemuan' => $jumlahPertemuan])->save();

                return;
            }

            $model->fill($data)->save();

            if ($jumlahPertemuan !== null && $jumlahPertemuan !== $model->jumlah_pertemuan) {
                $model->ubahJumlahPertemuan($jumlahPertemuan);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'exists' => ':attribute tidak ditemukan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'kode_kelas' => 'Kode Kelas',
            'tahun_akademik_id' => 'Tahun Akademik',
            'kapasitas' => 'Kapasitas',
            'jumlah_pertemuan' => 'Jumlah Pertemuan',
            'dosen_id' => 'Dosen',
            'matkul_id' => 'Mata Kuliah',
        ];
    }
}
