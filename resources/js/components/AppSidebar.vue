<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useFitur } from '@/composables/useFitur';
import { usePermissions } from '@/composables/usePermissions';
import { type NamaFitur, type NavEntry, type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    BookMarked,
    BookOpen,
    BookOpenCheck,
    Briefcase,
    Building2,
    CalendarDays,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    Database,
    DoorOpen,
    FileText,
    GraduationCap,
    HandHeart,
    Inbox,
    Landmark,
    LayoutGrid,
    Library,
    MapIcon,
    MapPin,
    NotebookPen,
    PauseCircle,
    Receipt,
    Settings2,
    ShieldCheck,
    SlidersHorizontal,
    Table2,
    UserCheck,
    Users,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can } = usePermissions();
const fitur = useFitur();
const tampil = (item: MenuItem): boolean => can(item.permission) && (!item.fitur || fitur.aktif(item.fitur));

// URL dibentuk setelah menu difilter izin: daftar route yang dikirim ke browser hanya berisi
// route milik peran pengguna (lihat config/ziggy.php), jadi route('admin…') tidak boleh dipanggil
// untuk pengguna yang tidak punya izin itu.
// `fitur`: menu hanya tampil selama fitur per klien itu aktif (config/client.php), selain izinnya.
type MenuItem = Omit<NavItem, 'href'> & { href?: string; routeName?: string; fitur?: NamaFitur };
type MenuSeksi = { title: string; icon?: NavItem['icon']; items: (MenuItem | MenuSeksi)[] };
type MenuEntry = MenuItem | MenuSeksi;

const adalahSeksi = (entry: MenuEntry): entry is MenuSeksi => 'items' in entry && Array.isArray(entry.items);

