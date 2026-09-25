<?php

namespace App\Http\Controllers\Admin;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\Pertemuan;
use App\Models\ProgramStudi;
use App\Models\RemidiPeserta;
use App\Models\Ruang;
use App\Models\TahunAkademik;
use App\Models\Ujian;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Jadwal ujian (UTS/UAS) seluruh kelas, disusun dan diterbitkan admin.
 */
class UjianController extends Controller
{
    public function index(Request $request): Response
    {
        $tahunAkademiks = TahunAkademik::orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester', 'status']);
        $filter = [
            'tahun_akademik_id' => $request->integer('tahun_akademik_id') ?: $tahunAkademiks->firstWhere('status', true)?->id,
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'jenis' => in_array($request->query('jenis'), Ujian::SEMUA_JENIS, true) ? $request->query('jenis') : null,
            'status' => in_array($request->query('status'), [Ujian::DRAF, Ujian::TERBIT], true) ? $request->query('status') : null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $ujians = Ujian::query()
            ->whereHas('kelasKuliah', fn (Builder $kelas) => $kelas
                ->where('tahun_akademik_id', $filter['tahun_akademik_id'])
                ->when($filter['prodi_id'], fn (Builder $q) => $q->whereHas('mataKuliah', fn (Builder $m) => $m->where('prodi_id', $filter['prodi_id'])))
                ->when($filter['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $cari) => $cari
                    ->where('kode_kelas', 'like', "%{$filter['search']}%")
                    ->orWhereHas('mataKuliah', fn (Builder $m) => $m->where('nama_matkul', 'like', "%{$filter['search']}%")->orWhere('kode_matkul', 'like', "%{$filter['search']}%")))))
            ->when($filter['jenis'], fn (Builder $q) => $q->where('jenis', $filter['jenis']))
            ->when($filter['status'], fn (Builder $q) => $q->where('status', $filter['status']))
            ->with(['kelasKuliah:id,kode_kelas,matkul_id,dosen_id', 'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul', 'kelasKuliah.dosen:id,user_id', 'kelasKuliah.dosen.user:id,name', 'ruang:id,kode_ruang'])
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->paginate(20)
            ->withQueryString();

        // Kelas di tahun ini yang belum punya jadwal UTS/UAS, sebagai pengingat bagi admin.
        $belumAda = collect(Ujian::JENIS)->mapWithKeys(fn (string $jenis): array => [$jenis => KelasKuliah::query()
            ->where('tahun_akademik_id', $filter['tahun_akademik_id'])
            ->whereDoesntHave('ujians', fn (Builder $q) => $q->where('jenis', $jenis))
            ->count()]);

        return Inertia::render('Admin/Ujian', [
            'ujians' => $ujians,
            'filter' => $filter,
            'belumAda' => $belumAda,
            // Kelas yang daftar remidinya sudah dikunci dan punya peserta lunas, tetapi belum dijadwalkan.
            'remidiSiap' => $this->kelasRemidiSiap($filter['tahun_akademik_id'])->count(),
            'jumlahDraf' => Ujian::query()->where('status', Ujian::DRAF)->whereHas('kelasKuliah', fn (Builder $k) => $k->where('tahun_akademik_id', $filter['tahun_akademik_id']))->count(),
            'tahunAkademikOptions' => $tahunAkademiks->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->tahun.' '.$t->semester]),
            'prodiOptions' => ProgramStudi::orderBy('nama_prodi')->get(['id', 'nama_prodi'])->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => $p->nama_prodi]),
        ]);
    }

    /**
     * Buat jadwal UTS/UAS (draf, tatap muka) untuk semua kelas di satu tahun akademik yang belum punya,
     * dengan tanggal, jam, dan ruang dari pertemuan UTS/UAS masing-masing kelas.
     */
    public function buatMassal(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tahun_akademik_id' => ['required', 'exists:tahun_akademik,id'],
            'jenis' => ['required', Rule::in(Ujian::JENIS)],
        ]);

        $hasil = ['dibuat' => 0, 'tanpa_pertemuan' => 0];

        DB::transaction(function () use ($data, $request, &$hasil): void {
            $kelas = KelasKuliah::query()
                ->where('tahun_akademik_id', $data['tahun_akademik_id'])
                ->whereDoesntHave('ujians', fn (Builder $q) => $q->where('jenis', $data['jenis']))
                ->with(['pertemuans' => fn ($q) => $q->where('jenis', $data['jenis'])])
                ->get();

            foreach ($kelas as $item) {
                $pertemuan = $item->pertemuans->first();

                if ($pertemuan === null) {
                    $hasil['tanpa_pertemuan']++;

                    continue;
                }

                Ujian::create([
                    'kelas_id' => $item->id,
                    'jenis' => $data['jenis'],
                    'mode' => Ujian::TATAP_MUKA,
                    'tanggal' => $pertemuan->tanggal,
                    'jam_mulai' => $pertemuan->jam_mulai,
                    'jam_akhir' => $pertemuan->jam_akhir,
                    'ruang_id' => $pertemuan->ruang_id,
                    'status' => Ujian::DRAF,
                    'dibuat_oleh' => $request->user()->id,
                ]);
                $hasil['dibuat']++;
            }
        });

        $jenis = strtoupper($data['jenis']);

        return back()->with('success', "{$hasil['dibuat']} jadwal {$jenis} dibuat sebagai draf dari pertemuan {$jenis} kelas."
            .($hasil['tanpa_pertemuan'] > 0 ? " {$hasil['tanpa_pertemuan']} kelas belum punya pertemuan {$jenis}; buat jadwalnya satu per satu." : ''));
    }

    public function create(Request $request): Response
    {
        $tahunId = $request->integer('tahun_akademik_id') ?: TahunAkademik::where('status', true)->value('id');

        return Inertia::render('Admin/UjianForm', [
            'ujian' => null,
            'jenisAwal' => in_array($request->query('jenis'), Ujian::SEMUA_JENIS, true) ? $request->query('jenis') : null,
            ...$this->opsiForm($tahunId),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request, null);
        $ujian = new Ujian([...$data, 'dibuat_oleh' => $request->user()->id]);
        $this->cekBentrok($ujian, $request->boolean('abaikan_bentrok_mahasiswa'));

        $ujian->save();
        $sinkron = $ujian->sinkronkanPertemuan();

        return to_route('admin.ujian.index', ['tahun_akademik_id' => $ujian->kelasKuliah->tahun_akademik_id])
            ->with('success', 'Jadwal ujian disimpan.'.$this->pesanSinkron($ujian, $sinkron));
    }

    public function edit(Ujian $ujian): Response
    {
        $ujian->load(['kelasKuliah:id,kode_kelas,matkul_id,tahun_akademik_id', 'kelasKuliah.mataKuliah:id,nama_matkul']);

        return Inertia::render('Admin/UjianForm', [
            'ujian' => $ujian,
            'jenisAwal' => null,
            ...$this->opsiForm($ujian->kelasKuliah->tahun_akademik_id),
        ]);
    }

    public function update(Request $request, Ujian $ujian): RedirectResponse
    {
        $data = $this->validasi($request, $ujian);

        // Mode tidak bisa diganti setelah ada yang mengerjakan, agar jawaban tidak kehilangan tempatnya.
        if ($data['mode'] !== $ujian->mode && $ujian->sudahDikerjakan()) {
            throw ValidationException::withMessages(['mode' => 'Mode tidak bisa diubah karena sudah ada mahasiswa yang mengerjakan ujian ini.']);
        }

        $ujian->fill($data);
        $this->cekBentrok($ujian, $request->boolean('abaikan_bentrok_mahasiswa'));

        $ujian->save();
        $sinkron = $ujian->sinkronkanPertemuan();
        // Batas lembar soal (mode soal di sistem) ikut jam selesai ujian yang baru.
        $ujian->quiz?->update(['tenggat_waktu' => $ujian->akhirAt()]);

        return to_route('admin.ujian.index', ['tahun_akademik_id' => $ujian->kelasKuliah->tahun_akademik_id])
            ->with('success', 'Jadwal ujian diperbarui.'.$this->pesanSinkron($ujian, $sinkron));
    }

    public function destroy(Ujian $ujian): RedirectResponse
    {
        if ($ujian->sudahDikerjakan()) {
            return back()->with('error', 'Jadwal ujian tidak bisa dihapus karena sudah ada mahasiswa yang mengumpulkan jawaban atau mengerjakan soal.');
        }

        if ($ujian->soal_berkas) {
            Storage::disk(AllowedUpload::DISK)->delete($ujian->soal_berkas);
        }

        $ujian->delete();

        return back()->with('success', $ujian->remidi() ? 'Jadwal remidi dihapus.' : 'Jadwal ujian dihapus. Pertemuan '.strtoupper($ujian->jenis).' kelas tidak berubah.');
    }

    /**
     * Terbitkan jadwal ujian yang dipilih (atau semua draf di tahun akademik) agar tampil ke mahasiswa.
     */
    public function terbitkan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'tahun_akademik_id' => ['required_without:ids', 'nullable', 'integer'],
        ]);

        $jumlah = Ujian::query()
            ->where('status', Ujian::DRAF)
            ->when($data['ids'] ?? null, fn (Builder $q, array $ids) => $q->whereIn('id', $ids))
            ->when(! ($data['ids'] ?? null), fn (Builder $q) => $q->whereHas('kelasKuliah', fn (Builder $k) => $k->where('tahun_akademik_id', $data['tahun_akademik_id'])))
            // Ujian tatap muka tanpa ruang belum lengkap untuk diterbitkan.
            ->where(fn (Builder $q) => $q->where('mode', '!=', Ujian::TATAP_MUKA)->orWhereNotNull('ruang_id'))
            ->update(['status' => Ujian::TERBIT]);

        return back()->with('success', "{$jumlah} jadwal ujian diterbitkan dan kini tampil ke mahasiswa.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Ujian $ujian): array
    {
        $kelasId = $ujian?->kelas_id ?? $request->integer('kelas_id');
        $kelas = KelasKuliah::with('tahunAkademik')->find($kelasId);

        $remidi = ($ujian?->jenis ?? $request->input('jenis')) === Ujian::REMIDI;
        $ta = $kelas?->tahunAkademik;

        if ($remidi && $kelas !== null) {
            $this->pastikanKelasSiapRemidi($kelas);
        }

        // UTS/UAS dalam rentang tahun akademik; remidi sesudah batas bayar sampai batas input nilai remidi.
        $rentangTanggal = match (true) {
            $ta === null => [],
            $remidi => ['after:'.$ta->batas_bayar_remidi->toDateString(), 'before_or_equal:'.$ta->batas_input_nilai_remidi->toDateString()],
            default => ['after_or_equal:'.$ta->tanggal_mulai->toDateString(), 'before_or_equal:'.$ta->tanggal_akhir->toDateString()],
        };

        $data = $request->validate([
            'kelas_id' => [$ujian ? 'prohibited' : 'required', 'exists:kelas_kuliah,id'],
            'jenis' => [$ujian ? 'prohibited' : 'required', Rule::in(Ujian::SEMUA_JENIS), Rule::unique('ujians')->where('kelas_id', $kelasId)],
            'mode' => ['required', Rule::in(Ujian::MODE)],
            'tanggal' => ['required', 'date_format:Y-m-d', ...$rentangTanggal],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_akhir' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruang_id' => ['nullable', 'required_if:mode,'.Ujian::TATAP_MUKA, 'exists:ruangs,id'],
            'pengawas' => ['nullable', 'string', 'max:255'],
            'petunjuk' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in([Ujian::DRAF, Ujian::TERBIT])],
        ], [
            'jenis.unique' => 'Kelas ini sudah punya jadwal ujian dengan jenis tersebut. Remidi hanya sekali per kelas.',
            'ruang_id.required_if' => 'Ruang wajib diisi untuk ujian tatap muka.',
            'tanggal.after_or_equal' => 'Tanggal ujian harus berada dalam tahun akademik kelas.',
            'tanggal.after' => 'Tanggal remidi harus sesudah batas bayar remidi ('.$ta?->batas_bayar_remidi?->translatedFormat('d M Y').').',
            'tanggal.before_or_equal' => $remidi
                ? 'Tanggal remidi paling lambat batas input nilai remidi ('.$ta?->batas_input_nilai_remidi?->translatedFormat('d M Y').').'
                : 'Tanggal ujian harus berada dalam tahun akademik kelas.',
            'after' => ':attribute harus lebih besar dari Jam Mulai.',
            'date_format' => ':attribute tidak valid.',
        ], [
            'kelas_id' => 'Kelas', 'jenis' => 'Jenis', 'mode' => 'Mode', 'tanggal' => 'Tanggal', 'jam_mulai' => 'Jam Mulai',
            'jam_akhir' => 'Jam Selesai', 'ruang_id' => 'Ruang', 'pengawas' => 'Pengawas', 'petunjuk' => 'Petunjuk', 'status' => 'Status',
        ]);

        // Ujian online tidak memakai ruang.
        if ($data['mode'] !== Ujian::TATAP_MUKA) {
            $data['ruang_id'] = null;
        }

        return $data;
    }

    /**
     * Tolak jadwal yang bentrok ruang (dengan pertemuan/ujian lain), dan jadwal yang membuat mahasiswa
     * kelas ini punya dua ujian di jam yang sama (kecuali admin sengaja mengabaikannya).
     */
    private function cekBentrok(Ujian $ujian, bool $abaikanMahasiswa): void
    {
        $kelas = $ujian->kelasKuliah()->first();
        $tanggal = $ujian->tanggal->toDateString();
        $mulai = substr($ujian->jam_mulai, 0, 5).':00';
        $akhir = substr($ujian->jam_akhir, 0, 5).':00';
        $bertumpuk = fn (Builder $q) => $q->whereDate('tanggal', $tanggal)->where('jam_mulai', '<', $akhir)->where('jam_akhir', '>', $mulai)
            ->when($ujian->exists, fn (Builder $x) => $x->whereKeyNot($ujian->id));

        if ($ujian->mode === Ujian::TATAP_MUKA) {
            $pesan = Pertemuan::bentrok($kelas, $tanggal, $mulai, $akhir, $ujian->ruang_id, null, $ujian->pertemuan()?->id);
            $ujianLain = Ujian::query()->where($bertumpuk)->where('mode', Ujian::TATAP_MUKA)->where('ruang_id', $ujian->ruang_id)
                ->with('kelasKuliah:id,kode_kelas')->first();
            $pesan ??= $ujianLain ? 'Ruang sudah dipakai ujian kelas '.$ujianLain->kelasKuliah?->kode_kelas.' pada jam yang sama.' : null;

            if ($pesan !== null) {
                throw ValidationException::withMessages(['ruang_id' => $pesan]);
            }
        }

        if ($abaikanMahasiswa) {
            return;
        }

        // Ujian remidi hanya diikuti peserta remidi, bukan seluruh kelas.
        $mahasiswaKelas = $ujian->remidi()
            ? RemidiPeserta::query()->where('kelas_id', $kelas->id)->select('mahasiswa_id')
            : Krs::query()->where('kelas_id', $kelas->id)->select('mahasiswa_id');
        $bentrok = Krs::query()
            ->whereIn('mahasiswa_id', $mahasiswaKelas)
            ->where('kelas_id', '!=', $kelas->id)
            ->whereIn('kelas_id', Ujian::query()->where($bertumpuk)->select('kelas_id'))
            ->distinct()
            ->count('mahasiswa_id');

        if ($bentrok > 0) {
            throw ValidationException::withMessages([
                'tanggal' => $bentrok.' mahasiswa '.($ujian->remidi() ? 'peserta remidi' : 'kelas ini').' juga punya ujian lain pada jam yang sama. Ubah jadwal, atau centang "Tetap simpan" bila memang disengaja.',
            ]);
        }
    }

    private function pesanSinkron(Ujian $ujian, bool $sinkron): string
    {
        if ($ujian->remidi()) {
            return '';
        }

        $jenis = strtoupper($ujian->jenis);

        return $sinkron
            ? " Pertemuan {$jenis} kelas ikut disesuaikan."
            : " Catatan: pertemuan {$jenis} kelas tidak ada atau sudah berjalan, jadi tidak ikut diubah.";
    }

    /**
     * Kelas yang bisa dijadwalkan remidi: daftar remidi dikunci, ada peserta yang lunas, belum punya jadwal remidi.
     *
     * @return Builder<KelasKuliah>
     */
    private function kelasRemidiSiap(?int $tahunId): Builder
    {
        return KelasKuliah::query()
            ->where('tahun_akademik_id', $tahunId)
            ->whereNotNull('remidi_dikunci_at')
            ->whereHas('remidiPesertas', fn (Builder $q) => $q->lunas())
            ->whereDoesntHave('ujians', fn (Builder $q) => $q->where('jenis', Ujian::REMIDI));
    }

    private function pastikanKelasSiapRemidi(KelasKuliah $kelas): void
    {
        $ta = $kelas->tahunAkademik;
        $pesan = match (true) {
            $ta->batas_bayar_remidi === null || $ta->batas_input_nilai_remidi === null => 'Isi dulu Batas Bayar Remidi dan Batas Input Nilai Remidi di menu Tahun Akademik.',
            $kelas->remidi_dikunci_at === null => 'Daftar remidi kelas ini belum dikunci dosen.',
            ! $kelas->remidiPesertas()->lunas()->exists() => 'Belum ada peserta remidi kelas ini yang tagihannya lunas.',
            default => null,
        };

        if ($pesan !== null) {
            throw ValidationException::withMessages(['kelas_id' => $pesan]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function opsiForm(?int $tahunId): array
    {
        $ta = TahunAkademik::find($tahunId);

        return [
            'tahunAkademikId' => $tahunId,
            'kelasRemidiOptions' => $this->kelasRemidiSiap($tahunId)
                ->with('mataKuliah:id,nama_matkul')
                ->withCount(['remidiPesertas as peserta_lunas' => fn (Builder $q) => $q->lunas()])
                ->orderBy('kode_kelas')
                ->get(['id', 'kode_kelas', 'matkul_id'])
                ->map(fn (KelasKuliah $k): array => ['id' => $k->id, 'name' => $k->kode_kelas.' — '.$k->mataKuliah?->nama_matkul.' ('.$k->peserta_lunas.' peserta lunas)']),
            'batasRemidi' => [
                'bayar' => $ta?->batas_bayar_remidi?->toDateString(),
                'nilai' => $ta?->batas_input_nilai_remidi?->toDateString(),
            ],
            'kelasOptions' => KelasKuliah::query()
                ->where('tahun_akademik_id', $tahunId)
                ->with('mataKuliah:id,kode_matkul,nama_matkul')
                ->orderBy('kode_kelas')
                ->get(['id', 'kode_kelas', 'matkul_id'])
                ->map(fn (KelasKuliah $k): array => ['id' => $k->id, 'name' => $k->kode_kelas.' — '.$k->mataKuliah?->nama_matkul]),
            'ruangOptions' => Ruang::orderBy('kode_ruang')->get(['id', 'kode_ruang', 'nama_ruang'])
                ->map(fn (Ruang $r): array => ['id' => $r->id, 'name' => $r->kode_ruang.' — '.$r->nama_ruang]),
        ];
    }
}
