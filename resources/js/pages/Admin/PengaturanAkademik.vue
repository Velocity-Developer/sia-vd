<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';

type BatasSks = { ips_minimal: number | string; maks_sks: number | string };
type SkalaNilai = { huruf: string; bobot: number | string; lulus: boolean; boleh_diulang: boolean; dipakai?: number };

const props = defineProps<{ maksSksTanpaIps: number; batasSks: BatasSks[]; skalaNilai: SkalaNilai[] }>();
const page = usePage<{ flash?: { success?: string; error?: string } }>();

const sksForm = useForm({
    maks_sks_tanpa_ips: props.maksSksTanpaIps,
    batas_sks: props.batasSks.map((row) => ({ ...row })),
});

const nilaiForm = useForm({
    skala_nilai: props.skalaNilai.map(({ huruf, bobot, lulus, boleh_diulang }) => ({ huruf, bobot, lulus, boleh_diulang })),
});

const dipakai = (huruf: string) => props.skalaNilai.find((row) => row.huruf === huruf.toUpperCase())?.dipakai ?? 0;
const errorOf = (form: { errors: object }, key: string) => (form.errors as Record<string, string | undefined>)[key];

const saveSks = () => sksForm.put(route('admin.pengaturan-akademik.batas-sks'), { preserveScroll: true });
const saveNilai = () => nilaiForm.put(route('admin.pengaturan-akademik.skala-nilai'), { preserveScroll: true });

const inp = 'h-9 rounded-lg border-[#dddddd]';
</script>

