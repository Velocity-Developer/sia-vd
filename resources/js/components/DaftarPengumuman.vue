<script setup lang="ts">
import { Paperclip } from 'lucide-vue-next';

export type Pengumuman = { id: number; kategori: string | null; information: string; created_at: string };

// "gelap" untuk pita berlatar foto (halaman Pengumuman); "terang" untuk latar putih.
const props = withDefaults(defineProps<{ items: Pengumuman[]; varian?: 'gelap' | 'terang' }>(), { varian: 'terang' });

const tanggal = (nilai: string) => new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(nilai));
</script>

<template>
    <ul>
        <li
            v-for="item in props.items"
            :key="item.id"
            class="border-b py-5 first:pt-0 last:border-b-0 last:pb-0"
            :class="props.varian === 'gelap' ? 'border-white/30' : 'border-[#e6e6e6] dark:border-border'"
        >
            <div class="flex flex-wrap gap-1.5">
                <span v-if="item.kategori" class="rounded-md bg-[#0075de] px-2 py-0.5 text-xs font-semibold text-white">{{ item.kategori }}</span>
                <span class="rounded-md bg-[#0075de] px-2 py-0.5 text-xs font-semibold text-white">{{ tanggal(item.created_at) }}</span>
            </div>
            <p
                class="mt-2 whitespace-pre-line text-base leading-7 sm:text-lg"
                :class="props.varian === 'gelap' ? 'text-white' : 'text-black dark:text-foreground'"
            >
                {{ item.information }}
            </p>
            <a
                :href="route('berkas.info-kuliah', item.id)"
                target="_blank"
                rel="noopener"
                class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium hover:underline"
                :class="props.varian === 'gelap' ? 'text-white/80 hover:text-white' : 'text-[#0075de]'"
                ><Paperclip class="size-4" /> Lihat lampiran</a
            >
        </li>
    </ul>
</template>
