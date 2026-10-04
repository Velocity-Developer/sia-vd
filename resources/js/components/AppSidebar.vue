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
    Award,
    BadgeCheck,
    BookCopy,
    BookMarked,
    BookOpen,
    BookOpenCheck,
    Briefcase,
    Building2,
    CalendarDays,
    CalendarRange,
    ChartColumn,
    ClipboardCheck,
    ClipboardList,
    ClipboardPen,
    Contact,
    Crown,
    Database,
    DoorOpen,
    FileCheck2,
    FilePen,
    FileSearch,
    FileSpreadsheet,
    FileStack,
    FileText,
    FileUp,
    Gauge,
    GraduationCap,
    HandHeart,
    History,
    IdCard,
    Inbox,
    Landmark,
    LayoutGrid,
    Library,
    ListChecks,
    ListTree,
    MapIcon,
    MapPin,
    Megaphone,
    NotebookPen,
    NotebookTabs,
    PauseCircle,
    Percent,
    Presentation,
    Printer,
    Receipt,
    Scale,
    ScrollText,
    Settings2,
    ShieldCheck,
    SlidersHorizontal,
    Table2,
    Tent,
    ToggleRight,
    UserCheck,
    UserMinus,
    UserPlus,
    UserRoundCheck,
    UserRoundCog,
    Users,
    Wallet,
    Wrench,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const { can } = usePermissions();
const fitur = useFitur();
const tampil = (item: MenuItem): boolean =>
    can(item.permission) &&
    (!item.izinSalahSatu || item.izinSalahSatu.some((izin) => can(izin))) &&
    (!item.fitur || fitur.aktif(item.fitur)) &&
    (!item.tanpaFitur || !fitur.aktif(item.tanpaFitur));

// URL dibentuk setelah menu difilter izin: daftar route yang dikirim ke browser hanya berisi
// route milik peran pengguna (lihat config/ziggy.php), jadi route('admin…') tidak boleh dipanggil
// untuk pengguna yang tidak punya izin itu.
// `fitur`: menu hanya tampil selama fitur per klien itu aktif (config/client.php), selain izinnya.
// `tanpaFitur`: kebalikannya, menu pengganti yang hanya tampil selama fitur itu mati.
// `izinSalahSatu`: menu tampil bila pengguna punya minimal satu izin di daftar ini.
type MenuItem = Omit<NavItem, 'href'> & {
    href?: string;
    routeName?: string;
    fitur?: NamaFitur;
    tanpaFitur?: NamaFitur;
    izinSalahSatu?: string[];
};
// `tetap`: seksi tetap tampil sebagai dropdown walau isinya tinggal satu menu.
type MenuSeksi = { title: string; icon?: NavItem['icon']; tetap?: boolean; items: (MenuItem | MenuSeksi)[] };
type MenuEntry = MenuItem | MenuSeksi;

const adalahSeksi = (entry: MenuEntry): entry is MenuSeksi => 'items' in entry && Array.isArray(entry.items);

