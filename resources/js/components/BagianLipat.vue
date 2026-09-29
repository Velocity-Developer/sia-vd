<script setup lang="ts">
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { ChevronDown } from 'lucide-vue-next';

/**
 * Kartu bagian yang bisa dibuka-tutup (accordion). Judul, jumlah data, dan tombol aksi (slot `aksi`)
 * tetap terlihat saat tertutup; isi bagian ada di slot bawaan.
 */
defineProps<{ judul: string; jumlah?: number; keterangan?: string }>();

const terbuka = defineModel<boolean>('open', { default: false });
</script>

<template>
    <Collapsible v-model:open="terbuka" as-child>
        <section class="kartu">
            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-6">
                <div class="min-w-0 flex-1 space-y-1">
                    <h2>
                        <CollapsibleTrigger
                            class="group -m-1 flex w-full items-center gap-2 rounded-lg p-1 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0075de]/30"
                        >
                            <ChevronDown class="size-5 shrink-0 text-[#a39e98] transition-transform group-data-[state=open]:rotate-180" />
                            <span class="judul-bagian">{{ judul }}</span>
                            <span
                                v-if="jumlah !== undefined"
                                class="rounded-full bg-[#f6f5f4] px-2 py-0.5 text-xs font-semibold tabular-nums text-[#615d59]"
                                >{{ jumlah }}</span
                            >
                            <slot name="status" />
                        </CollapsibleTrigger>
                    </h2>
                    <div v-if="keterangan || $slots.keterangan" class="space-y-1 pl-7">
                        <p v-if="keterangan" class="teks-bantu">{{ keterangan }}</p>
                        <slot name="keterangan" />
                    </div>
                </div>
                <div v-if="$slots.aksi" class="flex flex-wrap gap-2 pl-7 sm:pl-0">
                    <slot name="aksi" />
                </div>
            </div>
            <CollapsibleContent>
                <div class="border-t border-[#e6e6e6] p-4 sm:p-6 [&>*:first-child]:mt-0">
                    <slot />
                </div>
            </CollapsibleContent>
        </section>
    </Collapsible>
</template>
