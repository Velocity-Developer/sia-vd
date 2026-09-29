<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import { computed } from 'vue';

type Opsi = { id: number; name: string };

const props = defineProps<{
    pengajuan: { id: number; nama: string | null; judul: string | null; pembimbing: string[] };
    dosenOptions: Opsi[];
    ruangOptions: Opsi[];
}>();
const emit = defineEmits<{ (e: 'tutup'): void }>();

const form = useForm({
    tanggal: '',
    jam_mulai: '',
    jam_akhir: '',
    ruang_id: null as number | null,
    penguji_1_id: null as number | null,
    penguji_2_id: null as number | null,
    penguji_3_id: null as number | null,
    abaikan_peringatan: false,
});

// Bentrok dengan jadwal mengajar penguji hanya peringatan: admin bisa tetap menyimpan.
const peringatan = computed<string[]>(() => {
    const e = form.errors as Record<string, string | undefined>;
    return Object.keys(e)
        .filter((k) => k === 'peringatan' || k.startsWith('peringatan.'))
        .map((k) => e[k] as string);
});

const simpan = (abaikan = false) => {
    form.abaikan_peringatan = abaikan;
    form.post(route('admin.pengajuan-akademik.setujui', props.pengajuan.id), { preserveScroll: true, onSuccess: () => emit('tutup') });
};

const penguji = [
    { kunci: 'penguji_1_id', label: 'Ketua penguji' },
    { kunci: 'penguji_2_id', label: 'Penguji 2' },
    { kunci: 'penguji_3_id', label: 'Penguji 3' },
] as const;
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/30 p-4" @click.self="emit('tutup')">
        <form class="kartu my-auto w-full max-w-2xl p-6" @submit.prevent="simpan(false)">
            <h3 class="judul-bagian">Setujui & jadwalkan pendadaran</h3>
            <p class="mt-1 text-sm text-[#615d59] dark:text-muted-foreground">
                {{ props.pengajuan.nama }} — {{ props.pengajuan.judul }}
                <span v-if="props.pengajuan.pembimbing.length" class="block text-xs">Pembimbing: {{ props.pengajuan.pembimbing.join(', ') }}</span>
            </p>

            <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                <div class="grid content-start gap-2">
                    <Label for="tanggal" class="label-isian">Tanggal</Label>
                    <Input id="tanggal" v-model="form.tanggal" type="date" required />
                    <InputError :message="form.errors.tanggal" />
                </div>
                <div class="grid content-start gap-2">
                    <Label for="jam_mulai" class="label-isian">Jam mulai</Label>
                    <Input id="jam_mulai" v-model="form.jam_mulai" type="time" required />
                    <InputError :message="form.errors.jam_mulai" />
                </div>
                <div class="grid content-start gap-2">
                    <Label for="jam_akhir" class="label-isian">Jam selesai</Label>
                    <Input id="jam_akhir" v-model="form.jam_akhir" type="time" required />
                    <InputError :message="form.errors.jam_akhir" />
                </div>
            </div>
            <div class="mt-4 grid gap-2">
                <Label for="ruang_id" class="label-isian">Ruang</Label>
                <SearchSelect
                    id="ruang_id"
                    v-model="form.ruang_id"
                    :options="props.ruangOptions"
                    placeholder="Pilih ruang"
                    search-placeholder="Cari ruang"
                    required
                />
                <InputError :message="form.errors.ruang_id" />
            </div>
            <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                <div v-for="p in penguji" :key="p.kunci" class="grid content-start gap-2">
                    <Label :for="p.kunci" class="label-isian">{{ p.label }}</Label>
                    <SearchSelect
                        :id="p.kunci"
                        v-model="form[p.kunci]"
                        :options="props.dosenOptions"
                        placeholder="Pilih dosen"
                        search-placeholder="Cari dosen"
                        required
                    />
                    <InputError :message="form.errors[p.kunci]" />
                </div>
            </div>
            <p class="teks-bantu mt-2">Pembimbing boleh menjadi penguji. Ketua penguji menetapkan hasil pendadaran.</p>

            <div v-if="peringatan.length" class="alert-gagal mt-4" role="alert">
                <p class="flex items-center gap-1.5 font-medium"><TriangleAlert class="size-4" /> Jadwal penguji bentrok dengan jadwal mengajar</p>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="p in peringatan" :key="p">{{ p }}</li>
                </ul>
                <p class="mt-2">Jadwal tetap bisa disimpan bila memang disengaja.</p>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" @click="emit('tutup')">Batal</Button>
                <Button v-if="peringatan.length" type="button" :disabled="form.processing" variant="destructive" @click="simpan(true)"
                    >Tetap Simpan</Button
                >
                <Button v-else type="submit" :disabled="form.processing">Setujui & Terbitkan Jadwal</Button>
            </div>
        </form>
    </div>
</template>
