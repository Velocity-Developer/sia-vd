<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissions } from '@/composables/usePermissions';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BookOpen,
    BookMarked,
    CalendarDays,
    ClipboardList,
    DoorOpen,
    FileText,
    GraduationCap,
    LayoutGrid,
    Library,
    ShieldCheck,
    SlidersHorizontal,
    Users,
    CalendarRange,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can } = usePermissions();

// Setiap menu hanya tampil jika role user memiliki permission terkait (diatur di halaman Kelola Role).
const navigationSections: { label: string; items: NavItem[] }[] = [
    {
        label: 'Admin',
        items: [
            { title: 'Dashboard Admin', href: '/admin', icon: LayoutGrid, permission: 'admin.dashboard' },
            { title: 'Info Kuliah', href: route('admin.info-kuliah.index'), icon: FileText, permission: 'admin.info-kuliah' },
            { title: 'Tahun Akademik', href: route('admin.tahun-akademik.index'), icon: CalendarRange, permission: 'admin.tahun-akademik' },
            { title: 'Fakultas', href: route('admin.fakultas.index'), icon: GraduationCap, permission: 'admin.fakultas' },
            { title: 'Program Studi', href: route('admin.program-studi.index'), icon: BookOpen, permission: 'admin.program-studi' },
            { title: 'Mata Kuliah', href: route('admin.mata-kuliah.index'), icon: Library, permission: 'admin.mata-kuliah' },
            { title: 'Ruang', href: route('admin.ruang.index'), icon: DoorOpen, permission: 'admin.ruang' },
            { title: 'Kelas Kuliah', href: route('admin.kelas-kuliah.index'), icon: ClipboardList, permission: 'admin.kelas-kuliah' },
            { title: 'Pindah Kelas', href: route('admin.pindah-kelas.index'), icon: ArrowLeftRight, permission: 'admin.pindah-kelas' },
            { title: 'Pengaturan Akademik', href: route('admin.pengaturan-akademik.index'), icon: SlidersHorizontal, permission: 'admin.pengaturan-akademik' },
            {
                title: 'Manage User',
                href: '/admin/users/dosen',
                icon: Users,
                items: [
                    { title: 'Dosen', href: '/admin/users/dosen', permission: 'admin.users.dosen' },
                    { title: 'Mahasiswa', href: '/admin/users/mahasiswa', permission: 'admin.users.mahasiswa' },
                    { title: 'Karyawan', href: '/admin/users/karyawan', permission: 'admin.users.karyawan' },
                ],
            },
            { title: 'Kelola Role', href: route('admin.roles.index'), icon: ShieldCheck, permission: 'admin.roles' },
        ],
    },
    {
        label: 'Dosen',
        items: [
            { title: 'Beranda', href: '/dosen', icon: LayoutGrid, permission: 'dosen.dashboard' },
            { title: 'Kelas Kuliah', href: '/dosen/kelas-kuliah', icon: CalendarDays, permission: 'dosen.kelas-kuliah' },
            { title: 'Mahasiswa Kelas', href: '/dosen/mahasiswa-kelas', icon: Users, permission: 'dosen.mahasiswa-kelas' },
        ],
    },
    {
        label: 'Mahasiswa',
        items: [
            { title: 'Beranda', href: '/mahasiswa', icon: LayoutGrid, permission: 'mahasiswa.dashboard' },
            { title: 'Info Kuliah', href: route('mahasiswa.info-kuliah'), icon: FileText, permission: 'mahasiswa.info-kuliah' },
            { title: 'Pindah Kelas', href: route('mahasiswa.pindah-kelas'), icon: ArrowLeftRight, permission: 'mahasiswa.pindah-kelas' },
            {
                title: 'Rencana dan Hasil Studi',
                href: '/mahasiswa/krs',
                icon: GraduationCap,
                items: [
                    { title: 'Rencana Studi (KRS)', href: '/mahasiswa/krs', permission: 'mahasiswa.krs' },
                    { title: 'Kartu Hasil Studi', href: '/mahasiswa/khs', permission: 'mahasiswa.hasil-studi' },
                    { title: 'Transkrip Nilai', href: '/mahasiswa/transkrip', permission: 'mahasiswa.hasil-studi' },
                ],
            },
            { title: 'Jadwal Kuliah', href: '/mahasiswa/jadwal', icon: CalendarDays, permission: 'mahasiswa.jadwal-kuliah' },
            { title: 'Perpustakaan', href: '/mahasiswa/perpustakaan', icon: Library, permission: 'mahasiswa.perpustakaan' },
            { title: 'Pinjaman Aktif', href: '/mahasiswa/perpustakaan/pinjaman-aktif', icon: BookMarked, permission: 'mahasiswa.perpustakaan' },
            { title: 'Riwayat Pinjaman', href: '/mahasiswa/perpustakaan/riwayat-pinjaman', icon: FileText, permission: 'mahasiswa.perpustakaan' },
        ],
    },
];

const filterItems = (items: NavItem[]): NavItem[] =>
    items.flatMap((item) => {
        if (!can(item.permission)) return [];
        if (!item.items) return [item];

        const subItems = filterItems(item.items);

        return subItems.length ? [{ ...item, href: subItems[0].href, items: subItems }] : [];
    });

const visibleSections = computed(() =>
    navigationSections.map((section) => ({ ...section, items: filterItems(section.items) })).filter((section) => section.items.length > 0),
);

const footerNavItems: NavItem[] = [];
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
                :items="section.items"
                :label="visibleSections.length > 1 ? section.label : 'Platform'"
            />
        </SidebarContent>

        <SidebarFooter class="border-t border-[#e6e6e6] bg-white">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
