<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\DosenProfile;
use App\Models\NilaiPendadaran;
use App\Models\Pendadaran;
use App\Models\SkalaNilai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Penilaian pendadaran oleh penguji: nilai per penguji, hasil oleh ketua, dan pengesahan revisi oleh ketua.
 */
class PendadaranController extends Controller
{
    public function nilai(Request $request, Pendadaran $pendadaran): RedirectResponse
    {
        $dosen = $this->dosen($request);
        $nomor = array_search($dosen->id, $pendadaran->pengujiIds(), true);
        abort_if($nomor === false, 404);

        $data = $request->validate([
            'nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['nilai' => 'Nilai', 'catatan' => 'Catatan']);

        if (! $pendadaran->bolehDinilai()) {
            return back()->with('error', $pendadaran->status === Pendadaran::DIJADWALKAN
                ? 'Nilai baru bisa diisi setelah pendadaran dimulai.'
                : 'Hasil pendadaran sudah ditetapkan; nilai tidak bisa diubah.');
        }

        NilaiPendadaran::query()->updateOrCreate(
            ['pendadaran_id' => $pendadaran->id, 'penguji_ke' => $nomor],
            ['dosen_id' => $dosen->id, 'nilai' => round((float) $data['nilai'], 2), 'catatan' => $data['catatan'] ?? null],
        );

        return back()->with('success', 'Nilai pendadaran disimpan.');
    }

    /**
     * Ketua penguji menetapkan hasil setelah ketiga penguji menilai. Huruf dari rata-rata yang tidak lulus
     * hanya boleh berhasil "tidak lulus".
     */
    public function hasil(Request $request, Pendadaran $pendadaran): RedirectResponse
    {
        $this->ketua($request, $pendadaran);

        $data = $request->validate([
            'hasil' => ['required', Rule::in(Pendadaran::HASIL)],
            'catatan_hasil' => ['nullable', 'required_if:hasil,'.Pendadaran::HASIL_LULUS_REVISI, 'string', 'max:3000'],
        ], ['catatan_hasil.required_if' => 'Tuliskan bagian yang harus direvisi.'], ['hasil' => 'Hasil', 'catatan_hasil' => 'Catatan']);

        return DB::transaction(function () use ($pendadaran, $data): RedirectResponse {
            $pendadaran = Pendadaran::query()->lockForUpdate()->findOrFail($pendadaran->id);

            if ($pendadaran->status !== Pendadaran::DIJADWALKAN) {
                return back()->with('error', 'Hasil pendadaran ini sudah ditetapkan.');
            }
            if (($rata = $pendadaran->rataRata()) === null) {
                return back()->with('error', 'Belum semua penguji mengisi nilai.');
            }
            if (($huruf = SkalaNilai::dariAngka($rata)) === null) {
                return back()->with('error', 'Angka minimal skala nilai belum diatur di Pengaturan Akademik, jadi nilai tidak bisa dikonversi ke huruf.');
            }
            if (! SkalaNilai::lulus($huruf) && $data['hasil'] !== Pendadaran::HASIL_TIDAK_LULUS) {
                return back()->with('error', "Rata-rata {$rata} bernilai {$huruf} (tidak lulus), jadi hasilnya harus Tidak lulus.");
            }

            $pendadaran->update([
                'nilai_akhir' => $rata,
                'huruf' => $huruf,
                'hasil' => $data['hasil'],
                'catatan_hasil' => $data['catatan_hasil'] ?? null,
                'hasil_ditetapkan_at' => now(),
                'status' => match ($data['hasil']) {
                    Pendadaran::HASIL_LULUS_REVISI => Pendadaran::REVISI,
                    Pendadaran::HASIL_TIDAK_LULUS => Pendadaran::TIDAK_LULUS,
                    default => Pendadaran::DIJADWALKAN,
                },
            ]);
            if ($data['hasil'] === Pendadaran::HASIL_LULUS) {
                $pendadaran->selesaikan();
            }

            return back()->with('success', match ($data['hasil']) {
                Pendadaran::HASIL_LULUS => "Hasil ditetapkan: lulus dengan nilai {$huruf}. Nilai masuk ke mata kuliah TA/Skripsi.",
                Pendadaran::HASIL_LULUS_REVISI => 'Hasil ditetapkan: lulus dengan revisi. Nilai masuk setelah revisi disahkan.',
                default => 'Hasil ditetapkan: tidak lulus. Mahasiswa bisa mendaftar pendadaran ulang.',
            });
        });
    }

    public function sahkanRevisi(Request $request, Pendadaran $pendadaran): RedirectResponse
    {
        $this->ketua($request, $pendadaran);

        if ($pendadaran->status !== Pendadaran::REVISI || $pendadaran->revisi_diunggah_at === null) {
            return back()->with('error', 'Belum ada naskah revisi yang menunggu pengesahan.');
        }

        $pendadaran->update(['revisi_disahkan_at' => now()]);
        $pendadaran->selesaikan();

        return back()->with('success', "Revisi disahkan. Tugas akhir selesai dengan nilai {$pendadaran->huruf}.");
    }

    /**
     * Naskah revisi belum sesuai: mahasiswa mengunggah ulang dengan catatan dari ketua.
     */
    public function tolakRevisi(Request $request, Pendadaran $pendadaran): RedirectResponse
    {
        $this->ketua($request, $pendadaran);
        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']], attributes: ['catatan' => 'Catatan']);

        if ($pendadaran->status !== Pendadaran::REVISI || $pendadaran->revisi_diunggah_at === null) {
            return back()->with('error', 'Belum ada naskah revisi yang menunggu pengesahan.');
        }

        $pendadaran->update(['revisi_diunggah_at' => null, 'catatan_revisi' => $data['catatan']]);

        return back()->with('success', 'Revisi dikembalikan ke mahasiswa.');
    }

    private function ketua(Request $request, Pendadaran $pendadaran): void
    {
        abort_unless($pendadaran->penguji_1_id === $this->dosen($request)->id, 404);
    }

    private function dosen(Request $request): DosenProfile
    {
        $dosen = $request->user()->dosenProfile;
        abort_if($dosen === null, 403);

        return $dosen;
    }
}