<template>
    <Head title="Pengaturan Akademik" />
    <AppLayout :breadcrumbs="[{ title: 'Pengaturan Akademik', href: route('admin.pengaturan-akademik.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto flex w-full max-w-[1000px] flex-col gap-6 px-4 py-6 sm:px-6 lg:px-8">
                <div class="space-y-1">
                    <h1 class="text-[26px] font-bold">Pengaturan Akademik</h1>
                    <p class="text-sm text-[#615d59]">Batas SKS saat mengisi KRS dan skala nilai untuk KHS serta transkrip.</p>
                </div>

                <div v-if="page.props.flash?.success" class="rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39]">
                    {{ page.props.flash.success }}
                </div>

                <form class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm" @submit.prevent="saveSks">
                    <h2 class="text-lg font-semibold text-black">Batas SKS per Semester</h2>
                    <p class="mt-1 text-sm text-[#615d59]">
                        Ditentukan dari IPS semester terakhir mahasiswa yang sudah bernilai. Baris dengan IPS minimal tertinggi yang terpenuhi yang
                        dipakai.
                    </p>

                    <div class="relative mt-5 overflow-x-auto rounded-xl border border-[#e6e6e6]">
                        <table class="w-full min-w-[420px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">IPS minimal</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Maks SKS</th>
                                    <th class="w-12 px-4 py-3"><span class="sr-only">Hapus</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(row, index) in sksForm.batas_sks" :key="index">
                                    <td class="px-4 py-2">
                                        <Input
                                            v-model="row.ips_minimal"
                                            type="number"
                                            min="0"
                                            max="4"
                                            step="0.01"
                                            :class="inp"
                                            aria-label="IPS minimal"
                                        />
                                        <InputError :message="errorOf(sksForm, `batas_sks.${index}.ips_minimal`)" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <Input v-model="row.maks_sks" type="number" min="1" max="40" :class="inp" aria-label="Maks SKS" />
                                        <InputError :message="errorOf(sksForm, `batas_sks.${index}.maks_sks`)" />
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            aria-label="Hapus baris"
                                            :disabled="sksForm.batas_sks.length === 1"
                                            @click="sksForm.batas_sks.splice(index, 1)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <InputError class="mt-2" :message="sksForm.errors.batas_sks" />
                    <Button type="button" variant="outline" size="sm" class="mt-3" @click="sksForm.batas_sks.push({ ips_minimal: '', maks_sks: '' })">
                        <Plus class="size-4" /> Tambah baris
                    </Button>

                    <div class="mt-6 grid max-w-xs gap-2">
                        <Label for="maks_sks_tanpa_ips">Maks SKS bila belum ada IPS</Label>
                        <Input id="maks_sks_tanpa_ips" v-model="sksForm.maks_sks_tanpa_ips" type="number" min="1" max="40" :class="inp" />
                        <p class="text-xs text-[#a39e98]">Untuk mahasiswa baru atau yang nilai semester lalunya belum keluar.</p>
                        <InputError :message="sksForm.errors.maks_sks_tanpa_ips" />
                    </div>

                    <div class="mt-6 flex justify-end">
                        <Button type="submit" class="bg-[#0075de] text-white hover:bg-[#005bab]" :disabled="sksForm.processing"
                            >Simpan Batas SKS</Button
                        >
                    </div>
                </form>

                <form class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-sm" @submit.prevent="saveNilai">
                    <h2 class="text-lg font-semibold text-black">Skala Nilai</h2>
                    <p class="mt-1 text-sm text-[#615d59]">
                        Dipakai untuk pilihan nilai di kelas, IP/IPK, KHS, dan transkrip. Mengubah bobot akan mengubah IP/IPK semua mahasiswa yang
                        memiliki nilai tersebut.
                    </p>

                    <div class="relative mt-5 overflow-x-auto rounded-xl border border-[#e6e6e6]">
                        <table class="w-full min-w-[560px] text-left">
                            <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]">
                                <tr>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Huruf</th>
                                    <th class="px-4 py-3 text-xs font-semibold uppercase text-[#a39e98]">Bobot</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-[#a39e98]">Lulus</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase text-[#a39e98]">Boleh diulang</th>
                                    <th class="w-12 px-4 py-3"><span class="sr-only">Hapus</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#e6e6e6]">
                                <tr v-for="(row, index) in nilaiForm.skala_nilai" :key="index">
                                    <td class="px-4 py-2">
                                        <Input v-model="row.huruf" maxlength="2" :class="[inp, 'w-20 uppercase']" aria-label="Huruf" />
                                        <InputError :message="errorOf(nilaiForm, `skala_nilai.${index}.huruf`)" />
                                    </td>
                                    <td class="px-4 py-2">
                                        <Input
                                            v-model="row.bobot"
                                            type="number"
                                            min="0"
                                            max="4"
                                            step="0.01"
                                            :class="[inp, 'w-28']"
                                            aria-label="Bobot"
                                        />
                                        <InputError :message="errorOf(nilaiForm, `skala_nilai.${index}.bobot`)" />
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <input v-model="row.lulus" type="checkbox" class="size-4 accent-[#0075de]" aria-label="Lulus" />
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <input
                                            v-model="row.boleh_diulang"
                                            type="checkbox"
                                            class="size-4 accent-[#0075de]"
                                            aria-label="Boleh diulang"
                                        />
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            :aria-label="dipakai(row.huruf) ? `Nilai ${row.huruf} dipakai ${dipakai(row.huruf)} KRS` : 'Hapus baris'"
                                            :title="dipakai(row.huruf) ? `Dipakai ${dipakai(row.huruf)} KRS, tidak bisa dihapus` : 'Hapus baris'"
                                            :disabled="dipakai(row.huruf) > 0 || nilaiForm.skala_nilai.length === 1"
                                            @click="nilaiForm.skala_nilai.splice(index, 1)"
                                        >
                                            <Trash2 class="size-4" />
                                        </Button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <InputError class="mt-2" :message="nilaiForm.errors.skala_nilai" />
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        @click="nilaiForm.skala_nilai.push({ huruf: '', bobot: '', lulus: true, boleh_diulang: false })"
                    >
                        <Plus class="size-4" /> Tambah nilai
                    </Button>

                    <div class="mt-6 flex justify-end">
                        <Button type="submit" class="bg-[#0075de] text-white hover:bg-[#005bab]" :disabled="nilaiForm.processing"
                            >Simpan Skala Nilai</Button
                        >
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
