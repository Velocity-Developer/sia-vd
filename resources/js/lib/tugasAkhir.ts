export type JenisPengajuan = 'tugas_akhir' | 'pendadaran' | 'wisuda';
export type StatusPengajuan = 'menunggu' | 'perlu_perbaikan' | 'disetujui' | 'ditolak';
export type KeadaanForm = 'selesai' | 'menunggu' | 'perbaikan' | 'baru' | 'belum_memenuhi';
export type Syarat = { label: string; terpenuhi: boolean; keterangan: string | null };

export const JENIS_PENGAJUAN: Record<JenisPengajuan, string> = {
    tugas_akhir: 'Tugas Akhir',
    pendadaran: 'Pendadaran',
    wisuda: 'Wisuda',
};

export const STATUS_PENGAJUAN: Record<StatusPengajuan, { label: string; kelas: string }> = {
    menunggu: { label: 'Menunggu', kelas: 'bg-[#f2f9ff] text-[#0075de]' },
    perlu_perbaikan: { label: 'Perlu perbaikan', kelas: 'bg-[#fff3e0] text-[#b25000]' },
    disetujui: { label: 'Disetujui', kelas: 'bg-[#eaf7ed] text-[#1aae39]' },
    ditolak: { label: 'Ditolak', kelas: 'bg-[#fdecea] text-[#b42318]' },
};

/** Label berkas lampiran per kunci isian form. */
export const LABEL_LAMPIRAN: Record<string, string> = {
    proposal: 'Proposal',
};
