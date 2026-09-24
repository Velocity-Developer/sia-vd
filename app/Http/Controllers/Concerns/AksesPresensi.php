<?php

namespace App\Http\Controllers\Concerns;

use App\Models\KelasKuliah;
use App\Models\Pertemuan;

/**
 * Hak akses presensi untuk rute dosen. Admin (rute admin.*) selalu boleh.
 *
 * - Pengampu kelas: melihat dan mengelola seluruh presensi kelasnya.
 * - Dosen pengganti: mengelola pertemuan yang ditunjukkan kepadanya saja.
 * - Kaprodi: melihat presensi kelas di prodinya dan memberi dispensasi ujian, tanpa mengubah presensi.
 *
 * Dipakai bersama KontenKelas (untuk peran()).
 */
trait AksesPresensi
{
    protected function dosenId(): ?int
    {
        return request()->user()?->dosenProfile?->id;
    }

    protected function pengampu(KelasKuliah $kelas): bool
    {
        return $this->peran() === 'admin' || ($this->dosenId() !== null && $kelas->dosen_id === $this->dosenId());
    }

    /**
     * Program studi yang dipimpin dosen yang sedang login sebagai kaprodi.
     *
     * @return list<int>
     */
    protected function prodiKaprodi(): array
    {
        // Tidak di-cache di objek controller: instance controller bisa dipakai ulang antar-request.
        return request()->user()?->dosenProfile?->prodiDipimpin()->pluck('id')->all() ?? [];
    }

    protected function kaprodiKelas(KelasKuliah $kelas): bool
    {
        return $this->prodiKaprodi() !== []
            && in_array($kelas->loadMissing('mataKuliah:id,prodi_id')->mataKuliah?->prodi_id, $this->prodiKaprodi(), true);
    }

    protected function bolehLihatKelas(KelasKuliah $kelas): bool
    {
        return $this->pengampu($kelas) || $this->kaprodiKelas($kelas);
    }

    protected function bolehDispensasi(KelasKuliah $kelas): bool
    {
        return $this->peran() === 'admin' || $this->kaprodiKelas($kelas);
    }

    protected function penggantiPertemuan(Pertemuan $pertemuan): bool
    {
        return $this->dosenId() !== null
            && $pertemuan->dosen_id === $this->dosenId()
            && $pertemuan->kelasKuliah->dosen_id !== $this->dosenId();
    }

    protected function bolehKelolaPertemuan(Pertemuan $pertemuan): bool
    {
        return $this->pengampu($pertemuan->kelasKuliah) || $this->penggantiPertemuan($pertemuan);
    }

    protected function pastikanLihatKelas(KelasKuliah $kelas): void
    {
        abort_unless($this->bolehLihatKelas($kelas), 403);
    }

    protected function pastikanPengampu(KelasKuliah $kelas): void
    {
        abort_unless($this->pengampu($kelas), 403);
    }

    protected function pastikanKelolaPertemuan(Pertemuan $pertemuan): void
    {
        abort_unless($this->bolehKelolaPertemuan($pertemuan), 403);
    }
}
