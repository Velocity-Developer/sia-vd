import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#0075de]/30 disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0",
  {
    variants: {
      variant: {
        // Warna tombol aplikasi (docs/standar-ui.md); halaman tidak perlu menimpa warna/radius lagi.
        default: "bg-[#0075de] text-white shadow-sm hover:bg-[#005bab]",
        destructive: "bg-[#dd5b00] text-white shadow-sm hover:bg-[#b84b00]",
        outline:
          "border border-[#d8d5d2] bg-white text-[#31302e] shadow-sm hover:bg-[#f6f5f4] dark:border-border dark:bg-background dark:text-foreground dark:hover:bg-accent",
        secondary: "bg-[#f0eeec] text-[#31302e] hover:bg-[#e6e3e0] dark:bg-secondary dark:text-secondary-foreground",
        ghost: "text-[#31302e] hover:bg-[#f0eeec] dark:text-foreground dark:hover:bg-accent",
        link: "text-[#0075de] underline-offset-4 hover:underline",
      },
      size: {
        "default": "h-10 px-4",
        "xs": "h-7 px-2 text-xs",
        "sm": "h-8 px-3",
        "lg": "h-11 px-6",
        "icon": "size-9",
        "icon-sm": "size-8",
        "icon-lg": "size-10",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)

export type ButtonVariants = VariantProps<typeof buttonVariants>
