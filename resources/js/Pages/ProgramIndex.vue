<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Programs
            </h2>
        </template>

        <div>
            <!-- Filter Toggle -->
            <div class="flex items-center mb-4">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" v-model="filterEnrolled" class="form-checkbox"/>
                    <span>Show only enrolled programs</span>
                </label>
            </div>

            <!-- Content for the Programs page -->
            <div class="space-y-4">
                <div v-if="loading" class="text-sm text-gray-500">Loading programs…</div>
                <div v-else-if="error" class="text-sm text-red-600">Error: {{ error }}</div>
                <div v-else>
                    <template v-if="filteredPrograms.length">
                        <ul class="space-y-2">
                            <li
                                v-for="program in filteredPrograms"
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
                                    <span
                                        v-if="program.is_enrolled"
                                        class="ml-2 px-2 py-1 text-xs bg-green-100 text-green-600 rounded"
                                    >
                                        Enrolled
                                    </span>
                                </div>
                                <div class="text-sm text-gray-600">
                                    {{ program.description ?? 'No description' }}
                                </div>
                            </li>
                        </ul>
                    </template>
                    <div v-else class="text-sm text-gray-500">
                        No programs found.
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';

const programs = ref([]);
const loading = ref(true);
const error = ref(null);
const filterEnrolled = ref(false); // Filter toggle

const filteredPrograms = computed(() => {
    return filterEnrolled.value
        ? programs.value.filter(program => program.is_enrolled)
        : programs.value;
});

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
