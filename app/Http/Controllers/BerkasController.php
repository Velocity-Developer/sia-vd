<?php

namespace App\Http\Controllers;

use App\AllowedUpload;
use App\Models\InfoKuliah;
use App\Models\KelasKuliah;
use App\Models\Materi;
use App\Models\PengajuanIzin;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan berkas kuliah dari disk privat, setelah mengecek bahwa pengguna berhak melihatnya.
 */
class BerkasController extends Controller
{
    public function materi(Request $request, Materi $materi, int $index): StreamedResponse
    {
        abort_unless($this->bolehAksesKelas($request->user(), $materi->kelasKuliah), 403);

        return $this->kirim($this->berkasKe($materi->file, $index));
    }

    public function tugas(Request $request, Tugas $tugas, int $index): StreamedResponse
    {
        abort_unless($this->bolehAksesKelas($request->user(), $tugas->kelasKuliah), 403);

        return $this->kirim($this->berkasKe($tugas->file, $index));
    }

    /**
     * Jawaban tugas hanya untuk mahasiswa pemiliknya, dosen pengampu, dan admin.
     */
    public function pengumpulan(Request $request, PengumpulanTugas $pengumpulan, int $index): StreamedResponse
    {
        $user = $request->user();
        $milikSendiri = $user->mahasiswaProfile !== null && $pengumpulan->mahasiswa_id === $user->mahasiswaProfile->id;

        abort_unless($milikSendiri || $this->bolehAksesKelas($user, $pengumpulan->tugas?->kelasKuliah, mahasiswaKelas: false), 403);

        return $this->kirim($this->berkasKe($pengumpulan->file_jawaban, $index));
    }

    /**
     * Lampiran izin/sakit: mahasiswa pengaju, dosen pengampu, dan admin presensi.
     */
    public function izin(Request $request, PengajuanIzin $pengajuanIzin, int $index): StreamedResponse
    {
        $user = $request->user();
        $kelas = $pengajuanIzin->pertemuan?->kelasKuliah;
        $boleh = ($user->mahasiswaProfile !== null && $pengajuanIzin->mahasiswa_id === $user->mahasiswaProfile->id)
            || $user->hasPermission('admin.presensi')
            || ($user->dosenProfile !== null && $kelas?->dosen_id === $user->dosenProfile->id && $user->hasPermission('dosen.presensi'));

        abort_unless($boleh, 403);

        return $this->kirim($this->berkasKe($pengajuanIzin->lampiran, $index));
    }

    /**
     * Berkas soal ujian: dosen pengampu & admin kapan saja; mahasiswa peserta hanya setelah ujian dimulai
     * dan bila boleh mengikuti ujian (syarat kehadiran).
     */
    public function soalUjian(Request $request, Ujian $ujian, int $index): StreamedResponse
    {
        $user = $request->user();
        $staf = $user->hasPermission('admin.ujian')
            || ($user->dosenProfile !== null && $ujian->kelasKuliah->dosen_id === $user->dosenProfile->id && $user->hasPermission('dosen.ujian'));
        $mahasiswa = $user->mahasiswaProfile;
        $peserta = $mahasiswa !== null && $user->hasPermission('mahasiswa.ujian') && $ujian->status === Ujian::TERBIT
            && $ujian->sudahMulai() && $ujian->bolehIkut($mahasiswa->id);

        abort_unless($staf || $peserta, 403);

        return $this->kirim($this->berkasKe($ujian->soal_berkas, $index));
    }

    /**
     * Berkas jawaban ujian: mahasiswa pemiliknya, dosen pengampu, dan admin.
     */
    public function jawabanUjian(Request $request, UjianJawaban $jawaban, int $index): StreamedResponse
    {
        $user = $request->user();
        $kelas = $jawaban->ujian->kelasKuliah;
        $boleh = ($user->mahasiswaProfile !== null && $jawaban->mahasiswa_id === $user->mahasiswaProfile->id)
            || $user->hasPermission('admin.ujian')
            || ($user->dosenProfile !== null && $kelas->dosen_id === $user->dosenProfile->id && $user->hasPermission('dosen.ujian'));

        abort_unless($boleh, 403);

        return $this->kirim($this->berkasKe($jawaban->berkas, $index));
    }

    public function infoKuliah(Request $request, InfoKuliah $infoKuliah): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('admin.info-kuliah') || $request->user()->hasPermission('mahasiswa.info-kuliah'), 403);

        return $this->kirim($infoKuliah->file);
    }

    private function bolehAksesKelas(User $user, ?KelasKuliah $kelas, bool $mahasiswaKelas = true): bool
    {
        if ($kelas === null) {
            return false;
        }

        if ($user->hasPermission('admin.kelas-kuliah')) {
            return true;
        }

        if ($user->dosenProfile !== null && $kelas->dosen_id === $user->dosenProfile->id && $user->hasPermission('dosen.kelas-kuliah')) {
            return true;
        }

        return $mahasiswaKelas
            && $user->mahasiswaProfile !== null
            && $user->hasPermission('mahasiswa.jadwal-kuliah')
            && $kelas->krs()->where('mahasiswa_id', $user->mahasiswaProfile->id)->exists();
    }

    private function berkasKe(mixed $files, int $index): ?string
    {
        // Urutan dan penyaringan sama dengan daftar berkas di frontend, supaya indeks tautan cocok.
        return array_values(array_filter((array) $files, fn ($file): bool => is_string($file) && $file !== ''))[$index] ?? null;
    }

    /**
     * Tipe berkas ditentukan dari ekstensi yang sudah divalidasi, bukan ditebak dari isinya; selain PDF dan
     * gambar berkas diunduh, dan header sandbox mencegah isi berkas dijalankan sebagai halaman.
     */
    private function kirim(?string $path): StreamedResponse
    {
        $disk = Storage::disk(AllowedUpload::DISK);
        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'Content-Type' => AllowedUpload::mime($path),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ], AllowedUpload::bolehInline($path) ? 'inline' : 'attachment');
    }
}
