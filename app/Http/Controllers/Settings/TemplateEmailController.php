<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\PengaturanInstitusi;
use App\Models\TemplateEmail;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TemplateEmailController extends Controller
{
    /**
     * Daftar template untuk halaman Pengaturan Email.
     *
     * @return list<array<string, mixed>>
     */
    public static function untukHalaman(): array
    {
        $tersimpan = TemplateEmail::query()->pluck('jenis')->all();

        return collect(TemplateEmail::JENIS)->map(fn (array $definisi, string $jenis) => [
            'jenis' => $jenis,
            'judul' => $definisi['judul'],
            'keterangan' => $definisi['keterangan'],
            'pakai_tombol' => $definisi['tombol'],
            'variabel' => $definisi['variabel'],
            'isi' => TemplateEmail::isiBerlaku($jenis),
            'bawaan' => $definisi['bawaan'],
            'diubah' => in_array($jenis, $tersimpan, true),
        ])->values()->all();
    }

    /**
     * Simpan isi template satu jenis surel.
     */
    public function update(Request $request, string $jenis): RedirectResponse
    {
        $data = $this->validasi($request, $jenis);
        $data['updated_by'] = $request->user()->id;

        TemplateEmail::query()->updateOrCreate(['jenis' => $jenis], $data);

        return back()->with('success', 'Template "'.TemplateEmail::JENIS[$jenis]['judul'].'" berhasil disimpan.');
    }

    /**
     * Kembalikan template ke isi bawaan.
     */
    public function destroy(string $jenis): RedirectResponse
    {
        abort_unless(isset(TemplateEmail::JENIS[$jenis]), 404);

        TemplateEmail::query()->whereKey($jenis)->delete();

        return back()->with('success', 'Template "'.TemplateEmail::JENIS[$jenis]['judul'].'" dikembalikan ke isi bawaan.');
    }

    /**
     * Pratinjau isian yang belum disimpan, dengan nilai contoh untuk tiap variabel.
     */
    public function pratinjau(Request $request, string $jenis): JsonResponse
    {
        $isi = $this->validasi($request, $jenis);
        $tautan = url('/contoh-tautan');

        $nilai = collect(TemplateEmail::JENIS[$jenis]['variabel'])
            ->mapWithKeys(fn ($label, $kunci) => [$kunci => TemplateEmail::CONTOH[$kunci] ?? null])
            ->merge(['institusi' => PengaturanInstitusi::shared()['nama_pt'], 'tautan' => $tautan])
            ->only(array_keys(TemplateEmail::JENIS[$jenis]['variabel']))
            ->all();

        $pesan = TemplateEmail::susun($jenis, $isi, $nilai, $tautan);

        return response()->json([
            'subjek' => $pesan->subject,
            'html' => (string) $pesan->render(),
        ]);
    }

    /**
     * @return array{subjek: string, sapaan: ?string, isi: string, tombol: ?string, penutup: ?string}
     */
    private function validasi(Request $request, string $jenis): array
    {
        abort_unless(isset(TemplateEmail::JENIS[$jenis]), 404);

        $definisi = TemplateEmail::JENIS[$jenis];
        $dikenal = [...array_keys($definisi['variabel']), ...($definisi['tombol'] ? ['tombol'] : [])];

        // Variabel salah ketik akan terkirim apa adanya ke pengguna, jadi ditolak sejak disimpan.
        $variabelDikenal = function (string $attribute, mixed $value, Closure $fail) use ($dikenal): void {
            preg_match_all('/\{([a-z_]+)\}/', (string) $value, $cocok);
            $asing = array_diff(array_unique($cocok[1]), $dikenal);
            if ($asing !== []) {
                $fail('Variabel tidak dikenal: '.collect($asing)->map(fn ($v) => '{'.$v.'}')->implode(', ').'.');
            }
        };

        $data = $request->validate([
            'subjek' => ['required', 'string', 'max:255', $variabelDikenal],
            'sapaan' => ['nullable', 'string', 'max:255', $variabelDikenal],
            'isi' => ['required', 'string', 'max:5000', $variabelDikenal],
            'tombol' => [$definisi['tombol'] ? 'required' : 'nullable', 'string', 'max:100', $variabelDikenal],
            'penutup' => ['nullable', 'string', 'max:500', $variabelDikenal],
        ], attributes: [
            'subjek' => 'Subjek',
            'sapaan' => 'Sapaan',
            'isi' => 'Isi surel',
            'tombol' => 'Teks tombol',
            'penutup' => 'Penutup',
        ]);

        return [
            'subjek' => $data['subjek'],
            'sapaan' => $data['sapaan'] ?? null,
            'isi' => $data['isi'],
            'tombol' => $definisi['tombol'] ? $data['tombol'] : null,
            'penutup' => $data['penutup'] ?? null,
        ];
    }
}