const IZIN_KELOLA_USER = ['admin.users.karyawan', 'admin.users.dosen', 'admin.users.mahasiswa'];

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
                    { title: 'Impor Data Excel', href: '/admin/impor/mahasiswa', icon: FileSpreadsheet, permission: 'admin.users.mahasiswa' },
                ],
            },
            {
                title: 'Akademik',
                icon: CalendarRange,
                tetap: true,
                items: [
                    {
                        title: 'Konfigurasi',
                        icon: Settings2,
                        tetap: true,
                        items: [
                            {
                                title: 'Tahun Akademik',
                                routeName: 'admin.tahun-akademik.index',
                                icon: CalendarRange,
                                permission: 'admin.tahun-akademik',
                            },
                            { title: 'Kurikulum', routeName: 'admin.kurikulum.index', icon: BookCopy, permission: 'admin.kurikulum' },
                            { title: 'Mata Kuliah', routeName: 'admin.mata-kuliah.index', icon: Library, permission: 'admin.mata-kuliah' },
                            { title: 'Mata Kuliah Prasyarat', routeName: 'admin.prasyarat.index', icon: ListTree, permission: 'admin.prasyarat' },
                            { title: 'Ruang', routeName: 'admin.ruang.index', icon: DoorOpen, permission: 'admin.ruang' },
                            { title: 'Batas SKS per Semester', routeName: 'admin.batas-sks.index', icon: Gauge, permission: 'admin.batas-sks' },
                            { title: 'Bobot Nilai', routeName: 'admin.bobot-nilai.index', icon: Scale, permission: 'admin.bobot-nilai' },
                            { title: 'Predikat', routeName: 'admin.predikat.index', icon: Award, permission: 'admin.predikat' },
                            {
                                title: 'Syarat Ujian & Remedial',
                                routeName: 'admin.syarat-ujian.index',
                                icon: ListChecks,
                                permission: 'admin.syarat-ujian',
                            },
                        ],
                    },
                    {
                        title: 'Perkuliahan',
                        icon: BookOpenCheck,
                        tetap: true,
                        items: [
                            {
                                title: 'Set Penasehat Akademik',
                                routeName: 'admin.penasehat-akademik.index',
                                icon: UserRoundCog,
                                permission: 'admin.penasehat-akademik',
                            },
                            { title: 'Kelas Kuliah', routeName: 'admin.kelas-kuliah.index', icon: ClipboardList, permission: 'admin.kelas-kuliah' },
                            { title: 'Jadwal Kelas', routeName: 'admin.jadwal.index', icon: CalendarDays, permission: 'admin.jadwal' },
                            { title: 'Materi', routeName: 'admin.materi.index', icon: BookMarked, permission: 'admin.materi', fitur: 'materi' },
                            { title: 'Tugas', routeName: 'admin.tugas.index', icon: ClipboardList, permission: 'admin.tugas', fitur: 'tugas' },
                            { title: 'Quiz', routeName: 'admin.quiz.index', icon: FileText, permission: 'admin.quiz', fitur: 'quiz' },
                            { title: 'Presensi Mahasiswa', routeName: 'admin.presensi.index', icon: UserCheck, permission: 'admin.presensi' },
                            {
                                title: 'Rekap Presensi Mahasiswa',
                                routeName: 'admin.rekap-presensi.index',
                                icon: ChartColumn,
                                permission: 'admin.rekap-presensi',
                            },
                            {
                                title: 'Presensi Dosen',
                                routeName: 'admin.presensi-dosen.index',
                                icon: UserRoundCheck,
                                permission: 'admin.presensi-dosen',
                            },
                            {
                                title: 'Verifikasi Presensi Dosen',
                                routeName: 'admin.verifikasi-presensi-dosen.index',
                                icon: BadgeCheck,
                                permission: 'admin.verifikasi-presensi-dosen',
                            },
                            { title: 'Jadwal Ujian', routeName: 'admin.ujian.index', icon: NotebookPen, permission: 'admin.ujian' },
                            { title: 'Ketua Kelas', routeName: 'admin.ketua-kelas.index', icon: Crown, permission: 'admin.ketua-kelas' },
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
                        title: 'KRS',
                        icon: ClipboardList,
                        tetap: true,
                        items: [
                            { title: 'Input KRS', routeName: 'admin.input-krs.index', icon: FilePen, permission: 'admin.input-krs' },
                            {
                                title: 'Verifikasi KRS',
                                routeName: 'admin.verifikasi-krs.index',
                                icon: ClipboardCheck,
                                permission: 'admin.verifikasi-krs',
                            },
                            { title: 'Status KRS', routeName: 'admin.status-krs.index', icon: ToggleRight, permission: 'admin.status-krs' },
                            { title: 'Cetak KST', routeName: 'admin.cetak-kst.index', icon: Printer, permission: 'admin.cetak-kst' },
                            { title: 'Cetak Kartu Ujian', routeName: 'admin.kartu-ujian.index', icon: IdCard, permission: 'admin.kartu-ujian' },
                            { title: 'Rekap KRS', routeName: 'admin.rekap-krs.index', icon: ChartColumn, permission: 'admin.rekap-krs' },
                        ],
                    },
                    {
                        title: 'Penilaian',
                        icon: NotebookTabs,
                        tetap: true,
                        items: [
                            {
                                title: 'Nilai Semester',
                                routeName: 'admin.nilai-semester.index',
                                icon: NotebookPen,
                                permission: 'admin.nilai-semester',
                            },
                            { title: 'Detail Nilai', routeName: 'admin.detail-nilai.index', icon: FileSearch, permission: 'admin.detail-nilai' },
                            // Urutan mengikuti alur: input → rincian komponen → konversi huruf → (KKM) → validasi.
                            {
                                title: 'Pendataan Nilai Akhir',
                                routeName: 'admin.pendataan-nilai.index',
                                icon: ClipboardPen,
                                permission: 'admin.pendataan-nilai',
                            },
                            { title: 'Nilai KKM', routeName: 'admin.nilai-kkm.index', icon: Presentation, permission: 'admin.nilai-kkm' },
                            {
                                title: 'Validasi Nilai',
                                routeName: 'admin.validasi-nilai.index',
                                icon: FileCheck2,
                                permission: 'admin.validasi-nilai',
                            },
                            {
                                title: 'Tambah Komponen Nilai',
                                routeName: 'admin.komponen-nilai.index',
                                icon: Percent,
                                permission: 'admin.komponen-nilai',
                            },
                        ],
                    },
                    {
                        title: 'Hasil Studi',
                        icon: BookOpenCheck,
                        tetap: true,
                        items: [
                            { title: 'KHS', routeName: 'admin.khs.index', icon: FileText, permission: 'admin.khs' },
                            {
                                title: 'Transkrip Nilai',
                                routeName: 'admin.transkrip-nilai.index',
                                icon: ScrollText,
                                permission: 'admin.transkrip-nilai',
                            },
                        ],
                    },
                ],
            },
            {
                title: 'Pengajuan & Pendaftaran',
                icon: FileStack,
                tetap: true,
                items: [
                    {
                        title: 'Status Mahasiswa',
                        icon: UserRoundCog,
                        tetap: true,
                        items: [
                            {
                                title: 'Pengajuan Cuti',
                                routeName: 'admin.pengajuan-cuti.index',
                                icon: PauseCircle,
                                permission: 'admin.pengajuan-cuti',
                            },
                            { title: 'Mahasiswa Cuti', routeName: 'admin.mahasiswa-cuti.index', icon: UserMinus, permission: 'admin.pengajuan-cuti' },
                        ],
                    },
                    {
                        title: 'Tugas Akhir/Skripsi',
                        icon: BookOpenCheck,
                        tetap: true,
                        items: [
                            {
                                title: 'Persetujuan Tugas Akhir',
                                routeName: 'admin.persetujuan-ta.index',
                                icon: FileCheck2,
                                permission: 'admin.pengajuan-akademik',
                            },
                            {
                                title: 'Pendaftaran Pendadaran',
                                routeName: 'admin.pendaftaran-pendadaran.index',
                                icon: Presentation,
                                permission: 'admin.pengajuan-akademik',
                                fitur: 'pendadaran',
                            },
                            {
                                title: 'Pendaftaran Sidang',
                                routeName: 'admin.pendaftaran-sidang.index',
                                icon: Presentation,
                                permission: 'admin.pengajuan-akademik',
                                tanpaFitur: 'pendadaran',
                            },
                        ],
                    },
                    {
                        title: 'Kuliah Kerja Mahasiswa',
                        icon: Tent,
                        tetap: true,
                        items: [
                            {
                                title: 'Persetujuan KKM/PKL/KKN',
                                routeName: 'admin.persetujuan-kkm.index',
                                icon: ClipboardCheck,
                                permission: 'admin.pengajuan-akademik',
                            },
                        ],
                    },
                    {
                        title: 'Praktek Pengalaman Lapangan',
                        icon: Briefcase,
                        tetap: true,
                        items: [
                            { title: 'Daftar PPL', routeName: 'admin.daftar-ppl.index', icon: ClipboardList, permission: 'admin.pengajuan-akademik' },
                        ],
                    },
                    {
                        title: 'Ujian Komprehensif',
                        icon: NotebookPen,
                        tetap: true,
                        items: [
                            {
                                title: 'Daftar Ujian Komprehensif',
                                routeName: 'admin.pengajuan-kompre.index',
                                icon: ClipboardList,
                                permission: 'admin.pengajuan-akademik',
                            },
                            {
                                title: 'Gelombang Ujian Komprehensif',
                                routeName: 'admin.gelombang-kompre.index',
                                icon: CalendarRange,
                                permission: 'admin.pengajuan-akademik',
                            },
                        ],
                    },
                    {
                        title: 'Wisuda',
                        icon: GraduationCap,
                        tetap: true,
                        items: [
                            { title: 'Daftar Wisuda', routeName: 'admin.daftar-wisuda.index', icon: Users, permission: 'admin.pengajuan-akademik' },
                            {
                                title: 'Periode Wisuda',
                                routeName: 'admin.periode-wisuda.index',
                                icon: CalendarRange,
                                permission: 'admin.pengajuan-akademik',
                            },
                        ],
                    },
                ],
            },
            {
                title: 'Administrasi',
                icon: Inbox,
                items: [
                    {
                        title: 'Informasi & Pengumuman',
                        routeName: 'admin.info-kuliah.index',
                        icon: Megaphone,
                        permission: 'admin.info-kuliah',
                    },
                    {
                        title: 'Pindah Kelas',
                        routeName: 'admin.pindah-kelas.index',
                        icon: ArrowLeftRight,
                        permission: 'admin.pindah-kelas',
                        fitur: 'pindah_kelas',
                    },
                ],
            },
            {
                title: 'Mahasiswa Baru',
                icon: UserPlus,
                tetap: true,
                items: [
                    { title: 'Calon Maba', routeName: 'admin.pendaftar-pmb.index', icon: ClipboardList, permission: 'admin.pendaftar-pmb' },
                    {
                        title: 'Konfigurasi',
                        icon: Settings2,
                        tetap: true,
                        items: [
                            { title: 'Atur Periode PMB', routeName: 'admin.periode-pmb.index', icon: CalendarRange, permission: 'admin.periode-pmb' },
                            { title: 'Informasi PMB', routeName: 'admin.informasi-pmb.edit', icon: Megaphone, permission: 'admin.informasi-pmb' },
                        ],
                    },
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
                // Semua akun (Prodi, Dosen, Mahasiswa, Karyawan) dibuat & dicari dari sini; Data Dosen/Mahasiswa di Master tetap ada.
                title: 'Tools',
                icon: Wrench,
                tetap: true,
                items: [
                    { title: 'Create User', routeName: 'admin.pengguna.buat', icon: UserPlus, izinSalahSatu: IZIN_KELOLA_USER },
                    { title: 'Data Pengguna', routeName: 'admin.pengguna.index', icon: Contact, izinSalahSatu: IZIN_KELOLA_USER },
                    { title: 'Kelola Role', routeName: 'admin.roles.index', icon: ShieldCheck, permission: 'admin.roles', fitur: 'kelola_role' },
                    { title: 'Log Aktivitas', routeName: 'admin.log-aktivitas.index', icon: History, permission: 'admin.log-aktivitas' },
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
                    { title: 'Input Nilai', routeName: 'dosen.input-nilai.index', icon: ClipboardPen, permission: 'dosen.kelas-kuliah' },
                    { title: 'Jadwal Mengajar', routeName: 'dosen.jadwal.index', icon: CalendarDays, permission: 'dosen.jadwal' },
                    { title: 'Presensi', routeName: 'dosen.presensi.index', icon: UserCheck, permission: 'dosen.presensi' },
                    { title: 'Ujian', routeName: 'dosen.ujian.index', icon: NotebookPen, permission: 'dosen.ujian' },
                    { title: 'Mahasiswa Kelas', href: '/dosen/mahasiswa-kelas', icon: Users, permission: 'dosen.mahasiswa-kelas' },
                    {
                        title: 'Bimbingan TA',
                        routeName: 'dosen.bimbingan.index',
                        icon: GraduationCap,
                        permission: 'dosen.bimbingan',
                        fitur: 'pendadaran',
                    },
                ],
            },
            {
                title: 'Pengajuan & Pendaftaran',
                icon: Inbox,
                tetap: true,
                items: [
                    {
                        title: 'Pengajuan Mahasiswa PA',
                        routeName: 'dosen.pengajuan-pa.index',
                        icon: Inbox,
                        permission: 'dosen.pengajuan-pa',
                    },
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
                    { title: 'Cetak KST', routeName: 'mahasiswa.cetak-kst', icon: Printer, permission: 'mahasiswa.krs' },
                    { title: 'Kartu Hasil Studi', href: '/mahasiswa/khs', icon: FileText, permission: 'mahasiswa.hasil-studi' },
                    { title: 'Transkrip Nilai', href: '/mahasiswa/transkrip', icon: BookOpen, permission: 'mahasiswa.hasil-studi' },
                    { title: 'Jadwal Kuliah', href: '/mahasiswa/jadwal', icon: CalendarDays, permission: 'mahasiswa.jadwal-kuliah' },
                    { title: 'Presensi', routeName: 'mahasiswa.presensi', icon: UserCheck, permission: 'mahasiswa.presensi' },
                    { title: 'Jadwal Ujian', routeName: 'mahasiswa.ujian', icon: NotebookPen, permission: 'mahasiswa.ujian' },
                    { title: 'Cetak Kartu UTS & UAS', routeName: 'mahasiswa.cetak-kartu-ujian', icon: IdCard, permission: 'mahasiswa.ujian' },
                ],
            },
            {
                title: 'Pengajuan & Pendaftaran',
                icon: FileStack,
                tetap: true,
                items: [
                    {
                        title: 'Status Mahasiswa',
                        icon: UserRoundCog,
                        tetap: true,
                        items: [
                            {
                                title: 'Pengajuan Cuti',
                                routeName: 'mahasiswa.pengajuan-cuti',
                                icon: PauseCircle,
                                permission: 'mahasiswa.pengajuan-cuti',
                            },
                        ],
                    },
                    {
                        title: 'Tugas Akhir/Skripsi',
                        icon: BookOpenCheck,
                        tetap: true,
                        items: [
                            {
                                title: 'Pengajuan Judul & Upload TA',
                                routeName: 'mahasiswa.tugas-akhir',
                                icon: FileUp,
                                permission: 'mahasiswa.tugas-akhir',
                            },
                            {
                                title: 'Pendaftaran Sidang',
                                routeName: 'mahasiswa.pendaftaran-sidang',
                                icon: Presentation,
                                permission: 'mahasiswa.pengajuan-kegiatan',
                                tanpaFitur: 'pendadaran',
                            },
                        ],
                    },
                    {
                        title: 'Kuliah Kerja Mahasiswa',
                        icon: Tent,
                        tetap: true,
                        items: [
                            {
                                title: 'Pengajuan Judul KKM/PKL/KKN',
                                routeName: 'mahasiswa.pengajuan-kkm',
                                icon: ClipboardPen,
                                permission: 'mahasiswa.pengajuan-kegiatan',
                            },
                        ],
                    },
                    {
                        title: 'Praktek Pengalaman Lapangan',
                        icon: Briefcase,
                        tetap: true,
                        items: [
                            {
                                title: 'Pengajuan PPL',
                                routeName: 'mahasiswa.pengajuan-ppl',
                                icon: ClipboardPen,
                                permission: 'mahasiswa.pengajuan-kegiatan',
                            },
                        ],
                    },
                    {
                        title: 'Ujian Komprehensif',
                        icon: NotebookPen,
                        tetap: true,
                        items: [
                            {
                                title: 'Pengajuan Ujian Komprehensif',
                                routeName: 'mahasiswa.pengajuan-kompre',
                                icon: ClipboardPen,
                                permission: 'mahasiswa.pengajuan-kegiatan',
                            },
                        ],
                    },
                    {
                        title: 'Wisuda',
                        icon: GraduationCap,
                        tetap: true,
                        items: [
                            { title: 'Pengajuan Wisuda', routeName: 'mahasiswa.wisuda', icon: GraduationCap, permission: 'mahasiswa.tugas-akhir' },
                        ],
                    },
                ],
            },
            {
                title: 'Administrasi',
                icon: Inbox,
                items: [
                    {
                        title: 'Informasi & Pengumuman',
                        routeName: 'mahasiswa.info-kuliah',
                        icon: Megaphone,
                        permission: 'mahasiswa.info-kuliah',
                    },
                    {
                        title: 'Pindah Kelas',
                        routeName: 'mahasiswa.pindah-kelas',
                        icon: ArrowLeftRight,
                        permission: 'mahasiswa.pindah-kelas',
                        fitur: 'pindah_kelas',
                    },
                    { title: 'Biaya Kuliah', routeName: 'mahasiswa.info-biaya-kuliah', icon: Wallet, permission: 'mahasiswa.info-biaya' },
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
        if (items.length === 1 && !entry.tetap) return [items[0]];

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
