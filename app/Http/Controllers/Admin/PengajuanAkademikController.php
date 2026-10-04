<?php

namespace App\Http\Controllers\Admin;

use App\Feature;
use App\Http\Controllers\Controller;
use App\JadwalPendadaran;
use App\KrsKkm;
use App\Models\DosenProfile;
use App\Models\GelombangKompre;
use App\Models\Pendadaran;
use App\Models\PengajuanAkademik;
use App\Models\PeriodeWisuda;
use App\Models\Ruang;
use App\Models\TugasAkhir;
use App\Models\Wisuda;
use App\SyaratTugasAkhir;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Menu Pengajuan & Pendaftaran: Persetujuan Tugas Akhir, Pendaftaran Pendadaran, Persetujuan KKM/PKL/KKN, Daftar PPL,
 * Daftar Ujian Komprehensif, dan Daftar Wisuda — satu halaman per jenis (rute dengan `jenis` bawaan). Admin menyetujui, meminta
 * perbaikan, atau menolak. Pendadaran hilang bila fitur pendadaran mati.
 */
class PengajuanAkademikController extends Controller
{
    /** Rute halaman per jenis; alamat lama `admin/pengajuan-akademik?jenis=…` dialihkan ke sini. */
    public const RUTE = [
        PengajuanAkademik::TUGAS_AKHIR => 'admin.persetujuan-ta.index',
        PengajuanAkademik::PENDADARAN => 'admin.pendaftaran-pendadaran.index',
        PengajuanAkademik::WISUDA => 'admin.daftar-wisuda.index',
        PengajuanAkademik::KKM => 'admin.persetujuan-kkm.index',
        PengajuanAkademik::PPL => 'admin.daftar-ppl.index',
        PengajuanAkademik::KOMPRE => 'admin.pengajuan-kompre.index',
        PengajuanAkademik::SIDANG => 'admin.pendaftaran-sidang.index',
    ];

    public const JUDUL = [
        PengajuanAkademik::TUGAS_AKHIR => 'Persetujuan Tugas Akhir',
        PengajuanAkademik::PENDADARAN => 'Pendaftaran Pendadaran',
        PengajuanAkademik::WISUDA => 'Daftar Wisuda',
        PengajuanAkademik::KKM => 'Persetujuan KKM/PKL/KKN',
        PengajuanAkademik::PPL => 'Daftar PPL',
        PengajuanAkademik::KOMPRE => 'Daftar Ujian Komprehensif',
        PengajuanAkademik::SIDANG => 'Pendaftaran Sidang',
    ];

