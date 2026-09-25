<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { type NavEntry, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BookMarked,
    BookOpen,
    BookOpenCheck,
    Briefcase,
    CalendarDays,
    CalendarRange,
    ClipboardList,
    Database,
    DoorOpen,
    FileText,
    GraduationCap,
    Inbox,
    LayoutGrid,
    Library,
    NotebookPen,
    Receipt,
    Settings2,
    ShieldCheck,
    UserCheck,
    UserCog,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can } = usePermissions();

// URL dibentuk setelah menu difilter izin: daftar route yang dikirim ke browser hanya berisi
// route milik peran pengguna (lihat config/ziggy.php), jadi route('admin…') tidak boleh dipanggil
// untuk pengguna yang tidak punya izin itu.
type MenuItem = Omit<NavItem, 'href'> & { href?: string; routeName?: string };
type MenuSeksi = { title: string; icon?: NavItem['icon']; items: MenuItem[] };
type MenuEntry = MenuItem | MenuSeksi;

const adalahSeksi = (entry: MenuEntry): entry is MenuSeksi => 'items' in entry && Array.isArray(entry.items);

// Menu dikelompokkan per bidang kerja; menu tunggal (beranda) tetap di luar seksi.
// Setiap menu hanya tampil jika role user memiliki permission terkait (diatur di halaman Kelola Role).
const navigationSections: { label: string; entries: MenuEntry[] }[] = [
    {
        label: 'Admin',
        entries: [
            { title: 'Dashboard Admin', href: '/admin', icon: LayoutGrid, permission: 'admin.dashboard' },
            {
                title: 'Master Akademik',
                icon: Database,
                items: [
                    { title: 'Tahun Akademik', routeName: 'admin.tahun-akademik.index', icon: CalendarRange, permission: 'admin.tahun-akademik' },
                    { title: 'Fakultas', routeName: 'admin.fakultas.index', icon: GraduationCap, permission: 'admin.fakultas' },
                    { title: 'Program Studi', routeName: 'admin.program-studi.index', icon: BookOpen, permission: 'admin.program-studi' },
                    { title: 'Mata Kuliah', routeName: 'admin.mata-kuliah.index', icon: Library, permission: 'admin.mata-kuliah' },
                    { title: 'Ruang', routeName: 'admin.ruang.index', icon: DoorOpen, permission: 'admin.ruang' },
                ],
            },
            {
                title: 'Perkuliahan',
                icon: BookOpenCheck,
                items: [
                    { title: 'Kelas Kuliah', routeName: 'admin.kelas-kuliah.index', icon: ClipboardList, permission: 'admin.kelas-kuliah' },
                    { title: 'Jadwal Kelas', routeName: 'admin.jadwal.index', icon: CalendarDays, permission: 'admin.jadwal' },
                    { title: 'Materi', routeName: 'admin.materi.index', icon: BookMarked, permission: 'admin.materi' },
                    { title: 'Tugas', routeName: 'admin.tugas.index', icon: ClipboardList, permission: 'admin.tugas' },
                    { title: 'Quiz', routeName: 'admin.quiz.index', icon: FileText, permission: 'admin.quiz' },
                    { title: 'Presensi', routeName: 'admin.presensi.index', icon: UserCheck, permission: 'admin.presensi' },
                    { title: 'Jadwal Ujian', routeName: 'admin.ujian.index', icon: NotebookPen, permission: 'admin.ujian' },
                ],
            },
            {
                title: 'Administrasi',
                icon: Inbox,
                items: [
                    { title: 'Info Kuliah', routeName: 'admin.info-kuliah.index', icon: FileText, permission: 'admin.info-kuliah' },
                    { title: 'Pindah Kelas', routeName: 'admin.pindah-kelas.index', icon: ArrowLeftRight, permission: 'admin.pindah-kelas' },
                ],
            },
            {
                title: 'Keuangan',
                icon: Wallet,
                items: [
                    { title: 'Jenis Biaya', routeName: 'admin.jenis-biaya.index', icon: Receipt, permission: 'admin.jenis-biaya' },
                    { title: 'Tagihan Mahasiswa', routeName: 'admin.tagihan.index', icon: Wallet, permission: 'admin.tagihan' },
                ],
            },
            {
                title: 'Pengguna & Akses',
                icon: Users,
                items: [
                    { title: 'Dosen', href: '/admin/users/dosen', icon: GraduationCap, permission: 'admin.users.dosen' },
                    { title: 'Mahasiswa', href: '/admin/users/mahasiswa', icon: Users, permission: 'admin.users.mahasiswa' },
                    { title: 'Karyawan', href: '/admin/users/karyawan', icon: Briefcase, permission: 'admin.users.karyawan' },
                    { title: 'Kelola Role', routeName: 'admin.roles.index', icon: ShieldCheck, permission: 'admin.roles' },
                ],
            },
        ],
    },
    {
        label: 'Dosen',
        entries: [
            { title: 'Beranda', href: '/dosen', icon: LayoutGrid, permission: 'dosen.dashboard' },
            {
                title: 'Pengajaran',
                icon: BookOpenCheck,
                items: [
                    { title: 'Kelas Kuliah', href: '/dosen/kelas-kuliah', icon: ClipboardList, permission: 'dosen.kelas-kuliah' },
                    { title: 'Jadwal Mengajar', routeName: 'dosen.jadwal.index', icon: CalendarDays, permission: 'dosen.jadwal' },
                    { title: 'Presensi', routeName: 'dosen.presensi.index', icon: UserCheck, permission: 'dosen.presensi' },
                    { title: 'Ujian', routeName: 'dosen.ujian.index', icon: NotebookPen, permission: 'dosen.ujian' },
                    { title: 'Mahasiswa Kelas', href: '/dosen/mahasiswa-kelas', icon: Users, permission: 'dosen.mahasiswa-kelas' },
                ],
            },
            {
                title: 'Konten Kelas',
                icon: BookMarked,
                items: [
                    { title: 'Materi', routeName: 'dosen.materi.index', icon: BookMarked, permission: 'dosen.materi' },
                    { title: 'Tugas', routeName: 'dosen.tugas.index', icon: ClipboardList, permission: 'dosen.tugas' },
                    { title: 'Quiz', routeName: 'dosen.quiz.index', icon: FileText, permission: 'dosen.quiz' },
                ],
            },
        ],
    },
    {
        label: 'Mahasiswa',
        entries: [
            { title: 'Beranda', href: '/mahasiswa', icon: LayoutGrid, permission: 'mahasiswa.dashboard' },
            {
                title: 'Akademik',
                icon: GraduationCap,
                items: [
                    { title: 'Rencana Studi (KRS)', href: '/mahasiswa/krs', icon: ClipboardList, permission: 'mahasiswa.krs' },
                    { title: 'Kartu Hasil Studi', href: '/mahasiswa/khs', icon: FileText, permission: 'mahasiswa.hasil-studi' },
                    { title: 'Transkrip Nilai', href: '/mahasiswa/transkrip', icon: BookOpen, permission: 'mahasiswa.hasil-studi' },
                    { title: 'Jadwal Kuliah', href: '/mahasiswa/jadwal', icon: CalendarDays, permission: 'mahasiswa.jadwal-kuliah' },
                    { title: 'Presensi', routeName: 'mahasiswa.presensi', icon: UserCheck, permission: 'mahasiswa.presensi' },
                    { title: 'Jadwal Ujian', routeName: 'mahasiswa.ujian', icon: NotebookPen, permission: 'mahasiswa.ujian' },
                ],
            },
            {
                title: 'Administrasi',
                icon: Inbox,
                items: [
                    { title: 'Info Kuliah', routeName: 'mahasiswa.info-kuliah', icon: FileText, permission: 'mahasiswa.info-kuliah' },
                    { title: 'Pindah Kelas', routeName: 'mahasiswa.pindah-kelas', icon: ArrowLeftRight, permission: 'mahasiswa.pindah-kelas' },
                    { title: 'Biaya Kuliah', routeName: 'mahasiswa.info-biaya-kuliah', icon: Wallet, permission: 'mahasiswa.info-biaya' },
                ],
            },
            {
                title: 'Perpustakaan',
                icon: Library,
                items: [
                    { title: 'Katalog Buku', href: '/mahasiswa/perpustakaan', icon: Library, permission: 'mahasiswa.perpustakaan' },
                    {
                        title: 'Pinjaman Aktif',
                        href: '/mahasiswa/perpustakaan/pinjaman-aktif',
                        icon: BookMarked,
                        permission: 'mahasiswa.perpustakaan',
                    },
                    {
                        title: 'Riwayat Pinjaman',
                        href: '/mahasiswa/perpustakaan/riwayat-pinjaman',
                        icon: UserCog,
                        permission: 'mahasiswa.perpustakaan',
                    },
                ],
            },
        ],
    },
];

