<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { useTranslation } from '@/composables/useTranslation';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
  canLogin: {
    type: Boolean,
  },
  canRegister: {
    type: Boolean,
  },
});

const { t } = useTranslation();

const productSteps = [
  {
    title: 'Browse programs',
    description: 'Choose a program and see the workouts attached to it before you start.',
  },
  {
    title: 'Start the planned workout',
    description: 'Open the scheduled template and jump straight into logging the session.',
  },
  {
    title: 'Review recent sessions',
    description:
      'Use workout history to see what was completed, what is in progress, and what comes next.',
  },
];

const appHighlights = [
  'Upcoming workouts for the next 7 days',
  'A clear next action instead of a generic dashboard',
  'Recent workout history with completion status',
  'Built for day-to-day use, not a demo screen',
];
</script>

<template>
  <Head title="My Workouts Online" />
  <div class="relative min-h-screen overflow-hidden bg-background text-foreground">
    <div class="pointer-events-none absolute inset-0">
      <div
        class="absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-primary/10 via-accent/30 to-transparent"
      />
      <div class="absolute left-[-8rem] top-20 h-72 w-72 rounded-full bg-primary/12 blur-3xl" />
      <div class="absolute right-[-6rem] top-32 h-80 w-80 rounded-full bg-accent blur-3xl" />
    </div>
    <div class="mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 py-6 sm:px-8 lg:px-10">
      <header
        class="relative flex items-center justify-between rounded-full border border-border/80 bg-background/80 px-5 py-3 shadow-sm backdrop-blur"
      >
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-2xl bg-primary/10 p-2 text-primary">
            <ApplicationLogo class="h-full w-full fill-current" />
          </div>
          <div>
            <div class="text-sm font-semibold uppercase tracking-[0.18em] text-primary">
              My Workouts Online
            </div>
            <div class="text-sm text-muted-foreground">{{ t('Workout planning and logging') }}</div>
          </div>
        </div>

        <nav v-if="canLogin" class="flex items-center gap-2">
          <Link
            :href="route('login')"
            class="rounded-full px-4 py-2 text-sm font-medium text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
          >
            {{ t('Log in') }}
          </Link>
          <Link
            v-if="canRegister"
            :href="route('register')"
            class="rounded-full bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
          >
            {{ t('Create account') }}
          </Link>
        </nav>
      </header>

      <main class="flex flex-1 items-center py-12 lg:py-20">
        <div class="grid w-full gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-center">
          <section class="space-y-8">
            <div
              class="inline-flex rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-medium text-primary shadow-sm"
            >
              {{ t('Workout planning and logging') }}
            </div>

            <div class="space-y-5">
              <h1
                class="max-w-3xl text-4xl font-semibold leading-tight text-foreground sm:text-5xl lg:text-6xl"
              >
                {{ t('Know what to train next') }}
              </h1>
              <p class="max-w-2xl text-lg leading-8 text-muted-foreground">
                {{
                  t(
                    'Follow your program, start the right workout, and keep your training history in one place.'
                  )
                }}
              </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
              <Link
                v-if="canRegister"
                :href="route('register')"
                class="inline-flex items-center justify-center rounded-full bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
              >
                {{ t('Create account') }}
              </Link>
              <Link
                :href="route('login')"
                class="inline-flex items-center justify-center rounded-full border border-border bg-background px-6 py-3 text-sm font-semibold text-foreground transition hover:bg-accent hover:text-accent-foreground"
              >
                {{ t('Log in') }}
              </Link>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
              <article
                v-for="step in productSteps"
                :key="step.title"
                class="rounded-3xl border border-border/70 bg-card/90 p-5 shadow-sm backdrop-blur"
              >
                <div class="text-base font-semibold text-card-foreground">{{ t(step.title) }}</div>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                  {{ t(step.description) }}
                </p>
              </article>
            </div>
          </section>

          <section class="relative">
            <div class="absolute -left-10 -top-10 h-32 w-32 rounded-full bg-primary/20 blur-3xl" />
            <div class="absolute -bottom-12 right-0 h-40 w-40 rounded-full bg-accent blur-3xl" />

            <div
              class="relative overflow-hidden rounded-[2rem] border border-primary/10 bg-foreground p-6 text-background shadow-[0_24px_80px_rgba(15,23,42,0.18)] sm:p-8"
            >
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-sm font-medium uppercase tracking-[0.2em] text-primary/70">
                    {{ t('Inside the app') }}
                  </div>
                  <h2 class="mt-3 text-2xl font-semibold text-background">
                    {{ t('Built for a simple training flow') }}
                  </h2>
                </div>
                <div class="h-12 w-12 rounded-2xl bg-background/10 p-2 text-primary">
                  <ApplicationLogo class="h-full w-full fill-current" />
                </div>
              </div>

              <div class="mt-8 space-y-3">
                <div
                  v-for="highlight in appHighlights"
                  :key="highlight"
                  class="flex items-start gap-3 rounded-2xl border border-background/10 bg-background/5 px-4 py-3"
                >
                  <div class="mt-1 h-2.5 w-2.5 rounded-full bg-primary" />
                  <p class="text-sm leading-6 text-background/80">{{ t(highlight) }}</p>
                </div>
              </div>

              <div class="mt-8 rounded-3xl border border-background/10 bg-background/5 p-5">
                <div class="text-sm font-medium text-primary/70">{{ t('Coming up') }}</div>
                <div class="mt-2 text-2xl font-semibold text-background">
                  {{ t('Upcoming workouts for the next 7 days') }}
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                  <div class="rounded-2xl bg-background px-4 py-3 text-foreground">
                    <div
                      class="text-xs font-medium uppercase tracking-[0.18em] text-muted-foreground"
                    >
                      Monday
                    </div>
                    <div class="mt-2 text-sm font-semibold">Upper Body</div>
                    <div class="mt-1 text-sm text-muted-foreground">Strength program</div>
                  </div>
                  <div
                    class="rounded-2xl border border-background/10 bg-transparent px-4 py-3 text-background"
                  >
                    <div class="text-xs font-medium uppercase tracking-[0.18em] text-background/55">
                      Wednesday
                    </div>
                    <div class="mt-2 text-sm font-semibold">Legs & Conditioning</div>
                    <div class="mt-1 text-sm text-background/60">Progress stays visible</div>
                  </div>
                </div>
              </div>
            </div>
          </section>
        </div>
      </main>
    </div>
  </div>
</template>
