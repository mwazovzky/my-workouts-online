<script setup>
import { ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import Navigation from '@/Components/Navigation.vue';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue';
import { Link } from '@inertiajs/vue3';
import { Toaster } from '@/Components/ui/sonner';
import { useTranslation } from '@/composables/useTranslation';

const showingNavigationDropdown = ref(false);
const { t } = useTranslation();
</script>

<template>
  <div>
    <div class="min-h-screen bg-background text-foreground">
      <nav
        class="border-b border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80"
      >
        <!-- Primary Navigation Menu -->
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="flex h-16 justify-between">
            <div class="flex">
              <!-- Logo -->
              <div class="flex shrink-0 items-center">
                <Link :href="route('dashboard')">
                  <ApplicationLogo class="block h-11 w-11 fill-current text-foreground" />
                </Link>
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
                        {{ $page.props.auth.user.name }}

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
                    <DropdownLink :href="route('profile.edit')"> {{ t('Profile') }} </DropdownLink>
                    <DropdownLink :href="route('logout')" method="post" as="button">
                      {{ t('Log Out') }}
                    </DropdownLink>
                  </template>
                </Dropdown>
              </div>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
              <button
                class="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground transition duration-150 ease-in-out hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-background"
                @click="showingNavigationDropdown = !showingNavigationDropdown"
              >
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                  <path
                    :class="{
                      hidden: showingNavigationDropdown,
                      'inline-flex': !showingNavigationDropdown,
                    }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                  <path
                    :class="{
                      hidden: !showingNavigationDropdown,
                      'inline-flex': showingNavigationDropdown,
                    }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Responsive Navigation Menu -->
        <div
          :class="{
            block: showingNavigationDropdown,
            hidden: !showingNavigationDropdown,
          }"
          class="sm:hidden"
        >
          <div class="space-y-1 pb-3 pt-2">
            <ResponsiveNavLink :href="route('dashboard')" :active="route().current('dashboard')">
              {{ t('Dashboard') }}
            </ResponsiveNavLink>
            <ResponsiveNavLink
              :href="route('programs.index')"
              :active="route().current('programs.index')"
            >
              {{ t('Programs') }}
            </ResponsiveNavLink>
            <ResponsiveNavLink
              :href="route('workouts.index')"
              :active="route().current('workouts.index')"
            >
              {{ t('Workouts') }}
            </ResponsiveNavLink>
          </div>

          <!-- Responsive Settings Options -->
          <div class="border-t border-border pb-1 pt-4">
            <div class="px-4">
              <div class="text-base font-medium text-foreground">
                {{ $page.props.auth.user.name }}
              </div>
              <div class="text-sm font-medium text-muted-foreground">
                {{ $page.props.auth.user.email }}
              </div>
            </div>

            <div class="mt-3 space-y-1">
              <ResponsiveNavLink :href="route('profile.edit')">
                {{ t('Profile') }}
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('logout')" method="post" as="button">
                {{ t('Log Out') }}
              </ResponsiveNavLink>
            </div>
          </div>
        </div>
      </nav>

      <!-- Page Heading -->
      <header v-if="$slots.header" class="border-b border-border/70 bg-card shadow-sm">
        <div class="mx-auto max-w-7xl px-4 py-4 sm:px-6 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <!-- Page Content -->
      <main class="m-4">
        <slot />
      </main>

      <Toaster position="top-right" :duration="3000" />
    </div>
  </div>
</template>
