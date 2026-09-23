<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Eye, Pencil, Search, Trash2 } from 'lucide-vue-next';
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
}>();
const rute = rutePeran(props.peran);
// Filter dosen, tombol tambah/edit/hapus, dan kolom dosen hanya untuk admin.
const isAdmin = computed(() => props.peran === 'admin');

const search = ref(props.search ?? '');
const tahunAkademikId = ref<number | string>(props.tahunAkademikId ?? 'all');
const mataKuliahId = ref<number | string>(props.mataKuliahId ?? 'all');
const dosenId = ref<number | string>(props.dosenId ?? 'all');

const applyFilters = () =>
    router.get(
        rute('kelas-kuliah.index'),
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
    <Head title="Kelas Kuliah" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: rute('kelas-kuliah.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">Kelas Kuliah</h1>
                        <p class="text-sm leading-5 text-[#615d59]">Kelola kelas kuliah, tahun ajaran, dosen pengampu, dan mata kuliah.</p>
                    </div>
                    <Link v-if="isAdmin" :href="rute('kelas-kuliah.create')">
                        <Button class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]">Tambah Kelas Kuliah</Button>
                    </Link>
                </div>

                <!-- Susunan sama dengan FilterKonten pada menu Jadwal/Materi/Tugas/Quiz. -->
                <div class="flex flex-col gap-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="relative w-full sm:w-80">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                            <Input
                                v-model="search"
                                placeholder="Cari kode kelas, tahun ajaran, atau mata kuliah"
                                class="h-10 rounded-lg border-[#d8d5d2] bg-white pl-9 text-sm shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15"
                            />
                        </div>

                        <p class="text-sm text-[#615d59]">
                            <span class="font-medium text-black">{{ props.kelasKuliahs.total }}</span> data<span v-if="props.search">
                                · hasil untuk "{{ props.search }}"</span
                            >
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:flex lg:flex-wrap lg:items-center">
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
                    </div>
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02),0_2.025px_7.847px_rgba(0,0,0,0.027)]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <div
                    class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                >
                    <div class="overflow-x-auto">
                        <table class="tabel-responsif w-full text-left">
                            <thead>
                                <tr class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kode Kelas</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Tahun Ajaran</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kapasitas</th>
                                    <th v-if="isAdmin" class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Dosen</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mata Kuliah</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Jadwal</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.kelasKuliahs.data" :key="item.id" class="transition-colors hover:bg-[#f6f5f4]/60">
                                    <td data-label="No." class="px-4 py-3 text-[15px] leading-5 text-[#615d59]">
                                        {{ (props.kelasKuliahs.from ?? 0) + index }}
                                    </td>
                                    <td data-label="Kode Kelas" class="px-4 py-3 text-[15px] font-medium leading-5 text-black">
                                        {{ item.kode_kelas }}
                                    </td>
                                    <td data-label="Tahun Ajaran" class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        {{ tahunAkademik(item) ? `${tahunAkademik(item)?.tahun} ${tahunAkademik(item)?.semester}` : '-' }}
                                    </td>
                                    <td data-label="Kapasitas" class="px-4 py-3 text-center text-[15px] leading-5 text-[#31302e]">
                                        {{ item.kapasitas }}
                                    </td>
                                    <td data-label="Dosen" v-if="isAdmin" class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        <span class="block">{{ dosenName(item) }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ dosenNidn(item) }}</span>
                                    </td>
                                    <td data-label="Mata Kuliah" class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        <span class="block">{{ matkul(item)?.kode_matkul ?? '-' }} — {{ matkul(item)?.nama_matkul ?? '' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ matkul(item)?.prodi?.nama_prodi ?? '' }}</span>
                                    </td>
                                    <td data-label="Jadwal" class="px-4 py-3 text-[15px] leading-5 text-[#31302e]">
                                        <span class="block">{{ jadwalText(item) }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ ruangText(item) }}</span>
                                    </td>
                                    <td data-label="Aksi" class="px-4 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <Link :href="rute('kelas-kuliah.show', item.id)" title="Detail" aria-label="Detail">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#0075de] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Eye class="size-4"
                                                /></Button>
                                            </Link>
                                            <Link v-if="isAdmin" :href="rute('kelas-kuliah.edit', item.id)" title="Edit" aria-label="Edit">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Pencil class="size-4"
                                                /></Button>
                                            </Link>
                                            <button v-if="isAdmin" type="button" title="Hapus" aria-label="Hapus" @click="remove(item)">
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00] hover:bg-[#f6f5f4]"
                                                    aria-hidden="true"
                                                    ><Trash2 class="size-4"
                                                /></Button>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.kelasKuliahs.data.length">
                                    <td colspan="8" class="px-4 py-16 text-center">
                                        <div class="mx-auto max-w-sm rounded-xl border border-dashed border-[#e6e6e6] bg-[#f6f5f4] px-6 py-8">
                                            <p class="text-sm font-medium text-black">Belum ada data</p>
                                            <p class="mt-1 text-sm leading-5 text-[#615d59]">
                                                Data kelas kuliah akan tampil di sini. Tambahkan kelas baru untuk memulai.
                                            </p>
                                        </div>
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
