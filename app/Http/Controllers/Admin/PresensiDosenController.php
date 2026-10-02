<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\ProgramStudi;
use App\Models\RiwayatPresensiDosen;
use App\Models\TahunAkademik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Presensi dosen diambil dari pertemuan yang dibuka–ditutup dosen. Admin melihat semua pertemuan, mengoreksi
 * presensi dosen (wajib beralasan, tercatat di riwayat), dan melihat rekap per dosen untuk honor.
 */
class PresensiDosenController extends Controller
{
    /** Status daftar: terlaksana, tidak terlaksana (status dosen tidak hadir), terlewat (belum ada keterangan). */
    private const FILTER_STATUS = ['terlaksana', 'tidak_terlaksana', 'terlewat'];

    private const FILTER_VERIFIKASI = ['menunggu', Pertemuan::DISETUJUI, Pertemuan::DITOLAK];

    public function index(Request $request): Response
    {
        Pertemuan::tutupYangLewat();
        $filter = $this->filter($request);
        $filter['status'] = in_array($request->query('status'), self::FILTER_STATUS, true) ? $request->query('status') : null;
        $filter['verifikasi'] = in_array($request->query('verifikasi'), self::FILTER_VERIFIKASI, true) ? $request->query('verifikasi') : null;
        $filter['dari'] = $this->tanggal($request->query('dari'));
        $filter['sampai'] = $this->tanggal($request->query('sampai'));
        $toleransi = PengaturanAkademik::current()->toleransi_terlambat_menit;

        $pertemuan = $this->pertemuan($filter)
            // Pertemuan yang belum tiba jadwalnya belum punya presensi dosen.
            ->whereDate('tanggal', '<=', today())
            ->when($filter['dari'], fn (Builder $q, string $dari) => $q->whereDate('tanggal', '>=', $dari))
            ->when($filter['sampai'], fn (Builder $q, string $sampai) => $q->whereDate('tanggal', '<=', $sampai))
            ->when($filter['status'] === 'terlaksana', fn (Builder $q) => $q->where('status', Pertemuan::SELESAI))
            ->when($filter['status'] === 'tidak_terlaksana', fn (Builder $q) => $q->where('status', '!=', Pertemuan::SELESAI)->whereNotNull('status_dosen'))
            ->when($filter['status'] === 'terlewat', fn (Builder $q) => $q->where('status', Pertemuan::DIJADWALKAN)->whereNull('status_dosen'))
            ->when($filter['verifikasi'] === 'menunggu', fn (Builder $q) => $q->where('status', Pertemuan::SELESAI)->whereNull('verifikasi'))
            ->when(in_array($filter['verifikasi'], [Pertemuan::DISETUJUI, Pertemuan::DITOLAK], true), fn (Builder $q) => $q->where('verifikasi', $filter['verifikasi']))
            ->with(['riwayatPresensiDosen.pengubah:id,name', 'pemverifikasi:id,name'])
            ->orderByDesc('tanggal')->orderByDesc('jam_mulai')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Pertemuan $p): array => $this->baris($p, $toleransi) + [
                'riwayat' => $p->riwayatPresensiDosen->map(fn (RiwayatPresensiDosen $r): array => $r->ringkas()),
            ]);

        return Inertia::render('Admin/PresensiDosen', [
            'pertemuan' => $pertemuan,
            'filter' => $filter,
            'toleransi' => $toleransi,
            'statusDosen' => collect(Pertemuan::STATUS_DOSEN)->map(fn (string $label, string $id): array => ['id' => $id, 'name' => $label])->values(),
            'dosenOptions' => DosenProfile::opsi(),
            ...$this->opsiFilter(),
        ]);
    }

    /**
     * Koreksi presensi dosen satu pertemuan. Status Hadir/Digantikan menandai pertemuan terlaksana (pertemuan yang
     * terlewat ikut ditutup sebagai susulan); status lain hanya untuk pertemuan yang tidak terlaksana.
     */
    public function update(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $pertemuan->loadMissing('kelasKuliah');
        $pengampuId = $pertemuan->kelasKuliah->dosen_id;

        $data = $request->validate([
            'status_dosen' => ['required', Rule::in(array_keys(Pertemuan::STATUS_DOSEN))],
            'dosen_id' => ['nullable', DosenProfile::rulePilihan([$pertemuan->dosen_id, $pengampuId])],
            'jam_masuk' => ['nullable', 'required_if:status_dosen,hadir,digantikan', 'date_format:H:i'],
            'jam_keluar' => ['nullable', 'required_if:status_dosen,hadir,digantikan', 'date_format:H:i', 'after:jam_masuk'],
            'topik' => ['nullable', 'required_if:status_dosen,hadir,digantikan', 'string', 'max:5000'],
            'alasan' => ['required', 'string', 'max:255'],
        ], [
            'required_if' => ':attribute wajib diisi bila dosen hadir atau digantikan.',
            'jam_keluar.after' => 'Jam keluar harus sesudah jam masuk.',
        ], [
            'status_dosen' => 'Status kehadiran', 'dosen_id' => 'Dosen pengajar', 'jam_masuk' => 'Jam masuk', 'jam_keluar' => 'Jam keluar',
            'topik' => 'Topik / realisasi materi', 'alasan' => 'Alasan koreksi',
        ]);

        if ($pertemuan->terverifikasi()) {
            return back()->with('error', 'Presensi pertemuan ini sudah diverifikasi. Batalkan verifikasinya dulu di menu Verifikasi Presensi Dosen.');
        }

        if ($pertemuan->status === Pertemuan::BERLANGSUNG) {
            return back()->with('error', 'Pertemuan ini sedang berlangsung. Koreksi presensi dosen setelah pertemuan selesai.');
        }

        $hadir = in_array($data['status_dosen'], Pertemuan::DOSEN_HADIR, true);
        $dosenId = isset($data['dosen_id']) ? (int) $data['dosen_id'] : $pertemuan->dosen_id ?? $pengampuId;

        if ($hadir) {
            if ($pertemuan->status === Pertemuan::DIJADWALKAN && now()->lt($pertemuan->mulaiAt())) {
                throw ValidationException::withMessages(['status_dosen' => 'Jadwal pertemuan ini belum tiba.']);
            }

            if ($data['status_dosen'] === 'hadir' && $dosenId !== $pengampuId) {
                throw ValidationException::withMessages(['dosen_id' => 'Status Hadir untuk dosen pengampu. Pilih Digantikan bila diajar dosen lain.']);
            }

            if ($data['status_dosen'] === 'digantikan' && $dosenId === $pengampuId) {
                throw ValidationException::withMessages(['dosen_id' => 'Pilih dosen pengganti (bukan dosen pengampu).']);
            }
        } elseif ($pertemuan->status === Pertemuan::SELESAI) {
            throw ValidationException::withMessages(['status_dosen' => 'Pertemuan ini sudah terlaksana. Status tidak hadir hanya untuk pertemuan yang tidak terlaksana.']);
        }

        $tanggal = $pertemuan->tanggal->toDateString();
        $baru = [
            'status_dosen' => $data['status_dosen'],
            'dosen_id' => $hadir ? $dosenId : $pertemuan->dosen_id,
            'dosen_masuk_at' => $hadir ? Carbon::parse("{$tanggal} {$data['jam_masuk']}") : null,
            'dosen_keluar_at' => $hadir ? Carbon::parse("{$tanggal} {$data['jam_keluar']}") : null,
            'topik' => $hadir ? trim($data['topik']) : $pertemuan->topik,
        ];
        $perubahan = $this->perubahan($pertemuan, $baru, $pengampuId);

        if ($perubahan === [] && $pertemuan->status !== Pertemuan::DIJADWALKAN) {
            return back()->with('success', 'Tidak ada perubahan presensi dosen.');
        }

        $susulan = $hadir && $pertemuan->status === Pertemuan::DIJADWALKAN;

        DB::transaction(function () use ($pertemuan, $baru, $susulan, $perubahan, $data, $request): void {
            $pertemuan->update($baru + ($susulan ? ['status' => Pertemuan::SELESAI] : []));

            if ($susulan) {
                $pertemuan->siapkanPeserta();
            }

            $pertemuan->riwayatPresensiDosen()->create([
                'aksi' => RiwayatPresensiDosen::KOREKSI,
                'perubahan' => $perubahan + ($susulan ? ['Pertemuan' => ['Terlewat', 'Terlaksana (susulan)']] : []),
                'alasan' => trim($data['alasan']),
                'diubah_oleh' => $request->user()->id,
            ]);
            $pertemuan->kembaliMenungguVerifikasi();
        });

        return back()->with('success', 'Presensi dosen pertemuan ke-'.$pertemuan->pertemuan_ke.' '.$pertemuan->kelasKuliah->kode_kelas.' dikoreksi.'
            .($susulan ? ' Isi presensi mahasiswanya di halaman pertemuan.' : ''));
    }

    /**
     * Rekap per dosen pengajar untuk honor: pertemuan terlaksana, total jam terjadwal, SKS, keterlambatan, dan
     * ketidakhadiran. Bawaan hanya pertemuan yang sudah diverifikasi.
     */
    public function rekap(Request $request): Response|StreamedResponse
    {
        Pertemuan::tutupYangLewat();
        $filter = $this->filter($request);
        $filter['bulan'] = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan')) ? $request->query('bulan') : null;
        $filter['semua'] = $request->boolean('semua');
        $toleransi = PengaturanAkademik::current()->toleransi_terlambat_menit;

        $pertemuan = $this->pertemuan($filter)
            ->when($filter['bulan'], fn (Builder $q, string $bulan) => $q->whereBetween('tanggal', [
                Carbon::parse("{$bulan}-01")->toDateString(), Carbon::parse("{$bulan}-01")->endOfMonth()->toDateString(),
            ]))
            ->whereDate('tanggal', '<=', today())
            ->with(['kelasKuliah.mataKuliah:id,sks'])
            ->get();

        $baris = $pertemuan->groupBy(fn (Pertemuan $p): int => (int) ($p->dosen_id ?? $p->kelasKuliah->dosen_id))
            ->map(function (Collection $daftar, int $dosenId) use ($filter, $toleransi): array {
                $terlaksana = $daftar->filter(fn (Pertemuan $p): bool => $p->status === Pertemuan::SELESAI && ($filter['semua'] || $p->terverifikasi()));
                $status = $daftar->map(fn (Pertemuan $p): ?string => $p->statusDosen())->countBy();

                return [
                    'dosen_id' => $dosenId,
                    'terlaksana' => $terlaksana->count(),
                    'digantikan' => $terlaksana->filter(fn (Pertemuan $p): bool => $p->statusDosen() === 'digantikan')->count(),
                    'jam' => round($terlaksana->sum(fn (Pertemuan $p): float => $p->durasiJam()), 2),
                    'sks' => $terlaksana->sum(fn (Pertemuan $p): int => (int) $p->kelasKuliah->mataKuliah?->sks),
                    'terlambat' => $terlaksana->filter(fn (Pertemuan $p): bool => $p->menitTerlambat($toleransi) !== null)->count(),
                    'belum_verifikasi' => $daftar->filter(fn (Pertemuan $p): bool => $p->status === Pertemuan::SELESAI && ! $p->terverifikasi())->count(),
                    'tidak_hadir' => $status['tidak_hadir'] ?? 0,
                    'kuliah_diganti' => $status['kuliah_diganti'] ?? 0,
                    'sakit' => $status['sakit'] ?? 0,
                    'izin' => $status['izin'] ?? 0,
                    'alpa' => $status['alpa'] ?? 0,
                    'terlewat' => $daftar->filter(fn (Pertemuan $p): bool => $p->terlewat() && $p->status_dosen === null)->count(),
                ];
            });

        $dosen = DosenProfile::with('user:id,name')->whereIn('id', $baris->keys())->get(['id', 'user_id', 'nidn'])->keyBy('id');
        $baris = $baris->map(fn (array $b): array => ['dosen' => $dosen[$b['dosen_id']]?->user?->name ?? '-', 'nidn' => $dosen[$b['dosen_id']]?->nidn] + $b)
            ->sortBy('dosen', SORT_NATURAL | SORT_FLAG_CASE)->values();

        if ($request->query('format') === 'csv') {
            return $this->csvRekap($baris, $filter);
        }

        return Inertia::render('Admin/RekapPresensiDosen', [
            'baris' => $baris,
            'filter' => $filter,
            'toleransi' => $toleransi,
            ...$this->opsiFilter(),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $baris
     * @param  array<string, mixed>  $filter
     */
    private function csvRekap(Collection $baris, array $filter): StreamedResponse
    {
        $tahun = TahunAkademik::find($filter['tahun_akademik_id']);
        $nama = 'rekap-presensi-dosen-'.Str::slug(($tahun?->tahun ?? '').'-'.($tahun?->semester ?? '').($filter['bulan'] ? '-'.$filter['bulan'] : '')).'.csv';

        return response()->streamDownload(function () use ($baris): void {
            $keluar = fopen('php://output', 'w');
            fwrite($keluar, "\xEF\xBB\xBF");
            fputcsv($keluar, ['Dosen', 'NIDN', 'Pertemuan Terlaksana', 'Sebagai Pengganti', 'Total Jam', 'Total SKS', 'Masuk Terlambat', 'Belum Diverifikasi', 'Tidak Hadir', 'Kuliah Diganti', 'Sakit', 'Izin', 'Alpa', 'Terlewat']);
            foreach ($baris as $b) {
                fputcsv($keluar, [$b['dosen'], $b['nidn'], $b['terlaksana'], $b['digantikan'], $b['jam'], $b['sks'], $b['terlambat'], $b['belum_verifikasi'], $b['tidak_hadir'], $b['kuliah_diganti'], $b['sakit'], $b['izin'], $b['alpa'], $b['terlewat']]);
            }
            fclose($keluar);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Satu baris pertemuan untuk daftar Presensi Dosen dan Verifikasi Presensi Dosen.
     *
     * @return array<string, mixed>
     */
    public static function baris(Pertemuan $p, int $toleransi): array
    {
        $kelas = $p->kelasKuliah;
        $status = $p->statusDosen($kelas->dosen_id);

        return [
            'id' => $p->id,
            'pertemuan_ke' => $p->pertemuan_ke,
            'jenis' => $p->jenis,
            'tanggal' => $p->tanggal->toDateString(),
            'jam_mulai' => substr($p->jam_mulai, 0, 5),
            'jam_akhir' => substr($p->jam_akhir, 0, 5),
            'status' => $p->status,
            'terlewat' => $p->terlewat(),
            'kelas' => ['id' => $kelas->id, 'kode' => $kelas->kode_kelas, 'mata_kuliah' => $kelas->mataKuliah?->nama_matkul, 'prodi' => $kelas->mataKuliah?->prodi?->nama_prodi],
            'pengampu' => ['id' => $kelas->dosen_id, 'nama' => $kelas->dosen?->user?->name],
            'pengajar' => ['id' => $p->dosen_id, 'nama' => $p->dosen?->user?->name],
            'status_dosen' => $status,
            'status_dosen_label' => $status ? Pertemuan::STATUS_DOSEN[$status] : ($p->terlewat() ? 'Terlewat' : 'Belum'),
            'jam_masuk' => $p->dosen_masuk_at?->format('H:i'),
            'jam_keluar' => $p->dosen_keluar_at?->format('H:i'),
            'menit_terlambat' => $p->menitTerlambat($toleransi),
            'topik' => $p->topik,
            'verifikasi' => $p->verifikasi,
            'catatan_verifikasi' => $p->catatan_verifikasi,
            'diverifikasi_oleh' => $p->pemverifikasi?->name,
            'diverifikasi_at' => $p->diverifikasi_at?->toIso8601String(),
        ];
    }

    /**
     * Filter bersama: tahun akademik (bawaan TA aktif), prodi, dosen pengajar, dan pencarian kelas/MK.
     *
     * @return array{tahun_akademik_id: ?int, prodi_id: ?int, dosen_id: ?int, search: string}
     */
    public static function filter(Request $request): array
    {
        return [
            'tahun_akademik_id' => $request->integer('tahun_akademik_id') ?: TahunAkademik::where('status', true)->value('id'),
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'dosen_id' => $request->integer('dosen_id') ?: null,
            'search' => trim((string) $request->query('search')),
        ];
    }

    /**
     * @param  array{tahun_akademik_id: ?int, prodi_id: ?int, dosen_id: ?int, search: string}  $filter
     * @return Builder<Pertemuan>
     */
    public static function pertemuan(array $filter): Builder
    {
        return Pertemuan::query()
            ->whereHas('kelasKuliah', fn (Builder $kelas) => $kelas
                ->where('tahun_akademik_id', $filter['tahun_akademik_id'])
                ->when($filter['prodi_id'], fn (Builder $q, int $prodi) => $q->whereHas('mataKuliah', fn (Builder $m) => $m->where('prodi_id', $prodi)))
                ->when($filter['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $cari) => $cari
                    ->where('kode_kelas', 'like', "%{$filter['search']}%")
                    ->orWhereHas('mataKuliah', fn (Builder $m) => $m->where('nama_matkul', 'like', "%{$filter['search']}%")->orWhere('kode_matkul', 'like', "%{$filter['search']}%")))))
            // Dosen pengajar pertemuan; pertemuan tanpa dosen memakai pengampu kelas.
            ->when($filter['dosen_id'], fn (Builder $q, int $dosen) => $q->where(fn (Builder $d) => $d->where('dosen_id', $dosen)
                ->orWhere(fn (Builder $tanpa) => $tanpa->whereNull('dosen_id')->whereHas('kelasKuliah', fn (Builder $k) => $k->where('dosen_id', $dosen)))))
            ->with(['kelasKuliah:id,kode_kelas,matkul_id,dosen_id', 'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul,sks,prodi_id', 'kelasKuliah.mataKuliah.prodi:id,nama_prodi',
                'kelasKuliah.dosen:id,user_id', 'kelasKuliah.dosen.user:id,name', 'dosen:id,user_id', 'dosen.user:id,name']);
    }

    /**
     * @return array{tahunAkademikOptions: Collection<int, array{id: int, name: string}>, prodiOptions: Collection<int, array{id: int, name: string}>, dosenFilterOptions: list<array{id: int, name: string}>}
     */
    public static function opsiFilter(): array
    {
        return [
            'tahunAkademikOptions' => TahunAkademik::orderByDesc('tanggal_mulai')->get(['id', 'tahun', 'semester'])
                ->map(fn (TahunAkademik $t): array => ['id' => $t->id, 'name' => $t->tahun.' '.$t->semester]),
            'prodiOptions' => ProgramStudi::orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => trim(($p->jenjang ? $p->jenjang.' ' : '').$p->nama_prodi)]),
            'dosenFilterOptions' => DosenProfile::query()->with('user:id,name')->get(['id', 'user_id'])
                ->map(fn (DosenProfile $d): array => ['id' => $d->id, 'name' => (string) $d->user?->name])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
        ];
    }

    /**
     * Daftar perubahan [label => [lama, baru]] untuk riwayat koreksi.
     *
     * @param  array<string, mixed>  $baru
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    private function perubahan(Pertemuan $pertemuan, array $baru, ?int $pengampuId): array
    {
        $namaDosen = fn (?int $id): ?string => $id ? DosenProfile::with('user:id,name')->find($id)?->user?->name : null;
        $statusLama = $pertemuan->statusDosen($pengampuId);
        $pasangan = [
            'Status kehadiran' => [$statusLama ? Pertemuan::STATUS_DOSEN[$statusLama] : null, Pertemuan::STATUS_DOSEN[$baru['status_dosen']]],
            'Dosen pengajar' => [$pertemuan->dosen_id, $baru['dosen_id']],
            'Jam masuk' => [$pertemuan->dosen_masuk_at?->format('H:i'), $baru['dosen_masuk_at']?->format('H:i')],
            'Jam keluar' => [$pertemuan->dosen_keluar_at?->format('H:i'), $baru['dosen_keluar_at']?->format('H:i')],
            'Topik' => [$pertemuan->topik, $baru['topik']],
        ];

        $hasil = array_filter($pasangan, fn (array $p): bool => $p[0] !== $p[1]);

        if (isset($hasil['Dosen pengajar'])) {
            $hasil['Dosen pengajar'] = [$namaDosen($hasil['Dosen pengajar'][0]), $namaDosen($hasil['Dosen pengajar'][1])];
        }

        return $hasil;
    }

    private function tanggal(mixed $nilai): ?string
    {
        return is_string($nilai) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) ? $nilai : null;
    }
}
