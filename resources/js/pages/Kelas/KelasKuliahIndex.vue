<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ClipboardPen, Eye, FileSpreadsheet, Pencil, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const page = usePage<{ flash: { success?: string; error?: string } }>();

type Jadwal = {
    id: number;
    hari: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang?: { kode_ruang?: string; nama_ruang?: string } | null;
};
type KelasKuliah = {
    id: number;
    kode_kelas: string;
    kapasitas: number;
    dosen?: { nidn?: string; user?: { name?: string } | null } | null;
    mata_kuliah?: { kode_matkul?: string; nama_matkul?: string; prodi?: { nama_prodi?: string } | null } | null;
    mataKuliah?: { kode_matkul?: string; nama_matkul?: string; prodi?: { nama_prodi?: string } | null } | null;
    jadwals?: Jadwal[];
    tahun_akademik?: { tahun?: string; semester?: string } | null;
    tahunAkademik?: { tahun?: string; semester?: string } | null;
};
type Pagination = { data: KelasKuliah[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };

const props = defineProps<{
    kelasKuliahs: Pagination;
    search?: string;
    tahunAkademiks: { id: number; name: string }[];
    tahunAkademikId: number | null;
    mataKuliahId: number | null;
    mataKuliahOptions: { id: number; name: string }[];
    dosenId?: number | null;
    dosenOptions?: { id: number; name: string }[];
    peran: Peran;
    // Menu dosen Input Nilai: daftar yang sama, tombol detail langsung ke bagian Nilai Mahasiswa.
    modeNilai?: boolean;
}>();
const rute = rutePeran(props.peran);
const judul = computed(() => (props.modeNilai ? 'Input Nilai' : 'Kelas Kuliah'));
const ruteDaftar = computed(() => (props.modeNilai ? route('dosen.input-nilai.index') : rute('kelas-kuliah.index')));
// Filter dosen, tombol tambah/edit/hapus, dan kolom dosen hanya untuk admin.
const isAdmin = computed(() => props.peran === 'admin');

const search = ref(props.search ?? '');
const tahunAkademikId = ref<number | string>(props.tahunAkademikId ?? 'all');
const mataKuliahId = ref<number | string>(props.mataKuliahId ?? 'all');
const dosenId = ref<number | string>(props.dosenId ?? 'all');

const applyFilters = () =>
    router.get(
        ruteDaftar.value,
        { search: search.value, tahun_akademik_id: tahunAkademikId.value, mata_kuliah_id: mataKuliahId.value, dosen_id: dosenId.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );

watch(search, applyFilters);

const onTahunAkademikChange = () => {
    mataKuliahId.value = 'all';
    dosenId.value = 'all';
    applyFilters();
};

const confirmOpen = ref(false);
const pendingItem = ref<KelasKuliah | null>(null);

const remove = (item: KelasKuliah) => {
    pendingItem.value = item;
    confirmOpen.value = true;
};

const confirmDelete = () => {
    if (!pendingItem.value) return;
    router.delete(rute('kelas-kuliah.destroy', pendingItem.value.id), {
        onFinish: () => {
            confirmOpen.value = false;
            pendingItem.value = null;
        },
    });
};

const dosenName = (item: KelasKuliah) => item.dosen?.user?.name ?? '-';
const dosenNidn = (item: KelasKuliah) => item.dosen?.nidn ?? '';
const matkul = (item: KelasKuliah) => (item as any).mataKuliah ?? (item as any).mata_kuliah ?? null;

const tahunAkademik = (item: KelasKuliah) => item.tahunAkademik ?? item.tahun_akademik ?? null;

const jam = (time: string) => time.slice(0, 5);
const jadwalText = (item: KelasKuliah) => {
    if (!item.jadwals?.length) return '-';
    return item.jadwals.map((j) => `${j.hari} ${jam(j.jam_mulai)}–${jam(j.jam_akhir)}`).join(', ');
};
const ruangText = (item: KelasKuliah) => {
    if (!item.jadwals?.length) return '';
    const codes = [...new Set(item.jadwals.map((j) => j.ruang?.kode_ruang).filter(Boolean))];
    return codes.join(', ');
};
</script>

<template>
    <Head :title="judul" />
    <AppLayout :breadcrumbs="[{ title: judul, href: ruteDaftar }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ judul }}</h1>
                        <p v-if="props.modeNilai" class="deskripsi-halaman">
                            Pilih kelas yang Anda ampu untuk mengisi nilai komponen mahasiswa, lalu kirim ke validasi.
                        </p>
                        <p v-else class="deskripsi-halaman">Kelola kelas kuliah, tahun ajaran, dosen pengampu, dan mata kuliah.</p>
                    </div>
                    <div v-if="isAdmin" class="flex flex-wrap gap-2">
                        <Button as-child variant="outline">
                            <Link :href="route('admin.impor.index', 'kelas-kuliah')"><FileSpreadsheet /> Impor Excel</Link>
                        </Button>
                        <Button as-child>
                            <Link :href="rute('kelas-kuliah.create')">Tambah Kelas Kuliah</Link>
                        </Button>
                    </div>
                </div>

                <!-- Susunan sama dengan FilterKonten pada menu Jadwal/Materi/Tugas/Quiz. -->
                <div class="bilah-filter">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari kode kelas, tahun ajaran, atau mata kuliah" aria-label="Cari" class="pl-9" />
                    </div>
                    <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="onTahunAkademikChange">
                        <option value="all">Semua Tahun Akademik</option>
                        <option v-for="ta in props.tahunAkademiks" :key="ta.id" :value="ta.id">{{ ta.name }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="mataKuliahId" label="Filter mata kuliah" @change="applyFilters">
                        <option value="all">Semua Mata Kuliah</option>
                        <option v-for="mataKuliah in props.mataKuliahOptions" :key="mataKuliah.id" :value="mataKuliah.id">
                            {{ mataKuliah.name }}
                        </option>
                    </SelectFilter>
                    <SelectFilter v-if="isAdmin" v-model="dosenId" label="Filter dosen" @change="applyFilters">
                        <option value="all">Semua Dosen</option>
                        <option v-for="dosen in props.dosenOptions ?? []" :key="dosen.id" :value="dosen.id">{{ dosen.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.kelasKuliahs.total }}</span> data<span v-if="props.search">
                            · hasil untuk "{{ props.search }}"</span
                        >
                    </p>
                </div>

                <div v-if="page.props.flash?.success" class="alert-sukses" role="alert">{{ page.props.flash.success }}</div>
                <div v-if="page.props.flash?.error" class="alert-gagal" role="alert">{{ page.props.flash.error }}</div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[1040px]">
                            <thead>
                                <tr>
                                    <th class="kolom-no">No</th>
                                    <th>Kode Kelas</th>
                                    <th>Tahun Ajaran</th>
                                    <th class="text-center">Kapasitas</th>
                                    <th v-if="isAdmin">Dosen</th>
                                    <th>Mata Kuliah</th>
                                    <th>Jadwal</th>
                                    <th class="kolom-aksi">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in props.kelasKuliahs.data" :key="item.id">
                                    <td class="kolom-no">{{ (props.kelasKuliahs.from ?? 1) + index }}</td>
                                    <td class="font-medium text-black">{{ item.kode_kelas }}</td>
                                    <td>
                                        {{ tahunAkademik(item) ? `${tahunAkademik(item)?.tahun} ${tahunAkademik(item)?.semester}` : '-' }}
                                    </td>
                                    <td class="text-center tabular-nums">{{ item.kapasitas }}</td>
                                    <td v-if="isAdmin">
                                        <span class="block">{{ dosenName(item) }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ dosenNidn(item) }}</span>
                                    </td>
                                    <td>
                                        <span class="block">{{ matkul(item)?.kode_matkul ?? '-' }} — {{ matkul(item)?.nama_matkul ?? '' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ matkul(item)?.prodi?.nama_prodi ?? '' }}</span>
                                    </td>
                                    <td>
                                        <span class="block">{{ jadwalText(item) }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ ruangText(item) }}</span>
                                    </td>
                                    <td class="kolom-aksi">
                                        <div class="aksi-tabel">
                                            <Button v-if="props.modeNilai" as-child size="sm">
                                                <Link :href="rute('kelas-kuliah.show', { kelasKuliah: item.id, bagian: 'nilai' })"
                                                    ><ClipboardPen /> Isi Nilai</Link
                                                >
                                            </Button>
                                            <Button v-else as-child variant="outline" size="icon-sm" class="text-[#0075de]">
                                                <Link :href="rute('kelas-kuliah.show', item.id)" title="Detail" aria-label="Detail"><Eye /></Link>
                                            </Button>
                                            <Button v-if="isAdmin" as-child variant="outline" size="icon-sm" class="text-[#2a9d99]">
                                                <Link :href="rute('kelas-kuliah.edit', item.id)" title="Edit" aria-label="Edit"><Pencil /></Link>
                                            </Button>
                                            <Button
                                                v-if="isAdmin"
                                                variant="outline"
                                                size="icon-sm"
                                                class="text-[#dd5b00]"
                                                title="Hapus"
                                                aria-label="Hapus"
                                                @click="remove(item)"
                                                ><Trash2
                                            /></Button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.kelasKuliahs.data.length" class="baris-kosong">
                                    <td :colspan="isAdmin ? 8 : 7" class="tabel-kosong">
                                        Belum ada data kelas kuliah. Tambahkan kelas baru untuk memulai.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <AlertModal
                    :open="confirmOpen"
                    description="Anda yakin ingin menghapus data ini?"
                    confirm-text="Ya"
                    cancel-text="Batal"
                    @update:open="confirmOpen = $event"
                    @confirm="confirmDelete"
                    @cancel="confirmOpen = false"
                />

                <Pagination :links="props.kelasKuliahs.links" :total="props.kelasKuliahs.total" />
            </div>
        </div>
    </AppLayout>
</template>
