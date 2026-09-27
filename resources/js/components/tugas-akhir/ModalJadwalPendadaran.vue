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
const inp = 'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px]';
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/30 p-4" @click.self="emit('tutup')">
        <form class="my-auto w-full max-w-2xl rounded-xl bg-white p-6 shadow-xl" @submit.prevent="simpan(false)">
            <h3 class="text-lg font-semibold">Setujui & jadwalkan pendadaran</h3>
            <p class="mt-1 text-sm text-[#615d59]">
                {{ props.pengajuan.nama }} — {{ props.pengajuan.judul }}
                <span v-if="props.pengajuan.pembimbing.length" class="block text-xs">Pembimbing: {{ props.pengajuan.pembimbing.join(', ') }}</span>
            </p>

            <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                <div class="grid content-start gap-2">
                    <Label for="tanggal">Tanggal</Label>
                    <Input id="tanggal" v-model="form.tanggal" type="date" :class="inp" required />
                    <InputError :message="form.errors.tanggal" />
                </div>
                <div class="grid content-start gap-2">
                    <Label for="jam_mulai">Jam mulai</Label>
                    <Input id="jam_mulai" v-model="form.jam_mulai" type="time" :class="inp" required />
                    <InputError :message="form.errors.jam_mulai" />
                </div>
                <div class="grid content-start gap-2">
                    <Label for="jam_akhir">Jam selesai</Label>
                    <Input id="jam_akhir" v-model="form.jam_akhir" type="time" :class="inp" required />
                    <InputError :message="form.errors.jam_akhir" />
                </div>
            </div>
            <div class="mt-4 grid gap-2">
                <Label for="ruang_id">Ruang</Label>
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
                    <Label :for="p.kunci">{{ p.label }}</Label>
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
            <p class="mt-2 text-xs text-[#615d59]">Pembimbing boleh menjadi penguji. Ketua penguji menetapkan hasil pendadaran.</p>

            <div v-if="peringatan.length" class="mt-4 rounded-lg border border-[#f5d0b5] bg-[#fff8f2] px-4 py-3 text-sm text-[#b25000]" role="alert">
                <p class="flex items-center gap-1.5 font-medium"><TriangleAlert class="size-4" /> Jadwal penguji bentrok dengan jadwal mengajar</p>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="p in peringatan" :key="p">{{ p }}</li>
                </ul>
                <p class="mt-2">Jadwal tetap bisa disimpan bila memang disengaja.</p>
            </div>

            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" class="rounded-full" @click="emit('tutup')">Batal</Button>
                <Button
                    v-if="peringatan.length"
                    type="button"
                    :disabled="form.processing"
                    class="rounded-full bg-[#b25000] text-white hover:bg-[#8f4000]"
                    @click="simpan(true)"
                    >Tetap Simpan</Button
                >
                <Button v-else type="submit" :disabled="form.processing" class="rounded-full bg-[#0075de] text-white hover:bg-[#005bab]"
                    >Setujui & Terbitkan Jadwal</Button
                >
            </div>
        </form>
    </div>
</template>
