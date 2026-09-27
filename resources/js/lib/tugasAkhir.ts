export type JenisPengajuan = 'tugas_akhir' | 'pendadaran' | 'wisuda';
export type StatusPengajuan = 'menunggu_pembimbing' | 'menunggu' | 'perlu_perbaikan' | 'disetujui' | 'ditolak';
export type KeadaanForm = 'terkunci' | 'selesai' | 'terjadwal' | 'terdaftar' | 'menunggu' | 'perbaikan' | 'baru' | 'belum_memenuhi';
export type Syarat = { label: string; terpenuhi: boolean; keterangan: string | null };
export type JadwalPendadaran = {
    id: number;
    tanggal: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang: string | null;
    penguji: { peran: string; nama: string | null }[];
    status: 'dijadwalkan' | 'revisi' | 'selesai' | 'tidak_lulus';
    nomor_surat: string | null;
};
export type KodeHasil = 'lulus' | 'lulus_revisi' | 'tidak_lulus';
export type HasilPendadaran = {
    nilai_akhir: number | null;
    huruf: string | null;
    hasil: KodeHasil | null;
    catatan_hasil: string | null;
    hasil_ditetapkan_at: string | null;
    ada_revisi: boolean;
    revisi_diunggah_at: string | null;
    catatan_revisi: string | null;
    revisi_disahkan_at: string | null;
};

export const HASIL_PENDADARAN: Record<KodeHasil, { label: string; kelas: string }> = {
    lulus: { label: 'Lulus', kelas: 'bg-[#eaf7ed] text-[#1aae39]' },
    lulus_revisi: { label: 'Lulus dengan revisi', kelas: 'bg-[#fff3e0] text-[#b25000]' },
    tidak_lulus: { label: 'Tidak lulus', kelas: 'bg-[#fdecea] text-[#b42318]' },
};

export const JENIS_PENGAJUAN: Record<JenisPengajuan, string> = {
    tugas_akhir: 'Tugas Akhir',
    pendadaran: 'Pendadaran',
    wisuda: 'Wisuda',
};

export const STATUS_PENGAJUAN: Record<StatusPengajuan, { label: string; kelas: string }> = {
    menunggu_pembimbing: { label: 'Menunggu pembimbing', kelas: 'bg-[#f6f5f4] text-[#615d59]' },
    menunggu: { label: 'Menunggu admin', kelas: 'bg-[#f2f9ff] text-[#0075de]' },
    perlu_perbaikan: { label: 'Perlu perbaikan', kelas: 'bg-[#fff3e0] text-[#b25000]' },
    disetujui: { label: 'Disetujui', kelas: 'bg-[#eaf7ed] text-[#1aae39]' },
    ditolak: { label: 'Ditolak', kelas: 'bg-[#fdecea] text-[#b42318]' },
};

/** Label peristiwa di riwayat pengajuan. */
export const labelPeristiwa = (status: string, pertama: boolean): string =>
    status === 'dikirim'
        ? pertama
            ? 'Dikirim'
            : 'Dikirim ulang'
        : status === 'disetujui_pembimbing'
          ? 'Disetujui pembimbing'
          : (STATUS_PENGAJUAN[status as StatusPengajuan]?.label ?? status);

/** Label berkas lampiran per kunci isian form. */
export const LABEL_LAMPIRAN: Record<string, string> = {
    proposal: 'Proposal',
    naskah: 'Naskah',
    persetujuan_pembimbing: 'Lembar persetujuan',
    bukti_bayar: 'Bukti bayar',
    pas_foto: 'Pas foto',
    naskah_final: 'Naskah final',
    bebas_pinjam: 'Bebas pinjam perpustakaan',
};
