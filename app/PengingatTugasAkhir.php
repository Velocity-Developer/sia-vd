<?php

namespace App;

use App\Models\DosenProfile;
use App\Models\MahasiswaProfile;
use App\Models\Pendadaran;
use App\Models\PengajuanAkademik;
use App\Models\TugasAkhir;
use App\Models\Wisuda;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Pengingat tugas akhir, pendadaran, dan wisuda untuk Beranda: satu daftar pesan per peran beserta tautan ke
 * halaman tempat menindaklanjutinya. Null bila tidak ada yang perlu diingatkan.
 */
class PengingatTugasAkhir
{
    /**
     * @return array{pesan: list<array{teks: string, penting: bool}>, tautan: string}|null
     */
    public static function untukMahasiswa(MahasiswaProfile $mahasiswa): ?array
    {
        $pesan = [];

        foreach (PengajuanAkademik::JENIS as $jenis) {
            $p = PengajuanAkademik::terakhir($mahasiswa->id, $jenis);
            if ($p?->status === PengajuanAkademik::PERLU_PERBAIKAN) {
                $pesan[] = ['teks' => 'Pengajuan '.strtolower(PengajuanAkademik::LABEL_JENIS[$jenis]).' diminta perbaikan: '.$p->catatan, 'penting' => true];
            }
        }

        $ta = TugasAkhir::milik($mahasiswa->id);
        $pendadaran = $ta ? Pendadaran::query()->where('tugas_akhir_id', $ta->id)->whereIn('status', Pendadaran::AKTIF)->with('ruang')->latest('id')->first() : null;
        if ($pendadaran?->status === Pendadaran::DIJADWALKAN && $pendadaran->tanggal->greaterThanOrEqualTo(today())) {
            $pesan[] = ['teks' => 'Pendadaran '.$pendadaran->tanggal->translatedFormat('l, d F Y').' pukul '.substr($pendadaran->jam_mulai, 0, 5)
                .' di '.trim($pendadaran->ruang?->kode_ruang.' '.$pendadaran->ruang?->nama_ruang).'.', 'penting' => false];
        }
        if ($pendadaran?->status === Pendadaran::REVISI && $pendadaran->revisi_diunggah_at === null) {
            $pesan[] = ['teks' => 'Unggah naskah revisi pendadaran'.($pendadaran->catatan_revisi ? ' (dikembalikan: '.$pendadaran->catatan_revisi.')' : '').'.', 'penting' => true];
        }

        $wisuda = Wisuda::query()->where('mahasiswa_id', $mahasiswa->id)->with('periode')->first();
        if ($wisuda?->nomor_skl !== null) {
            $pesan[] = ['teks' => 'Surat keterangan lulus Anda sudah terbit dan bisa diunduh.', 'penting' => false];
        } elseif ($wisuda?->periode && $wisuda->periode->tanggal_acara->greaterThanOrEqualTo(today())) {
            $pesan[] = ['teks' => 'Anda peserta '.$wisuda->periode->nama.', '.$wisuda->periode->tanggal_acara->translatedFormat('d F Y').'.', 'penting' => false];
        }

        return $pesan === [] ? null : ['pesan' => $pesan, 'tautan' => route('mahasiswa.tugas-akhir')];
    }

    /**
     * @return array{pesan: list<array{teks: string, penting: bool}>, tautan: string}|null
     */
    public static function untukDosen(DosenProfile $dosen): ?array
    {
        $pesan = [];

        $persetujuan = PengajuanAkademik::query()->where('jenis', PengajuanAkademik::PENDADARAN)->where('status', PengajuanAkademik::MENUNGGU_PEMBIMBING)
            ->whereHas('tugasAkhir', fn (Builder $q) => $q->dibimbing($dosen->id))->count();
        if ($persetujuan > 0) {
            $pesan[] = ['teks' => "{$persetujuan} pendaftaran pendadaran menunggu persetujuan Anda sebagai pembimbing.", 'penting' => true];
        }

        $aktif = Pendadaran::query()->diuji($dosen->id)->where('status', Pendadaran::DIJADWALKAN)->with(['nilai', 'mahasiswa.user:id,name'])->orderBy('tanggal')->orderBy('jam_mulai')->get();
        foreach ($aktif as $p) {
            $nama = $p->mahasiswa?->user?->name;
            if (! $p->bolehDinilai()) {
                $pesan[] = ['teks' => 'Menguji pendadaran '.$nama.', '.$p->tanggal->translatedFormat('d F Y').' pukul '.substr($p->jam_mulai, 0, 5).' ('.$p->peranPenguji($dosen->id).').', 'penting' => false];
            } elseif ($p->nilai->firstWhere('dosen_id', $dosen->id) === null) {
                $pesan[] = ['teks' => "Isi nilai pendadaran {$nama}.", 'penting' => true];
            } elseif ($p->penguji_1_id === $dosen->id && $p->nilai->count() === 3) {
                $pesan[] = ['teks' => "Semua penguji sudah menilai; tetapkan hasil pendadaran {$nama}.", 'penting' => true];
            }
        }

        $revisi = Pendadaran::query()->where('penguji_1_id', $dosen->id)->where('status', Pendadaran::REVISI)->whereNotNull('revisi_diunggah_at')->count();
        if ($revisi > 0) {
            $pesan[] = ['teks' => "{$revisi} naskah revisi pendadaran menunggu pengesahan Anda.", 'penting' => true];
        }

        return $pesan === [] ? null : ['pesan' => $pesan, 'tautan' => route('dosen.bimbingan.index')];
    }

    /**
     * @return array{pesan: list<array{teks: string, penting: bool}>, tautan: string}|null
     */
    public static function untukAdmin(): ?array
    {
        $menunggu = PengajuanAkademik::query()->where('status', PengajuanAkademik::MENUNGGU)
            ->selectRaw('jenis, count(*) as jumlah')->groupBy('jenis')->pluck('jumlah', 'jenis');
        $pesan = collect(PengajuanAkademik::JENIS)
            ->filter(fn (string $jenis): bool => ($menunggu[$jenis] ?? 0) > 0)
            ->map(fn (string $jenis): array => ['teks' => $menunggu[$jenis].' pengajuan '.strtolower(PengajuanAkademik::LABEL_JENIS[$jenis]).' menunggu keputusan.', 'penting' => true])
            ->values()->all();

        $belumSkl = Wisuda::query()->whereNull('nomor_skl')->count();
        if ($belumSkl > 0) {
            $pesan[] = ['teks' => "{$belumSkl} peserta wisuda belum dibuatkan SKL (menu Periode Wisuda).", 'penting' => false];
        }

        return $pesan === [] ? null : ['pesan' => $pesan, 'tautan' => route('admin.pengajuan-akademik.index')];
    }
}
