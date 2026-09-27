<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';
import { Paperclip } from 'lucide-vue-next';

const props = defineProps<{
    /** Id input sekaligus kunci berkas di lampiran pengajuan. */
    id: string;
    label: string;
    accept: string;
    /** Pengajuan yang berkasnya sudah dikirim (saat perbaikan/menunggu), untuk tautan berkas lama. */
    pengajuanId?: number | null;
    sudahAda?: boolean;
    wajib: boolean;
    /** Form sedang terkunci (menunggu keputusan): hanya tautan berkas lama yang tampil. */
    terkunci?: boolean;
    error?: string;
}>();

const emit = defineEmits<{ (e: 'pilih', berkas: File | null): void }>();
</script>

<template>
    <div class="grid content-start gap-2">
        <Label :for="props.terkunci ? undefined : props.id">{{ props.label }}</Label>
        <a
            v-if="props.pengajuanId && props.sudahAda"
            :href="route('berkas.pengajuan-akademik', [props.pengajuanId, props.id])"
            target="_blank"
            rel="noopener"
            class="inline-flex items-center gap-1 text-sm text-[#0075de] hover:underline"
            ><Paperclip class="size-3.5" /> Berkas yang sudah dikirim</a
        >
        <input
            v-if="!props.terkunci"
            :id="props.id"
            type="file"
            :accept="props.accept"
            class="text-sm file:mr-3 file:rounded-full file:border-0 file:bg-[#f2f9ff] file:px-4 file:py-2 file:text-sm file:font-medium file:text-[#0075de]"
            :required="props.wajib"
            @change="emit('pilih', ($event.target as HTMLInputElement).files?.[0] ?? null)"
        />
        <span v-if="props.sudahAda && !props.wajib && !props.terkunci" class="text-xs text-[#615d59]">Kosongkan bila tidak perlu diganti.</span>
        <InputError :message="props.error" />
    </div>
</template>
