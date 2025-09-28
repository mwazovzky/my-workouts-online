<script setup>
import { ref, onMounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link } from '@inertiajs/vue3';

const program = ref(null);
const loading = ref(true);
const error = ref(null);
const isEnrolled = ref(false);

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

async function loadProgram() {
    loading.value = true;
    error.value = null;
    try {
        const res = await fetch(`/api/programs/${route().params.id}`, { credentials: 'same-origin' });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || res.statusText);
        }
        const payload = await res.json();
        program.value = payload.data ?? [];
        isEnrolled.value = payload.data?.is_enrolled ?? false; // Assuming API provides `is_enrolled`
    } catch (e) {
        error.value = e.message || 'Failed to load program details';
    } finally {
        loading.value = false;
    }
}

async function enroll() {
    try {
        const res = await fetch(`/api/programs/${route().params.id}/enroll`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(text || res.statusText);
        }
        isEnrolled.value = true;
        alert('Successfully enrolled in the program!');
    } catch (e) {
        alert('Failed to enroll: ' + (e.message || 'Unknown error'));
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
                    <p class="text-sm text-gray-600">
                        Duration: {{ program.start_date }} - {{ program.end_date }}
                    </p>
                </div>

                <div class="mt-4">
                    <h4 class="font-semibold text-md p-4">Workouts</h4>
                    <ul class="space-y-2 mt-2">
                        <li
                            v-for="workout in program.workouts"
                            :key="workout.id"
                            class="p-4 border rounded bg-white"
                        >
                            <div class="font-semibold">
                                <Link
                                    :href="route('workout.templates.show', { id: workout.id })"
                                    class="text-indigo-600 hover:underline"
                                >
                                    {{ workout.name }}
                                </Link>
                            </div>
                            <div class="text-sm text-gray-600">Scheduled: {{ workout.weekday }}</div>
                        </li>
                    </ul>
                </div>

                <div class="m-6">
                    <button
                        v-if="!isEnrolled"
                        @click="enroll"
                        class="px-4 py-2 bg-indigo-600 text-white rounded text-sm"
                    >
                        Enroll
                    </button>
                    <p v-else class="text-sm text-green-600">You are already enrolled in this program.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
