<script lang="ts" setup>
import type { CalendarHeadingProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { computed } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { CalendarHeading, useForwardProps, injectCalendarRootContext } from "reka-ui"
import { cn } from "@/lib/utils"

const props = defineProps<CalendarHeadingProps & { class?: HTMLAttributes["class"] }>()

defineSlots<{
  default: (props: { headingValue: string }) => any
}>()

const delegatedProps = reactiveOmit(props, "class")

const forwardedProps = useForwardProps(delegatedProps)
const calendar = injectCalendarRootContext()
const months = Array.from({ length: 12 }, (_, index) => index + 1)
const years = computed(() => {
  const current = calendar.placeholder.value.year
  return Array.from({ length: 121 }, (_, index) => current - 60 + index)
})
const monthName = (month: number) => new Intl.DateTimeFormat(calendar.locale.value, { month: 'long' }).format(new Date(2024, month - 1, 1))
const changePlaceholder = (part: 'month' | 'year', value: string) => calendar.onPlaceholderChange(calendar.placeholder.value.set({ [part]: Number(value) }))
</script>

<template>
  <CalendarHeading :class="cn('flex items-center gap-1 text-sm font-medium', props.class)" v-bind="forwardedProps">
    <select :value="calendar.placeholder.value.month" class="rounded-md border-0 bg-transparent px-1 py-1 text-sm font-medium focus:ring-1 focus:ring-ring" aria-label="Pilih bulan" @change="changePlaceholder('month', ($event.target as HTMLSelectElement).value)">
      <option v-for="month in months" :key="month" :value="month">{{ monthName(month) }}</option>
    </select>
    <select :value="calendar.placeholder.value.year" class="rounded-md border-0 bg-transparent px-1 py-1 text-sm font-medium focus:ring-1 focus:ring-ring" aria-label="Pilih tahun" @change="changePlaceholder('year', ($event.target as HTMLSelectElement).value)">
      <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
    </select>
  </CalendarHeading>
</template>
