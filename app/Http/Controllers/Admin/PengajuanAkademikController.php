<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\JadwalPendadaran;
use App\Models\DosenProfile;
use App\Models\Pendadaran;
use App\Models\PengajuanAkademik;
use App\Models\Ruang;
use App\Models\TugasAkhir;
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
 * Pengajuan TA, pendadaran, dan wisuda: admin menyetujui, meminta perbaikan, atau menolak.
 */
class PengajuanAkademikController extends Controller
{
    /** Jenis yang sudah bisa diproses; tab lain menyusul. */
    public const JENIS_TERSEDIA = [PengajuanAkademik::TUGAS_AKHIR, PengajuanAkademik::PENDADARAN];

    public function index(Request $request): Response
    {
        $jenis = in_array($request->query('jenis'), self::JENIS_TERSEDIA, true) ? $request->query('jenis') : PengajuanAkademik::TUGAS_AKHIR;
        $filter = [
            'jenis' => $jenis,
            'status' => in_array($request->query('status'), PengajuanAkademik::STATUS, true) ? $request->query('status') : null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $dosen = collect(DosenProfile::opsi())->pluck('name', 'id');
        $jadwal = [];
        $pengajuan = PengajuanAkademik::query()
            ->where('jenis', $jenis)
            ->when($filter['status'], fn (Builder $q, string $status) => $q->where('status', $status))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->whereHas('mahasiswa', fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['mahasiswa:id,user_id,nim,prodi_id', 'mahasiswa.user:id,name', 'mahasiswa.prodi:id,nama_prodi,jenjang', 'pemroses:id,name',
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
                ->mapWithKeys(fn (Pendadaran $p): array => [$p->pengajuan_id => $p->jadwal()])->all();
        }
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
            'status' => $p->status,
            'catatan' => $p->catatan,
            'diproses_oleh' => $p->pemroses?->name,
            'diproses_at' => $p->diproses_at?->toIso8601String(),
            'diajukan_at' => $p->diajukan_at?->toIso8601String(),
        ]);

        return Inertia::render('Admin/PengajuanAkademik', [
            'pengajuan' => $pengajuan,
            'filter' => $filter,
            'jenisTersedia' => self::JENIS_TERSEDIA,
            'jumlahMenunggu' => PengajuanAkademik::query()->where('status', PengajuanAkademik::MENUNGGU)
                ->whereIn('jenis', self::JENIS_TERSEDIA)->selectRaw('jenis, count(*) as jumlah')->groupBy('jenis')->pluck('jumlah', 'jenis'),
            'dosenOptions' => DosenProfile::opsi(),
            'ruangOptions' => $jenis === PengajuanAkademik::PENDADARAN
                ? Ruang::query()->orderBy('kode_ruang')->get(['id', 'kode_ruang', 'nama_ruang'])->map(fn (Ruang $r): array => ['id' => $r->id, 'name' => trim($r->kode_ruang.' '.$r->nama_ruang)])
                : [],
        ]);
    }

    public function setujui(Request $request, PengajuanAkademik $pengajuanAkademik): RedirectResponse
    {
        abort_unless(in_array($pengajuanAkademik->jenis, self::JENIS_TERSEDIA, true), 404);

        return match ($pengajuanAkademik->jenis) {
            PengajuanAkademik::PENDADARAN => $this->setujuiPendadaran($request, $pengajuanAkademik),
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
            'pembimbing_1_id' => ['required', 'integer', Rule::exists('dosen_profiles', 'id')],
            'pembimbing_2_id' => ['nullable', 'integer', Rule::exists('dosen_profiles', 'id'), 'different:pembimbing_1_id'],
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
                'pembimbing_1_id' => $data['pembimbing_1_id'],
                'pembimbing_2_id' => $data['pembimbing_2_id'] ?? null,
                'status' => TugasAkhir::BERJALAN,
                'disahkan_oleh' => $request->user()->id,
            ]);
            $pengajuan->catat(PengajuanAkademik::DISETUJUI, null, $request->user()->id);

            return back()->with('success', 'Pengajuan tugas akhir disetujui; judul dan pembimbing sudah disahkan.');
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
            'penguji_1_id' => ['required', 'integer', Rule::exists('dosen_profiles', 'id')],
            'penguji_2_id' => ['required', 'integer', Rule::exists('dosen_profiles', 'id'), 'different:penguji_1_id'],
            'penguji_3_id' => ['required', 'integer', Rule::exists('dosen_profiles', 'id'), 'different:penguji_1_id', 'different:penguji_2_id'],
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
            if ($tugasAkhir?->status !== TugasAkhir::BERJALAN || Pendadaran::query()->where('tugas_akhir_id', $tugasAkhir->id)->where('status', Pendadaran::DIJADWALKAN)->exists()) {
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

    private function kembalikan(Request $request, PengajuanAkademik $pengajuan, string $status, string $pesan): RedirectResponse
    {
        abort_unless(in_array($pengajuan->jenis, self::JENIS_TERSEDIA, true), 404);
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
