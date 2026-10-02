<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { FileText, FileUp, UserRound } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

// Pilih satu berkas untuk formulir (v-model = File); gambar diberi pratinjau, berkas lain tampil nama + ukurannya.
const props = defineProps<{ id: string; label: string; accept: string; bantuan: string; error?: string; foto?: boolean }>();
const berkas = defineModel<File | null>({ default: null });

const input = ref<HTMLInputElement | null>(null);
const pratinjau = ref<string | null>(null);

const ukuran = computed(() => {
    const byte = berkas.value?.size ?? 0;
    return byte >= 1024 * 1024 ? `${(byte / 1024 / 1024).toFixed(1)} MB` : `${Math.max(1, Math.round(byte / 1024))} KB`;
});

watch(
    berkas,
    (file) => {
        if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
        pratinjau.value = file?.type.startsWith('image/') ? URL.createObjectURL(file) : null;
    },
    { immediate: true },
);

const pilih = (event: Event) => {
    berkas.value = (event.target as HTMLInputElement).files?.[0] ?? null;
};

onBeforeUnmount(() => {
    if (pratinjau.value) URL.revokeObjectURL(pratinjau.value);
});
</script>

<template>
    <div class="grid gap-2">
        <Label :for="props.id" class="label-isian">{{ props.label }}</Label>
        <input :id="props.id" ref="input" type="file" :accept="props.accept" class="hidden" @change="pilih" />
        <div class="flex items-center gap-4">
            <div
                class="flex shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] dark:border-border dark:bg-muted"
                :class="props.foto ? 'h-24 w-[72px]' : 'size-14'"
            >
                <img v-if="pratinjau" :src="pratinjau" :alt="props.label" class="size-full object-cover" />
                <UserRound v-else-if="props.foto" class="size-8 text-[#a39e98]" />
                <FileText v-else class="size-6 text-[#a39e98]" />
            </div>
            <div class="grid min-w-0 gap-1.5">
                <Button type="button" variant="outline" class="w-fit" @click="input?.click()">
                    <FileUp /> {{ berkas ? 'Ganti Berkas' : 'Pilih Berkas' }}
                </Button>
                <span v-if="berkas" class="truncate text-sm text-black dark:text-foreground">{{ berkas.name }} · {{ ukuran }}</span>
            </div>
        </div>
        <p class="teks-bantu">{{ props.bantuan }}</p>
        <InputError class="pesan-galat" :message="props.error" />
    </div>
</template>
