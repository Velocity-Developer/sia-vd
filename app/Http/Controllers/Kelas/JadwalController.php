<?php

namespace App\Http\Controllers\Kelas;

use App\Http\Controllers\Concerns\FilterKelasKuliah;
use App\Http\Controllers\Concerns\KontenKelas;
use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\KelasKuliah;
use App\Models\Ruang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class JadwalController extends Controller
{
    use FilterKelasKuliah, KontenKelas;

    /**
     * Daftar jadwal lintas kelas dengan filter; admin bisa mengelola, dosen melihat jadwal mengajarnya.
     */
    public function index(Request $request): Response
    {
        $filter = $this->filterKelas($request);

        $jadwals = Jadwal::query()
            ->with(['kelasKuliah:id,kode_kelas,matkul_id,dosen_id,tahun_akademik_id', 'kelasKuliah.mataKuliah:id,kode_matkul,nama_matkul', 'kelasKuliah.dosen:id,user_id', 'kelasKuliah.dosen.user:id,name', 'kelasKuliah.tahunAkademik:id,tahun,semester', 'ruang:id,kode_ruang,nama_ruang'])
            ->tap(fn ($query) => $this->terapkanFilterKelas($query, $filter))
            ->when($filter['search'] !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('hari', 'like', "%{$filter['search']}%")
                ->orWhereHas('ruang', fn ($ruang) => $ruang->where('kode_ruang', 'like', "%{$filter['search']}%")->orWhere('nama_ruang', 'like', "%{$filter['search']}%"))
                ->orWhereHas('kelasKuliah', fn ($kelas) => $kelas->where('kode_kelas', 'like', "%{$filter['search']}%"))))
            // Urutan hari dituliskan manual agar sama di MySQL maupun SQLite.
            ->orderByRaw("CASE hari WHEN 'Senin' THEN 1 WHEN 'Selasa' THEN 2 WHEN 'Rabu' THEN 3 WHEN 'Kamis' THEN 4 WHEN 'Jumat' THEN 5 ELSE 6 END")
            ->orderBy('jam_mulai')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Kelas/JadwalIndex', [
            'jadwals' => $jadwals,
            ...$this->propsFilterKelas($filter),
        ]);
    }

    public function create(KelasKuliah $kelasKuliah): Response
    {
        $kelasKuliah->load(['mataKuliah', 'dosen.user']);

        return Inertia::render('Admin/JadwalForm', [
            'kelasKuliah' => $kelasKuliah,
            'jadwal' => null,
            'ruangs' => $this->ruangs(),
        ]);
    }

    public function store(Request $request, KelasKuliah $kelasKuliah): RedirectResponse
    {
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());
        $this->ensureNoConflict($kelasKuliah, $data, null);
        $data['kelas_id'] = $kelasKuliah->id;
        Jadwal::create($data);

        return $this->keKelas($kelasKuliah)->with('jadwal_success', 'Jadwal berhasil ditambahkan.');
    }

    public function edit(KelasKuliah $kelasKuliah, Jadwal $jadwal): Response
    {
        $this->ensureScoped($kelasKuliah, $jadwal);
        $kelasKuliah->load(['mataKuliah', 'dosen.user']);

        return Inertia::render('Admin/JadwalForm', [
            'kelasKuliah' => $kelasKuliah,
            'jadwal' => $jadwal,
            'ruangs' => $this->ruangs(),
        ]);
    }

    public function update(Request $request, KelasKuliah $kelasKuliah, Jadwal $jadwal): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $jadwal);
        $data = $request->validate($this->rules(), $this->messages(), $this->attributes());
        $this->ensureNoConflict($kelasKuliah, $data, $jadwal);
        $jadwal->update($data);

        return $this->keKelas($kelasKuliah)->with('jadwal_success', 'Jadwal berhasil diperbarui.');
    }

    public function destroy(KelasKuliah $kelasKuliah, Jadwal $jadwal): RedirectResponse
    {
        $this->ensureScoped($kelasKuliah, $jadwal);
        try {
            $jadwal->delete();
        } catch (Throwable) {
            return $this->keKelas($kelasKuliah)->with('jadwal_error', 'Jadwal gagal dihapus.');
        }

        return $this->keKelas($kelasKuliah)->with('jadwal_success', 'Jadwal berhasil dihapus.');
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function ruangs(): array
    {
        return Ruang::orderBy('kode_ruang')->get()->map(fn (Ruang $r): array => [
            'id' => $r->id,
            'name' => $r->kode_ruang.' — '.$r->nama_ruang,
        ])->all();
    }

    private function ensureScoped(KelasKuliah $kelasKuliah, Jadwal $jadwal): void
    {
        abort_unless($jadwal->kelas_id === $kelasKuliah->id, 404);
    }

    /**
     * Cegah bentrok jadwal di tahun akademik yang sama: kelas yang sama, ruang yang sama, atau dosen yang sama.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureNoConflict(KelasKuliah $kelasKuliah, array $data, ?Jadwal $ignore): void
    {
        $overlapping = fn () => Jadwal::query()
            ->overlapping($data['hari'], $data['jam_mulai'].':00', $data['jam_akhir'].':00')
            ->inTahunAkademik($kelasKuliah->tahun_akademik_id)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id));

        if ($overlapping()->where('kelas_id', $kelasKuliah->id)->exists()) {
            throw ValidationException::withMessages([
                'hari' => 'Kelas ini sudah memiliki jadwal pada hari dan jam yang sama.',
            ]);
        }

        $ruangConflict = $overlapping()
            ->where('ruang_id', $data['ruang_id'])
            ->with('kelasKuliah:id,kode_kelas')
            ->first();

        if ($ruangConflict) {
            throw ValidationException::withMessages([
                'ruang_id' => 'Ruang pada jam ini sudah digunakan oleh kelas '.($ruangConflict->kelasKuliah?->kode_kelas ?? '-').'.',
            ]);
        }

        $dosenConflict = $overlapping()
            ->where('kelas_id', '!=', $kelasKuliah->id)
            ->whereHas('kelasKuliah', fn ($query) => $query->where('dosen_id', $kelasKuliah->dosen_id))
            ->with('kelasKuliah:id,kode_kelas')
            ->first();

        if ($dosenConflict) {
            throw ValidationException::withMessages([
                'jam_mulai' => 'Dosen pengampu sudah mengajar kelas '.($dosenConflict->kelasKuliah?->kode_kelas ?? '-').' pada hari dan jam ini.',
            ]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        return [
            'hari' => ['required', 'in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_akhir' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'ruang_id' => ['required', 'exists:ruangs,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'in' => ':attribute tidak valid.',
            'date_format' => ':attribute harus berformat jam:menit (mis. 07:00).',
            'after' => ':attribute harus lebih besar dari Jam Mulai.',
            'exists' => ':attribute tidak ditemukan.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        return [
            'hari' => 'Hari',
            'jam_mulai' => 'Jam Mulai',
            'jam_akhir' => 'Jam Akhir',
            'ruang_id' => 'Ruang',
        ];
    }
}
