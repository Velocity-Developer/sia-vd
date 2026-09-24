<?php

namespace App\Http\Controllers\Mahasiswa;

use App\AllowedUpload;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Kelas\PresensiController as KelasPresensiController;
use App\Models\KelasKuliah;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\PengajuanIzin;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\TahunAkademik;
use App\SyaratUjian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PresensiController extends Controller
{
    /** Penanda perangkat untuk mendeteksi titip absen; bertahan lima tahun di browser. */
    private const COOKIE_PERANGKAT = 'presensi_perangkat';

    /**
     * Presensi mandiri (PIN) untuk pertemuan yang sedang dibuka, plus riwayat kehadiran per mata kuliah.
     */
    public function index(Request $request): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        Pertemuan::tutupYangLewat();

        $tahunAkademiks = TahunAkademik::query()
            ->whereHas('kelasKuliahs.krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))
            ->orderByDesc('tanggal_mulai')
            ->get(['id', 'tahun', 'semester', 'status']);
        $tahunAkademik = $tahunAkademiks->firstWhere('id', $request->integer('tahun_akademik_id'))
            ?? $tahunAkademiks->firstWhere('status', true)
            ?? $tahunAkademiks->first();

        $kelas = KelasKuliah::query()
            ->where('tahun_akademik_id', $tahunAkademik?->id)
            ->whereHas('krs', fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id))
            ->with(['mataKuliah:id,kode_matkul,nama_matkul,sks', 'dosen:id,user_id', 'dosen.user:id,name'])
            ->orderBy('kode_kelas')
            ->get(['id', 'kode_kelas', 'matkul_id', 'dosen_id', 'jumlah_pertemuan']);

        $rekap = PresensiMahasiswa::rekapMahasiswa($mahasiswa->id, $kelas->pluck('id')->all());
        $minKehadiran = PengaturanAkademik::current()->min_kehadiran_ujian;

        $pertemuan = Pertemuan::query()
            ->whereIn('kelas_id', $kelas->pluck('id'))
            ->with(['presensiMahasiswas' => fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id)->select(['id', 'pertemuan_id', 'mahasiswa_id', 'status', 'waktu_presensi', 'metode', 'keterangan'])])
            ->orderBy('pertemuan_ke')
            ->get(['id', 'kelas_id', 'pertemuan_ke', 'tanggal', 'jam_mulai', 'jam_akhir', 'jenis', 'status', 'topik'])
            ->groupBy('kelas_id');

        $pengajuan = PengajuanIzin::query()
            ->where('mahasiswa_id', $mahasiswa->id)
            ->whereIn('pertemuan_id', $pertemuan->flatten()->pluck('id'))
            ->get(['id', 'pertemuan_id', 'jenis', 'alasan', 'status', 'catatan_dosen'])
            ->keyBy('pertemuan_id');

        return Inertia::render('Mahasiswa/Presensi', [
            'tahunAkademiks' => $tahunAkademiks->map(fn (TahunAkademik $tahun): array => ['id' => $tahun->id, 'name' => $tahun->tahun.' '.$tahun->semester]),
            'tahunAkademikId' => $tahunAkademik?->id,
            'minKehadiran' => $minKehadiran,
            'terbuka' => $this->pertemuanTerbuka($mahasiswa),
            'batasIzinHari' => PengaturanAkademik::current()->batas_pengajuan_izin_hari,
            'kelas' => $kelas->map(function (KelasKuliah $item) use ($rekap, $pertemuan, $minKehadiran, $pengajuan, $mahasiswa): array {
                $daftar = $pertemuan->get($item->id, collect());
                $rekapKelas = $rekap[$item->id] ?? null;
                // Batas absen dari rencana pertemuan kuliah yang tidak dibatalkan.
                $rencana = $daftar->where('jenis', Pertemuan::KULIAH)->where('status', '!=', Pertemuan::DIBATALKAN)->count();
                $maksAbsen = (int) floor($rencana * (100 - $minKehadiran) / 100);
                $absen = $rekapKelas ? $rekapKelas['dihitung'] - $rekapKelas['hadir'] - $rekapKelas['terlambat'] : 0;
                $ujian = SyaratUjian::untukKelas($item, [$mahasiswa->id]);

                return [
                    'id' => $item->id,
                    'kode_kelas' => $item->kode_kelas,
                    'nama_matkul' => $item->mataKuliah?->nama_matkul,
                    'kode_matkul' => $item->mataKuliah?->kode_matkul,
                    'dosen' => $item->dosen?->user?->name,
                    'rekap' => $rekapKelas,
                    'rencana' => $rencana,
                    'sisa_absen' => $maksAbsen - $absen,
                    'ujian' => [
                        'aktif' => $ujian['aktif'],
                        'uts' => KelasPresensiController::jadwalUjian($ujian['jadwal']['uts']),
                        'uas' => KelasPresensiController::jadwalUjian($ujian['jadwal']['uas']),
                        'syarat' => $ujian['peserta'][$mahasiswa->id] ?? null,
                    ],
                    'pertemuan' => $daftar->map(fn (Pertemuan $p): array => [
                        'id' => $p->id,
                        'pertemuan_ke' => $p->pertemuan_ke,
                        'tanggal' => $p->tanggal?->toDateString(),
                        'jam_mulai' => $p->jam_mulai,
                        'jam_akhir' => $p->jam_akhir,
                        'jenis' => $p->jenis,
                        'status' => $p->status,
                        'topik' => $p->topik,
                        'presensi' => $p->presensiMahasiswas->first()?->toArray(),
                        'pengajuan' => $pengajuan->get($p->id)?->only(['jenis', 'alasan', 'status', 'catatan_dosen']),
                        'bisa_ajukan_izin' => $this->bisaAjukanIzin($p, $pengajuan->get($p->id)),
                    ])->values(),
                ];
            }),
        ]);
    }

    /**
     * Tujuan tautan QR. Hanya menampilkan konfirmasi; presensi baru tercatat setelah tombol ditekan
     * (tautan GET tidak boleh mengubah data).
     */
    public function masuk(Request $request, Pertemuan $pertemuan): Response
    {
        $mahasiswa = $this->mahasiswa($request);
        $pertemuan->load(['kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul', 'ruang:id,kode_ruang']);
        $terdaftar = Krs::where('kelas_id', $pertemuan->kelas_id)->where('mahasiswa_id', $mahasiswa->id)->exists();

        return Inertia::render('Mahasiswa/PresensiMasuk', [
            'pertemuan' => $terdaftar ? [
                ...$pertemuan->only(['id', 'pertemuan_ke', 'jam_mulai', 'jam_akhir', 'jenis']),
                'tanggal' => $pertemuan->tanggal->toDateString(),
                'kelas_kuliah' => $pertemuan->kelasKuliah,
                'ruang' => $pertemuan->ruang,
            ] : null,
            'kode' => $request->string('k')->limit(32, '')->toString(),
            'terdaftar' => $terdaftar,
            'terbuka' => $terdaftar && $pertemuan->mandiriTerbuka(),
            'presensi' => $terdaftar
                ? $pertemuan->presensiMahasiswas()->where('mahasiswa_id', $mahasiswa->id)->first(['status', 'waktu_presensi'])
                : null,
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $data = $request->validate([
            'pertemuan_id' => ['required', 'integer'],
            'kode' => ['required', 'string', 'max:32'],
        ], attributes: ['kode' => 'Kode presensi']);

        $pertemuan = Pertemuan::find($data['pertemuan_id']);
        $kode = strtolower(preg_replace('/\s+/', '', $data['kode']));

        if ($pertemuan === null || ! Krs::where('kelas_id', $pertemuan->kelas_id)->where('mahasiswa_id', $mahasiswa->id)->exists()) {
            throw ValidationException::withMessages(['kode' => 'Anda tidak terdaftar di kelas pertemuan ini.']);
        }

        if (! $pertemuan->mandiriTerbuka()) {
            throw ValidationException::withMessages(['kode' => 'Presensi mandiri pertemuan ini belum dibuka atau sudah ditutup dosen.']);
        }

        if (! $pertemuan->kodeCocok($kode)) {
            throw ValidationException::withMessages(['kode' => 'Kode salah atau sudah kedaluwarsa. Pindai ulang QR atau ketik PIN yang sedang tampil.']);
        }

        $perangkat = $request->cookie(self::COOKIE_PERANGKAT);
        $perangkat = is_string($perangkat) && strlen($perangkat) === 40 ? $perangkat : Str::random(40);
        Cookie::queue(self::COOKIE_PERANGKAT, $perangkat, 60 * 24 * 365 * 5);

        $pertemuan->siapkanPeserta();

        $hasil = DB::transaction(function () use ($pertemuan, $mahasiswa, $request, $kode, $perangkat): array {
            $baris = $pertemuan->presensiMahasiswas()->where('mahasiswa_id', $mahasiswa->id)->lockForUpdate()->firstOrFail();

            if (in_array($baris->status, PresensiMahasiswa::DIHITUNG_HADIR, true)) {
                return [false, $baris];
            }

            $baris->update([
                'status' => $pertemuan->statusPresensiMandiri(),
                'waktu_presensi' => now(),
                'metode' => strlen($kode) === 6 ? 'pin' : 'qr',
                'diubah_oleh' => $request->user()->id,
                'ip' => $request->ip(),
                'perangkat' => hash('sha256', $perangkat),
            ]);

            return [true, $baris];
        });

        [$baru, $baris] = $hasil;
        $jam = $baris->waktu_presensi?->format('H:i');

        return to_route('mahasiswa.presensi')->with('success', $baru
            ? ($baris->status === PresensiMahasiswa::TERLAMBAT ? "Presensi tercatat pukul {$jam} (terlambat)." : "Presensi tercatat hadir pukul {$jam}.")
            : "Anda sudah tercatat hadir pukul {$jam}.");
    }

    /**
     * Ajukan izin/sakit untuk satu pertemuan, paling lambat N hari sesudah tanggalnya. Pengajuan yang
     * ditolak boleh diajukan ulang selama masih dalam batas waktu.
     */
    public function ajukanIzin(Request $request): RedirectResponse
    {
        $mahasiswa = $this->mahasiswa($request);
        $data = $request->validate([
            'pertemuan_id' => ['required', 'integer'],
            'jenis' => ['required', Rule::in(PengajuanIzin::JENIS)],
            'alasan' => ['required', 'string', 'max:1000'],
            'lampiran' => ['nullable', 'array', 'max:3'],
            'lampiran.*' => ['file', 'max:5120', 'extensions:'.implode(',', PengajuanIzin::EKSTENSI_LAMPIRAN), 'mimes:'.implode(',', PengajuanIzin::EKSTENSI_LAMPIRAN)],
        ], [
            'lampiran.*.extensions' => 'Lampiran harus berupa PDF atau foto (JPG/PNG).',
            'lampiran.*.mimes' => 'Isi lampiran tidak sesuai dengan formatnya.',
            'lampiran.*.max' => 'Ukuran lampiran maksimal 5 MB.',
        ], ['jenis' => 'Jenis', 'alasan' => 'Alasan', 'lampiran' => 'Lampiran']);

        $pertemuan = Pertemuan::find($data['pertemuan_id']);

        if ($pertemuan === null || ! Krs::where('kelas_id', $pertemuan->kelas_id)->where('mahasiswa_id', $mahasiswa->id)->exists()) {
            throw ValidationException::withMessages(['pertemuan_id' => 'Anda tidak terdaftar di kelas pertemuan ini.']);
        }

        $pertemuan->load(['presensiMahasiswas' => fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id)]);
        $lama = PengajuanIzin::where('pertemuan_id', $pertemuan->id)->where('mahasiswa_id', $mahasiswa->id)->first();

        if (! $this->bisaAjukanIzin($pertemuan, $lama)) {
            throw ValidationException::withMessages(['pertemuan_id' => $lama !== null && $lama->status !== PengajuanIzin::DITOLAK
                ? 'Pengajuan untuk pertemuan ini sudah dikirim.'
                : 'Batas pengajuan izin untuk pertemuan ini sudah lewat, atau Anda sudah tercatat hadir.']);
        }

        $lampiran = collect($request->file('lampiran', []))
            ->map(fn ($berkas): string => $berkas->storeAs('izin', Str::random(24).'.'.strtolower($berkas->getClientOriginalExtension()), AllowedUpload::DISK))
            ->all();

        if ($lama?->lampiran) {
            Storage::disk(AllowedUpload::DISK)->delete($lama->lampiran);
        }

        PengajuanIzin::updateOrCreate(
            ['pertemuan_id' => $pertemuan->id, 'mahasiswa_id' => $mahasiswa->id],
            [
                'jenis' => $data['jenis'], 'alasan' => $data['alasan'], 'lampiran' => $lampiran,
                'status' => PengajuanIzin::MENUNGGU, 'diproses_oleh' => null, 'diproses_at' => null, 'catatan_dosen' => null,
            ],
        );

        return back()->with('success', 'Pengajuan '.$data['jenis'].' untuk pertemuan ke-'.$pertemuan->pertemuan_ke.' dikirim ke dosen pengampu.');
    }

    private function bisaAjukanIzin(Pertemuan $pertemuan, ?PengajuanIzin $lama): bool
    {
        $status = $pertemuan->relationLoaded('presensiMahasiswas')
            ? $pertemuan->presensiMahasiswas->first()?->status
            : null;

        return PengajuanIzin::masihBisaDiajukan($pertemuan)
            && ($lama === null || $lama->status === PengajuanIzin::DITOLAK)
            && ! in_array($status, PresensiMahasiswa::DIHITUNG_HADIR, true);
    }

    /**
     * Pertemuan kelas mahasiswa yang sedang membuka presensi mandiri.
     *
     * @return list<array<string, mixed>>
     */
    private function pertemuanTerbuka(MahasiswaProfile $mahasiswa): array
    {
        return Pertemuan::query()
            ->where('status', Pertemuan::BERLANGSUNG)
            ->where('mandiri_sampai', '>', now())
            ->whereIn('kelas_id', Krs::where('mahasiswa_id', $mahasiswa->id)->select('kelas_id'))
            ->with(['kelasKuliah:id,kode_kelas,matkul_id', 'kelasKuliah.mataKuliah:id,nama_matkul', 'presensiMahasiswas' => fn ($query) => $query->where('mahasiswa_id', $mahasiswa->id)])
            ->get()
            ->map(fn (Pertemuan $p): array => [
                'id' => $p->id,
                'pertemuan_ke' => $p->pertemuan_ke,
                'jam_mulai' => $p->jam_mulai,
                'jam_akhir' => $p->jam_akhir,
                'kode_kelas' => $p->kelasKuliah?->kode_kelas,
                'nama_matkul' => $p->kelasKuliah?->mataKuliah?->nama_matkul,
                'status_saya' => $p->presensiMahasiswas->first()?->status,
            ])
            ->all();
    }

    private function mahasiswa(Request $request): MahasiswaProfile
    {
        $mahasiswa = $request->user()->mahasiswaProfile;
        abort_if($mahasiswa === null, 403);

        return $mahasiswa;
    }
}
