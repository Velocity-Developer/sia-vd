<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Isian unggah bukti bayar satu tagihan (PDF/JPG/PNG, maks 5 MB).
 */
const props = defineProps<{ rute: string; id: number; adaBukti: boolean; labelKirim?: string }>();

const form = useForm<{ bukti: File | null }>({ bukti: null });
// Input berkas bawaan tidak ikut kosong saat form direset, jadi dirender ulang lewat key.
const kunciInput = ref(0);

const pilih = (event: Event) => {
    form.clearErrors();
    form.bukti = (event.target as HTMLInputElement).files?.[0] ?? null;
};
const kirim = () =>
    form.post(route(props.rute, props.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            kunciInput.value++;
        },
    });
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <input
                :id="`bukti-${props.rute}-${props.id}`"
                :key="kunciInput"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                aria-label="Berkas bukti bayar"
                class="min-w-0 text-sm file:mr-3 file:rounded-full file:border file:border-[#dddddd] file:bg-white file:px-3 file:py-1.5 file:text-sm"
                @change="pilih"
            />
            <Button
                size="sm"
                class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                :disabled="!form.bukti || form.processing"
                @click="kirim"
                >{{ props.labelKirim ?? (props.adaBukti ? 'Ganti Bukti' : 'Kirim Bukti') }}</Button
            >
        </div>
        <InputError :message="form.errors.bukti" />
    </div>
</template>
