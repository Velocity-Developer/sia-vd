<?php

namespace App\Http\Controllers\Pmb;

use App\Http\Controllers\Controller;
use App\Models\Agama;
use App\Models\Cmb;
use App\Models\PengaturanPmb;
use App\Models\PengaturanRecaptcha;
use App\Models\ProgramStudi;
use App\Models\WilayahKecamatan;
use App\Pmb\OpsiPmb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PendaftaranController extends Controller
{
    public function create(): Response
    {
        $periode = PengaturanPmb::aktif();

        return Inertia::render('Pmb/Daftar', [
            'periode' => $periode ? [
                ...$periode->only(['kode', 'tahun_angkatan', 'tanggal_tutup', 'biaya_pendaftaran']),
                'penuh' => $periode->pendaftar()->count() >= $periode->kapasitas,
            ] : null,
            'agama' => $periode ? Agama::query()->orderByRaw('LENGTH(kode), kode')->get(['id', 'nama']) : [],
            'programStudi' => $periode ? ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang']) : [],
            'opsi' => $periode ? OpsiPmb::untukForm() : [],
            'recaptchaSiteKey' => PengaturanRecaptcha::siteKeyPmb(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Pendaftar tidak memilih periode: selalu masuk ke periode yang sedang dibuka.
        $aktif = PengaturanPmb::aktif();
        if (! $aktif) {
            throw ValidationException::withMessages(['periode' => 'Pendaftaran sudah ditutup.']);
        }

        $data = $this->validasi($request, $aktif);
        $this->periksaCaptcha($request);

        $cmb = DB::transaction(function () use ($data, $aktif): Cmb {
            // Kunci baris periode agar nomor urut dan kapasitas tidak bentrok saat pendaftar bersamaan.
            $periode = PengaturanPmb::query()->menerimaPendaftaran()->lockForUpdate()->find($aktif->id);
            if (! $periode) {
                throw ValidationException::withMessages(['periode' => 'Pendaftaran sudah ditutup.']);
            }
            $jumlah = $periode->pendaftar()->count();
            if ($jumlah >= $periode->kapasitas) {
                throw ValidationException::withMessages(['periode' => 'Kuota pendaftar periode ini sudah penuh.']);
            }

            $cmb = new Cmb($data);
            $cmb->pengaturan_pmb_id = $periode->id;
            $cmb->nomor_pendaftaran = $periode->kode.'-'.str_pad((string) ($jumlah + 1), 4, '0', STR_PAD_LEFT);
            // Nomor urut bisa bergeser bila ada pendaftar yang dihapus; lompati yang sudah terpakai.
            while (Cmb::query()->where('nomor_pendaftaran', $cmb->nomor_pendaftaran)->exists()) {
                $cmb->nomor_pendaftaran = $periode->kode.'-'.str_pad((string) (++$jumlah + 1), 4, '0', STR_PAD_LEFT);
            }
            $cmb->save();

            return $cmb;
        });

        $request->session()->put('pmb_terakhir', $cmb->id);

        return to_route('pmb.selesai');
    }

    public function selesai(Request $request): Response|RedirectResponse
    {
        $cmb = Cmb::query()->with(['periode', 'programStudi:id,nama_prodi,jenjang'])->find($request->session()->get('pmb_terakhir'));
        if (! $cmb) {
            return to_route('pmb.daftar');
        }

        return Inertia::render('Pmb/Selesai', [
            'pendaftar' => [
                'nomor_pendaftaran' => $cmb->nomor_pendaftaran,
                'nama' => $cmb->nama,
                'email' => $cmb->email,
                'program_studi' => trim($cmb->programStudi->jenjang.' '.$cmb->programStudi->nama_prodi),
            ],
            'periode' => $cmb->periode->only([
                'kode', 'tanggal_usm_mulai', 'tanggal_usm_selesai', 'tanggal_her', 'biaya_pendaftaran',
                'tanggal_pembayaran_mulai', 'tanggal_pembayaran_selesai',
            ]),
        ]);
    }

    /** Pencarian kecamatan untuk isian alamat (daftarnya terlalu besar untuk dikirim utuh). */
    public function kecamatan(Request $request): JsonResponse
    {
        $cari = $request->string('q')->trim()->limit(60, '')->toString();
        if (mb_strlen($cari) < 3) {
            return response()->json([]);
        }

        return response()->json(
            WilayahKecamatan::query()->where('nama', 'like', '%'.addcslashes($cari, '%_\\').'%')->orderBy('nama')->limit(30)->get(['id', 'nama']),
        );
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, PengaturanPmb $periode): array
    {
        $pindahan = $request->input('status_masuk') === 'P';

        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date', 'before:today', 'after:1900-01-01'],
            'nama_ibu' => ['required', 'string', 'max:255'],
            'agama_id' => ['required', 'integer', Rule::exists('agamas', 'id')],
            'jenis_kelamin' => ['required', Rule::in(array_keys(OpsiPmb::JENIS_KELAMIN))],
            'status_sipil' => ['nullable', Rule::in(array_keys(OpsiPmb::STATUS_SIPIL))],

            'nik' => ['required', 'digits:16', Rule::unique('cmb', 'nik')->where('pengaturan_pmb_id', $periode->id)],
            'kewarganegaraan' => ['required', Rule::in(array_keys(OpsiPmb::negara()))],
            'npwp' => ['nullable', 'digits_between:15,16'],
            'jalan' => ['required', 'string', 'max:255'],
            'dusun' => ['required', 'string', 'max:100'],
            'rt' => ['required', 'digits_between:1,3'],
            'rw' => ['required', 'digits_between:1,3'],
            'kelurahan' => ['required', 'string', 'max:100'],
            'wilayah_kecamatan_id' => ['required', 'integer', Rule::exists('wilayah_kecamatan', 'id')],
            'kode_pos' => ['required', 'digits:5'],
            'alat_transportasi' => ['nullable', Rule::in(array_keys(OpsiPmb::ALAT_TRANSPORTASI))],
            'jenis_tinggal' => ['nullable', Rule::in(array_keys(OpsiPmb::JENIS_TINGGAL))],
            'jenis_masuk' => ['nullable', Rule::in(array_keys(OpsiPmb::JENIS_MASUK))],
            'email' => ['required', 'email', 'max:255'],
            'telepon_wali' => ['nullable', 'regex:/^\+?[0-9]{8,15}$/'],
            'hp' => ['required', 'regex:/^\+?[0-9]{8,15}$/'],
            'penerima_kps' => ['boolean'],
            'nomor_kps' => [$request->boolean('penerima_kps') ? 'required' : 'exclude', 'string', 'max:30'],
            'jenis_pembiayaan' => ['nullable', Rule::in(array_keys(OpsiPmb::JENIS_PEMBIAYAAN))],
            'jumlah_pembiayaan' => ['nullable', 'integer', 'min:0', 'max:9999999999'],

            'kelas' => ['required', Rule::in(array_keys(OpsiPmb::KELAS))],
            'program_studi_id' => ['required', 'integer', Rule::exists('program_studis', 'id')],
            'status_masuk' => ['required', Rule::in(array_keys(OpsiPmb::STATUS_MASUK))],
            // Asal sekolah untuk peserta didik baru, asal perguruan tinggi untuk pindahan.
            'asal_sekolah' => [$pindahan ? 'exclude' : 'nullable', 'string', 'max:255'],
            'nisn' => [$pindahan ? 'exclude' : 'nullable', 'digits:10'],
            'nilai_un' => [$pindahan ? 'exclude' : 'nullable', 'string', 'max:10'],
            'asal_perguruan_tinggi' => [$pindahan ? 'nullable' : 'exclude', 'string', 'max:255'],
            'jenjang_asal' => [$pindahan ? 'nullable' : 'exclude', Rule::in(array_keys(OpsiPmb::JENJANG))],
            'prodi_asal' => [$pindahan ? 'nullable' : 'exclude', 'string', 'max:255'],
            'nim_asal' => [$pindahan ? 'nullable' : 'exclude', 'string', 'max:30'],
            'sks_diakui' => [$pindahan ? 'nullable' : 'exclude', 'integer', 'min:0', 'max:200'],
            'agen' => ['nullable', 'string', 'max:255'],
            'info' => ['nullable', 'string', 'max:255'],
        ], [
            'nik.unique' => 'NIK ini sudah terdaftar di periode yang sama.',
            'nik.digits' => 'NIK harus 16 digit angka.',
            'hp.regex' => 'Nomor HP hanya berisi angka (8–15 digit).',
            'telepon_wali.regex' => 'Nomor HP wali/ortu hanya berisi angka (8–15 digit).',
            'tanggal_lahir.before' => 'Tanggal lahir harus sebelum hari ini.',
        ], [
            'nama' => 'Nama lengkap',
            'tempat_lahir' => 'Tempat lahir',
            'tanggal_lahir' => 'Tanggal lahir',
            'nama_ibu' => 'Nama lengkap ibu',
            'agama_id' => 'Agama',
            'jenis_kelamin' => 'Jenis kelamin',
            'status_sipil' => 'Status perkawinan',
            'nik' => 'NIK',
            'kewarganegaraan' => 'Kewarganegaraan',
            'npwp' => 'NPWP',
            'jalan' => 'Jalan',
            'dusun' => 'Dusun',
            'rt' => 'RT',
            'rw' => 'RW',
            'kelurahan' => 'Kelurahan',
            'wilayah_kecamatan_id' => 'Kecamatan',
            'kode_pos' => 'Kode pos',
            'email' => 'Email',
            'hp' => 'Nomor HP',
            'nomor_kps' => 'Nomor KPS',
            'jumlah_pembiayaan' => 'Jumlah pembiayaan',
            'kelas' => 'Kelas',
            'program_studi_id' => 'Program studi',
            'status_masuk' => 'Status calon mahasiswa baru',
            'nisn' => 'NISN',
            'sks_diakui' => 'SKS diakui',
        ]);
    }

    private function periksaCaptcha(Request $request): void
    {
        $pengaturan = PengaturanRecaptcha::query()->find(PengaturanRecaptcha::SINGLETON_ID);
        if (! $pengaturan?->dipakaiPmb()) {
            return;
        }

        $token = $request->input('g-recaptcha-response');
        if (blank($token)) {
            throw ValidationException::withMessages(['captcha' => 'Centang kotak "Saya bukan robot" terlebih dahulu.']);
        }
        if (! PengaturanRecaptcha::verifikasi($pengaturan->secret_key, $token, $request->ip())) {
            throw ValidationException::withMessages(['captcha' => 'Verifikasi captcha gagal. Silakan centang ulang.']);
        }
    }
}
