<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight, Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

type Perubahan = Record<string, { lama?: unknown; baru?: unknown }>;
type Log = {
    id: number;
    waktu: string | null;
    pengguna: string;
    username: string | null;
    aksi: string;
    objek: string | null;
    objek_id: number | null;
    label: string | null;
    perubahan: Perubahan;
    ip: string | null;
    rute: string | null;
};

const props = defineProps<{
    log: { data: Log[]; links: { url: string | null; label: string; active: boolean }[]; total: number; from: number | null };
    filter: { dari: string | null; sampai: string | null; aksi: string | null; objek: string | null; search: string };
    aksiOptions: Record<string, string>;
    objekOptions: { id: string; name: string }[];
}>();

const dari = ref(props.filter.dari ?? '');
const sampai = ref(props.filter.sampai ?? '');
const aksi = ref<string | number>(props.filter.aksi ?? '');
const objek = ref<string | number>(props.filter.objek ?? '');
const search = ref(props.filter.search);
const saring = () =>
    router.get(
        route('admin.log-aktivitas.index'),
        Object.fromEntries(
            Object.entries({ dari: dari.value, sampai: sampai.value, aksi: aksi.value, objek: objek.value, search: search.value }).filter(
                ([, v]) => v !== '',
            ),
        ),
        { preserveState: true, preserveScroll: true, replace: true },
    );
watch([search, dari, sampai], saring);

const terbuka = ref<number[]>([]);
const alih = (id: number) => (terbuka.value = terbuka.value.includes(id) ? terbuka.value.filter((x) => x !== id) : [...terbuka.value, id]);

const waktu = (iso: string | null) =>
    iso ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso)) : '-';
const teks = (v: unknown) => (v === null || v === undefined || v === '' ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v));
const warnaAksi: Record<string, string> = {
    dibuat: 'bg-[#1aae39]/10 text-[#137a2a]',
    diubah: 'bg-[#0075de]/10 text-[#0075de]',
    dihapus: 'bg-[#dd5b00]/10 text-[#b54a00]',
    masuk: 'bg-[#a39e98]/15 text-[#615d59]',
    keluar: 'bg-[#a39e98]/15 text-[#615d59]',
};
</script>

<template>
    <Head title="Log Aktivitas" />
    <AppLayout :breadcrumbs="[{ title: 'Log Aktivitas', href: route('admin.log-aktivitas.index') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Log Aktivitas</h1>
                        <p class="deskripsi-halaman">Jejak data yang dibuat, diubah, atau dihapus pengguna, serta riwayat masuk dan keluar.</p>
                    </div>
                </div>

                <div class="bilah-filter flex-wrap">
                    <div class="kolom-cari">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                        <Input v-model="search" placeholder="Cari pengguna atau nama data" aria-label="Cari" class="pl-9" />
                    </div>
                    <Input v-model="dari" type="date" aria-label="Dari tanggal" class="lg:max-w-[160px]" />
                    <Input v-model="sampai" type="date" aria-label="Sampai tanggal" class="lg:max-w-[160px]" />
                    <SelectFilter v-model="aksi" label="Filter aksi" @change="saring">
                        <option value="">Semua Aksi</option>
                        <option v-for="(label, kunci) in props.aksiOptions" :key="kunci" :value="kunci">{{ label }}</option>
                    </SelectFilter>
                    <SelectFilter v-model="objek" label="Filter jenis data" @change="saring">
                        <option value="">Semua Jenis Data</option>
                        <option v-for="o in props.objekOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </SelectFilter>
                    <p class="info-jumlah sm:ml-auto">
                        <span class="font-medium text-black">{{ props.log.total }}</span> catatan
                    </p>
                </div>

                <div class="tabel-wadah">
                    <div class="tabel-gulir">
                        <table class="tabel min-w-[960px]">
                            <thead>
                                <tr>
                                    <th class="w-8"><span class="sr-only">Rincian</span></th>
                                    <th>Waktu</th>
                                    <th>Pengguna</th>
                                    <th>Aksi</th>
                                    <th>Data</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="item in props.log.data" :key="item.id">
                                    <tr>
                                        <td>
                                            <Button
                                                v-if="Object.keys(item.perubahan).length"
                                                variant="ghost"
                                                size="icon-sm"
                                                :aria-label="terbuka.includes(item.id) ? 'Tutup rincian' : 'Lihat rincian'"
                                                @click="alih(item.id)"
                                            >
                                                <ChevronDown v-if="terbuka.includes(item.id)" />
                                                <ChevronRight v-else />
                                            </Button>
                                        </td>
                                        <td class="whitespace-nowrap">{{ waktu(item.waktu) }}</td>
                                        <td>
                                            <span class="block font-medium text-black">{{ item.pengguna }}</span>
                                            <span v-if="item.username" class="teks-bantu block">{{ item.username }}</span>
                                        </td>
                                        <td>
                                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="warnaAksi[item.aksi]">{{
                                                props.aksiOptions[item.aksi] ?? item.aksi
                                            }}</span>
                                        </td>
                                        <td>
                                            <span class="block"
                                                >{{ item.objek ?? '-'
                                                }}<span v-if="item.objek_id" class="teks-bantu"> #{{ item.objek_id }}</span></span
                                            >
                                            <span v-if="item.label" class="teks-bantu block">{{ item.label }}</span>
                                        </td>
                                        <td class="teks-bantu whitespace-nowrap">{{ item.ip ?? '-' }}</td>
                                    </tr>
                                    <tr v-if="terbuka.includes(item.id)" class="bg-[#f6f5f4]/60">
                                        <td></td>
                                        <td colspan="5">
                                            <table class="w-full text-xs">
                                                <thead>
                                                    <tr class="text-left text-[#615d59]">
                                                        <th class="py-1 pr-4 font-medium">Kolom</th>
                                                        <th class="py-1 pr-4 font-medium">Sebelum</th>
                                                        <th class="py-1 font-medium">Sesudah</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="(nilai, kolom) in item.perubahan" :key="kolom" class="align-top">
                                                        <td class="py-1 pr-4 font-mono text-black">{{ kolom }}</td>
                                                        <td class="break-all py-1 pr-4">{{ teks(nilai.lama) }}</td>
                                                        <td class="break-all py-1">{{ teks(nilai.baru) }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <p v-if="item.rute" class="teks-bantu mt-2">Rute: {{ item.rute }}</p>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="!props.log.data.length" class="baris-kosong">
                                    <td colspan="6" class="tabel-kosong">Belum ada aktivitas pada filter ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <Pagination :links="props.log.links" :total="props.log.total" />
            </div>
        </div>
    </AppLayout>
</template>
