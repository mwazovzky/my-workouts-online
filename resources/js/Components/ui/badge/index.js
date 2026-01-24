import { cva } from "class-variance-authority";

export { default as Badge } from "./Badge.vue";

export const badgeVariants = cva(
  "inline-flex gap-1 items-center rounded-md border-transparent px-2 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground shadow hover:bg-primary/80",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-secondary/80",
        destructive:
          "bg-destructive text-destructive-foreground shadow hover:bg-destructive/80",
        outline: "border border-input text-foreground",
        success:
          "bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300",
        warning:
          "bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300",
        info:
          "bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
);
