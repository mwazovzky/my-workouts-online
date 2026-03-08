import { cva } from 'class-variance-authority';

export { default as Badge } from './Badge.vue';

export const badgeVariants = cva(
  'inline-flex gap-1 items-center rounded-md border-transparent px-2 py-0.5 text-xs font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2',
  {
    variants: {
      variant: {
        default: 'bg-primary text-primary-foreground shadow hover:bg-primary/80',
        secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
        destructive: 'bg-destructive text-destructive-foreground shadow hover:bg-destructive/80',
        outline: 'border border-input text-foreground',
        success:
          'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-1 dark:ring-emerald-400/20',
        warning:
          'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-1 dark:ring-amber-400/20',
        info:
          'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-1 dark:ring-sky-400/20',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
);
