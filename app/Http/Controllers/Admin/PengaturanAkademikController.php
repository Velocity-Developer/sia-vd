<?php

namespace App\Http\Controllers\Admin;

use App\Feature;
use App\Http\Controllers\Controller;
use App\Models\BatasSks;
use App\Models\PengaturanAkademik;
use App\Models\PengaturanPindahKelas;
use App\Models\SkalaNilai;
use App\ValidasiBatasSks;
use App\ValidasiSkalaNilai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PengaturanAkademikController extends Controller
{
    public function index(): Response
    {
        $dipakai = SkalaNilai::jumlahDipakai();
        $pengaturan = PengaturanAkademik::current();

        return Inertia::render('PengaturanSistem/Akademik', [
            'maksSksTanpaIps' => $pengaturan->maks_sks_tanpa_ips,
            'kunciKrsAktif' => $pengaturan->kunci_krs_aktif,
            'verifikasiKrsAktif' => $pengaturan->verifikasi_krs_aktif,
            'susulan' => $pengaturan->only(['batas_pengajuan_susulan_hari', 'batas_bayar_susulan_hari']),
            'minSksPendadaran' => $pengaturan->min_sks_pendadaran,
            'minSksAmbilTa' => $pengaturan->min_sks_ambil_ta,
            'maksCuti' => $pengaturan->maks_cuti,
            'pindahKelasAktif' => PengaturanPindahKelas::current()->is_active,
            'presensi' => $pengaturan->only(['jumlah_pertemuan', 'toleransi_terlambat_menit', 'durasi_presensi_mandiri_menit', 'batas_pengajuan_izin_hari']),
            'batasSks' => BatasSks::query()->orderByDesc('ips_minimal')->get(['ips_minimal', 'maks_sks']),
            'skalaNilai' => SkalaNilai::query()->orderByDesc('bobot')->orderBy('huruf')->get(['huruf', 'bobot', 'angka_minimal', 'lulus', 'boleh_diulang'])
                ->map(fn (SkalaNilai $nilai): array => [...$nilai->toArray(), 'dipakai' => $dipakai[$nilai->huruf] ?? 0]),
        ]);
    }

    /**
     * Nyalakan/matikan penguncian KRS bagi mahasiswa yang tagihan semester berjalannya belum lunas.
     */
    public function updateKunciKrs(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kunci_krs_aktif' => ['required', 'boolean'],
        ], attributes: ['kunci_krs_aktif' => 'Penguncian KRS']);

        PengaturanAkademik::current()->update([
            'kunci_krs_aktif' => $data['kunci_krs_aktif'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', $data['kunci_krs_aktif']
            ? 'Penguncian KRS dinyalakan: mahasiswa dengan tagihan belum lunas tidak bisa mengisi KRS.'
            : 'Penguncian KRS dimatikan.');
    }

    /**
     * Nyalakan/matikan verifikasi KRS: KRS yang disimpan mahasiswa menunggu persetujuan admin dan bisa
     * dikembalikan untuk revisi sampai akhir masa revisi di Tahun Akademik.
     */
    public function updateVerifikasiKrs(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'verifikasi_krs_aktif' => ['required', 'boolean'],
        ], attributes: ['verifikasi_krs_aktif' => 'Verifikasi KRS']);

        PengaturanAkademik::current()->update([
            'verifikasi_krs_aktif' => $data['verifikasi_krs_aktif'],
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('success', $data['verifikasi_krs_aktif']
            ? 'Verifikasi KRS dinyalakan: KRS yang disimpan mahasiswa menunggu persetujuan admin.'
            : 'Verifikasi KRS dimatikan: KRS yang disimpan mahasiswa langsung final.');
    }

    /**
     * Bawaan presensi. Jumlah pertemuan hanya menjadi nilai awal kelas baru; kelas yang sudah ada tidak berubah.
     */
    public function updatePresensi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jumlah_pertemuan' => ['required', 'integer', 'min:1', 'max:32'],
            'toleransi_terlambat_menit' => ['required', 'integer', 'min:0', 'max:180'],
            'durasi_presensi_mandiri_menit' => ['required', 'integer', 'min:1', 'max:180'],
            'batas_pengajuan_izin_hari' => ['required', 'integer', 'min:0', 'max:14'],
        ], attributes: [
            'jumlah_pertemuan' => 'Jumlah pertemuan',
            'toleransi_terlambat_menit' => 'Toleransi terlambat',
            'durasi_presensi_mandiri_menit' => 'Durasi presensi mandiri',
            'batas_pengajuan_izin_hari' => 'Batas pengajuan izin',
        ]);

        PengaturanAkademik::current()->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Pengaturan presensi berhasil disimpan.');
    }

    /**
     * Buka/tutup form pengajuan pindah kelas bagi mahasiswa.
     */
    public function updatePindahKelas(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ], ['boolean' => ':attribute tidak valid.'], ['is_active' => 'Status form pindah kelas']);

        PengaturanPindahKelas::current()->update(['is_active' => $data['is_active'], 'updated_by' => $request->user()->id]);

        return back()->with('success', $data['is_active'] ? 'Form pindah kelas dibuka untuk mahasiswa.' : 'Form pindah kelas ditutup.');
    }

    public function updateBatasSks(Request $request): RedirectResponse
    {
        $data = ValidasiBatasSks::validasi($request);

        DB::transaction(function () use ($data, $request): void {
            PengaturanAkademik::current()->update(['maks_sks_tanpa_ips' => $data['maks_sks_tanpa_ips'], 'updated_by' => $request->user()->id]);
            BatasSks::query()->delete();

            foreach ($data['batas_sks'] as $row) {
                BatasSks::create($row);
            }
        });

        return back()->with('success', 'Batas SKS berhasil disimpan.');
    }

    public function updateSkalaNilai(Request $request): RedirectResponse
    {
        $data = ['skala_nilai' => ValidasiSkalaNilai::validasi($request, 'skala_nilai')];

        // Huruf yang sudah dipakai di nilai mahasiswa tidak boleh hilang, agar nilai lama tetap punya bobot.
        $hilang = collect(SkalaNilai::jumlahDipakai())->keys()->diff(collect($data['skala_nilai'])->pluck('huruf'));

        if ($hilang->isNotEmpty()) {
            throw ValidationException::withMessages([
                'skala_nilai' => 'Nilai '.$hilang->implode(', ').' masih dipakai di KRS mahasiswa sehingga tidak bisa dihapus.',
            ]);
        }

        DB::transaction(function () use ($data): void {
            // Batas huruf remidi yang hurufnya dihapus dari skala kembali bebas.
            $maks = PengaturanAkademik::current();
            if ($maks->huruf_maks_remidi !== null && ! collect($data['skala_nilai'])->pluck('huruf')->contains($maks->huruf_maks_remidi)) {
                $maks->update(['huruf_maks_remidi' => null]);
            }

            SkalaNilai::query()->delete();

            foreach ($data['skala_nilai'] as $row) {
                SkalaNilai::create(ValidasiSkalaNilai::kolom($row));
            }
        });

        return back()->with('success', 'Skala nilai berhasil disimpan.');
    }

    public function updateSusulan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'batas_pengajuan_susulan_hari' => ['required', 'integer', 'min:0', 'max:30'],
            // Batas bayar susulan hanya tampil selama fitur keuangan aktif; bila mati, nilai lamanya dibiarkan.
            'batas_bayar_susulan_hari' => Feature::aktif('keuangan') ? ['required', 'integer', 'min:1', 'max:30'] : ['exclude'],
        ], attributes: [
            'batas_pengajuan_susulan_hari' => 'Batas pengajuan susulan',
            'batas_bayar_susulan_hari' => 'Batas bayar susulan',
        ]);

        PengaturanAkademik::current()->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Pengaturan ujian susulan disimpan.');
    }

    public function updateTugasAkhir(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'min_sks_ambil_ta' => ['required', 'integer', 'min:0', 'max:300'],
            'min_sks_pendadaran' => ['required', 'integer', 'min:0', 'max:300'],
        ], attributes: ['min_sks_ambil_ta' => 'SKS minimal ambil TA/Skripsi', 'min_sks_pendadaran' => 'SKS minimal pendadaran']);

        PengaturanAkademik::current()->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Pengaturan tugas akhir disimpan.');
    }

    public function updateCuti(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'maks_cuti' => ['required', 'integer', 'min:0', 'max:14'],
        ], attributes: ['maks_cuti' => 'Batas cuti']);

        PengaturanAkademik::current()->update([...$data, 'updated_by' => $request->user()->id]);

        return back()->with('success', 'Pengaturan cuti disimpan.');
    }
}
