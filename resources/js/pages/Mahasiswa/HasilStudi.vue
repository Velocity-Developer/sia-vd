<script setup lang="ts">
import IsiKhs, { type KhsProps } from '@/components/IsiKhs.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<KhsProps>();

const selectedYear = ref(props.tahunAkademikTerpilih ?? '');

const downloadUrl = computed(() => route('mahasiswa.hasil-studi.download', { tahun_akademik_id: selectedYear.value }));

const changeYear = () => {
    router.get(route('mahasiswa.hasil-studi'), { tahun_akademik_id: selectedYear.value }, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Head title="Kartu Hasil Studi" />
    <AppLayout :breadcrumbs="[{ title: 'Kartu Hasil Studi', href: route('mahasiswa.hasil-studi') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Kartu Hasil Studi</h1>
                        <p class="deskripsi-halaman">Daftar kelas yang diambil dan nilai akademik Anda.</p>
                    </div>
                    <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-end">
                        <div class="grid gap-2">
                            <label for="tahun_akademik" class="label-isian">Tahun Akademik</label>
                            <select id="tahun_akademik" v-model="selectedYear" class="isian isian-pilih min-w-[210px]" @change="changeYear">
                                <option v-for="tahun in tahunAkademiks" :key="tahun.id" :value="tahun.id">
                                    {{ tahun.tahun }} — {{ tahun.semester }}{{ tahun.status ? ' (Aktif)' : '' }}
                                </option>
                            </select>
                        </div>
                        <Button as-child>
                            <a :href="downloadUrl">
                                <Download class="h-4 w-4" />
                                Download KHS
                            </a>
                        </Button>
                    </div>
                </div>

                <IsiKhs :krs="krs" :tahun-akademiks="tahunAkademiks" :tahun-akademik-terpilih="tahunAkademikTerpilih" :ringkasan="ringkasan" />
            </div>
        </div>
    </AppLayout>
</template>
