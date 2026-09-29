<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        description?: string;
        confirmText?: string;
        cancelText?: string;
        loading?: boolean;
        /** Tombol konfirmasi merah (aksi merusak). Bila tidak diisi, ditebak dari judul/teks tombol (Hapus, Batalkan, Tolak). */
        destructive?: boolean;
    }>(),
    {
        title: 'Hapus data?',
        description: 'Anda yakin ingin menghapus data ini?',
        confirmText: 'Ya',
        cancelText: 'Batal',
        loading: false,
        destructive: undefined,
    },
);

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'confirm'): void;
    (e: 'cancel'): void;
}>();

// Aksi merusak (hapus/batalkan/tolak) memakai tombol merah; lainnya tombol utama biru.
const polaMerusak = /^(hapus|batalkan|tolak)/i;
const merusak = computed(() => props.destructive ?? (polaMerusak.test(props.title) || polaMerusak.test(props.confirmText)));

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
                <div class="kartu relative w-full max-w-sm p-6 shadow-lg" @keydown.esc="close">
                    <h2 class="judul-bagian">{{ title }}</h2>
                    <p class="mt-2 text-sm leading-5 text-[#615d59] dark:text-muted-foreground">{{ description }}</p>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button v-if="cancelText" variant="outline" @click="close">
                            {{ cancelText }}
                        </Button>
                        <Button :variant="merusak ? 'destructive' : 'default'" :disabled="loading" @click="emit('confirm')">
                            {{ confirmText }}
                        </Button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