// Bila daftar route di browser belum diperbarui (mis. sesi lama sebelum izin berubah),
// menu yang route-nya tidak dikenal dilewati saja daripada menggagalkan seluruh sidebar.
const bentukItem = (item: MenuItem): NavItem[] => {
    const { routeName, ...sisa } = item;

    if (!routeName) return [{ ...sisa, href: item.href ?? '#' }];

    try {
        return [{ ...sisa, href: route(routeName) }];
    } catch {
        console.warn(`Menu "${item.title}" dilewati: route ${routeName} tidak ada di daftar route peran ini.`);

        return [];
    }
};

// Seksi yang tersisa satu menu ditampilkan langsung tanpa dropdown agar tidak menambah klik.
const filterEntries = (entries: MenuEntry[]): NavEntry[] =>
    entries.flatMap((entry): NavEntry[] => {
        if (!adalahSeksi(entry)) return can(entry.permission) ? bentukItem(entry) : [];

        const items = entry.items.filter((item) => can(item.permission)).flatMap(bentukItem);

        if (items.length === 0) return [];
        if (items.length === 1) return [items[0]];

        return [{ title: entry.title, icon: entry.icon, items }];
    });

const visibleSections = computed(() =>
    navigationSections
        .map((section) => ({ label: section.label, entries: filterEntries(section.entries) }))
        .filter((section) => section.entries.length > 0),
);

