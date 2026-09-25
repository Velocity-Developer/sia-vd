<?php

namespace App\Http\Controllers\Kelas;

use App\AllowedUpload;
use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\SyaratUjian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Detail satu ujian untuk dosen pengampu (dan admin): menyiapkan soal, memantau pengumpulan, dan menilai.
 * Jadwal & mode tetap diatur admin di menu Jadwal Ujian.
 */
class UjianKelasController extends Controller
{
    use KontenKelas;

    /** Batas jumlah berkas soal per ujian. */
    private const MAKS_BERKAS_SOAL = 5;

    public function show(Ujian $ujian): Response
    {
        $kelas = $ujian->kelasKuliah;
        $this->pastikanAksesKelas($kelas);
        $kelas->load(['mataKuliah:id,kode_matkul,nama_matkul', 'dosen:id,user_id', 'dosen.user:id,name', 'tahunAkademik:id,tahun,semester,status']);
        $ujian->load('ruang:id,kode_ruang,nama_ruang');

        $syarat = SyaratUjian::untukKelas($kelas);
        $jawaban = $ujian->jawabans()->with('penilai:id,name')->get()->keyBy('mahasiswa_id');

        $peserta = $kelas->krs()
            ->with(['mahasiswa:id,user_id,nim', 'mahasiswa.user:id,name'])
            ->get(['id', 'kelas_id', 'mahasiswa_id'])
            ->sortBy(fn ($krs) => $krs->mahasiswa?->nim)
            ->values()
            ->map(function ($krs) use ($jawaban, $syarat, $ujian): array {
                $j = $jawaban->get($krs->mahasiswa_id);

                return [
                    'mahasiswa_id' => $krs->mahasiswa_id,
                    'nim' => $krs->mahasiswa?->nim,
                    'nama' => $krs->mahasiswa?->user?->name,
                    'syarat' => $syarat['peserta'][$krs->mahasiswa_id][$ujian->jenis] ?? null,
                    'jawaban' => $j === null ? null : [
                        'id' => $j->id,
                        'jumlah_berkas' => count($j->berkas ?? []),
                        'nama_berkas' => array_map(fn (string $path): string => basename($path), $j->berkas ?? []),
                        'dikumpulkan_at' => $j->dikumpulkan_at?->toIso8601String(),
                        'nilai' => $j->nilai,
                        'catatan_dosen' => $j->catatan_dosen,
                        'penilai' => $j->penilai?->name,
                    ],
                ];
            });

        return Inertia::render('Kelas/UjianKelas', [
            'peran' => $this->peran(),
            'kelasKuliah' => $kelas,
            'ujian' => [
                ...$ujian->only(['id', 'jenis', 'mode', 'jam_mulai', 'jam_akhir', 'pengawas', 'petunjuk', 'status', 'nilai_dirilis']),
                'tanggal' => $ujian->tanggal->toDateString(),
                'label_mode' => $ujian->labelMode(),
                'ruang' => $ujian->ruang ? $ujian->ruang->kode_ruang.' — '.$ujian->ruang->nama_ruang : null,
                'soal' => array_map(fn (string $path): string => basename($path), $ujian->soal_berkas ?? []),
            ],
            'peserta' => $peserta,
            'syaratAktif' => $syarat['aktif'],
            'sudahMulai' => $ujian->sudahMulai(),
            'sudahSelesai' => $ujian->sudahSelesai(),
            'terkunci' => $this->nilaiTerkunci($kelas),
        ]);
    }

    /**
     * Unggah berkas soal (mode unggah berkas), hanya sebelum ujian dimulai agar semua mahasiswa menerima
     * soal yang sama.
     */
    public function unggahSoal(Request $request, Ujian $ujian): RedirectResponse
    {
        $this->pastikanAksesKelas($ujian->kelasKuliah);
        abort_unless($ujian->mode === Ujian::ONLINE_BERKAS, 404);

        if ($ujian->sudahMulai()) {
            return back()->with('error', 'Ujian sudah dimulai; soal tidak bisa diubah lagi.');
        }

        $ada = $ujian->soal_berkas ?? [];
        $request->validate([
            'soal' => ['required', 'array', 'min:1', 'max:'.(self::MAKS_BERKAS_SOAL - count($ada))],
            'soal.*' => ['file', 'max:20480', ...AllowedUpload::rules()],
        ], [
            'soal.max' => 'Maksimal '.self::MAKS_BERKAS_SOAL.' berkas soal per ujian.',
            'soal.*.extensions' => AllowedUpload::message(),
            'soal.*.mimes' => AllowedUpload::messageIsi(),
            'soal.*.max' => 'Ukuran berkas soal maksimal 20 MB.',
        ], ['soal' => 'Berkas soal', 'soal.*' => 'Berkas soal']);

        $baru = collect($request->file('soal'))->map(function ($berkas) use ($ujian): string {
            $nama = Str::slug(pathinfo($berkas->getClientOriginalName(), PATHINFO_FILENAME), '_') ?: 'soal';

            return $berkas->storeAs('ujian/'.$ujian->id.'/soal', substr($nama, 0, 80).'-'.Str::lower(Str::random(6)).'.'.strtolower($berkas->getClientOriginalExtension()), AllowedUpload::DISK);
        })->all();

        $ujian->update(['soal_berkas' => [...$ada, ...$baru]]);

        return back()->with('success', count($baru).' berkas soal diunggah.');
    }

    public function hapusSoal(Ujian $ujian, int $index): RedirectResponse
    {
        $this->pastikanAksesKelas($ujian->kelasKuliah);

        if ($ujian->sudahMulai()) {
            return back()->with('error', 'Ujian sudah dimulai; soal tidak bisa diubah lagi.');
        }

        $berkas = $ujian->soal_berkas ?? [];
        abort_unless(isset($berkas[$index]), 404);
        Storage::disk(AllowedUpload::DISK)->delete($berkas[$index]);
        unset($berkas[$index]);
        $ujian->update(['soal_berkas' => array_values($berkas)]);

        return back()->with('success', 'Berkas soal dihapus.');
    }

    /**
     * Nilai angka (0–100) untuk jawaban berkas, setelah ujian selesai.
     */
    public function nilai(Request $request, Ujian $ujian, UjianJawaban $jawaban): RedirectResponse
    {
        abort_unless($jawaban->ujian_id === $ujian->id, 404);
        $this->pastikanAksesKelas($ujian->kelasKuliah);
        $this->pastikanNilaiTidakTerkunci($ujian->kelasKuliah);

        if (! $ujian->sudahSelesai()) {
            return back()->with('error', 'Jawaban dinilai setelah ujian selesai.');
        }

        $data = $request->validate([
            'nilai' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catatan_dosen' => ['nullable', 'string', 'max:1000'],
        ], attributes: ['nilai' => 'Nilai', 'catatan_dosen' => 'Catatan']);

        $jawaban->update([...$data, 'dinilai_oleh' => $request->user()->id]);

        return back()->with('success', 'Nilai disimpan.');
    }

    /**
     * Tampilkan/sembunyikan nilai ujian ke mahasiswa.
     */
    public function rilisNilai(Request $request, Ujian $ujian): RedirectResponse
    {
        $this->pastikanAksesKelas($ujian->kelasKuliah);
        $data = $request->validate(['nilai_dirilis' => ['required', 'boolean']]);
        $ujian->update($data);

        return back()->with('success', $data['nilai_dirilis'] ? 'Nilai ujian kini terlihat oleh mahasiswa.' : 'Nilai ujian disembunyikan dari mahasiswa.');
    }
}
