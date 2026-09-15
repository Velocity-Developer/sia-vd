<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import InputError from '@/components/InputError.vue';
import SearchSelect from '@/components/SearchSelect.vue';
import TimePicker from '@/components/TimePicker.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{
    kelasKuliah: Record<string, any>;
    jadwal: Record<string, any> | null;
    ruangs: { id: number; name: string }[];
}>();

const title = `${props.jadwal ? 'Edit' : 'Tambah'} Jadwal`;
const hariOptions = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

const toHHMM = (time: string) => (time ?? '').slice(0, 5);

const form = useForm({
    hari: props.jadwal?.hari ?? '',
    jam_mulai: toHHMM(props.jadwal?.jam_mulai ?? ''),
    jam_akhir: toHHMM(props.jadwal?.jam_akhir ?? ''),
    ruang_id: props.jadwal?.ruang_id ?? '',
});

const submit = () =>
    props.jadwal
        ? form.put(route('admin.kelas-kuliah.jadwal.update', [props.kelasKuliah.id, props.jadwal.id]))
        : form.post(route('admin.kelas-kuliah.jadwal.store', props.kelasKuliah.id));

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
const sel =
    'h-10 rounded-[4px] border border-[#dddddd] bg-white px-3 text-[15px] text-black focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]';
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Kelas Kuliah', href: route('admin.kelas-kuliah.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">
                            Kelas {{ props.kelasKuliah?.kode_kelas }} — lengkapi hari, jam mulai, jam akhir, dan ruang.
                        </p>
                    </div>
                    <Link :href="route('admin.kelas-kuliah.show', props.kelasKuliah.id)"
                        ><Button variant="outline" class="rounded-lg border-[#e6e6e6] bg-white text-black hover:bg-white">Kembali</Button></Link
                    >
                </div>

                <div
                    v-if="page.props.flash?.success"
                    class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#1aae39] shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01)]"
                    role="alert"
                >
                    {{ page.props.flash.success }}
                </div>
                <div v-if="page.props.flash?.error" class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]" role="alert">
                    {{ page.props.flash.error }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <section class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]">
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Jadwal</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-3">
                            <div class="grid gap-2">
                                <Label for="hari" class="text-sm font-medium text-black">Hari</Label>
                                <select id="hari" v-model="form.hari" :class="sel" required>
                                    <option value="">Pilih hari</option>
                                    <option v-for="hari in hariOptions" :key="hari" :value="hari">{{ hari }}</option>
                                </select>
                                <InputError :message="form.errors.hari" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="jam_mulai" class="text-sm font-medium text-black">Jam Mulai</Label>
                                <TimePicker id="jam_mulai" v-model="form.jam_mulai" :class="inp" required />
                                <InputError :message="form.errors.jam_mulai" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="jam_akhir" class="text-sm font-medium text-black">Jam Akhir</Label>
                                <TimePicker id="jam_akhir" v-model="form.jam_akhir" :class="inp" required />
                                <InputError :message="form.errors.jam_akhir" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="ruang_id" class="text-sm font-medium text-black">Ruang</Label>
                            <SearchSelect
                                id="ruang_id"
                                v-model="form.ruang_id"
                                :options="props.ruangs"
                                placeholder="Pilih ruang"
                                search-placeholder="Cari ruang (kode / nama)"
                                required
                            />
                            <InputError :message="form.errors.ruang_id" />
                        </div>
                    </section>

                    <div class="flex justify-end pt-2">
                        <Button :disabled="form.processing" class="rounded-full bg-[#0075de] px-8 text-white hover:bg-[#005bab]">Simpan</Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
