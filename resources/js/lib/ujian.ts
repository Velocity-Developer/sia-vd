export type ModeUjian = 'tatap_muka' | 'online_berkas' | 'online_soal';
export type JenisUjian = 'uts' | 'uas' | 'remidi';

export const MODE_UJIAN: { value: ModeUjian; label: string; teks: string }[] = [
    { value: 'tatap_muka', label: 'Tatap muka', teks: 'Di ruang ujian; hanya jadwal, kartu, dan daftar hadir.' },
    { value: 'online_berkas', label: 'Online – unggah berkas', teks: 'Dosen mengunggah soal, mahasiswa mengunggah jawaban selama jam ujian.' },
    { value: 'online_soal', label: 'Online – soal di sistem', teks: 'Dosen menyusun soal seperti quiz, mahasiswa mengerjakan di SIA.' },
];

export const labelMode = (mode: ModeUjian): string => MODE_UJIAN.find((m) => m.value === mode)?.label ?? mode;

export const JENIS_UJIAN: Record<JenisUjian, string> = { uts: 'UTS', uas: 'UAS', remidi: 'Remidi' };

export const STATUS_UJIAN: Record<'draf' | 'terbit', { label: string; kelas: string }> = {
    draf: { label: 'Draf', kelas: 'bg-[#f6f5f4] text-[#615d59]' },
    terbit: { label: 'Terbit', kelas: 'bg-[#e8f7ec] text-[#1a7f37]' },
};
