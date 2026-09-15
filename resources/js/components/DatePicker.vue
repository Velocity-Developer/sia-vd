<script setup lang="ts">
import { computed, ref } from 'vue';
import { DateFormatter, getLocalTimeZone, parseDate, today, type DateValue } from '@internationalized/date';
import { CalendarIcon } from 'lucide-vue-next';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = withDefaults(defineProps<{ id?: string; modelValue: string; placeholder?: string; required?: boolean; minValue?: string }>(), { required: true });
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void }>();

const open = ref(false);
const formatter = new DateFormatter('id-ID', { dateStyle: 'long' });

const selected = computed<DateValue | undefined>(() => (props.modelValue ? parseDate(props.modelValue) : undefined));
const display = computed(() => (selected.value ? formatter.format(selected.value.toDate(getLocalTimeZone())) : props.placeholder ?? 'Pilih tanggal'));

const handleSelect = (date: DateValue | undefined) => {
    if (date) {
        emit('update:modelValue', date.toString());
        open.value = false;
    }
};

const defaultPlaceholder = today(getLocalTimeZone());
const minimumDate = computed(() => (props.minValue ? parseDate(props.minValue) : undefined));
</script>

<template>
    <Popover v-model:open="open">
        <PopoverTrigger as-child>
            <Button
                :id="props.id"
                type="button"
                variant="outline"
                :class="cn('w-full justify-start gap-2 text-left font-normal', !selected && 'text-muted-foreground')"
            >
                <CalendarIcon class="size-4" />
                {{ display }}
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0" align="start">
            <Calendar :model-value="selected" :default-placeholder="defaultPlaceholder" :min-value="minimumDate" locale="id-ID" initial-focus @update:model-value="handleSelect" />
            <div v-if="selected" class="border-t p-2">
                <Button type="button" variant="ghost" class="w-full" @click="emit('update:modelValue', ''); open = false">Hapus tanggal</Button>
            </div>
        </PopoverContent>
    </Popover>
</template>
