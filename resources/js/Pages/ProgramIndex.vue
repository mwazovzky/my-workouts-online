<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Programs
            </h2>
        </template>

        <div>
            <!-- Content for the Programs page -->
            <div class="space-y-4">
                <div v-if="loading" class="text-sm text-gray-500">Loading programs…</div>
                <div v-else-if="error" class="text-sm text-red-600">Error: {{ error }}</div>
                <div v-else>
                    <template v-if="programs.length">
                        <ul class="space-y-2">
                            <li
                                v-for="program in programs"
                                :key="program.id"
                                class="p-4 bg-white rounded shadow-sm"
                            >
                                <div class="font-semibold">
                                    <Link
                                        :href="route('programs.show', { id: program.id })"
                                        class="text-indigo-600 hover:underline"
                                    >
                                        {{ program.name }}
                                    </Link>
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ program.description ?? 'No description' }}
                                </div>
                            </li>
                        </ul>
                    </template>
                    <div v-else class="text-sm text-gray-500">
                        No programs yet.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3'; // Ensure Link and route are imported

// Reactive state
const programs = ref([]);
const loading = ref(true);
const error = ref(null);

// Fetch programs on mount
async function loadPrograms() {
    loading.value = true;
    error.value = null;

    try {
        // Expect an authenticated JSON endpoint at /api/programs
        const res = await fetch('/api/programs', { credentials: 'same-origin' });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || res.statusText);
        }
        const payload = await res.json();
        programs.value = payload.data ?? [];
    } catch (e) {
        error.value = e.message || 'Failed to load programs';
    } finally {
        loading.value = false;
    }
}

onMounted(loadPrograms);
</script>
