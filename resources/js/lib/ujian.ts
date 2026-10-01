import { useFitur } from '@/composables/useFitur';

export type ModeUjian = 'tatap_muka' | 'online_berkas' | 'online_soal';
export type JenisUjian = 'uts' | 'uas' | 'remidi' | 'uts_susulan' | 'uas_susulan';

export const MODE_UJIAN: { value: ModeUjian; label: string; teks: string }[] = [
    { value: 'tatap_muka', label: 'Tatap muka', teks: 'Di ruang ujian; hanya jadwal, kartu, dan daftar hadir.' },
    { value: 'online_berkas', label: 'Online – unggah berkas', teks: 'Dosen mengunggah soal, mahasiswa mengunggah jawaban selama jam ujian.' },
    { value: 'online_soal', label: 'Online – soal di sistem', teks: 'Dosen menyusun soal seperti quiz, mahasiswa mengerjakan di SIA.' },
];

export const labelMode = (mode: ModeUjian): string => MODE_UJIAN.find((m) => m.value === mode)?.label ?? mode;

export const JENIS_UJIAN: Record<JenisUjian, string> = {
    uts: 'UTS',
    uas: 'UAS',
    remidi: 'Remidi',
    uts_susulan: 'UTS Susulan',
    uas_susulan: 'UAS Susulan',
};

/**
 * Pilihan mode dan jenis ujian sesuai fitur per klien: tanpa ujian_online hanya tatap muka,
 * tanpa ujian_susulan jenis susulan tidak ditawarkan. Server memvalidasi hal yang sama.
 */
export function useOpsiUjian() {
    const fitur = useFitur();
    const modeTersedia = MODE_UJIAN.filter((m) => fitur.aktif('ujian_online') || m.value === 'tatap_muka');
    const susulanAktif = fitur.aktif('ujian_susulan');
    const jenisTersedia = Object.fromEntries(
        Object.entries(JENIS_UJIAN).filter(([j]) => susulanAktif || (j !== 'uts_susulan' && j !== 'uas_susulan')),
    ) as Partial<Record<JenisUjian, string>>;
    const modeAwal = (mode?: ModeUjian | null): ModeUjian => (mode && modeTersedia.some((m) => m.value === mode) ? mode : 'tatap_muka');

    return { modeTersedia, jenisTersedia, susulanAktif, modeAwal };
}

/** Remidi dan susulan hanya untuk pesertanya sendiri. */
export const jenisKhusus = (jenis: JenisUjian): boolean => jenis !== 'uts' && jenis !== 'uas';

export const STATUS_UJIAN: Record<'draf' | 'terbit', { label: string; kelas: string }> = {
    draf: { label: 'Draf', kelas: 'bg-[#f6f5f4] text-[#615d59]' },
    terbit: { label: 'Terbit', kelas: 'bg-[#e8f7ec] text-[#1a7f37]' },
};

export type StatusPengajuanSusulan = 'menunggu' | 'disetujui' | 'ditolak' | 'dibatalkan' | 'gugur';

export const STATUS_PENGAJUAN_SUSULAN: Record<StatusPengajuanSusulan, { label: string; kelas: string }> = {
    menunggu: { label: 'Menunggu', kelas: 'bg-[#f2f9ff] text-[#0075de]' },
    disetujui: { label: 'Disetujui', kelas: 'bg-[#eaf7ed] text-[#1aae39]' },
    ditolak: { label: 'Ditolak', kelas: 'bg-[#fdecea] text-[#b42318]' },
    dibatalkan: { label: 'Dibatalkan', kelas: 'bg-[#f6f5f4] text-[#615d59]' },
    gugur: { label: 'Gugur – ikut ujian utama', kelas: 'bg-[#f6f5f4] text-[#615d59]' },
};
