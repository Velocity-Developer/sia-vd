import type { LucideIcon } from 'lucide-vue-next';

export interface AuthRole {
    id: number;
    name: string;
    slug: string;
    user_type: 'admin' | 'dosen' | 'mahasiswa';
}

export interface Auth {
    user: User;
    role: AuthRole | null;
    permissions: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    permission?: string;
    icon?: LucideIcon;
    isActive?: boolean;
    items?: NavItem[];
}

/** Seksi menu di sidebar: kumpulan menu sejenis yang dibuka-tutup sebagai dropdown. */
export interface NavGroup {
    title: string;
    icon?: LucideIcon;
    items: NavItem[];
}

export type NavEntry = NavItem | NavGroup;

export interface Institusi {
    nama_pt: string;
    singkatan: string | null;
    logo_url: string | null;
    zona_waktu: string;
    /** WIB, WITA, atau WIT. */
    zona_singkatan: string;
}

/** Pengaturan tampilan sistem, sudah terisi nilai bawaan (lihat PengaturanTampilan::shared). */
export interface Tampilan {
    nama_aplikasi: string;
    favicon_url: string;
    login_judul: string;
    login_teks: string;
    login_gambar_url: string | null;
    login_sorotan: boolean;
    login_tata_letak: 'panel' | 'tengah';
    sidebar_bawaan: 'lebar' | 'ringkas';
}

export interface SharedData {
    [key: string]: unknown;
    name: string;
    quote: { message: string; author: string };
    institusi: Institusi;
    tampilan: Tampilan;
    /** Fitur per klien dari config/client.php (lihat App\\Feature). */
    fitur: Record<'kelola_role' | 'keuangan' | 'ujian_susulan', boolean>;
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    role_id?: number | null;
}

export type BreadcrumbItemType = BreadcrumbItem;
