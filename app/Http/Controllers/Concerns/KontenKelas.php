<?php

namespace App\Http\Controllers\Concerns;

use App\Models\KelasKuliah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Dipakai controller konten kelas (materi, tugas, quiz, koreksi quiz) yang melayani rute admin.* dan dosen.*.
 * Akses menu dicek oleh middleware grup rute; di sini hanya pembatasan dosen ke kelas yang diampunya.
 */
trait KontenKelas
{
    /**
     * 'admin' atau 'dosen', sesuai grup rute yang sedang dipakai.
     */
    protected function peran(): string
    {
        return request()->routeIs('dosen.*') ? 'dosen' : 'admin';
    }

    protected function pastikanAksesKelas(KelasKuliah $kelasKuliah): void
    {
        if ($this->peran() === 'dosen') {
            abort_unless($kelasKuliah->dosen_id === request()->user()?->dosenProfile?->id, 403);
        }
    }

    protected function keKelas(KelasKuliah $kelasKuliah): RedirectResponse
    {
        return to_route($this->peran().'.kelas-kuliah.show', $kelasKuliah);
    }

    protected function rute(string $nama): string
    {
        return $this->peran().'.'.$nama;
    }

    /**
     * Kelas tujuan duplikasi yang sah: dosen hanya ke kelas yang diampunya sendiri.
     *
     * @return Collection<int, KelasKuliah>
     */
    protected function kelasTujuanDuplikasi(Request $request, KelasKuliah $asal): Collection
    {
        $ids = $request->validate(['target_ids' => ['required', 'array', 'min:1'], 'target_ids.*' => ['integer']])['target_ids'];

        $targets = KelasKuliah::query()
            ->whereIn('id', $ids)
            ->whereKeyNot($asal->id)
            ->when($this->peran() === 'dosen', fn ($query) => $query->where('dosen_id', $request->user()->dosenProfile?->id))
            ->get();

        abort_if($targets->count() !== count(array_unique($ids)), 404);

        return $targets;
    }
}
