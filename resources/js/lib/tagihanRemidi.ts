export type Rincian = { nama: string; cara_hitung: string; nominal_satuan: number; jumlah: number; subtotal: number };

export type StatusTagihanRemidi = 'belum_bayar' | 'menunggu_verifikasi' | 'lunas' | 'ditolak' | 'gugur';

export const STATUS_TAGIHAN_REMIDI: Record<StatusTagihanRemidi, { label: string; kelas: string }> = {
    belum_bayar: { label: 'Belum Bayar', kelas: 'bg-[#fdf1e9] text-[#dd5b00]' },
    menunggu_verifikasi: { label: 'Menunggu Verifikasi', kelas: 'bg-[#f2f9ff] text-[#0075de]' },
    lunas: { label: 'Lunas', kelas: 'bg-[#eaf7ed] text-[#1aae39]' },
    ditolak: { label: 'Ditolak', kelas: 'bg-[#fdecea] text-[#b42318]' },
    gugur: { label: 'Gugur', kelas: 'bg-[#f6f5f4] text-[#615d59]' },
};

export const rupiah = (nilai: number) =>
    new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(nilai || 0);
