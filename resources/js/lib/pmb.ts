// Status seleksi pendaftar PMB; kosong = belum diputuskan panitia.
export const labelStatusPmb = (status: string | null): { label: string; kelas: string } =>
    ({
        lulus: { label: 'Lulus', kelas: 'bg-[#1aae39]/10 text-[#137a2a]' },
        ditolak: { label: 'Ditolak', kelas: 'bg-[#dd5b00]/10 text-[#b34700]' },
    })[status ?? ''] ?? { label: 'Menunggu', kelas: 'bg-[#f6f5f4] text-[#615d59]' };
