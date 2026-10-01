export type StatusKrs = 'diajukan' | 'perlu_revisi' | 'disetujui';

/** Status KRS semester (tabel krs_semester); null = belum disimpan. */
export const STATUS_KRS: Record<StatusKrs, { label: string; kelas: string }> = {
    diajukan: { label: 'Menunggu verifikasi', kelas: 'bg-[#f2f9ff] text-[#0075de]' },
    perlu_revisi: { label: 'Perlu revisi', kelas: 'bg-[#fff3e0] text-[#b25000]' },
    disetujui: { label: 'Disetujui', kelas: 'bg-[#eaf7ed] text-[#1aae39]' },
};

/** Ringkasan status dari KrsSemester::ringkasan(). */
export type RingkasanKrs = {
    status: StatusKrs | null;
    disimpan_pada: string | null;
    catatan_revisi: string | null;
    batas_revisi: string | null;
    bisa_direvisi?: boolean;
    diverifikasi_oleh?: string | null;
    diverifikasi_pada?: string | null;
};
