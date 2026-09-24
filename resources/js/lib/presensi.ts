export type StatusPresensi = 'hadir' | 'terlambat' | 'izin' | 'sakit' | 'alpa';
export type StatusPertemuan = 'dijadwalkan' | 'berlangsung' | 'selesai' | 'dibatalkan';
export type JenisPertemuan = 'kuliah' | 'uts' | 'uas';

export const STATUS_PRESENSI: { value: StatusPresensi; label: string; singkat: string; kelas: string }[] = [
    { value: 'hadir', label: 'Hadir', singkat: 'H', kelas: 'bg-[#e8f7ec] text-[#1a7f37] border-[#b7e4c2]' },
    { value: 'terlambat', label: 'Terlambat', singkat: 'T', kelas: 'bg-[#eaf3fd] text-[#0b62b5] border-[#bcd8f5]' },
    { value: 'izin', label: 'Izin', singkat: 'I', kelas: 'bg-[#fff6e0] text-[#8a5a00] border-[#f1d9a0]' },
    { value: 'sakit', label: 'Sakit', singkat: 'S', kelas: 'bg-[#f3ecfb] text-[#6b3fa0] border-[#d9c6ef]' },
    { value: 'alpa', label: 'Alpa', singkat: 'A', kelas: 'bg-[#fdecea] text-[#b42318] border-[#f4c3bd]' },
];

export const infoStatusPresensi = (status: string) => STATUS_PRESENSI.find((item) => item.value === status);

export const STATUS_PERTEMUAN: Record<StatusPertemuan, { label: string; kelas: string }> = {
    dijadwalkan: { label: 'Dijadwalkan', kelas: 'bg-[#f6f5f4] text-[#615d59]' },
    berlangsung: { label: 'Berlangsung', kelas: 'bg-[#eaf3fd] text-[#0b62b5]' },
    selesai: { label: 'Selesai', kelas: 'bg-[#e8f7ec] text-[#1a7f37]' },
    dibatalkan: { label: 'Dibatalkan', kelas: 'bg-[#fdecea] text-[#b42318]' },
};

export const JENIS_PERTEMUAN: Record<JenisPertemuan, string> = { kuliah: 'Kuliah', uts: 'UTS', uas: 'UAS' };

const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

/** "2025-08-04" → "Senin, 04 Agu 2025". String dipotong apa adanya agar tidak bergeser zona waktu. */
export const formatTanggal = (nilai?: string | null, denganHari = true): string => {
    if (!nilai) return '-';
    const [tahun, bulan, tanggal] = nilai.slice(0, 10).split('-').map(Number);
    const hari = HARI[new Date(tahun, bulan - 1, tanggal).getDay()];

    return `${denganHari ? `${hari}, ` : ''}${String(tanggal).padStart(2, '0')} ${BULAN[bulan - 1]} ${tahun}`;
};

/** "2025-08-04T07:50:00+07:00" → "07:50". */
export const formatJamDari = (nilai?: string | null): string => (nilai ? nilai.slice(11, 16) : '-');

export const jam = (nilai?: string | null): string => (nilai ?? '').slice(0, 5);
