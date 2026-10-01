<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { ChevronDown, LoaderCircle } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';

// Daftar kecamatan Feeder (±7.600) dicari ke server, bukan dikirim utuh seperti SearchSelect.
const props = defineProps<{ id: string; modelValue: number | null; label?: string | null }>();
const emit = defineEmits<{ (e: 'update:modelValue', value: number): void; (e: 'update:label', value: string): void }>();

type Opsi = { id: number; nama: string };

const open = ref(false);
const cari = ref('');
const hasil = ref<Opsi[]>([]);
const memuat = ref(false);
const rootRef = ref<HTMLElement | null>(null);
let tunda: ReturnType<typeof setTimeout> | undefined;
let permintaan = 0;

watch(cari, (nilai) => {
    clearTimeout(tunda);
    if (nilai.trim().length < 3) {
        hasil.value = [];
        return;
    }
    tunda = setTimeout(async () => {
        const nomor = ++permintaan;
        memuat.value = true;
        try {
            const res = await fetch(route('pmb.kecamatan', { q: nilai.trim() }), { headers: { Accept: 'application/json' } });
            const data: Opsi[] = res.ok ? await res.json() : [];
            if (nomor === permintaan) hasil.value = data;
        } finally {
            if (nomor === permintaan) memuat.value = false;
        }
    }, 300);
});

const pilih = (opsi: Opsi) => {
    emit('update:modelValue', opsi.id);
    emit('update:label', opsi.nama);
    open.value = false;
};

const onClickOutside = (event: MouseEvent) => {
    if (open.value && rootRef.value && !rootRef.value.contains(event.target as Node)) open.value = false;
};
onMounted(() => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            :id="id"
            type="button"
            class="isian flex items-center justify-between gap-2 text-left"
            role="combobox"
            :aria-expanded="open"
            :aria-controls="`${id}-options`"
            @click="open = !open"
            @keydown.escape="open = false"
        >
            <span class="truncate" :class="{ 'text-[#a39e98]': !props.label }">{{ props.label || 'Pilih kecamatan' }}</span>
            <ChevronDown class="size-4 shrink-0 text-[#a39e98] transition-transform" :class="open ? 'rotate-180' : ''" />
        </button>
        <div
            v-if="open"
            :id="`${id}-options`"
            class="absolute z-10 mt-1 w-full rounded-xl border border-[#e6e6e6] bg-white p-2 text-black shadow-[0_4px_18px_rgba(0,0,0,0.04),0_23px_52px_rgba(0,0,0,0.05)] dark:border-border dark:bg-popover dark:text-popover-foreground"
            role="listbox"
        >
            <Input v-model="cari" placeholder="Ketik minimal 3 huruf, mis. Rappocini" aria-label="Cari kecamatan" autofocus />
            <div class="mt-1 max-h-60 overflow-y-auto">
                <button
                    v-for="opsi in hasil"
                    :key="opsi.id"
                    type="button"
                    class="block w-full rounded-lg px-2 py-2 text-left text-sm hover:bg-[#f6f5f4] dark:hover:bg-accent"
                    role="option"
                    :aria-selected="props.modelValue === opsi.id"
                    @click="pilih(opsi)"
                >
                    {{ opsi.nama }}
                </button>
                <p v-if="memuat" class="flex items-center gap-2 px-2 py-2 text-sm text-[#615d59]">
                    <LoaderCircle class="size-4 animate-spin" /> Mencari…
                </p>
                <p v-else-if="cari.trim().length >= 3 && !hasil.length" class="px-2 py-2 text-sm text-[#615d59]">Tidak ditemukan</p>
            </div>
        </div>
    </div>
</template>
