<script setup>
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import BottomNav from '@/components/BottomNav.vue';
import Dropdown from '@/components/Dropdown.vue';
import DropdownLink from '@/components/DropdownLink.vue';
import LegalLinks from '@/components/LegalLinks.vue';
import Navigation from '@/components/Navigation.vue';
import { Toaster } from '@/components/ui/sonner';
import { useLogout } from '@/composables/useLogout';
import { useTranslation } from '@/composables/useTranslation';
import { useAuthStore } from '@/stores/auth';

const { t } = useTranslation();
const auth = useAuthStore();
const { logout } = useLogout();
</script>

<template>
  <div>
    <div class="min-h-screen bg-background text-foreground">
      <nav
        class="relative z-50 hidden border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:block"
      >
        <!-- Primary Navigation Menu -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="flex h-16 justify-between">
            <div class="flex">
              <!-- Logo -->
              <div class="flex shrink-0 items-center">
                <RouterLink :to="{ name: 'dashboard' }">
                  <ApplicationLogo class="block h-11 w-11 fill-current text-primary" />
                </RouterLink>
              </div>

              <!-- Navigation Links -->
              <Navigation />
            </div>

            <div class="hidden sm:ms-6 sm:flex sm:items-center">
              <!-- Settings Dropdown -->
              <div class="relative ms-3">
                <Dropdown align="right" width="48">
                  <template #trigger>
                    <span class="inline-flex rounded-md">
                      <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-transparent bg-background px-3 py-2 text-sm font-medium leading-4 text-muted-foreground transition duration-150 ease-in-out hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background"
                      >
                        {{ auth.user?.name }}

                        <svg
                          class="-me-0.5 ms-2 h-4 w-4"
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 20 20"
                          fill="currentColor"
                        >
                          <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                          />
                        </svg>
                      </button>
                    </span>
                  </template>

                  <template #content>
                    <DropdownLink :to="{ name: 'profile.edit' }"> {{ t('Profile') }} </DropdownLink>
                    <button
                      type="button"
                      class="block w-full px-4 py-2 text-start text-sm leading-5 text-popover-foreground transition duration-150 ease-in-out hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none"
                      @click="logout"
                    >
                      {{ t('Log Out') }}
                    </button>
                  </template>
                </Dropdown>
              </div>
            </div>
          </div>
        </div>
      </nav>

      <!-- Page Heading -->
      <header
        v-if="$slots.header || $slots['header-actions']"
        class="sticky top-0 z-40 border-b border-border/50 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80 sm:border-border/70 sm:bg-card sm:shadow-sm"
      >
        <div
          class="mx-auto flex h-12 max-w-7xl items-center justify-between px-4 sm:h-auto sm:px-6 sm:py-4 lg:px-8"
        >
          <slot name="header" />
          <div v-if="$slots['header-actions']" class="flex-shrink-0 sm:hidden">
            <slot name="header-actions" />
          </div>
        </div>
      </header>

      <!-- Page Content -->
      <main class="pb-20">
        <slot />

        <LegalLinks class="py-6" />
      </main>

      <Toaster position="top-right" :duration="3000" />
      <BottomNav />
    </div>
  </div>
</template>
