<?php

namespace App\Http\Controllers;

use App\AllowedUpload;
use App\Models\InfoKuliah;
use App\Models\KelasKuliah;
use App\Models\Materi;
use App\Models\PengumpulanTugas;
use App\Models\Tugas;
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

    private function kirim(?string $path): StreamedResponse
    {
        $disk = Storage::disk(AllowedUpload::DISK);
        abort_if($path === null || ! $disk->exists($path), 404);

        return $disk->response($path, basename($path), ['X-Content-Type-Options' => 'nosniff']);
    }
}
