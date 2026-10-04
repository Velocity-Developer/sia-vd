<script setup lang="ts">
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import { ref } from 'vue';

type Kartu = { jenis: string; label: string; alasan: string | null };

const props = defineProps<{
    tahunAkademikOptions: { id: number; name: string }[];
    tahunAkademikId: number | null;
    kartu: Kartu[];
}>();

const tahunAkademikId = ref<number | string>(props.tahunAkademikId ?? '');
const pilihTahun = () => router.get(route('mahasiswa.cetak-kartu-ujian'), { tahun_akademik_id: tahunAkademikId.value }, { preserveScroll: true });
</script>

<template>
    <Head title="Cetak Kartu UTS & UAS" />
    <AppLayout :breadcrumbs="[{ title: 'Cetak Kartu UTS & UAS', href: route('mahasiswa.cetak-kartu-ujian') }]">
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">Cetak Kartu UTS &amp; UAS</h1>
                        <p class="deskripsi-halaman">
                            Kartu ujian bisa dicetak setelah KRS disetujui dan kehadiran memenuhi syarat ujian (atau mendapat dispensasi).
                        </p>
                    </div>
                </div>

                <div v-if="props.tahunAkademikOptions.length > 1" class="bilah-filter">
                    <SelectFilter v-model="tahunAkademikId" label="Tahun akademik" @change="pilihTahun">
                        <option v-for="ta in props.tahunAkademikOptions" :key="ta.id" :value="ta.id">{{ ta.name }}</option>
                    </SelectFilter>
                </div>

                <section
                    v-for="item in props.kartu"
                    :key="item.jenis"
                    class="kartu flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2 class="judul-bagian">{{ item.label }}</h2>
                        <p class="teks-bantu mt-1" :class="item.alasan ? 'text-[#b54a00]' : ''">
                            {{ item.alasan ?? 'Memenuhi syarat. Kartu siap dicetak.' }}
                        </p>
                    </div>
                    <Button v-if="!item.alasan && props.tahunAkademikId" as-child class="shrink-0">
                        <a :href="route('mahasiswa.ujian.kartu', { jenis: item.jenis, tahun_akademik_id: props.tahunAkademikId })">
                            <Download /> Unduh {{ item.label }}
                        </a>
                    </Button>
                    <Button v-else disabled class="shrink-0"><Download /> Unduh {{ item.label }}</Button>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
