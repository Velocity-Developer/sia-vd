<script setup lang="ts">
import { Button } from '@/components/ui/button';
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
    /** Filter tambahan khas satu menu, mis. jenis materi. */
    tambahan?: Record<string, string | number | null>;
}>();

const sel = 'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black';
const semua = 'all';
const nilai = ref({ ...props.filter });
const cari = ref(props.filter.search);
let jedaCari: number | undefined;

const keParameter = (value: number | string | null) => (value === null || value === semua ? undefined : value);

const kirim = () => {
    router.get(
        props.url,
        {
            tahun_akademik_id: keParameter(nilai.value.tahun_akademik_id),
            prodi_id: keParameter(nilai.value.prodi_id),
            mata_kuliah_id: keParameter(nilai.value.mata_kuliah_id),
            kelas_id: keParameter(nilai.value.kelas_id),
            dosen_id: keParameter(nilai.value.dosen_id),
            search: cari.value || undefined,
            ...(props.tambahan ?? {}),
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

// Mengganti filter yang lebih luas mengosongkan filter di bawahnya, agar pilihannya tidak saling bertentangan.
const ubah = (field: keyof NilaiFilter) => {
    if (field === 'tahun_akademik_id' || field === 'prodi_id') {
        nilai.value.mata_kuliah_id = null;
        nilai.value.kelas_id = null;
    }
    if (field === 'mata_kuliah_id') nilai.value.kelas_id = null;

    kirim();
};

const reset = () => {
    nilai.value = { tahun_akademik_id: null, prodi_id: null, mata_kuliah_id: null, kelas_id: null, dosen_id: null, search: '' };
    cari.value = '';
    kirim();
};

watch(cari, () => {
    window.clearTimeout(jedaCari);
    jedaCari = window.setTimeout(kirim, 400);
});
watch(
    () => props.filter,
    (baru) => {
        nilai.value = { ...baru };
        cari.value = baru.search;
    },
);

defineExpose({ kirim });
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 rounded-xl border border-[#e6e6e6] bg-white p-4">
        <div class="relative min-w-[220px] flex-1">
            <Search class="absolute left-3 top-1/2 size-4 -translate-y-1/2 text-[#a39e98]" />
            <Input v-model="cari" placeholder="Cari…" class="h-10 rounded-[4px] border-[#dddddd] pl-9" aria-label="Cari" />
        </div>

        <select v-model="nilai.tahun_akademik_id" :class="sel" aria-label="Filter tahun akademik" @change="ubah('tahun_akademik_id')">
            <option :value="null">Semua tahun akademik</option>
            <option v-for="item in props.tahunAkademikOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>

        <select v-model="nilai.prodi_id" :class="sel" aria-label="Filter program studi" @change="ubah('prodi_id')">
            <option :value="null">Semua program studi</option>
            <option v-for="item in props.prodiOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>

        <select v-model="nilai.mata_kuliah_id" :class="sel" aria-label="Filter mata kuliah" @change="ubah('mata_kuliah_id')">
            <option :value="null">Semua mata kuliah</option>
            <option v-for="item in props.mataKuliahOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>

        <select v-model="nilai.kelas_id" :class="sel" aria-label="Filter kelas" @change="ubah('kelas_id')">
            <option :value="null">Semua kelas</option>
            <option v-for="item in props.kelasOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>

        <select v-if="props.dosenOptions.length" v-model="nilai.dosen_id" :class="sel" aria-label="Filter dosen" @change="ubah('dosen_id')">
            <option :value="null">Semua dosen</option>
            <option v-for="item in props.dosenOptions" :key="item.id" :value="item.id">{{ item.name }}</option>
        </select>

        <slot name="tambahan" />

        <Button variant="outline" class="h-10 rounded-[4px]" @click="reset">Reset</Button>
    </div>
</template>
