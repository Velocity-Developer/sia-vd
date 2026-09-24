<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\AksesPresensi;
use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\SyaratUjian;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Satu pertemuan: jadwal ulang/batal, presensi dosen (mulai–selesai + jurnal), dan presensi mahasiswa.
 */
class PertemuanController extends Controller
{
    use AksesPresensi, KontenKelas;

    public function show(Pertemuan $pertemuan): Response
    {
        $kelas = $pertemuan->kelasKuliah;
        abort_unless($this->bolehLihatKelas($kelas) || $this->penggantiPertemuan($pertemuan), 403);
        Pertemuan::tutupYangLewat();
        $pertemuan->refresh();

        if ($pertemuan->status === Pertemuan::BERLANGSUNG) {
            $pertemuan->siapkanPeserta();
        }

        $kelas->load(['mataKuliah:id,kode_matkul,nama_matkul', 'dosen:id,user_id', 'dosen.user:id,name', 'tahunAkademik:id,tahun,semester,status']);
        $pertemuan->load(['ruang:id,kode_ruang,nama_ruang', 'dosen:id,user_id,nidn', 'dosen.user:id,name']);

        $baris = $pertemuan->presensiMahasiswas()
            ->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name', 'pengubah:id,name'])
            ->get();

        // Pada pertemuan UTS/UAS, tandai mahasiswa yang belum memenuhi syarat kehadiran (bila syarat diberlakukan).
        $syarat = $pertemuan->jenis !== Pertemuan::KULIAH ? SyaratUjian::untukKelas($kelas)['peserta'] : collect();
        $pengajuan = $pertemuan->pengajuanIzins()->get(['id', 'mahasiswa_id', 'jenis', 'status'])->keyBy('mahasiswa_id');

        // Satu perangkat yang dipakai presensi mandiri oleh lebih dari satu mahasiswa: kemungkinan titip absen.
        $perangkatBersama = $baris->whereNotNull('perangkat')->countBy('perangkat')->filter(fn (int $jumlah): bool => $jumlah > 1);

        $presensi = $baris
            ->sortBy(fn (PresensiMahasiswa $baris) => $baris->mahasiswa?->nim)
            ->values()
            ->map(fn (PresensiMahasiswa $baris): array => [
                'mahasiswa_id' => $baris->mahasiswa_id,
                'nim' => $baris->mahasiswa?->nim,
                'nama' => $baris->mahasiswa?->user?->name,
                'status' => $baris->status,
                'keterangan' => $baris->keterangan,
                'metode' => $baris->metode,
                'waktu_presensi' => $baris->waktu_presensi?->toIso8601String(),
                'diubah_oleh' => $baris->pengubah?->name,
                'perangkat_bersama' => $baris->perangkat !== null && $perangkatBersama->has($baris->perangkat),
                'memenuhi_syarat_ujian' => $syarat[$baris->mahasiswa_id][$pertemuan->jenis]['memenuhi'] ?? null,
                'pengajuan' => $pengajuan[$baris->mahasiswa_id] ?? null,
            ]);

        return Inertia::render('Kelas/PresensiPertemuan', [
            'peran' => $this->peran(),
            'kelasKuliah' => $kelas,
            'pertemuan' => $pertemuan,
            'presensi' => $presensi,
            'jumlahPeserta' => $kelas->krs()->count(),
            'bisaKelola' => $this->bolehKelolaPertemuan($pertemuan),
            'bisaAturJadwal' => $this->pengampu($kelas),
            'bisaDimulai' => $this->bolehKelolaPertemuan($pertemuan) && $this->bisaDimulai($pertemuan),
            // Hitung mundur di layar memakai jam server, bukan jam perangkat.
            'detikSampaiMulai' => $pertemuan->status === Pertemuan::DIJADWALKAN && now()->lt($pertemuan->mulaiAt())
                ? (int) now()->diffInSeconds($pertemuan->mulaiAt(), true)
                : null,
            'mandiriTerbuka' => $pertemuan->mandiriTerbuka(),
            'durasiMandiri' => PengaturanAkademik::current()->durasi_presensi_mandiri_menit,
            'terkunci' => $this->nilaiTerkunci($kelas),
            'dosenOptions' => $this->peran() === 'admin'
                ? DosenProfile::with('user:id,name')->orderBy('nidn')->get(['id', 'user_id', 'nidn'])
                    ->map(fn (DosenProfile $dosen): array => ['id' => $dosen->id, 'name' => ($dosen->user?->name ?? '-').' — '.$dosen->nidn])
                : [],
        ]);
    }

