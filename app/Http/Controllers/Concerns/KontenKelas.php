<?php

namespace App\Http\Controllers\Concerns;

use App\Models\KelasKuliah;
use App\Models\Ujian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

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

    /**
     * Presensi dan izin dikunci untuk dosen setelah tahun akademik kelas tidak aktif lagi; admin tetap bisa mengubah.
     */
    protected function tahunAkademikTerkunci(KelasKuliah $kelasKuliah): bool
    {
        return $this->peran() === 'dosen'
            && $kelasKuliah->loadMissing('tahunAkademik')->tahunAkademik?->status !== true;
    }

    /**
     * Nilai (huruf akhir, nilai tugas, koreksi quiz, nilai ujian) dikunci untuk dosen bila tahun akademik tidak
     * aktif, nilai kelas sudah difinalisasi, atau batas input nilai sudah lewat. Admin tetap bisa mengubah.
     */
    protected function nilaiTerkunci(KelasKuliah $kelasKuliah, ?Ujian $ujian = null): bool
    {
        return $this->pesanNilaiTerkunci($kelasKuliah, $ujian) !== null;
    }

    /**
     * Ujian remidi punya jendela sendiri: nilai kelas memang sudah final, tetapi soal dan nilai remidi masih boleh
     * diubah dosen sampai batas input nilai remidi.
     */
    protected function pesanNilaiTerkunci(KelasKuliah $kelasKuliah, ?Ujian $ujian = null): ?string
    {
        if ($this->tahunAkademikTerkunci($kelasKuliah)) {
            return 'Nilai terkunci karena tahun akademik kelas ini sudah tidak aktif. Hubungi admin untuk perubahan nilai.';
        }

        if ($ujian?->remidi()) {
            if ($this->peran() !== 'dosen') {
                return null;
            }

            return match (true) {
                $kelasKuliah->remidi_final_at !== null => 'Nilai remidi kelas ini sudah difinalisasi. Hubungi admin bila perlu perubahan.',
                (bool) $kelasKuliah->tahunAkademik?->batasNilaiRemidiLewat() => 'Batas input nilai remidi sudah lewat. Hubungi admin bila perlu perubahan.',
                default => null,
            };
        }

        if ($this->peran() === 'dosen' && $kelasKuliah->nilaiFinal()) {
            return $kelasKuliah->nilai_final_at !== null
                ? 'Nilai kelas ini sudah dikirim ke validasi. Hubungi admin bila perlu dikembalikan untuk koreksi.'
                : 'Batas input nilai sudah lewat. Hubungi admin bila perlu dibuka kembali.';
        }

        return null;
    }

    protected function pastikanNilaiTidakTerkunci(KelasKuliah $kelasKuliah, ?Ujian $ujian = null): void
    {
        $pesan = $this->pesanNilaiTerkunci($kelasKuliah, $ujian);
        abort_if($pesan !== null, 403, $pesan ?? '');
    }

    protected function keKelas(KelasKuliah $kelasKuliah): RedirectResponse
    {
        return to_route($this->peran().'.kelas-kuliah.show', $kelasKuliah);
    }

    /**
     * Tujuan sesudah simpan/hapus: kembali ke menu (Jadwal Kelas/Materi/Tugas/Quiz) bila aksi dimulai dari menu,
     * selain itu ke halaman kelas seperti biasa.
     */
    protected function kembali(KelasKuliah $kelasKuliah, string $menu): RedirectResponse
    {
        if (request()->input('dari') !== 'menu') {
            return $this->keKelas($kelasKuliah);
        }

        return request()->isMethod('delete') ? back() : to_route($this->rute($menu.'.index'));
    }

    /**
     * Kelas yang bisa dipilih di isian Kelas Kuliah saat menambah dari menu: tahun akademik aktif, bukan
     * matkul tugas akhir, dan untuk dosen hanya kelas yang diampunya.
     *
     * @return Builder<KelasKuliah>
     */
    protected function kelasPilihanMenu(): Builder
    {
        return KelasKuliah::query()
            ->whereHas('tahunAkademik', fn ($query) => $query->where('status', true))
            ->whereHas('mataKuliah', fn ($query) => $query->where('tugas_akhir', false))
            ->when($this->peran() === 'dosen', fn ($query) => $query->where('dosen_id', request()->user()?->dosenProfile?->id));
    }

    /**
     * @return array<int, array{id: int, name: string, jumlah_pertemuan: int}>
     */
    protected function opsiKelasMenu(): array
    {
        return $this->kelasPilihanMenu()
            ->with(['mataKuliah:id,kode_matkul,nama_matkul', 'dosen:id,user_id', 'dosen.user:id,name'])
            ->orderBy('kode_kelas')
            ->get(['id', 'kode_kelas', 'matkul_id', 'dosen_id', 'jumlah_pertemuan'])
            ->map(fn (KelasKuliah $kelas): array => [
                'id' => $kelas->id,
                'name' => $kelas->kode_kelas.' — '.($kelas->mataKuliah?->nama_matkul ?? '-')
                    .($this->peran() === 'admin' ? ' ('.($kelas->dosen?->user?->name ?? 'tanpa dosen').')' : ''),
                'jumlah_pertemuan' => (int) $kelas->jumlah_pertemuan,
            ])->all();
    }

    /**
     * Kelas dari isian Kelas Kuliah pada form tambah di menu; kelas di luar pilihan ditolak.
     */
    protected function kelasDariIsian(Request $request): KelasKuliah
    {
        $request->validate(['kelas_kuliah_id' => ['required', 'integer']], [], ['kelas_kuliah_id' => 'Kelas Kuliah']);
        $kelas = $this->kelasPilihanMenu()->find($request->integer('kelas_kuliah_id'));

        if ($kelas === null) {
            throw ValidationException::withMessages(['kelas_kuliah_id' => 'Kelas Kuliah tidak valid atau tidak bisa dipilih.']);
        }

        return $kelas;
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
            ->whereHas('mataKuliah', fn ($query) => $query->where('tugas_akhir', false))
            ->when($this->peran() === 'dosen', fn ($query) => $query->where('dosen_id', $request->user()->dosenProfile?->id))
            ->get();

        abort_if($targets->count() !== count(array_unique($ids)), 404);

        return $targets;
    }
}
