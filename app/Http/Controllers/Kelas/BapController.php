<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\AksesPresensi;
use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\KelasKuliah;
use App\Models\PengaturanInstitusi;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Berita Acara Perkuliahan (BAP) PDF: per pertemuan dan rekap satu kelas. Dosen hanya mencetak pertemuan yang sudah
 * diverifikasi; admin boleh mencetak draf (bertanda DRAF) sebelum verifikasi.
 */
class BapController extends Controller
{
    use AksesPresensi, KontenKelas;

    public function pertemuan(Pertemuan $pertemuan): Response|RedirectResponse
    {
        $kelas = $pertemuan->kelasKuliah;
        abort_unless($this->bolehLihatKelas($kelas) || $this->penggantiPertemuan($pertemuan), 403);

        if ($pertemuan->status !== Pertemuan::SELESAI) {
            return back()->with('error', 'BAP hanya untuk pertemuan yang sudah selesai.');
        }

        if (! $pertemuan->terverifikasi() && $this->peran() !== 'admin') {
            return back()->with('error', 'BAP bisa dicetak setelah presensi pertemuan ini diverifikasi admin.');
        }

        $this->muatKelas($kelas);
        $pertemuan->load(['ruang:id,kode_ruang,nama_ruang', 'dosen:id,user_id,nidn', 'dosen.user:id,name', 'pemverifikasi:id,name']);
        $presensi = $pertemuan->presensiMahasiswas()->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name'])->get()
            ->sortBy(fn (PresensiMahasiswa $p) => $p->mahasiswa?->nim)->values();

        return $this->pdf('pdf.bap-pertemuan', [
            'kelas' => $kelas,
            'pertemuan' => $pertemuan,
            'presensi' => $presensi,
            'rekap' => $presensi->countBy('status'),
            'draf' => ! $pertemuan->terverifikasi(),
            'petugas' => $pertemuan->pemverifikasi?->name,
            'tanggal' => $pertemuan->tanggal,
        ], 'bap-'.Str::slug($kelas->kode_kelas.'-pertemuan-'.$pertemuan->pertemuan_ke));
    }

    public function kelas(KelasKuliah $kelasKuliah): Response|RedirectResponse
    {
        $this->pastikanLihatKelas($kelasKuliah);
        $admin = $this->peran() === 'admin';

        $pertemuan = $kelasKuliah->pertemuans()
            ->where('status', Pertemuan::SELESAI)
            ->when(! $admin, fn ($q) => $q->where('verifikasi', Pertemuan::DISETUJUI))
            ->with(['dosen:id,user_id', 'dosen.user:id,name', 'pemverifikasi:id,name'])
            ->withCount([
                'presensiMahasiswas as jumlah_peserta',
                'presensiMahasiswas as jumlah_hadir' => fn ($q) => $q->whereIn('status', PresensiMahasiswa::DIHITUNG_HADIR),
            ])
            ->orderBy('pertemuan_ke')
            ->get();

        if ($pertemuan->isEmpty()) {
            return back()->with('error', $admin ? 'Belum ada pertemuan yang selesai di kelas ini.' : 'Belum ada pertemuan yang diverifikasi admin di kelas ini.');
        }

        $this->muatKelas($kelasKuliah);
        $terakhir = $pertemuan->where('verifikasi', Pertemuan::DISETUJUI)->sortByDesc('diverifikasi_at')->first();

        return $this->pdf('pdf.bap-kelas', [
            'kelas' => $kelasKuliah,
            'pertemuan' => $pertemuan,
            'draf' => $pertemuan->contains(fn (Pertemuan $p): bool => ! $p->terverifikasi()),
            'petugas' => $terakhir?->pemverifikasi?->name,
            'tanggal' => now(),
        ], 'bap-'.Str::slug($kelasKuliah->kode_kelas.'-'.$kelasKuliah->tahunAkademik?->tahun.'-'.$kelasKuliah->tahunAkademik?->semester));
    }

    private function muatKelas(KelasKuliah $kelas): void
    {
        $kelas->load([
            'mataKuliah:id,kode_matkul,nama_matkul,sks,prodi_id', 'mataKuliah.prodi:id,nama_prodi,jenjang,kaprodi',
            'mataKuliah.prodi.ketuaProgramStudi:id,user_id,nidn', 'mataKuliah.prodi.ketuaProgramStudi.user:id,name',
            'dosen:id,user_id,nidn', 'dosen.user:id,name', 'tahunAkademik:id,tahun,semester',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pdf(string $view, array $data, string $nama): Response
    {
        $institusi = PengaturanInstitusi::current();

        return Pdf::loadView($view, $data + [
            'institusi' => $institusi,
            'logoSrc' => $institusi->logoDataUri(),
            'kontak' => $institusi->kontakKop(),
            'statusDosen' => Pertemuan::STATUS_DOSEN,
        ])->setPaper('a4')->stream($nama.'.pdf');
    }
}
