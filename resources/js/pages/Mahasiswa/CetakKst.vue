<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Printer } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    tahunAkademikOptions: { id: number; name: string }[];
    tahunAkademikId: number | null;
    alasan: string | null;
}>();

const tahunAkademikId = ref<number | string>(props.tahunAkademikId ?? '');
const pilihTahun = () => router.get(route('mahasiswa.cetak-kst'), { tahun_akademik_id: tahunAkademikId.value }, { preserveScroll: true });
</script>

<template>
    <Head title="Cetak KST" />
    <AppLayout :breadcrumbs="[{ title: 'Cetak KST', href: route('mahasiswa.cetak-kst') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Cetak KST</h1>
                        <p class="deskripsi-halaman">Kartu Studi Tetap berisi mata kuliah KRS yang sudah disetujui pada tahun akademik terpilih.</p>
                    </div>
                </div>

                <div v-if="props.tahunAkademikOptions.length > 1" class="bilah-filter">
                    <SelectFilter v-model="tahunAkademikId" label="Tahun akademik" @change="pilihTahun">
                        <option v-for="ta in props.tahunAkademikOptions" :key="ta.id" :value="ta.id">{{ ta.name }}</option>
                    </SelectFilter>
                </div>

                <section class="kartu flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="judul-bagian">Kartu Studi Tetap</h2>
                        <p class="teks-bantu mt-1">
                            {{ props.alasan ?? 'KRS sudah disetujui. KST siap dicetak.' }}
                        </p>
                    </div>
                    <Button v-if="!props.alasan && props.tahunAkademikId" as-child>
                        <a :href="route('mahasiswa.krs.kst', { tahun_akademik_id: props.tahunAkademikId })" target="_blank" rel="noopener">
                            <Printer /> Cetak KST
                        </a>
                    </Button>
                    <Button v-else disabled><Printer /> Cetak KST</Button>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
