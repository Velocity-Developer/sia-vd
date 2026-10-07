<script setup lang="ts">
import DaftarPengumuman, { type Pengumuman } from '@/components/DaftarPengumuman.vue';
import Pagination from '@/components/Pagination.vue';
import PortalLayout from '@/layouts/PortalLayout.vue';
import { type SharedData } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { Info } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    pengumuman: { data: Pengumuman[]; links: { url: string | null; label: string; active: boolean }[]; total: number };
}>();

// Pita gelap berlatar gambar Pengaturan Sistem → Tampilan, sesuai contoh tampilan klien.
const gambar = computed(() => usePage<SharedData>().props.tampilan?.login_gambar_url);
</script>

<template>
    <Head title="Informasi & Pengumuman" />
    <PortalLayout>
        <section class="relative flex-1 bg-[#0b2a4a] bg-cover bg-center" :style="gambar ? { backgroundImage: `url(${gambar})` } : undefined">
            <div v-if="gambar" class="pointer-events-none absolute inset-0 bg-[#0b2a4a]/90" aria-hidden="true" />
            <div class="relative mx-auto w-full max-w-[1120px] px-4 py-10 sm:py-14">
                <h1 class="mb-8 flex items-center gap-2 text-xl font-semibold text-white"><Info class="size-5" /> Informasi & Pengumuman</h1>
                <DaftarPengumuman v-if="props.pengumuman.data.length" :items="props.pengumuman.data" varian="gelap" />
                <p v-else class="text-sm text-white/70">Belum ada informasi & pengumuman.</p>

                <div v-if="props.pengumuman.links.length > 3" class="mt-8">
                    <Pagination :links="props.pengumuman.links" :total="props.pengumuman.total" />
                </div>
            </div>
        </section>
    </PortalLayout>
</template>
