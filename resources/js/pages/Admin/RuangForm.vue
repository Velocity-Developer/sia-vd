<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<{ flash: { success?: string; error?: string } }>();

const props = defineProps<{ ruang: Record<string, any> | null }>();

const title = `${props.ruang ? 'Edit' : 'Tambah'} Ruang`;

const form = useForm({
    kode_ruang: props.ruang?.kode_ruang ?? '',
    nama_ruang: props.ruang?.nama_ruang ?? '',
    kapasitas: props.ruang?.kapasitas ?? '',
    detail: props.ruang?.detail ?? '',
});

const submit = () => (props.ruang ? form.put(route('admin.ruang.update', props.ruang.id)) : form.post(route('admin.ruang.store')));

const inp =
    'h-10 rounded-[4px] border-[#dddddd] bg-white text-[15px] text-black placeholder:text-[#a39e98] focus-visible:ring-1 focus-visible:ring-[#0075de] focus-visible:ring-offset-0';
</script>

<template>
    <Head :title="title" />
    <AppLayout :breadcrumbs="[{ title: 'Ruang', href: route('admin.ruang.index') }]">
        <div class="min-h-full bg-[#f6f5f4]">
            <div class="mx-auto w-full max-w-[1000px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <h1 class="text-[26px] font-bold leading-[1.23] tracking-[-0.625px] text-black">{{ title }}</h1>
                        <p class="max-w-xl text-sm leading-5 text-[#615d59]">Lengkapi kode, nama, kapasitas, dan detail fasilitas ruang.</p>
                    </div>
                    <Link :href="route('admin.ruang.index')"
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
                <div
                    v-if="page.props.flash?.error"
                    class="mb-4 rounded-xl border border-[#e6e6e6] bg-white px-4 py-3 text-sm text-[#dd5b00]"
                    role="alert"
                >
                    {{ page.props.flash.error }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <section
                        class="rounded-xl border border-[#e6e6e6] bg-white p-6 shadow-[0_0.175px_1.041px_rgba(0,0,0,0.01),0_0.8px_2.925px_rgba(0,0,0,0.02)]"
                    >
                        <h2 class="text-xs font-semibold uppercase tracking-[0.08em] text-[#a39e98]">Data Ruang</h2>
                        <div class="mt-4 grid items-start gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="kode_ruang" class="text-sm font-medium text-black">Kode Ruang</Label>
                                <Input id="kode_ruang" v-model="form.kode_ruang" type="text" :class="inp" required />
                                <InputError :message="form.errors.kode_ruang" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="nama_ruang" class="text-sm font-medium text-black">Nama Ruang</Label>
                                <Input id="nama_ruang" v-model="form.nama_ruang" type="text" :class="inp" required />
                                <InputError :message="form.errors.nama_ruang" />
                            </div>
                        </div>
                        <div class="mt-4 grid gap-2 sm:max-w-[240px]">
                            <Label for="kapasitas" class="text-sm font-medium text-black">Kapasitas</Label>
                            <Input id="kapasitas" v-model="form.kapasitas" type="number" min="1" max="1000" :class="inp" required />
                            <InputError :message="form.errors.kapasitas" />
                        </div>
                        <div class="mt-4 grid gap-2">
                            <Label for="detail" class="text-sm font-medium text-black">Detail</Label>
                            <textarea
                                id="detail"
                                v-model="form.detail"
                                rows="4"
                                placeholder="Gedung, lantai, fasilitas (AC, proyektor, dll)"
                                class="rounded-[4px] border border-[#dddddd] bg-white px-3 py-2 text-[15px] text-black placeholder:text-[#a39e98] focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-[#0075de]"
                            />
                            <InputError :message="form.errors.detail" />
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
