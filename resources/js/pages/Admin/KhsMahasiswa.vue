<script setup lang="ts">
import IsiKhs, { type KhsProps } from '@/components/IsiKhs.vue';
import SelectFilter from '@/components/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Download } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<
    KhsProps & { mahasiswa: { id: number; nim: string | null; nama: string | null; prodi: string | null; angkatan: string | null } }
>();

const tahunAkademikId = ref(props.tahunAkademikTerpilih);
const pilihTahun = () =>
    router.get(route('admin.khs.show', { mahasiswa: props.mahasiswa.id, tahun_akademik_id: tahunAkademikId.value }), {}, { preserveScroll: true });
</script>

<template>
    <Head :title="`KHS ${props.mahasiswa.nama ?? ''}`" />
    <AppLayout
        :breadcrumbs="[
            { title: 'KHS', href: route('admin.khs.index', { tahun_akademik_id: props.tahunAkademikTerpilih }) },
            { title: props.mahasiswa.nim ?? '-', href: route('admin.khs.show', props.mahasiswa.id) },
        ]"
    >
        <div class="halaman">
            <div class="konten">
                <div class="kepala-halaman">
                    <div>
                        <h1 class="judul-halaman">{{ props.mahasiswa.nama }}</h1>
                        <p class="deskripsi-halaman">
                            NIM {{ props.mahasiswa.nim ?? '-' }} · {{ props.mahasiswa.prodi ?? '-' }} · Angkatan {{ props.mahasiswa.angkatan ?? '-' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button as-child variant="outline">
                            <Link :href="route('admin.khs.index', { tahun_akademik_id: props.tahunAkademikTerpilih })">
                                <ArrowLeft class="size-4" /> Kembali
                            </Link>
                        </Button>
                        <Button as-child>
                            <a :href="route('admin.khs.download', { mahasiswa: props.mahasiswa.id, tahun_akademik_id: props.tahunAkademikTerpilih })">
                                <Download class="size-4" /> Download KHS
                            </a>
                        </Button>
                    </div>
                </div>

                <div class="bilah-filter">
                    <SelectFilter v-model="tahunAkademikId" label="Tahun akademik" @change="pilihTahun">
                        <option v-for="t in props.tahunAkademiks" :key="t.id" :value="t.id">
                            {{ t.tahun }} — {{ t.semester }}{{ t.status ? ' (aktif)' : '' }}
                        </option>
                    </SelectFilter>
                </div>

                <IsiKhs
                    :krs="props.krs"
                    :tahun-akademiks="props.tahunAkademiks"
                    :tahun-akademik-terpilih="props.tahunAkademikTerpilih"
                    :ringkasan="props.ringkasan"
                />
            </div>
        </div>
    </AppLayout>
</template>
