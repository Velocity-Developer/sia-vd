<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { ImageUp, Trash2, Undo2, UserRound } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';

// Pilih foto profil: berkas baru dikirim lewat v-model, "hapus" lewat v-model:hapus untuk mengosongkan foto tersimpan.
const props = defineProps<{ urlTersimpan?: string | null; error?: string }>();
const berkas = defineModel<File | null>({ default: null });
const hapus = defineModel<boolean>('hapus', { default: false });

const input = ref<HTMLInputElement | null>(null);
const urlBaru = ref<string | null>(null);

const pratinjau = computed(() => urlBaru.value ?? (hapus.value ? null : (props.urlTersimpan ?? null)));

const lepasUrlBaru = () => {
    if (urlBaru.value) URL.revokeObjectURL(urlBaru.value);
    urlBaru.value = null;
};

const pilih = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    lepasUrlBaru();
    berkas.value = file;
    urlBaru.value = file ? URL.createObjectURL(file) : null;
    if (file) hapus.value = false;
};

const batalkanBaru = () => {
    lepasUrlBaru();
    berkas.value = null;
    if (input.value) input.value.value = '';
};

onBeforeUnmount(lepasUrlBaru);
</script>

<template>
    <div class="grid gap-2">
        <Label for="foto" class="label-isian">Foto</Label>
        <input id="foto" ref="input" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="pilih" />
        <div class="flex items-center gap-4">
            <div
                class="flex size-24 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[#e6e6e6] bg-[#f6f5f4] dark:border-border dark:bg-muted"
            >
                <img v-if="pratinjau" :src="pratinjau" alt="Foto" class="size-full object-cover" />
                <UserRound v-else class="size-10 text-[#a39e98]" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button type="button" variant="outline" @click="input?.click()"><ImageUp /> {{ pratinjau ? 'Ganti Foto' : 'Pilih Foto' }}</Button>
                <Button v-if="berkas" type="button" variant="outline" @click="batalkanBaru"><Undo2 /> Batal</Button>
                <Button v-else-if="props.urlTersimpan && !hapus" type="button" variant="outline" class="text-[#dd5b00]" @click="hapus = true">
                    <Trash2 /> Hapus Foto
                </Button>
                <Button v-else-if="props.urlTersimpan && hapus" type="button" variant="outline" @click="hapus = false"><Undo2 /> Batal Hapus</Button>
            </div>
        </div>
        <p class="teks-bantu">Format jpg, jpeg, png, atau webp. Maksimal 2 MB.<span v-if="hapus"> Foto akan dihapus saat disimpan.</span></p>
        <InputError :message="props.error" />
    </div>
</template>
