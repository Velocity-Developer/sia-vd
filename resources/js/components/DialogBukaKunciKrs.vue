<script setup lang="ts">
import DatePicker from '@/components/DatePicker.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { formatTanggal } from '@/lib/presensi';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/**
 * Form kembalikan KRS untuk revisi / buka kunci KRS oleh admin. Setelah masa revisi semester habis,
 * tanggal "Dibuka sampai" wajib agar mahasiswa tetap bisa mengubah KRS.
 */
const props = defineProps<{
    judul: string;
    nama: string;
    url: string;
    metode: 'post' | 'delete';
    masaRevisiBerjalan: boolean;
    batasRevisi: string | null;
    catatanWajib?: boolean;
    data?: Record<string, unknown>;
}>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm({ catatan: '', dibuka_sampai: '' });
const hariIni = new Date().toLocaleDateString('en-CA');

watch(open, (buka) => {
    if (!buka) return;
    form.reset();
    form.clearErrors();
});

const kirim = () =>
    form
        .transform((data) => ({ ...props.data, catatan: data.catatan || null, dibuka_sampai: data.dibuka_sampai || null }))
        .submit(props.metode, props.url, { preserveScroll: true, onSuccess: () => (open.value = false) });
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/30 p-4" @click.self="open = false">
        <form class="kartu w-full max-w-md p-6 shadow-xl" @submit.prevent="kirim">
            <h3 class="judul-bagian">{{ judul }}</h3>
            <p class="mt-2 text-sm text-[#615d59] dark:text-muted-foreground">
                {{ nama }} bisa mengubah KRS lalu menyimpannya lagi.
                <template v-if="masaRevisiBerjalan && batasRevisi"
                    >Tanpa tanggal khusus, KRS terbuka sampai {{ formatTanggal(batasRevisi, false) }}.</template
                >
                <template v-else>Masa KRS dan revisi tidak sedang berjalan, jadi isi batas tanggalnya.</template>
            </p>
            <label class="mt-4 grid gap-2">
                <span class="label-isian">Catatan{{ catatanWajib ? '' : ' (opsional)' }}</span>
                <textarea
                    v-model="form.catatan"
                    rows="3"
                    maxlength="1000"
                    class="isian isian-area"
                    placeholder="Mis. ganti kelas Anatomi ke kelas B, SKS melebihi batas"
                    :required="catatanWajib"
                />
                <span class="teks-bantu">Ditampilkan ke mahasiswa di halaman KRS.</span>
                <InputError :message="form.errors.catatan" />
            </label>
            <div class="mt-4 grid gap-2">
                <span class="label-isian">Dibuka sampai{{ masaRevisiBerjalan ? ' (opsional)' : '' }}</span>
                <DatePicker v-model="form.dibuka_sampai" :min-value="hariIni" :required="!masaRevisiBerjalan" placeholder="Pilih tanggal" />
                <InputError :message="form.errors.dibuka_sampai" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <Button type="button" variant="outline" @click="open = false">Batal</Button>
                <Button type="submit" :disabled="form.processing">{{ judul }}</Button>
            </div>
        </form>
    </div>
</template>