const footerNavItems: NavItem[] = [];

// Pengaturan Sistem selalu di bawah sidebar (di luar daftar menu yang bisa di-scroll), tampil bila
// pengguna boleh membuka minimal satu tab-nya.
const page = usePage();
const bisaPengaturanSistem = computed(() =>
    ['admin.institusi', 'admin.pengaturan-email', 'admin.pengaturan-akademik', 'admin.pengaturan-tampilan'].some((izin) => can(izin)),
);
const pengaturanSistemAktif = computed(() => page.url.startsWith('/pengaturan-sistem'));
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-[#e6e6e6] bg-white">
        <SidebarHeader class="border-b border-[#e6e6e6] bg-white px-2 py-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child class="rounded-[5px] hover:bg-[#f6f5f4]">
                        <Link :href="route('dashboard')">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="bg-white px-2 py-3">
            <NavMain
                v-for="section in visibleSections"
                :key="section.label"
                :entries="section.entries"
                :label="visibleSections.length > 1 ? section.label : 'Platform'"
            />
        </SidebarContent>

        <SidebarFooter class="border-t border-[#e6e6e6] bg-white">
            <SidebarMenu v-if="bisaPengaturanSistem">
                <SidebarMenuItem>
                    <SidebarMenuButton as-child :is-active="pengaturanSistemAktif" tooltip="Pengaturan Sistem">
                        <Link :href="route('pengaturan-sistem.index')">
                            <Settings2 class="text-muted-foreground transition-colors group-data-[active=true]/menu-button:text-sidebar-primary" />
                            <span>Pengaturan Sistem</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
