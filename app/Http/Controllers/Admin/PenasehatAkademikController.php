<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Set Penasehat Akademik: mengisi dosen wali sekaligus untuk mahasiswa aktif (Aktif/Pindahan) dalam satu rentang NIM.
 */
class PenasehatAkademikController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/PenasehatAkademik', [
            'mahasiswa' => $this->mahasiswa()->with(['user:id,name', 'prodi:id,nama_prodi,jenjang', 'dosenWali.user:id,name'])
                ->get(['id', 'user_id', 'nim', 'status', 'prodi_id', 'dosen_wali_id'])
                ->map(fn (MahasiswaProfile $mhs): array => [
                    'nim' => $mhs->nim,
                    'nama' => (string) $mhs->user?->name,
                    'status' => $mhs->status,
                    'prodi' => $mhs->prodi ? trim(($mhs->prodi->jenjang ? $mhs->prodi->jenjang.' ' : '').$mhs->prodi->nama_prodi) : null,
                    'dosen_wali' => $mhs->dosenWali?->user?->name,
                ]),
            'dosen' => DosenProfile::opsi(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $nimAktif = Rule::exists('mahasiswa_profiles', 'nim')->where(fn ($query) => $query->whereIn('status', MahasiswaProfile::STATUS_AKTIF));

        $data = $request->validate([
            'nim_awal' => ['required', 'string', $nimAktif],
            'nim_akhir' => ['required', 'string', $nimAktif],
            'dosen_wali_id' => ['required', DosenProfile::rulePilihan()],
        ], [], ['nim_awal' => 'NIM awal', 'nim_akhir' => 'NIM akhir', 'dosen_wali_id' => 'Pembimbing Akademik']);

        // Urutan NIM dibandingkan oleh database (sama dengan urutan daftar di halaman), bukan oleh PHP.
        if ($this->mahasiswa()->where('nim', $data['nim_awal'])->where('nim', '>', $data['nim_akhir'])->exists()) {
            return back()->withErrors(['nim_akhir' => 'NIM akhir harus sama dengan atau sesudah NIM awal.'])->withInput();
        }

        $jumlah = $this->mahasiswa()->whereBetween('nim', [$data['nim_awal'], $data['nim_akhir']])
            ->update(['dosen_wali_id' => $data['dosen_wali_id'], 'updated_at' => now()]);
        $dosen = DosenProfile::with('user:id,name')->find($data['dosen_wali_id'])?->user?->name;

        return to_route('admin.penasehat-akademik.index')
            ->with('success', "Pembimbing Akademik {$jumlah} mahasiswa (NIM {$data['nim_awal']} s.d. {$data['nim_akhir']}) diubah menjadi {$dosen}.");
    }

    /**
     * Mahasiswa aktif ber-NIM, urut NIM.
     *
     * @return Builder<MahasiswaProfile>
     */
    private function mahasiswa(): Builder
    {
        return MahasiswaProfile::query()->aktif()->whereNotNull('nim')->where('nim', '!=', '')->orderBy('nim');
    }
}