    /**
     * Jadwal ulang (tanggal, jam, ruang) hanya selama pertemuan belum dimulai; jenis dan catatan boleh
     * diubah kapan saja selama belum terkunci. Dosen pengganti hanya diatur admin.
     */
    public function update(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $kelas = $pertemuan->kelasKuliah;
        $this->pastikanPengampu($kelas);
        $this->pastikanTidakTerkunci($pertemuan);

        $data = $request->validate([
            'tanggal' => ['required', 'date_format:Y-m-d'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_akhir' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruang_id' => ['nullable', 'exists:ruangs,id'],
            'jenis' => ['required', Rule::in(Pertemuan::JENIS)],
            'catatan' => ['nullable', 'string', 'max:255'],
            'dosen_id' => ['nullable', 'exists:dosen_profiles,id'],
        ], [
            'after' => ':attribute harus lebih besar dari Jam Mulai.',
            'date_format' => ':attribute tidak valid.',
        ], [
            'tanggal' => 'Tanggal', 'jam_mulai' => 'Jam Mulai', 'jam_akhir' => 'Jam Akhir', 'ruang_id' => 'Ruang',
            'jenis' => 'Jenis', 'catatan' => 'Catatan', 'dosen_id' => 'Dosen',
        ]);

        if ($this->peran() !== 'admin') {
            unset($data['dosen_id']);
        }

        $waktuBerubah = $data['tanggal'] !== $pertemuan->tanggal->toDateString()
            || $data['jam_mulai'] !== substr($pertemuan->jam_mulai, 0, 5)
            || $data['jam_akhir'] !== substr($pertemuan->jam_akhir, 0, 5)
            || (int) ($data['ruang_id'] ?? 0) !== (int) $pertemuan->ruang_id;
        $jadwalBerubah = $waktuBerubah
            || (array_key_exists('dosen_id', $data) && (int) ($data['dosen_id'] ?? 0) !== (int) $pertemuan->dosen_id);

        if ($jadwalBerubah) {
            if ($pertemuan->status !== Pertemuan::DIJADWALKAN) {
                throw ValidationException::withMessages(['tanggal' => 'Tanggal, jam, ruang, dan dosen hanya bisa diubah sebelum pertemuan dimulai.']);
            }

            $bentrok = Pertemuan::bentrok(
                $kelas,
                $data['tanggal'],
                $data['jam_mulai'].':00',
                $data['jam_akhir'].':00',
                $data['ruang_id'] ?? null,
                $data['dosen_id'] ?? $pertemuan->dosen_id,
                $pertemuan->id,
            );

            if ($bentrok !== null) {
                throw ValidationException::withMessages(['tanggal' => $bentrok]);
            }

            // Jadwal ulang manual: pertemuan ini tidak ikut disusun ulang saat jadwal mingguan berubah.
            if ($waktuBerubah) {
                $data['jadwal_manual'] = true;
            }
        }

        $pertemuan->update($data);

        return back()->with('success', 'Pertemuan ke-'.$pertemuan->pertemuan_ke.' diperbarui.');
    }

    public function batal(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanPengampu($pertemuan->kelasKuliah);
        $this->pastikanTidakTerkunci($pertemuan);

        $data = $request->validate(['catatan' => ['required', 'string', 'max:255']], attributes: ['catatan' => 'Alasan pembatalan']);

        if ($pertemuan->status !== Pertemuan::DIJADWALKAN) {
            return back()->with('error', 'Hanya pertemuan yang belum dimulai yang bisa dibatalkan.');
        }

        $pertemuan->update(['status' => Pertemuan::DIBATALKAN, 'catatan' => $data['catatan']]);

        return back()->with('success', 'Pertemuan ke-'.$pertemuan->pertemuan_ke.' dibatalkan dan tidak dihitung dalam kehadiran.');
    }

    public function aktifkan(Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanPengampu($pertemuan->kelasKuliah);
        $this->pastikanTidakTerkunci($pertemuan);

        if ($pertemuan->status !== Pertemuan::DIBATALKAN) {
            return back()->with('error', 'Pertemuan ini tidak sedang dibatalkan.');
        }

        $pertemuan->update(['status' => Pertemuan::DIJADWALKAN]);

        return back()->with('success', 'Pertemuan ke-'.$pertemuan->pertemuan_ke.' dijadwalkan kembali.');
    }

    /**
     * Dosen membuka pertemuan: jam masuk tercatat dan daftar hadir mahasiswa disiapkan (bawaan Alpa).
     * Admin boleh membuka di luar jam (mis. mencatat pertemuan yang terlewat), tanpa jam masuk dosen.
     */
    public function mulai(Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanKelolaPertemuan($pertemuan);
        $this->pastikanTidakTerkunci($pertemuan);

        $dimulai = DB::transaction(function () use ($pertemuan): bool {
            $pertemuan = Pertemuan::query()->lockForUpdate()->findOrFail($pertemuan->id);

            if (! $this->bisaDimulai($pertemuan)) {
                return false;
            }

            $pertemuan->update([
                'status' => Pertemuan::BERLANGSUNG,
                'dosen_masuk_at' => $this->peran() === 'dosen' ? now() : null,
                'dosen_id' => $this->peran() === 'dosen' ? request()->user()->dosenProfile?->id : $pertemuan->dosen_id,
            ]);
            $pertemuan->siapkanPeserta();

            return true;
        });

        if (! $dimulai) {
            $pertemuan->refresh();

            $mulai = $pertemuan->mulaiAt()->locale('id')->translatedFormat('l, d F Y \\p\\u\\k\\u\\l H.i');

            return back()->with('error', match (true) {
                $pertemuan->status !== Pertemuan::DIJADWALKAN => 'Pertemuan ini sudah dimulai, selesai, atau dibatalkan.',
                now()->lt($pertemuan->mulaiAt()) => "Pertemuan belum bisa dibuka. Pertemuan bisa dimulai {$mulai}.",
                default => 'Jam pertemuan sudah lewat. Pertemuan yang terlewat hanya bisa dicatat admin sebagai susulan.',
            });
        }

        return back()->with('success', 'Pertemuan ke-'.$pertemuan->pertemuan_ke.' dimulai. Catat kehadiran mahasiswa di bawah.');
    }

    /**
     * Tutup pertemuan. Topik/realisasi materi wajib diisi sebagai jurnal perkuliahan.
     */
    public function selesai(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanKelolaPertemuan($pertemuan);
        $this->pastikanTidakTerkunci($pertemuan);

        $data = $request->validate(['topik' => ['required', 'string', 'max:5000']], attributes: ['topik' => 'Topik / realisasi materi']);

        if ($pertemuan->status !== Pertemuan::BERLANGSUNG) {
            return back()->with('error', 'Pertemuan ini belum dimulai atau sudah selesai.');
        }

        $pertemuan->update([
            'status' => Pertemuan::SELESAI,
            'topik' => $data['topik'],
            'mandiri_sampai' => null,
            'dosen_keluar_at' => $this->peran() === 'dosen' ? now() : $pertemuan->dosen_keluar_at,
        ]);

        return back()->with('success', 'Pertemuan ke-'.$pertemuan->pertemuan_ke.' selesai.');
    }

    /**
     * Buka presensi mandiri (QR/PIN) selama sekian menit; menekan lagi memperpanjang waktunya.
     */
    public function bukaMandiri(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanKelolaPertemuan($pertemuan);
        $this->pastikanTidakTerkunci($pertemuan);

        $data = $request->validate(['menit' => ['nullable', 'integer', 'min:1', 'max:180']], attributes: ['menit' => 'Durasi']);

        if ($pertemuan->status !== Pertemuan::BERLANGSUNG) {
            return back()->with('error', 'Presensi mandiri hanya bisa dibuka saat pertemuan berlangsung.');
        }

        // Presensi mandiri hanya dalam jam pertemuan; pertemuan susulan diisi manual.
        if ($pertemuan->akhirAt()->isPast()) {
            return back()->with('error', 'Jam pertemuan sudah berakhir, jadi presensi mandiri tidak bisa dibuka. Isi presensi secara manual.');
        }

        $menit = (int) ($data['menit'] ?? PengaturanAkademik::current()->durasi_presensi_mandiri_menit);
        $sampai = now()->addMinutes($menit)->min($pertemuan->akhirAt());

        $pertemuan->update([
            'kode_rahasia' => $pertemuan->kode_rahasia ?? Str::random(40),
            'mandiri_sampai' => $sampai,
        ]);

        return back()->with('success', 'Presensi mandiri dibuka sampai pukul '.$sampai->format('H.i').'. Tampilkan QR atau PIN kepada mahasiswa.');
    }

    public function tutupMandiri(Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanKelolaPertemuan($pertemuan);
        $pertemuan->update(['mandiri_sampai' => null]);

        return back()->with('success', 'Presensi mandiri ditutup.');
    }

    /**
     * Kode yang sedang berlaku, diminta berkala oleh layar QR dosen.
     */
    public function kode(Pertemuan $pertemuan): JsonResponse
    {
        $this->pastikanKelolaPertemuan($pertemuan);

        if (! $pertemuan->mandiriTerbuka()) {
            return response()->json(['terbuka' => false]);
        }

        $periode = Pertemuan::periodeKode();
        $kode = $pertemuan->kodeUntuk($periode);

        return response()->json([
            'terbuka' => true,
            'pin' => $kode['pin'],
            'url' => route('mahasiswa.presensi.masuk', ['pertemuan' => $pertemuan->id, 'k' => $kode['token']]),
            'sisa_detik' => ($periode + 1) * Pertemuan::PERIODE_KODE_DETIK - now()->getTimestamp(),
            'sampai' => $pertemuan->mandiri_sampai->toIso8601String(),
            'hadir' => $pertemuan->presensiMahasiswas()->whereIn('status', PresensiMahasiswa::DIHITUNG_HADIR)->count(),
            'total' => $pertemuan->presensiMahasiswas()->count(),
        ]);
    }

    public function jurnal(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanKelolaPertemuan($pertemuan);
        $this->pastikanTidakTerkunci($pertemuan);

        $data = $request->validate(['topik' => ['nullable', 'string', 'max:5000']], attributes: ['topik' => 'Topik / realisasi materi']);

        if (! in_array($pertemuan->status, [Pertemuan::BERLANGSUNG, Pertemuan::SELESAI], true)) {
            return back()->with('error', 'Jurnal diisi setelah pertemuan dimulai.');
        }

        $pertemuan->update(['topik' => $data['topik']]);

        return back()->with('success', 'Jurnal pertemuan disimpan.');
    }

    /**
     * Simpan status kehadiran beberapa mahasiswa sekaligus. Hanya baris yang berubah yang ditulis,
     * supaya catatan "diubah oleh" tetap menunjuk orang yang benar-benar mengubah.
     */
    public function simpanPresensi(Request $request, Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanKelolaPertemuan($pertemuan);
        $this->pastikanTidakTerkunci($pertemuan);

        $data = $request->validate([
            'presensi' => ['required', 'array', 'min:1'],
            'presensi.*.mahasiswa_id' => ['required', 'integer', 'distinct'],
            'presensi.*.status' => ['required', Rule::in(PresensiMahasiswa::STATUS)],
            'presensi.*.keterangan' => ['nullable', 'string', 'max:255'],
        ], attributes: ['presensi.*.status' => 'Status', 'presensi.*.keterangan' => 'Keterangan']);

        if (! in_array($pertemuan->status, [Pertemuan::BERLANGSUNG, Pertemuan::SELESAI], true)) {
            return back()->with('error', 'Presensi mahasiswa diisi setelah pertemuan dimulai.');
        }

        $diubah = 0;

        DB::transaction(function () use ($data, $pertemuan, $request, &$diubah): void {
            $baris = $pertemuan->presensiMahasiswas()->lockForUpdate()->get()->keyBy('mahasiswa_id');

            foreach ($data['presensi'] as $isian) {
                $presensi = $baris->get($isian['mahasiswa_id']);
                abort_if($presensi === null, 422, 'Mahasiswa tidak terdaftar di pertemuan ini.');

                $keterangan = filled($isian['keterangan'] ?? null) ? trim($isian['keterangan']) : null;

                if ($presensi->status === $isian['status'] && $presensi->keterangan === $keterangan) {
                    continue;
                }

                $hadir = in_array($isian['status'], PresensiMahasiswa::DIHITUNG_HADIR, true);

                $presensi->update([
                    'status' => $isian['status'],
                    'keterangan' => $keterangan,
                    'metode' => 'manual',
                    'diubah_oleh' => $request->user()->id,
                    'waktu_presensi' => $hadir ? ($presensi->waktu_presensi ?? now()) : null,
                ]);
                $diubah++;
            }
        });

        return back()->with('success', $diubah > 0 ? "Presensi {$diubah} mahasiswa disimpan." : 'Tidak ada perubahan presensi.');
    }

    private function bisaDimulai(Pertemuan $pertemuan): bool
    {
        if ($this->nilaiTerkunci($pertemuan->kelasKuliah)) {
            return false;
        }

        return $this->peran() === 'admin' ? $pertemuan->bisaDimulaiAdmin() : $pertemuan->bisaDimulaiDosen();
    }

    /**
     * Sama seperti nilai: dosen tidak bisa mengubah presensi setelah tahun akademik kelas tidak aktif.
     */
    private function pastikanTidakTerkunci(Pertemuan $pertemuan): void
    {
        abort_if($this->nilaiTerkunci($pertemuan->kelasKuliah), 403, 'Tahun akademik kelas ini sudah tidak aktif, presensi tidak dapat diubah lagi.');
    }
}
