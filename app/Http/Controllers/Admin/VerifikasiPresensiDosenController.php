<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PengaturanAkademik;
use App\Models\Pertemuan;
use App\Models\PresensiMahasiswa;
use App\Models\RiwayatPresensiDosen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Verifikasi presensi dosen per pertemuan yang sudah selesai. Disetujui = pertemuan terkunci (dosen dan admin
 * tidak bisa mengubah presensi/jurnal) dan BAP-nya sah dicetak; ditolak = dikembalikan ke dosen untuk diperbaiki,
 * lalu otomatis kembali menunggu verifikasi saat jurnal atau presensi mahasiswanya diubah.
 */
class VerifikasiPresensiDosenController extends Controller
{
    private const STATUS = ['menunggu', Pertemuan::DISETUJUI, Pertemuan::DITOLAK];

    public function index(Request $request): Response
    {
        Pertemuan::tutupYangLewat();
        $filter = PresensiDosenController::filter($request);
        $filter['status'] = in_array($request->query('status'), self::STATUS, true) ? $request->query('status') : 'menunggu';
        $toleransi = PengaturanAkademik::current()->toleransi_terlambat_menit;

        $pertemuan = PresensiDosenController::pertemuan($filter)
            ->where('status', Pertemuan::SELESAI)
            ->when($filter['status'] === 'menunggu', fn (Builder $q) => $q->whereNull('verifikasi'), fn (Builder $q) => $q->where('verifikasi', $filter['status']))
            ->with('pemverifikasi:id,name')
            ->withCount([
                'presensiMahasiswas as jumlah_peserta',
                'presensiMahasiswas as jumlah_hadir' => fn ($q) => $q->whereIn('status', PresensiMahasiswa::DIHITUNG_HADIR),
            ])
            ->orderBy('tanggal')->orderBy('jam_mulai')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Pertemuan $p): array => PresensiDosenController::baris($p, $toleransi) + [
                'jumlah_peserta' => $p->jumlah_peserta,
                'jumlah_hadir' => $p->jumlah_hadir,
            ]);

        return Inertia::render('Admin/VerifikasiPresensiDosen', [
            'pertemuan' => $pertemuan,
            'filter' => $filter,
            'jumlahMenunggu' => PresensiDosenController::pertemuan($filter)->where('status', Pertemuan::SELESAI)->whereNull('verifikasi')->count(),
            ...PresensiDosenController::opsiFilter(),
        ]);
    }

    /**
     * Setujui beberapa pertemuan sekaligus. Pertemuan tanpa jurnal (topik kosong) dilewati.
     */
    public function setujui(Request $request): RedirectResponse
    {
        $pertemuan = $this->pilihan($request);
        [$lengkap, $tanpaJurnal] = $pertemuan->partition(fn (Pertemuan $p): bool => filled($p->topik));

        DB::transaction(fn () => $lengkap->each(fn (Pertemuan $p) => $this->catat($p, RiwayatPresensiDosen::SETUJUI, null, $request, [
            'verifikasi' => Pertemuan::DISETUJUI, 'catatan_verifikasi' => null, 'diverifikasi_oleh' => $request->user()->id, 'diverifikasi_at' => now(),
        ])));

        return back()->with('success', "{$lengkap->count()} pertemuan disetujui."
            .($tanpaJurnal->isNotEmpty() ? " {$tanpaJurnal->count()} dilewati karena jurnal (topik) masih kosong." : ''));
    }

    public function tolak(Request $request): RedirectResponse
    {
        $data = $request->validate(['catatan' => ['required', 'string', 'max:255']], attributes: ['catatan' => 'Catatan penolakan']);
        $pertemuan = $this->pilihan($request);

        DB::transaction(fn () => $pertemuan->each(fn (Pertemuan $p) => $this->catat($p, RiwayatPresensiDosen::TOLAK, trim($data['catatan']), $request, [
            'verifikasi' => Pertemuan::DITOLAK, 'catatan_verifikasi' => trim($data['catatan']), 'diverifikasi_oleh' => $request->user()->id, 'diverifikasi_at' => now(),
        ])));

        return back()->with('success', "{$pertemuan->count()} pertemuan ditolak dan dikembalikan ke dosen.");
    }

    /**
     * Batalkan verifikasi (disetujui atau ditolak) agar presensi bisa diubah lagi; pertemuan kembali menunggu.
     */
    public function batal(Request $request): RedirectResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:255']], attributes: ['alasan' => 'Alasan pembatalan']);
        $pertemuan = $this->pilihan($request, true);

        DB::transaction(fn () => $pertemuan->each(fn (Pertemuan $p) => $this->catat($p, RiwayatPresensiDosen::BATAL, trim($data['alasan']), $request, [
            'verifikasi' => null, 'catatan_verifikasi' => null, 'diverifikasi_oleh' => null, 'diverifikasi_at' => null,
        ])));

        return back()->with('success', "Verifikasi {$pertemuan->count()} pertemuan dibatalkan; pertemuan kembali menunggu verifikasi.");
    }

    /**
     * Pertemuan selesai yang dipilih. Setujui/tolak hanya untuk yang belum disetujui; batal hanya yang sudah diverifikasi.
     *
     * @return Collection<int, Pertemuan>
     */
    private function pilihan(Request $request, bool $sudahDiverifikasi = false): Collection
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['integer']], ['ids.required' => 'Pilih minimal satu pertemuan.']);

        $pertemuan = Pertemuan::query()->whereIn('id', $data['ids'])->where('status', Pertemuan::SELESAI)
            ->when($sudahDiverifikasi, fn (Builder $q) => $q->whereNotNull('verifikasi'), fn (Builder $q) => $q->where(fn (Builder $v) => $v->whereNull('verifikasi')->orWhere('verifikasi', '!=', Pertemuan::DISETUJUI)))
            ->get();

        abort_if($pertemuan->isEmpty(), 422, 'Tidak ada pertemuan yang bisa diproses.');

        return $pertemuan;
    }

    /**
     * @param  array<string, mixed>  $perubahan
     */
    private function catat(Pertemuan $pertemuan, string $aksi, ?string $alasan, Request $request, array $perubahan): void
    {
        $pertemuan->update($perubahan);
        $pertemuan->riwayatPresensiDosen()->create(['aksi' => $aksi, 'alasan' => $alasan, 'diubah_oleh' => $request->user()->id]);
    }
}