// Menu dikelompokkan per bidang kerja; menu tunggal (beranda) tetap di luar seksi.
// Setiap menu hanya tampil jika role user memiliki permission terkait (diatur di halaman Kelola Role).
const navigationSections: { label: string; entries: MenuEntry[] }[] = [
    {
        label: 'Admin',
        entries: [
            { title: 'Dashboard', href: '/admin', icon: LayoutGrid, permission: 'admin.dashboard' },
            {
                title: 'Master',
                icon: Database,
                items: [
                    {
                        title: 'Master Tabel',
                        icon: Table2,
                        items: [
                            { title: 'Badan Hukum', routeName: 'admin.badan-hukum.edit', icon: Landmark, permission: 'admin.badan-hukum' },
                            { title: 'Perguruan Tinggi', routeName: 'admin.perguruan-tinggi.edit', icon: Building2, permission: 'admin.institusi' },
                            { title: 'Fakultas', routeName: 'admin.fakultas.index', icon: GraduationCap, permission: 'admin.fakultas' },
                            { title: 'Program Studi', routeName: 'admin.program-studi.index', icon: BookOpen, permission: 'admin.program-studi' },
                            { title: 'Agama', routeName: 'admin.agama.index', icon: HandHeart, permission: 'admin.agama' },
                            { title: 'Provinsi', routeName: 'admin.provinsi.index', icon: MapIcon, permission: 'admin.provinsi' },
                            { title: 'Kota/Kabupaten', routeName: 'admin.kota.index', icon: MapPin, permission: 'admin.kota' },
                        ],
                    },
                    { title: 'Data Dosen', href: '/admin/users/dosen', icon: GraduationCap, permission: 'admin.users.dosen' },
                    { title: 'Data Mahasiswa', href: '/admin/users/mahasiswa', icon: Users, permission: 'admin.users.mahasiswa' },
                ],
            },
            {
                title: 'Akademik',
                icon: CalendarRange,
                items: [
                    { title: 'Tahun Akademik', routeName: 'admin.tahun-akademik.index', icon: CalendarRange, permission: 'admin.tahun-akademik' },
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
                    { title: 'Materi', routeName: 'admin.materi.index', icon: BookMarked, permission: 'admin.materi', fitur: 'materi' },
                    { title: 'Tugas', routeName: 'admin.tugas.index', icon: ClipboardList, permission: 'admin.tugas', fitur: 'tugas' },
                    { title: 'Quiz', routeName: 'admin.quiz.index', icon: FileText, permission: 'admin.quiz', fitur: 'quiz' },
                    { title: 'Presensi', routeName: 'admin.presensi.index', icon: UserCheck, permission: 'admin.presensi' },
                    { title: 'Jadwal Ujian', routeName: 'admin.ujian.index', icon: NotebookPen, permission: 'admin.ujian' },
                    {
                        title: 'Ujian Susulan',
                        routeName: 'admin.ujian-susulan.index',
                        icon: NotebookPen,
                        permission: 'admin.ujian',
                        fitur: 'ujian_susulan',
                    },
                ],
            },
            {
                title: 'Administrasi',
                icon: Inbox,
                items: [
                    { title: 'Info Kuliah', routeName: 'admin.info-kuliah.index', icon: FileText, permission: 'admin.info-kuliah' },
                    { title: 'Verifikasi KRS', routeName: 'admin.verifikasi-krs.index', icon: ClipboardCheck, permission: 'admin.verifikasi-krs' },
                    {
                        title: 'Pindah Kelas',
                        routeName: 'admin.pindah-kelas.index',
                        icon: ArrowLeftRight,
                        permission: 'admin.pindah-kelas',
                        fitur: 'pindah_kelas',
                    },
                    {
                        title: 'TA & Wisuda',
                        routeName: 'admin.pengajuan-akademik.index',
                        icon: GraduationCap,
                        permission: 'admin.pengajuan-akademik',
                    },
                    { title: 'Periode Wisuda', routeName: 'admin.periode-wisuda.index', icon: CalendarRange, permission: 'admin.pengajuan-akademik' },
                    { title: 'Pengajuan Cuti', routeName: 'admin.pengajuan-cuti.index', icon: PauseCircle, permission: 'admin.pengajuan-cuti' },
                ],
            },
            {
                title: 'Keuangan',
                icon: Wallet,
                items: [
                    { title: 'Jenis Biaya', routeName: 'admin.jenis-biaya.index', icon: Receipt, permission: 'admin.jenis-biaya' },
                    { title: 'Tagihan Mahasiswa', routeName: 'admin.tagihan.index', icon: Wallet, permission: 'admin.tagihan' },
                    { title: 'Tagihan Remidi', routeName: 'admin.tagihan-remidi.index', icon: Receipt, permission: 'admin.tagihan' },
                    {
                        title: 'Tagihan Susulan',
                        routeName: 'admin.tagihan-susulan.index',
                        icon: Receipt,
                        permission: 'admin.tagihan',
                        fitur: 'ujian_susulan',
                    },
                ],
            },
            {
                title: 'Pengguna & Akses',
                icon: Users,
                items: [
                    { title: 'Karyawan', href: '/admin/users/karyawan', icon: Briefcase, permission: 'admin.users.karyawan' },
                    { title: 'Kelola Role', routeName: 'admin.roles.index', icon: ShieldCheck, permission: 'admin.roles', fitur: 'kelola_role' },
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
                    { title: 'Bimbingan TA', routeName: 'dosen.bimbingan.index', icon: GraduationCap, permission: 'dosen.bimbingan' },
                ],
            },
            {
                title: 'Konten Kelas',
                icon: BookMarked,
                items: [
                    { title: 'Materi', routeName: 'dosen.materi.index', icon: BookMarked, permission: 'dosen.materi', fitur: 'materi' },
                    { title: 'Tugas', routeName: 'dosen.tugas.index', icon: ClipboardList, permission: 'dosen.tugas', fitur: 'tugas' },
                    { title: 'Quiz', routeName: 'dosen.quiz.index', icon: FileText, permission: 'dosen.quiz', fitur: 'quiz' },
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
                    { title: 'Tugas Akhir & Wisuda', routeName: 'mahasiswa.tugas-akhir', icon: GraduationCap, permission: 'mahasiswa.tugas-akhir' },
                ],
            },
            {
                title: 'Administrasi',
                icon: Inbox,
                items: [
                    { title: 'Info Kuliah', routeName: 'mahasiswa.info-kuliah', icon: FileText, permission: 'mahasiswa.info-kuliah' },
                    {
                        title: 'Pindah Kelas',
                        routeName: 'mahasiswa.pindah-kelas',
                        icon: ArrowLeftRight,
                        permission: 'mahasiswa.pindah-kelas',
                        fitur: 'pindah_kelas',
                    },
                    { title: 'Biaya Kuliah', routeName: 'mahasiswa.info-biaya-kuliah', icon: Wallet, permission: 'mahasiswa.info-biaya' },
                    { title: 'Pengajuan Cuti', routeName: 'mahasiswa.pengajuan-cuti', icon: PauseCircle, permission: 'mahasiswa.pengajuan-cuti' },
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
// Seksi boleh berisi seksi lagi (mis. Master → Master Tabel); aturan yang sama berlaku di tiap tingkat.
const filterEntries = (entries: MenuEntry[]): NavEntry[] =>
    entries.flatMap((entry): NavEntry[] => {
        if (!adalahSeksi(entry)) return tampil(entry) ? bentukItem(entry) : [];

        const items = filterEntries(entry.items);

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
    [
        'admin.pengaturan-email',
        'admin.pengaturan-akademik',
        'admin.pengaturan-tampilan',
        'admin.pengaturan-recaptcha',
        'admin.pengaturan-maintenance',
    ].some((izin) => can(izin)),
);
const pengaturanSistemAktif = computed(() => page.url.startsWith('/pengaturan-sistem'));

// Panel developer (/dev) di luar sistem izin: hanya role developer, dan hanya bila DEV_PANEL=true.
const developer = computed(() => (page.props.auth as { developer?: boolean } | undefined)?.developer ?? false);
const menuDeveloper = [
    { title: 'Fitur Klien', routeName: 'dev.fitur.index', awalan: '/dev/fitur', icon: SlidersHorizontal },
    { title: 'Kelola Role', routeName: 'dev.roles.index', awalan: '/dev/roles', icon: ShieldCheck },
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
            <NavMain
                v-for="section in visibleSections"
                :key="section.label"
                :entries="section.entries"
                :label="visibleSections.length > 1 ? section.label : 'Platform'"
            />
        </SidebarContent>

        <SidebarFooter class="border-t border-[#e6e6e6] bg-white">
            <SidebarMenu v-if="developer">
                <SidebarMenuItem v-for="menu in menuDeveloper" :key="menu.awalan">
                    <SidebarMenuButton as-child :is-active="page.url.startsWith(menu.awalan)" :tooltip="menu.title">
                        <Link :href="route(menu.routeName)">
                            <component
                                :is="menu.icon"
                                class="text-muted-foreground transition-colors group-data-[active=true]/menu-button:text-sidebar-primary"
                            />
                            <span>{{ menu.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
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
