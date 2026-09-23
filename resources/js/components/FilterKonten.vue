<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import { Input } from '@/components/ui/input';
import { router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

export type Opsi = { id: number; name: string };
export type NilaiFilter = {
    tahun_akademik_id: number | null;
    prodi_id: number | null;
    mata_kuliah_id: number | null;
    kelas_id: number | null;
    dosen_id: number | null;
    search: string;
};

const props = defineProps<{
    /** URL daftar yang sedang dibuka, mis. route('admin.materi.index'). */
    url: string;
    filter: NilaiFilter;
    tahunAkademikOptions: Opsi[];
    prodiOptions: Opsi[];
    mataKuliahOptions: Opsi[];
    kelasOptions: Opsi[];
    dosenOptions: Opsi[];
    /** Jumlah data pada hasil filter, ditampilkan di kanan seperti halaman Kelas Kuliah. */
    total: number;
    placeholder?: string;
    /** Filter tambahan khas satu menu, mis. jenis materi. */
    tambahan?: Record<string, string | number | null>;
}>();

const semua = 'all';
const dariFilter = (nilai: number | null): number | string => nilai ?? semua;

const tahunAkademikId = ref<number | string>(dariFilter(props.filter.tahun_akademik_id));
const prodiId = ref<number | string>(dariFilter(props.filter.prodi_id));
const mataKuliahId = ref<number | string>(dariFilter(props.filter.mata_kuliah_id));
const kelasId = ref<number | string>(dariFilter(props.filter.kelas_id));
const dosenId = ref<number | string>(dariFilter(props.filter.dosen_id));
const search = ref(props.filter.search);
let jedaCari: number | undefined;

const kirim = () => {
    router.get(
        props.url,
        {
            search: search.value,
            tahun_akademik_id: tahunAkademikId.value,
            prodi_id: prodiId.value,
            mata_kuliah_id: mataKuliahId.value,
            kelas_id: kelasId.value,
            dosen_id: dosenId.value,
            ...(props.tambahan ?? {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

// Mengganti filter yang lebih luas mengosongkan filter di bawahnya, agar pilihannya tidak saling bertentangan.
const gantiTahunAkademik = () => {
    mataKuliahId.value = semua;
    kelasId.value = semua;
    kirim();
};

const gantiProdi = () => {
    mataKuliahId.value = semua;
    kelasId.value = semua;
    kirim();
};

const gantiMataKuliah = () => {
    kelasId.value = semua;
    kirim();
};

watch(search, () => {
    window.clearTimeout(jedaCari);
    jedaCari = window.setTimeout(kirim, 400);
});

watch(
    () => props.filter,
    (baru) => {
        tahunAkademikId.value = dariFilter(baru.tahun_akademik_id);
        prodiId.value = dariFilter(baru.prodi_id);
        mataKuliahId.value = dariFilter(baru.mata_kuliah_id);
        kelasId.value = dariFilter(baru.kelas_id);
        dosenId.value = dariFilter(baru.dosen_id);
        search.value = baru.search;
    },
);

defineExpose({ kirim });
</script>

<template>
    <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div class="grid gap-3 sm:grid-cols-2 xl:flex xl:items-center">
            <div class="relative w-full sm:max-w-sm">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
                <Input
                    v-model="search"
                    :placeholder="props.placeholder ?? 'Cari…'"
                    aria-label="Cari"
                    class="h-10 rounded-lg border-[#d8d5d2] bg-white pl-9 text-sm shadow-sm placeholder:text-[#a39e98] focus-visible:border-[#0075de] focus-visible:ring-2 focus-visible:ring-[#0075de]/15"
                />
            </div>

            <SelectFilter v-model="tahunAkademikId" label="Filter tahun akademik" @change="gantiTahunAkademik">
                <option :value="semua">Semua Tahun Akademik</option>
                <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
            </SelectFilter>

            <SelectFilter v-model="prodiId" label="Filter program studi" @change="gantiProdi">
                <option :value="semua">Semua Program Studi</option>
                <option v-for="item in props.prodiOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
            </SelectFilter>

            <SelectFilter v-model="mataKuliahId" label="Filter mata kuliah" @change="gantiMataKuliah">
                <option :value="semua">Semua Mata Kuliah</option>
                <option v-for="item in props.mataKuliahOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
            </SelectFilter>

            <SelectFilter v-model="kelasId" label="Filter kelas" @change="kirim">
                <option :value="semua">Semua Kelas</option>
                <option v-for="item in props.kelasOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
            </SelectFilter>

            <SelectFilter v-if="props.dosenOptions.length" v-model="dosenId" label="Filter dosen" @change="kirim">
                <option :value="semua">Semua Dosen</option>
                <option v-for="item in props.dosenOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
            </SelectFilter>

            <slot name="tambahan" />
        </div>

        <p class="text-sm text-[#615d59]">
            <span class="font-medium text-black">{{ props.total }}</span> data<span v-if="props.filter.search">
                · hasil untuk "{{ props.filter.search }}"</span
            >
        </p>
    </div>
</template>
