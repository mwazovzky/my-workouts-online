<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

const program = ref(null);
const loading = ref(true);
const error = ref(null);

async function loadProgram() {
    loading.value = true;
    error.value = null;
    console.log('Loading program...');
    try {
        const res = await fetch(`/api/programs/${route().params.id}`, { credentials: 'same-origin' });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || res.statusText);
        }
        const payload = await res.json();
        program.value = payload.data ?? [];
    } catch (e) {
        error.value = e.message || 'Failed to load program details';
    } finally {
        loading.value = false;
    }
}

onMounted(loadProgram);
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Program Details
            </h2>
        </template>

        <div>
            <div v-if="loading" class="text-sm text-gray-500">Loading program details…</div>
            <div v-else-if="error" class="text-sm text-red-600">Error: {{ error }}</div>
            <div v-else>
                <div class="p-4 bg-white rounded shadow-sm">
                    <h3 class="font-semibold text-lg">{{ program.name }}</h3>
                    <p class="text-sm text-gray-600">{{ program.description }}</p>
                </div>

                <div class="mt-6">
                    <h4 class="font-semibold text-md">Workouts</h4>
                    <ul class="space-y-2 mt-2">
                        <li
                            v-for="workout in program.workouts"
                            :key="workout.id"
                            class="p-4 bg-gray-100 rounded"
                        >
                            <div class="font-semibold">{{ workout.name }}</div>
                            <div class="text-sm text-gray-600">Scheduled: {{ workout.weekday }}</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
