<script setup lang="ts">
import { Button } from '@/components/ui/button';

withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        confirmText?: string;
        cancelText?: string;
        loading?: boolean;
    }>(),
    {
        title: 'Hapus data?',
        description: 'Anda yakin ingin menghapus data ini?',
        confirmText: 'Ya',
        cancelText: 'Batal',
        loading: false,
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

const close = () => {
    emit('update:open', false);
    emit('cancel');
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]" @click="close" />
                <div
                    class="relative w-full max-w-sm rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_8px_28px_rgba(0,0,0,0.08),0_23px_52px_rgba(0,0,0,0.08)]"
                    @keydown.esc="close"
                >
                    <h2 class="text-[15px] font-semibold leading-5 text-black">{{ title }}</h2>
                    <p class="mt-2 text-sm leading-5 text-[#615d59]">{{ description }}</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button
                            v-if="cancelText"
                            variant="outline"
                            class="rounded-full border-[#e6e6e6] bg-white text-black hover:bg-[#f6f5f4]"
                            @click="close"
                        >
                            {{ cancelText }}
                        </Button>
                        <Button class="rounded-full bg-[#0075de] px-6 text-white hover:bg-[#005bab]" :disabled="loading" @click="emit('confirm')">
                            {{ confirmText }}
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
