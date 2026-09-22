/**
 * Aplikasi hanya punya tema terang: warna halaman ditulis langsung (lihat DESIGN.md), jadi mode gelap
 * membuat teks dan latar tidak terbaca. Kelas `dark` dan preferensi lama di browser dibersihkan.
 */
export function initializeTheme() {
    document.documentElement.classList.remove('dark');

    try {
        localStorage.removeItem('appearance');
    } catch {
        // Penyimpanan browser tidak tersedia (mis. mode privat); tidak ada yang perlu dibersihkan.
    }
}
