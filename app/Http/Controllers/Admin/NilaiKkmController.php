<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\FilterKrs;
use App\Http\Controllers\Controller;
use App\KrsKkm;
use App\Models\Krs;
use App\Models\MahasiswaProfile;
use App\Models\MataKuliah;
use App\Models\PengajuanAkademik;
use App\Models\ProgramStudi;
use App\Models\SkalaNilai;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nilai KKM (Kuliah Kerja Mahasiswa: KKM/PKL/KKN), khusus admin: mahasiswa yang pengajuan KKM-nya disetujui, nilai angka 0–100 per mahasiswa.
 * Nilainya ditulis ke KRS mata kuliah KKM (nilai_angka + huruf dari bobot nilai prodi mata kuliah), sehingga ikut
 * Detail Nilai, Validasi Nilai, KHS, dan transkrip. Nilai yang sudah divalidasi terkunci.
 */
class NilaiKkmController extends Controller
{
    use FilterKrs;

    public function index(Request $request): Response
    {
        $filter = [
            'prodi_id' => $request->integer('prodi_id') ?: null,
            'search' => $request->string('search')->trim()->toString(),
        ];

        $mahasiswa = MahasiswaProfile::query()
            ->whereHas('pengajuanAkademik', fn (Builder $q) => $q->where('jenis', PengajuanAkademik::KKM)->where('status', PengajuanAkademik::DISETUJUI))
            ->when($filter['prodi_id'], fn (Builder $q, int $prodi) => $q->where('prodi_id', $prodi))
            ->when($filter['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $m) => $m->where('nim', 'like', "%{$filter['search']}%")
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$filter['search']}%"))))
            ->with(['user:id,name', 'prodi:id,nama_prodi,jenjang'])
            ->orderBy('nim')
            ->paginate(25, ['id', 'user_id', 'nim', 'prodi_id', 'angkatan'])
            ->withQueryString();

        // Pengajuan KKM disetujui terakhir per mahasiswa: jenis (KKM/PKL/KKN) dan judul kegiatannya.
        $pengajuan = PengajuanAkademik::query()->where('jenis', PengajuanAkademik::KKM)->where('status', PengajuanAkademik::DISETUJUI)
            ->whereIn('mahasiswa_id', $mahasiswa->getCollection()->pluck('id'))->latest('id')->get()->unique('mahasiswa_id')->keyBy('mahasiswa_id');

        $mahasiswa->through(function (MahasiswaProfile $m) use ($pengajuan): array {
            $kegiatan = $pengajuan->get($m->id);
            $krs = KrsKkm::krs($m->id)?->load(['kelasKuliah:id,matkul_id,kode_kelas', 'kelasKuliah.mataKuliah:id,prodi_id,kode_matkul,nama_matkul,jenis_penilaian']);

            return [
                'id' => $m->id,
                'nim' => $m->nim,
                'nama' => $m->user?->name,
                'prodi' => $this->namaProdi($m->prodi),
                'angkatan' => $m->angkatan,
                'jenis_kkm' => PengajuanAkademik::JENIS_KKM[$kegiatan?->isian['jenis_kkm'] ?? ''] ?? null,
                'judul' => $kegiatan?->isian['judul'] ?? null,
                'krs_id' => $krs?->id,
                'mata_kuliah' => $krs ? trim($krs->kelasKuliah?->mataKuliah?->kode_matkul.' '.$krs->kelasKuliah?->mataKuliah?->nama_matkul) : null,
                'nilai_angka' => $krs?->nilai_angka,
                'huruf' => $krs?->nilai,
                'tervalidasi' => (bool) $krs?->nilaiTervalidasi(),
            ];
        });

        return Inertia::render('Admin/NilaiKkm', [
            'mahasiswa' => $mahasiswa,
            'filter' => $filter,
            'prodiOptions' => ProgramStudi::query()->orderBy('nama_prodi')->get(['id', 'nama_prodi', 'jenjang'])
                ->map(fn (ProgramStudi $p): array => ['id' => $p->id, 'name' => $p->jenjang.' '.$p->nama_prodi]),
        ]);
    }

    /**
     * Simpan angka KKM (kosong = hapus nilai). Huruf dihitung dari angka minimal bobot nilai prodi mata kuliah KKM.
     */
    public function update(Request $request, Krs $krs): RedirectResponse
    {
        $krs->load(['kelasKuliah.mataKuliah:id,prodi_id,nama_matkul,jenis_penilaian', 'mahasiswa:id,nim']);
        abort_unless($krs->kelasKuliah?->mataKuliah?->jenis_penilaian === MataKuliah::KKM, 404);

        if ($krs->nilaiTervalidasi()) {
            return back()->with('error', "Nilai KKM {$krs->mahasiswa?->nim} sudah divalidasi. Batalkan validasinya dulu di Penilaian → Validasi Nilai.");
        }

        $data = $request->validate(
            ['nilai' => ['nullable', 'numeric', 'min:0', 'max:100']],
            ['nilai.numeric' => 'Nilai harus berupa angka.', 'nilai.min' => 'Nilai paling kecil 0.', 'nilai.max' => 'Nilai paling besar 100.'],
        );

        if (($data['nilai'] ?? null) === null) {
            $krs->update(['nilai_angka' => null, 'nilai' => null]);

            return back()->with('success', "Nilai KKM {$krs->mahasiswa?->nim} dihapus.");
        }

        $angka = round((float) $data['nilai'], 2);
        $huruf = SkalaNilai::dariAngka($angka, $krs->prodiNilai());
        if ($huruf === null) {
            return back()->with('error', 'Angka minimal huruf belum diatur untuk prodi mata kuliah KKM, atau nilai di bawah semua angka minimal. Atur di Akademik → Konfigurasi → Bobot Nilai.');
        }

        $krs->update(['nilai_angka' => $angka, 'nilai' => $huruf]);

        return back()->with('success', "Nilai KKM {$krs->mahasiswa?->nim} disimpan: {$angka} ({$huruf}).");
    }

    /**
     * Masukkan mahasiswa ke KRS mata kuliah KKM (bila saat pengajuan disetujui belum ada mata kuliah/kelasnya).
     */
    public function tambahKrs(MahasiswaProfile $mahasiswa): RedirectResponse
    {
        abort_unless(PengajuanAkademik::query()->where('mahasiswa_id', $mahasiswa->id)->where('jenis', PengajuanAkademik::KKM)
            ->where('status', PengajuanAkademik::DISETUJUI)->exists(), 404);

        $hasil = KrsKkm::tambahkan($mahasiswa);

        return back()->with($hasil['berhasil'] ? 'success' : 'error', $hasil['pesan']);
    }
}
