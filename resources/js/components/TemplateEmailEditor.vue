<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { router, useForm } from '@inertiajs/vue3';
import { Eye, LoaderCircle, RotateCcw } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

interface IsiTemplate {
    subjek: string;
    sapaan: string | null;
    isi: string;
    tombol: string | null;
    penutup: string | null;
}

export interface Template {
    jenis: string;
    judul: string;
    keterangan: string;
    pakai_tombol: boolean;
    variabel: Record<string, string>;
    isi: IsiTemplate;
    bawaan: IsiTemplate;
    diubah: boolean;
}

type Kolom = keyof IsiTemplate;

const props = defineProps<{ template: Template[] }>();

const isian = (isi: IsiTemplate) => ({
    subjek: isi.subjek,
    sapaan: isi.sapaan ?? '',
    isi: isi.isi,
    tombol: isi.tombol ?? '',
    penutup: isi.penutup ?? '',
});

// Satu formulir per jenis surel, agar isian yang belum disimpan tidak hilang saat berpindah jenis.
const forms = reactive(Object.fromEntries(props.template.map((t) => [t.jenis, useForm(isian(t.isi))])));

const jenisAktif = ref(props.template[0]?.jenis ?? '');
const aktif = computed(() => props.template.find((t) => t.jenis === jenisAktif.value)!);
const form = computed(() => forms[jenisAktif.value]);

// Variabel disisipkan ke kolom yang terakhir disentuh, di posisi kursor.
const kolomTerakhir = ref<Kolom>('isi');
const elemen: Partial<Record<Kolom, HTMLInputElement | HTMLTextAreaElement>> = {};
const fokus = (kolom: Kolom, event: FocusEvent) => {
    kolomTerakhir.value = kolom;
    elemen[kolom] = event.target as HTMLInputElement | HTMLTextAreaElement;
};

const sisipkan = (teks: string) => {
    const kolom = kolomTerakhir.value;
    const el = elemen[kolom];
    const nilai = form.value[kolom] as string;
    const awal = el?.selectionStart ?? nilai.length;
    const akhir = el?.selectionEnd ?? nilai.length;
    form.value[kolom] = nilai.slice(0, awal) + teks + nilai.slice(akhir);
    requestAnimationFrame(() => {
        el?.focus();
        el?.setSelectionRange(awal + teks.length, awal + teks.length);
    });
};

const simpan = () =>
    form.value.put(route('pengaturan-email.template.update', jenisAktif.value), {
        preserveScroll: true,
        onSuccess: () => form.value.defaults(),
    });

const kembalikanBawaan = () => {
    if (!confirm(`Kembalikan template "${aktif.value.judul}" ke isi bawaan? Isi yang sudah diubah akan hilang.`)) return;

    const jenis = jenisAktif.value;
    router.delete(route('pengaturan-email.template.destroy', jenis), {
        preserveScroll: true,
        onSuccess: () => {
            const target = forms[jenis];
            Object.assign(target, isian(props.template.find((t) => t.jenis === jenis)!.bawaan));
            target.defaults();
            target.clearErrors();
        },
    });
};

const xsrfToken = () =>
    decodeURIComponent(
        document.cookie
            .split('; ')
            .find((baris) => baris.startsWith('XSRF-TOKEN='))
            ?.slice('XSRF-TOKEN='.length) ?? '',
    );

const pratinjau = ref<{ subjek: string; html: string } | null>(null);
const memuatPratinjau = ref(false);

const tampilkanPratinjau = async () => {
    memuatPratinjau.value = true;
    form.value.clearErrors();

    try {
        const respon = await fetch(route('pengaturan-email.template.pratinjau', jenisAktif.value), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify(form.value.data()),
        });
        const hasil = await respon.json();

        if (respon.status === 422) {
            form.value.setError(Object.fromEntries(Object.entries(hasil.errors as Record<string, string[]>).map(([k, v]) => [k, v[0]])));
            pratinjau.value = null;
        } else if (respon.ok) {
            pratinjau.value = hasil;
        } else {
            alert('Pratinjau gagal dimuat. Coba lagi.');
        }
    } catch {
        alert('Pratinjau gagal dimuat. Periksa koneksi lalu coba lagi.');
    } finally {
        memuatPratinjau.value = false;
    }
};

const pilihJenis = (jenis: string) => {
    jenisAktif.value = jenis;
    kolomTerakhir.value = 'isi';
    pratinjau.value = null;
};
</script>

