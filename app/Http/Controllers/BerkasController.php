<?php

namespace App\Http\Controllers;

use App\AllowedUpload;
use App\Models\Cmb;
use App\Models\InfoKuliah;
use App\Models\KelasKuliah;
use App\Models\MahasiswaProfile;
use App\Models\Materi;
use App\Models\Pendadaran;
use App\Models\PengajuanAkademik;
use App\Models\PengajuanIzin;
use App\Models\PengajuanSusulan;
use App\Models\PengumpulanTugas;
use App\Models\TagihanRemidi;
use App\Models\TagihanSemester;
use App\Models\TagihanSusulan;
use App\Models\Tugas;
use App\Models\TugasAkhir;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\User;
use App\UserType;
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

    /**
     * Bukti bayar remidi: mahasiswa pemiliknya dan admin keuangan.
     */
    public function buktiRemidi(Request $request, TagihanRemidi $tagihanRemidi): StreamedResponse
    {
        $user = $request->user();
        $boleh = ($user->mahasiswaProfile !== null && $tagihanRemidi->mahasiswa_id === $user->mahasiswaProfile->id)
            || $user->hasPermission('admin.tagihan');

        abort_unless($boleh, 403);

        return $this->kirim($tagihanRemidi->bukti);
    }

    /**
     * Bukti bayar tagihan semester: mahasiswa pemilik tagihan dan admin keuangan.
     */
    public function buktiSemester(Request $request, TagihanSemester $tagihanSemester): StreamedResponse
    {
        $user = $request->user();
        $boleh = ($user->mahasiswaProfile !== null && $tagihanSemester->mahasiswa_id === $user->mahasiswaProfile->id)
            || $user->hasPermission('admin.tagihan');

        abort_unless($boleh && $tagihanSemester->bukti !== null, $boleh ? 404 : 403);

        return $this->kirim($tagihanSemester->bukti);
    }

    /**
     * Bukti bayar susulan: mahasiswa pemiliknya dan admin keuangan.
     */
    public function buktiSusulan(Request $request, TagihanSusulan $tagihanSusulan): StreamedResponse
    {
        $user = $request->user();
        $boleh = ($user->mahasiswaProfile !== null && $tagihanSusulan->mahasiswa_id === $user->mahasiswaProfile->id)
            || $user->hasPermission('admin.tagihan');

        abort_unless($boleh, 403);

        return $this->kirim($tagihanSusulan->bukti);
    }

    /**
     * Lampiran pengajuan susulan: mahasiswa pengaju dan admin ujian.
     */
    public function lampiranSusulan(Request $request, PengajuanSusulan $pengajuanSusulan, int $index): StreamedResponse
    {
        $user = $request->user();
        $boleh = ($user->mahasiswaProfile !== null && $pengajuanSusulan->mahasiswa_id === $user->mahasiswaProfile->id)
            || $user->hasPermission('admin.ujian');

        abort_unless($boleh, 403);

        return $this->kirim($this->berkasKe($pengajuanSusulan->lampiran, $index));
    }

    /**
     * Berkas pengajuan TA/pendadaran/wisuda: pemilik, admin pemroses, pembimbing TA mahasiswa itu, dan
     * penguji pendadaran dari pengajuan tersebut.
     */
    public function pengajuanAkademik(Request $request, PengajuanAkademik $pengajuanAkademik, string $kunci): StreamedResponse
    {
        $user = $request->user();
        $dosenId = $user->dosenProfile?->id;
        $boleh = ($user->mahasiswaProfile !== null && $pengajuanAkademik->mahasiswa_id === $user->mahasiswaProfile->id)
            // Dosen PA melihat pengajuan mahasiswa bimbingan akademiknya.
            || ($dosenId !== null && $user->hasPermission('dosen.pengajuan-pa') && $pengajuanAkademik->mahasiswa?->dosen_wali_id === $dosenId)
            || $user->hasPermission(in_array($pengajuanAkademik->jenis, PengajuanAkademik::JENIS_CUTI, true) ? 'admin.pengajuan-cuti' : 'admin.pengajuan-akademik')
            || ($dosenId !== null && $user->hasPermission('dosen.bimbingan')
                && (TugasAkhir::milik($pengajuanAkademik->mahasiswa_id)?->dibimbingOleh($dosenId)
                    || Pendadaran::query()->where('pengajuan_id', $pengajuanAkademik->id)->diuji($dosenId)->exists()));

        abort_unless($boleh, 403);

        $path = $pengajuanAkademik->lampiran[$kunci] ?? null;

        return $this->kirim(is_string($path) ? $path : null);
    }

    /** Lampiran Informasi & Pengumuman bersifat publik (tampil di halaman depan tanpa login). */
    public function infoKuliah(InfoKuliah $infoKuliah): StreamedResponse
    {
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
    /**
     * Foto profil: pemilik akun dan pengelola data jenis pengguna itu (Data Dosen/Mahasiswa, Karyawan).
     */
    public function foto(Request $request, User $user): StreamedResponse
    {
        $izin = match ($user->type()) {
            UserType::Admin => 'admin.users.karyawan',
            UserType::Dosen => 'admin.users.dosen',
            UserType::Mahasiswa => 'admin.users.mahasiswa',
            default => null,
        };

        abort_unless($request->user()->is($user) || ($izin !== null && $request->user()->hasPermission($izin)), 403);

        return $this->kirim($user->profile?->foto);
    }

    /**
     * Ijazah/transkrip mahasiswa (salinan dari PMB): pemilik akun dan pengelola Data Mahasiswa.
     */
    public function mahasiswa(Request $request, User $user, string $jenis): StreamedResponse
    {
        abort_unless($user->type() === UserType::Mahasiswa && array_key_exists($jenis, MahasiswaProfile::BERKAS), 404);
        abort_unless($request->user()->is($user) || $request->user()->hasPermission('admin.users.mahasiswa'), 403);

        return $this->kirim($user->mahasiswaProfile?->getAttribute($jenis));
    }

    /**
     * Berkas unggahan pendaftar PMB (pas foto, ijazah, transkrip): hanya pengelola Calon Maba.
     */
    public function pmb(Request $request, Cmb $cmb, string $jenis): StreamedResponse
    {
        abort_unless($request->user()->hasPermission('admin.pendaftar-pmb'), 403);
        abort_unless(array_key_exists($jenis, Cmb::BERKAS), 404);

        return $this->kirim($cmb->getAttribute($jenis));
    }

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