    /**
     * Jenis yang diproses di menu ini.
     *
     * @return list<string>
     */
    public static function jenisTersedia(): array
    {
        return array_values(array_filter(
            [...PengajuanAkademik::JENIS, ...PengajuanAkademik::JENIS_KEGIATAN],
            // Pendaftaran pendadaran (dengan jadwal & penguji) dan pendaftaran sidang sederhana saling menggantikan.
            fn (string $jenis): bool => match ($jenis) {
                PengajuanAkademik::PENDADARAN => Feature::aktif('pendadaran'),
                PengajuanAkademik::SIDANG => ! Feature::aktif('pendadaran'),
                default => true,
            },
        ));
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $dariRute = $request->route('jenis');
        $jenis = $dariRute ?? (in_array($request->query('jenis'), self::jenisTersedia(), true) ? $request->query('jenis') : PengajuanAkademik::TUGAS_AKHIR);
        if ($dariRute === null) {
            return redirect()->route(self::RUTE[$jenis], $request->only(['status', 'search']));
        }
        $filter = [
            'jenis' => $jenis,
            'status' => in_array($request->query('status'), PengajuanAkademik::STATUS, true) ? $request->query('status') : null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        // Nama untuk ditampilkan: semua dosen, termasuk yang kini nonaktif.
        $dosen = DosenProfile::query()->with('user:id,name')->get(['id', 'user_id'])->mapWithKeys(fn (DosenProfile $d): array => [$d->id => (string) $d->user?->name]);
        $jadwal = [];
        $pengajuan = PengajuanAkademik::query()
            ->where('jenis', $jenis)
            ->when($filter['status'], fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['mahasiswa:id,user_id,nim,prodi_id,tempat_lahir,tanggal_lahir', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'pemroses:id,name',
                'tugasAkhir.pembimbing1.user:id,name', 'tugasAkhir.pembimbing2.user:id,name', 'pembimbingPenyetuju.user:id,name'])
            // Yang menunggu keputusan tampil paling atas, yang paling lama menunggu lebih dulu.
            ->orderByRaw('status = ? desc', [PengajuanAkademik::MENUNGGU])
            ->orderByRaw('case when status = ? then diajukan_at end asc', [PengajuanAkademik::MENUNGGU])
            ->latest('id')
            ->paginate(20)
            ->withQueryString();
        if ($jenis === PengajuanAkademik::PENDADARAN) {
            $jadwal = Pendadaran::query()->whereIn('pengajuan_id', $pengajuan->getCollection()->pluck('id'))
                ->with(['ruang', 'penguji1.user:id,name', 'penguji2.user:id,name', 'penguji3.user:id,name'])->get()
                ->mapWithKeys(fn (Pendadaran $p): array => [$p->pengajuan_id => [...$p->jadwal(), ...$p->ringkasanHasil()]])->all();
        }
        $periode = $jenis === PengajuanAkademik::WISUDA ? PeriodeWisuda::query()->pluck('nama', 'id') : collect();
        $gelombang = $jenis === PengajuanAkademik::KOMPRE ? GelombangKompre::query()->get()->keyBy('id') : collect();
        // TA yang disahkan dari pengajuan TA ini, atau TA milik pendaftar wisuda (naskahnya = naskah final wisuda).
        $tugasAkhir = match ($jenis) {
            PengajuanAkademik::TUGAS_AKHIR => TugasAkhir::query()->whereIn('pengajuan_id', $pengajuan->getCollection()->pluck('id'))->get()->keyBy('pengajuan_id'),
            PengajuanAkademik::WISUDA => TugasAkhir::query()->whereIn('id', $pengajuan->getCollection()->pluck('tugas_akhir_id')->filter())->get()
                ->pipe(fn ($ta) => $pengajuan->getCollection()->mapWithKeys(fn (PengajuanAkademik $p): array => [$p->id => $ta->firstWhere('id', $p->tugas_akhir_id)])->filter()),
            default => collect(),
        };
        $pengajuan->through(fn (PengajuanAkademik $p): array => [
            'id' => $p->id,
            'nama' => $p->mahasiswa?->user?->name,
            'nim' => $p->mahasiswa?->nim,
            'prodi' => $p->mahasiswa?->prodi ? $p->mahasiswa->prodi->jenjang.' '.$p->mahasiswa->prodi->nama_prodi : null,
            'isian' => $p->isian,
            'usulan_pembimbing' => array_values(array_filter([
                $dosen[$p->isian['usulan_pembimbing_1_id'] ?? 0] ?? null,
                $dosen[$p->isian['usulan_pembimbing_2_id'] ?? 0] ?? null,
            ])),
            'lampiran' => array_keys($p->lampiran ?? []),
            'pembimbing' => $p->tugasAkhir?->namaPembimbing() ?? [],
            'disetujui_pembimbing' => $p->pembimbingPenyetuju?->user?->name,
            'disetujui_pembimbing_at' => $p->disetujui_pembimbing_at?->toIso8601String(),
            'jadwal' => $jadwal[$p->id] ?? null,
            'periode_wisuda' => $periode[$p->isian['periode_wisuda_id'] ?? 0] ?? null,
            'gelombang_kompre' => ($g = $gelombang->get($p->isian['gelombang_kompre_id'] ?? 0))
                ? ['nama' => $g->nama, 'tanggal_ujian' => $g->tanggal_ujian->toDateString()] : null,
            'jenis_kkm' => PengajuanAkademik::JENIS_KKM[$p->isian['jenis_kkm'] ?? ''] ?? null,
            'tugas_akhir' => ($ta = $tugasAkhir->get($p->id)) ? [
                'id' => $ta->id,
                'status' => $ta->status,
                'naskah_diunggah_at' => $ta->naskah !== null ? $ta->naskah_diunggah_at?->toIso8601String() : null,
            ] : null,
            // Data ijazah yang berbeda dari profil (koreksi dari mahasiswa) ditandai untuk diperiksa admin.
            'koreksi' => $jenis === PengajuanAkademik::WISUDA ? array_keys(array_filter([
                'nama_ijazah' => ($p->isian['nama_ijazah'] ?? null) !== $p->mahasiswa?->user?->name,
                'tempat_lahir' => ($p->isian['tempat_lahir'] ?? null) !== $p->mahasiswa?->tempat_lahir,
                'tanggal_lahir' => ($p->isian['tanggal_lahir'] ?? null) !== $p->mahasiswa?->tanggal_lahir?->toDateString(),
            ])) : [],
            'status' => $p->status,
            'catatan' => $p->catatan,
            'diproses_oleh' => $p->pemroses?->name,
            'diproses_at' => $p->diproses_at?->toIso8601String(),
            'diajukan_at' => $p->diajukan_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin/PengajuanAkademik', [
            'pengajuan' => $pengajuan,
            'filter' => $filter,
            'judul' => self::JUDUL[$jenis],
            'rute' => self::RUTE[$jenis],
            'dosenOptions' => DosenProfile::opsi(),
            'ruangOptions' => $jenis === PengajuanAkademik::PENDADARAN
                ? Ruang::query()->orderBy('kode_ruang')->get(['id', 'kode_ruang', 'nama_ruang'])->map(fn (Ruang $r): array => ['id' => $r->id, 'name' => trim($r->kode_ruang.' '.$r->nama_ruang)])
                : [],
        ]);
    }

    public function setujui(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        abort_unless(in_array($pengajuanAkademik->jenis, self::jenisTersedia(), true), 404);

        return match ($pengajuanAkademik->jenis) {
            PengajuanAkademik::PENDADARAN => $this->setujuiPendadaran($request, $pengajuanAkademik),
            PengajuanAkademik::WISUDA => $this->setujuiWisuda($request, $pengajuanAkademik),
            PengajuanAkademik::KKM, PengajuanAkademik::PPL, PengajuanAkademik::KOMPRE, PengajuanAkademik::SIDANG => $this->setujuiKegiatan($request, $pengajuanAkademik),
            default => $this->setujuiTa($request, $pengajuanAkademik),
        };
    }

    public function perbaikan(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        return $this->kembalikan($request, $pengajuanAkademik, PengajuanAkademik::PERLU_PERBAIKAN, 'Pengajuan dikembalikan ke mahasiswa untuk diperbaiki.');
    }

    public function tolak(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        return $this->kembalikan($request, $pengajuanAkademik, PengajuanAkademik::DITOLAK, 'Pengajuan ditolak.');
    }

    /**
     * Setujui pengajuan TA sekaligus sahkan judul dan tetapkan pembimbing (boleh berbeda dari usulan).
     */
    private function setujuiTa(Request $request, PengajuanAkademik $pengajuan): RedirectResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:300'],
            'pembimbing_1_id' => [Feature::aktif('pendadaran') ? 'required' : 'nullable', 'integer', DosenProfile::rulePilihan()],
            'pembimbing_2_id' => ['nullable', 'integer', DosenProfile::rulePilihan(), 'different:pembimbing_1_id'],
        ], ['pembimbing_2_id.different' => 'Pembimbing 2 harus berbeda dari pembimbing 1.'], [
            'judul' => 'Judul',
            'pembimbing_1_id' => 'Pembimbing 1',
            'pembimbing_2_id' => 'Pembimbing 2',
        ]);

        return DB::transaction(function () use ($request, $pengajuan, $data): RedirectResponse {
            $pengajuan = PengajuanAkademik::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if (! $pengajuan->menunggu()) {
                return back()->with('error', 'Pengajuan ini sudah diproses.');
            }
            if (TugasAkhir::milik($pengajuan->mahasiswa_id) !== null) {
                return back()->with('error', 'Mahasiswa ini sudah punya tugas akhir yang disahkan.');
            }
            // Diperiksa ulang: mata kuliah TA bisa saja dibatalkan dari KRS sesudah pengajuan dikirim.
            if (! SyaratTugasAkhir::terpenuhi(SyaratTugasAkhir::pengajuanTa($pengajuan->mahasiswa))) {
                return back()->with('error', 'Mahasiswa ini tidak lagi mengambil mata kuliah TA/Skripsi di semester aktif. Minta perbaikan atau tolak pengajuannya.');
            }

            TugasAkhir::query()->create([
                'mahasiswa_id' => $pengajuan->mahasiswa_id,
                'pengajuan_id' => $pengajuan->id,
                'judul' => $data['judul'],
                'bidang' => $pengajuan->isian['bidang'] ?? '-',
                'pembimbing_1_id' => $data['pembimbing_1_id'] ?? null,
                'pembimbing_2_id' => $data['pembimbing_2_id'] ?? null,
                'status' => TugasAkhir::BERJALAN,
                'disahkan_oleh' => $request->user()->id,
            ]);
            $pengajuan->catat(PengajuanAkademik::DISETUJUI, null, $request->user()->id);

            return back()->with('success', Feature::aktif('pendadaran') ? 'Pengajuan tugas akhir disetujui; judul dan pembimbing sudah disahkan.' : 'Pengajuan tugas akhir disetujui; judul sudah disahkan.');
        });
    }

    /**
     * Setujui pendaftaran pendadaran sekaligus jadwalkan: tanggal, jam, ruang, dan tiga penguji.
     *
     * Bentrok ruang dan bentrok antar-pendadaran seorang penguji menolak penyimpanan; bentrok dengan jadwal
     * mengajar penguji hanya peringatan yang bisa diabaikan admin (abaikan_peringatan).
     */
    private function setujuiPendadaran(Request $request, PengajuanAkademik $pengajuan): RedirectResponse
    {
        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_akhir' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruang_id' => ['required', 'integer', Rule::exists('ruangs', 'id')],
            'penguji_1_id' => ['required', 'integer', DosenProfile::rulePilihan()],
            'penguji_2_id' => ['required', 'integer', DosenProfile::rulePilihan(), 'different:penguji_1_id'],
            'penguji_3_id' => ['required', 'integer', DosenProfile::rulePilihan(), 'different:penguji_1_id', 'different:penguji_2_id'],
            'abaikan_peringatan' => ['boolean'],
        ], [
            'tanggal.after_or_equal' => 'Tanggal pendadaran tidak boleh sebelum hari ini.',
            'jam_akhir.after' => 'Jam selesai harus sesudah jam mulai.',
            'penguji_2_id.different' => 'Setiap penguji harus dosen yang berbeda.',
            'penguji_3_id.different' => 'Setiap penguji harus dosen yang berbeda.',
        ], [
            'tanggal' => 'Tanggal',
            'jam_mulai' => 'Jam mulai',
            'jam_akhir' => 'Jam selesai',
            'ruang_id' => 'Ruang',
            'penguji_1_id' => 'Ketua penguji',
            'penguji_2_id' => 'Penguji 2',
            'penguji_3_id' => 'Penguji 3',
        ]);

        return DB::transaction(function () use ($request, $pengajuan, $data): RedirectResponse {
            $pengajuan = PengajuanAkademik::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if (! $pengajuan->menunggu()) {
                return back()->with('error', 'Pendaftaran ini belum disetujui pembimbing atau sudah diproses.');
            }
            $tugasAkhir = $pengajuan->tugasAkhir;
            if ($tugasAkhir?->status !== TugasAkhir::BERJALAN || Pendadaran::query()->where('tugas_akhir_id', $tugasAkhir->id)->whereIn('status', Pendadaran::AKTIF)->exists()) {
                return back()->with('error', 'Tugas akhir mahasiswa ini tidak sedang menunggu pendadaran.');
            }
            // Diperiksa ulang: nilai atau KRS bisa berubah sejak pendaftaran dikirim.
            $kurang = collect(SyaratTugasAkhir::pendadaran($pengajuan->mahasiswa))->reject(fn (array $s): bool => $s['terpenuhi'])->pluck('label');
            if ($kurang->isNotEmpty()) {
                return back()->with('error', 'Mahasiswa ini tidak lagi memenuhi syarat: '.$kurang->join(', ').'. Minta perbaikan atau tolak pendaftarannya.');
            }

            $jadwal = new JadwalPendadaran($data['tanggal'], $data['jam_mulai'].':00', $data['jam_akhir'].':00');
            $penguji = [$data['penguji_1_id'], $data['penguji_2_id'], $data['penguji_3_id']];
            $galat = [];
            if (($ruang = $jadwal->ruangBentrok($data['ruang_id'])) !== null) {
                $galat['ruang_id'] = $ruang;
            }
            foreach ($jadwal->pengujiBentrok($penguji) as $dosenId => $pesan) {
                $galat['penguji_'.(array_search($dosenId, $penguji, true) + 1).'_id'] = $pesan;
            }
            if ($galat !== []) {
                throw ValidationException::withMessages($galat);
            }
            if (! ($data['abaikan_peringatan'] ?? false) && ($peringatan = $jadwal->peringatanMengajar($penguji)) !== []) {
                // Satu kunci per pesan: Inertia hanya meneruskan pesan pertama tiap kunci.
                throw ValidationException::withMessages(collect($peringatan)->mapWithKeys(fn (string $p, int $i): array => ["peringatan.{$i}" => $p])->all());
            }

            Pendadaran::query()->create([
                'pengajuan_id' => $pengajuan->id,
                'tugas_akhir_id' => $tugasAkhir->id,
                'mahasiswa_id' => $pengajuan->mahasiswa_id,
                'nomor_surat' => Pendadaran::nomorSuratBaru(now()),
                'tanggal' => $data['tanggal'],
                'jam_mulai' => $data['jam_mulai'],
                'jam_akhir' => $data['jam_akhir'],
                'ruang_id' => $data['ruang_id'],
                'penguji_1_id' => $data['penguji_1_id'],
                'penguji_2_id' => $data['penguji_2_id'],
                'penguji_3_id' => $data['penguji_3_id'],
                'status' => Pendadaran::DIJADWALKAN,
                'dijadwalkan_oleh' => $request->user()->id,
            ]);
            // Judul final dari form pendaftaran menjadi judul TA yang berlaku.
            $tugasAkhir->update(['judul' => $pengajuan->isian['judul'] ?? $tugasAkhir->judul]);
            $pengajuan->catat(PengajuanAkademik::DISETUJUI, null, $request->user()->id);

            return back()->with('success', 'Pendaftaran pendadaran disetujui dan jadwalnya sudah terbit.');
        });
    }

    /**
     * Setujui pendaftaran wisuda: mahasiswa masuk daftar peserta periode yang dipilihnya. Kuota diperiksa ulang.
     */
    private function setujuiWisuda(Request $request, PengajuanAkademik $pengajuan): RedirectResponse
    {
        // Tanggal lulus (yudisium) dicetak di SKL dan transkrip. Tanpa pendadaran tidak ada tanggal sidang, jadi wajib diisi.
        // Surat bebas pustaka dan surat keterangan lunas diunggah mahasiswa; admin wajib mencentang keduanya sudah diperiksa.
        $data = $request->validate(
            [
                'tanggal_lulus' => [Feature::aktif('pendadaran') ? 'nullable' : 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'bebas_pustaka' => ['accepted'],
                'lunas' => ['accepted'],
            ],
            [
                'tanggal_lulus.before_or_equal' => 'Tanggal lulus tidak boleh sesudah hari ini.',
                'bebas_pustaka.accepted' => 'Centang bebas pustaka setelah surat bebas pustaka diperiksa.',
                'lunas.accepted' => 'Centang lunas setelah surat keterangan lunas diperiksa.',
            ],
            ['tanggal_lulus' => 'Tanggal lulus (yudisium)'],
        );

        return DB::transaction(function () use ($request, $pengajuan, $data): RedirectResponse {
            $pengajuan = PengajuanAkademik::query()->lockForUpdate()->findOrFail($pengajuan->id);
            $periode = PeriodeWisuda::query()->lockForUpdate()->find($pengajuan->isian['periode_wisuda_id'] ?? 0);

            if (! $pengajuan->menunggu()) {
                return back()->with('error', 'Pendaftaran ini sudah diproses.');
            }
            if (Wisuda::query()->where('mahasiswa_id', $pengajuan->mahasiswa_id)->exists()) {
                return back()->with('error', 'Mahasiswa ini sudah terdaftar sebagai peserta wisuda.');
            }
            if ($periode === null || ($periode->kuota !== null && $periode->sisaKuota() === 0)) {
                return back()->with('error', 'Kuota periode wisuda ini sudah penuh. Minta mahasiswa memperbaiki pilihan periodenya.');
            }
            $kurang = collect(SyaratTugasAkhir::wisuda($pengajuan->mahasiswa))->reject(fn (array $s): bool => $s['terpenuhi'])->pluck('label')
                // Pendaftaran yang dikirim sebelum batas daftar tetap boleh disetujui sesudahnya.
                ->reject(fn (string $label): bool => str_starts_with($label, 'Ada periode wisuda'));
            if ($kurang->isNotEmpty()) {
                return back()->with('error', 'Mahasiswa ini tidak lagi memenuhi syarat: '.$kurang->join(', ').'. Minta perbaikan atau tolak pendaftarannya.');
            }

            Wisuda::query()->create([
                'pengajuan_id' => $pengajuan->id,
                'periode_wisuda_id' => $periode->id,
                'mahasiswa_id' => $pengajuan->mahasiswa_id,
                'tugas_akhir_id' => $pengajuan->tugas_akhir_id,
                'tanggal_lulus' => $data['tanggal_lulus'] ?? null,
            ]);
            $pengajuan->update(['isian' => [...$pengajuan->isian, 'dicentang' => [
                'bebas_pustaka' => true, 'lunas' => true, 'oleh' => $request->user()->name, 'at' => now()->toIso8601String(),
            ]]]);
            $pengajuan->catat(PengajuanAkademik::DISETUJUI, null, $request->user()->id);

            return back()->with('success', "Pendaftaran wisuda disetujui; mahasiswa masuk daftar peserta {$periode->nama}.");
        });
    }

    /**
     * Setujui pengajuan KKM, PPL, ujian komprehensif, atau pendaftaran sidang. KKM yang disetujui otomatis dimasukkan ke KRS mata kuliah KKM
     * prodinya (bila belum diambil) agar nilainya bisa diisi di menu Nilai KKM.
     */
    private function setujuiKegiatan(Request $request, PengajuanAkademik $pengajuan): RedirectResponse
    {
        return DB::transaction(function () use ($request, $pengajuan): RedirectResponse {
            $pengajuan = PengajuanAkademik::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if (! $pengajuan->menunggu()) {
                return back()->with('error', 'Pengajuan ini sudah diproses.');
            }

            $pengajuan->catat(PengajuanAkademik::DISETUJUI, null, $request->user()->id);
            $label = PengajuanAkademik::LABEL_JENIS[$pengajuan->jenis];
            if ($pengajuan->jenis === PengajuanAkademik::SIDANG) {
                return back()->with('success', 'Pendaftaran sidang disetujui. Jadwal dan penguji diatur di luar sistem; nilainya diisi lewat Nilai Semester mata kuliah TA/Skripsi.');
            }
            if ($pengajuan->jenis !== PengajuanAkademik::KKM) {
                return back()->with('success', "Pengajuan {$label} disetujui.");
            }

            $hasil = KrsKkm::tambahkan($pengajuan->mahasiswa);

            return back()->with($hasil['berhasil'] ? 'success' : 'error', "Pengajuan {$label} disetujui. {$hasil['pesan']}");
        });
    }

    private function kembalikan(Request $request, PengajuanAkademik $pengajuan, string $status, string $pesan): RedirectResponse
    {
        abort_unless(in_array($pengajuan->jenis, self::jenisTersedia(), true), 404);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']], attributes: ['catatan' => 'Catatan']);

        return DB::transaction(function () use ($request, $pengajuan, $status, $pesan, $data): RedirectResponse {
            $pengajuan = PengajuanAkademik::query()->lockForUpdate()->findOrFail($pengajuan->id);

            if (! $pengajuan->menunggu()) {
                return back()->with('error', 'Pengajuan ini sudah diproses.');
            }

            $pengajuan->catat($status, $data['catatan'], $request->user()->id);

            return back()->with('success', $pesan);
        });
    }
}
