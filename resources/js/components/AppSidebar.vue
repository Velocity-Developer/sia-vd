<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, BookMarked, CalendarDays, ClipboardList, DoorOpen, FileText, Folder, GraduationCap, LayoutGrid, Library, ListChecks, UserRound, Users, CalendarRange } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage<SharedData>();

const navigationByRole: Record<string, NavItem[]> = {
    admin: [
        { title: 'Dashboard Admin', href: '/admin', icon: LayoutGrid },
        { title: 'Info Kuliah', href: route('admin.info-kuliah.index'), icon: FileText },
        { title: 'Tahun Akademik', href: route('admin.tahun-akademik.index'), icon: CalendarRange },
        { title: 'Fakultas', href: route('admin.fakultas.index'), icon: GraduationCap },
        { title: 'Program Studi', href: route('admin.program-studi.index'), icon: BookOpen },
        { title: 'Mata Kuliah', href: route('admin.mata-kuliah.index'), icon: Library },
        { title: 'Ruang', href: route('admin.ruang.index'), icon: DoorOpen },
        { title: 'Kelas Kuliah', href: route('admin.kelas-kuliah.index'), icon: ClipboardList },
        {
            title: 'Manage User',
            href: '/admin/users/dosen',
            icon: Users,
            items: [
                { title: 'Dosen', href: '/admin/users/dosen' },
                { title: 'Mahasiswa', href: '/admin/users/mahasiswa' },
                { title: 'Karyawan', href: '/admin/users/karyawan' },
            ],
        },
    ],
    dosen: [
        { title: 'Beranda', href: '/dosen', icon: LayoutGrid },
        { title: 'Kelas Kuliah', href: '/dosen/kelas-kuliah', icon: CalendarDays },
        { title: 'Mahasiswa Kelas', href: '/dosen/mahasiswa-kelas', icon: Users },
    ],
    mahasiswa: [
        { title: 'Beranda', href: '/mahasiswa', icon: LayoutGrid },
        { title: 'Info Kuliah', href: route('mahasiswa.info-kuliah'), icon: FileText },
        { title: 'Rencana dan Hasil Studi', href: '/mahasiswa/krs', icon: GraduationCap, items: [{ title: 'Rencana Studi (KRS)', href: '/mahasiswa/krs' }, { title: 'Kartu Hasil Studi', href: '/mahasiswa/khs' }, { title: 'Transkrip Nilai', href: '/mahasiswa/transkrip' }] },
        { title: 'Jadwal Kuliah', href: '/mahasiswa/jadwal', icon: CalendarDays },
        { title: 'Perpustakaan', href: '/mahasiswa/perpustakaan', icon: Library },
        { title: 'Pinjaman Aktif', href: '/mahasiswa/perpustakaan/aktif', icon: BookMarked },
        { title: 'Riwayat Pinjaman', href: '/mahasiswa/perpustakaan/riwayat', icon: FileText },
    ],
};

const mainNavItems = computed(() => navigationByRole[page.props.auth?.user?.role ?? ''] ?? []);

const footerNavItems: NavItem[] = [
];
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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter class="border-t border-[#e6e6e6] bg-white">
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