<template>
    <div class="kartu grid gap-6 p-6">
        <HeadingSmall
            title="Template Email"
            description="Mengatur isi surel yang dikirim sistem. Variabel seperti {nama} diganti otomatis saat surel dikirim."
        />

        <div class="-mx-1 overflow-x-auto px-1">
            <div class="flex w-max gap-1 rounded-lg border border-[#e6e6e6] bg-white p-1 text-sm font-medium dark:border-border dark:bg-card">
                <button
                    v-for="t in props.template"
                    :key="t.jenis"
                    type="button"
                    class="flex h-9 items-center gap-2 whitespace-nowrap rounded-md px-4"
                    :class="
                        jenisAktif === t.jenis
                            ? 'bg-[#0075de] text-white'
                            : 'text-[#615d59] hover:bg-[#f6f5f4] dark:text-muted-foreground dark:hover:bg-accent'
                    "
                    :aria-pressed="jenisAktif === t.jenis"
                    @click="pilihJenis(t.jenis)"
                >
                    {{ t.judul }}
                    <span
                        v-if="t.diubah || forms[t.jenis].isDirty"
                        class="size-1.5 rounded-full"
                        :class="jenisAktif === t.jenis ? 'bg-white' : 'bg-[#0075de]'"
                        :title="forms[t.jenis].isDirty ? 'Ada perubahan yang belum disimpan' : 'Sudah diubah dari bawaan'"
                    />
                </button>
            </div>
        </div>

        <form class="grid gap-5" @submit.prevent="simpan">
            <p class="teks-bantu -mt-2">
                {{ aktif.keterangan }}
                <span v-if="aktif.diubah" class="font-medium text-[#0075de]">Sudah diubah dari bawaan.</span>
            </p>

            <div class="grid gap-2">
                <Label class="label-isian">Variabel</Label>
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="(label, kunci) in aktif.variabel"
                        :key="kunci"
                        type="button"
                        class="rounded-md border border-[#e6e6e6] bg-[#f6f5f4] px-2 py-1 font-mono text-xs text-[#31302e] hover:border-[#0075de] hover:text-[#0075de] dark:border-border dark:bg-accent dark:text-foreground"
                        :title="`${label} — klik untuk menyisipkan`"
                        @click="sisipkan(`{${kunci}}`)"
                    >
                        {{ '{' + kunci + '}' }}
                    </button>
                    <button
                        v-if="aktif.pakai_tombol"
                        type="button"
                        class="rounded-md border border-dashed border-[#0075de] px-2 py-1 font-mono text-xs text-[#0075de] hover:bg-[#f2f9ff] dark:hover:bg-accent"
                        title="Letak tombol tautan — tulis di baris tersendiri di dalam isi"
                        @click="sisipkan('\n\n{tombol}\n\n')"
                    >
                        {tombol}
                    </button>
                </div>
                <p class="teks-bantu">
                    Klik variabel untuk menyisipkannya di posisi kursor.
                    <template v-if="aktif.pakai_tombol">
                        <code>{tombol}</code> di baris tersendiri menandai letak tombol tautan; bila tidak ada, tombol diletakkan di akhir isi.
                    </template>
                </p>
            </div>

            <div class="grid content-start gap-4 sm:grid-cols-2">
                <div class="grid content-start gap-2">
                    <Label class="label-isian" for="template_subjek">Subjek</Label>
                    <Input id="template_subjek" v-model="form.subjek" maxlength="255" @focus="fokus('subjek', $event)" />
                    <InputError :message="form.errors.subjek" />
                </div>
                <div class="grid content-start gap-2">
                    <Label class="label-isian" for="template_sapaan">Sapaan</Label>
                    <Input
                        id="template_sapaan"
                        v-model="form.sapaan"
                        maxlength="255"
                        placeholder="Kosongkan bila tanpa sapaan"
                        @focus="fokus('sapaan', $event)"
                    />
                    <InputError :message="form.errors.sapaan" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label class="label-isian" for="template_isi">Isi Surel</Label>
                <textarea
                    id="template_isi"
                    v-model="form.isi"
                    rows="10"
                    maxlength="5000"
                    class="isian isian-area font-mono text-sm"
                    @focus="fokus('isi', $event)"
                />
                <p class="teks-bantu">Pisahkan paragraf dengan satu baris kosong. Mendukung Markdown sederhana, misalnya **tebal**.</p>
                <InputError :message="form.errors.isi" />
            </div>

            <div class="grid content-start gap-4 sm:grid-cols-2">
                <div v-if="aktif.pakai_tombol" class="grid content-start gap-2">
                    <Label class="label-isian" for="template_tombol">Teks Tombol</Label>
                    <Input id="template_tombol" v-model="form.tombol" maxlength="100" @focus="fokus('tombol', $event)" />
                    <InputError :message="form.errors.tombol" />
                </div>
                <div class="grid content-start gap-2">
                    <Label class="label-isian" for="template_penutup">Penutup</Label>
                    <textarea
                        id="template_penutup"
                        v-model="form.penutup"
                        rows="2"
                        maxlength="500"
                        class="isian isian-area"
                        placeholder="Kosongkan bila tanpa penutup"
                        @focus="fokus('penutup', $event)"
                    />
                    <InputError :message="form.errors.penutup" />
                </div>
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <Button v-if="aktif.diubah" type="button" variant="outline" :disabled="form.processing" @click="kembalikanBawaan">
                    <RotateCcw /> Kembalikan Bawaan
                </Button>
                <Button type="button" variant="outline" :disabled="memuatPratinjau" @click="tampilkanPratinjau">
                    <LoaderCircle v-if="memuatPratinjau" class="animate-spin" />
                    <Eye v-else />
                    Pratinjau
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Simpan Template
                </Button>
            </div>
        </form>

        <div v-if="pratinjau" class="grid gap-2">
            <div class="flex items-baseline justify-between gap-3">
                <Label class="label-isian">Pratinjau</Label>
                <button type="button" class="text-sm text-[#615d59] hover:underline dark:text-muted-foreground" @click="pratinjau = null">
                    Tutup
                </button>
            </div>
            <p class="text-sm"><span class="text-[#615d59] dark:text-muted-foreground">Subjek:</span> {{ pratinjau.subjek }}</p>
            <!-- sandbox kosong: skrip dan tautan di dalam surel tidak dijalankan. -->
            <iframe
                :srcdoc="pratinjau.html"
                sandbox=""
                title="Pratinjau surel"
                class="h-[520px] w-full rounded-lg border border-[#e6e6e6] bg-white dark:border-border"
            />
            <p class="teks-bantu">Memakai nilai contoh untuk tiap variabel. Isian belum disimpan sampai Anda menekan Simpan Template.</p>
        </div>
    </div>
</template>
