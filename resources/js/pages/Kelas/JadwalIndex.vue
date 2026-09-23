<script setup lang="ts">
import AlertModal from '@/components/AlertModal.vue';
import FilterKonten, { type NilaiFilter, type Opsi } from '@/components/FilterKonten.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { rutePeran, type Peran } from '@/lib/rutePeran';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pencil, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type KelasRingkas = {
    id: number;
    kode_kelas: string;
    mata_kuliah?: { kode_matkul: string; nama_matkul: string } | null;
    dosen?: { user?: { name: string } | null } | null;
    tahun_akademik?: { tahun: string; semester: string } | null;
};
type Jadwal = {
    id: number;
    hari: string;
    jam_mulai: string;
    jam_akhir: string;
    ruang?: { kode_ruang: string; nama_ruang: string } | null;
    kelas_kuliah?: KelasRingkas | null;
};

const props = defineProps<{
    jadwals: { data: Jadwal[]; links: { url: string | null; label: string; active: boolean }[]; from: number | null; total: number };
    peran: Peran;
    filter: NilaiFilter;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    mataKuliahOptions: Opsi[];
    kelasOptions: Opsi[];
    dosenOptions: Opsi[];
}>();

const page = usePage<{ flash?: { jadwal_success?: string; jadwal_error?: string } }>();
const rute = rutePeran(props.peran);
const isAdmin = computed(() => props.peran === 'admin');
const jam = (value: string) => value.slice(0, 5);

const pendingHapus = ref<Jadwal | null>(null);
const hapus = () => {
    const jadwal = pendingHapus.value;
    if (!jadwal?.kelas_kuliah) return;

    router.delete(rute('kelas-kuliah.jadwal.destroy', [jadwal.kelas_kuliah.id, jadwal.id]), {
        preserveScroll: true,
        onFinish: () => (pendingHapus.value = null),
    });
};
</script>

<template>
    <Head title="Jadwal Kelas" />
    <AppLayout :breadcrumbs="[{ title: 'Jadwal Kelas', href: rute('jadwal.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1200px] flex-col gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold leading-[1.23] text-black">{{ isAdmin ? 'Jadwal Kelas' : 'Jadwal Mengajar' }}</h1>
                    <p class="text-sm text-[#615d59]">
                        {{
                            isAdmin
                                ? 'Seluruh jadwal kelas kuliah. Jadwal ditambah dari halaman kelas.'
                                : 'Jadwal mengajar dari kelas yang Anda ampu.'
                        }}
                    </p>
                </div>

                <div v-if="page.props.flash?.jadwal_success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.jadwal_success }}
                </div>
                <div v-if="page.props.flash?.jadwal_error" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]">
                    {{ page.props.flash.jadwal_error }}
                </div>

                <FilterKonten
                    :url="rute('jadwal.index')"
                    :filter="props.filter"
                    :tahun-akademik-options="props.tahunAkademikOptions"
                    :prodi-options="props.prodiOptions"
                    :mata-kuliah-options="props.mataKuliahOptions"
                    :kelas-options="props.kelasOptions"
                    :dosen-options="props.dosenOptions"
                    :total="props.jadwals.total"
                    placeholder="Cari hari, ruang, atau kode kelas"
                />

                <div class="overflow-hidden rounded-xl border border-[#e6e6e6] bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">No.</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Hari &amp; Jam</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Kelas</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Mata Kuliah</th>
                                    <th v-if="isAdmin" class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Dosen</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Ruang</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(item, index) in props.jadwals.data" :key="item.id" class="hover:bg-[#f6f5f4]/60">
                                    <td class="px-4 py-3 text-[15px] text-[#615d59]">{{ (props.jadwals.from ?? 0) + index }}</td>
                                    <td class="px-4 py-3 text-[15px] text-black">
                                        <span class="block font-medium">{{ item.hari }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ jam(item.jam_mulai) }}–{{ jam(item.jam_akhir) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block font-medium">{{ item.kelas_kuliah?.kode_kelas ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">
                                            {{ item.kelas_kuliah?.tahun_akademik?.tahun }} {{ item.kelas_kuliah?.tahun_akademik?.semester }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ item.kelas_kuliah?.mata_kuliah?.nama_matkul ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.kelas_kuliah?.mata_kuliah?.kode_matkul }}</span>
                                    </td>
                                    <td v-if="isAdmin" class="px-4 py-3 text-[15px] text-[#31302e]">
                                        {{ item.kelas_kuliah?.dosen?.user?.name ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-[15px] text-[#31302e]">
                                        <span class="block">{{ item.ruang?.kode_ruang ?? '-' }}</span>
                                        <span class="block text-xs text-[#a39e98]">{{ item.ruang?.nama_ruang }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1.5">
                                            <Link
                                                :href="rute('kelas-kuliah.show', item.kelas_kuliah?.id ?? 0)"
                                                class="text-sm font-medium text-[#0075de] hover:underline"
                                            >
                                                Buka kelas
                                            </Link>
                                            <template v-if="isAdmin && item.kelas_kuliah">
                                                <Link
                                                    :href="rute('kelas-kuliah.jadwal.edit', [item.kelas_kuliah.id, item.id])"
                                                    title="Edit"
                                                    aria-label="Edit jadwal"
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="icon"
                                                        class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#2a9d99]"
                                                    >
                                                        <Pencil class="size-4" />
                                                    </Button>
                                                </Link>
                                                <Button
                                                    variant="outline"
                                                    size="icon"
                                                    class="size-8 rounded-full border-[#e6e6e6] bg-white text-[#dd5b00]"
                                                    title="Hapus"
                                                    aria-label="Hapus jadwal"
                                                    @click="pendingHapus = item"
                                                >
                                                    <Trash2 class="size-4" />
                                                </Button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!props.jadwals.data.length">
                                    <td :colspan="isAdmin ? 7 : 6" class="px-4 py-14 text-center text-sm text-[#615d59]">
                                        Tidak ada jadwal yang cocok dengan filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.jadwals.links" :total="props.jadwals.total" />

                <AlertModal
                    :open="pendingHapus !== null"
                    description="Hapus jadwal ini?"
                    confirm-text="Hapus"
                    cancel-text="Batal"
                    @update:open="!$event && (pendingHapus = null)"
                    @confirm="hapus"
                    @cancel="pendingHapus = null"
                />
            </div>
        </div>
    </AppLayout>
</template>
