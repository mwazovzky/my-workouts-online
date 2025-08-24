<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">My Workouts</h2>
        </template>

        <div class="p-4">
            <div v-if="loading" class="text-sm text-gray-500">Loading…</div>
            <div v-else-if="error" class="text-sm text-red-600">Error: {{ error }}</div>
            <div v-else>
                <div v-if="logs.length === 0" class="text-sm text-gray-600">No workouts yet.</div>
                <ul class="space-y-2">
                    <li v-for="log in logs" :key="log.id" class="p-3 bg-white rounded shadow-sm flex items-center justify-between">
                        <div>
                            <div class="font-medium flex items-center gap-2">
                                <!-- lock icon for completed logs -->
                                <svg v-if="log.status === 'completed'" class="w-4 h-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5 8V6a5 5 0 1110 0v2h1a1 1 0 011 1v8a1 1 0 01-1 1H4a1 1 0 01-1-1V9a1 1 0 011-1h1zm2-2a3 3 0 116 0v2H7V6z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ log.workout_template_name ?? 'Workout' }}</span>
                            </div>
                            <div class="text-sm text-gray-500">{{ log.date ?? log.created_at }} · {{ log.activities_count ?? 0 }} activities</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Open goes to canonical show (read-only) -->
                            <button
                                :disabled="opening[log.id]"
                                @click="() => navigateWithLoading('workout.logs.show', { id: log.id }, (opening[log.id] = true, opening))"
                                class="px-3 py-1 bg-indigo-600 text-white rounded text-sm flex items-center gap-2"
                            >
                                <span v-if="!opening[log.id]">Open</span>
                                <span v-else>Opening…</span>
                            </button>

                            <!-- Continue (edit) only for owner and when in_progress -->
                            <button
                                v-if="log.user_id === currentUserId() && log.status === 'in_progress'"
                                :disabled="continuing[log.id]"
                                @click="() => navigateWithLoading('workout.logs.edit', { id: log.id }, (continuing[log.id] = true, continuing))"
                                class="px-3 py-1 bg-indigo-500 text-white rounded text-sm flex items-center gap-2"
                            >
                                <span v-if="!continuing[log.id]">Continue</span>
                                <span v-else>Continuing…</span>
                            </button>

                            <button @click="deleteWorkout(log.id)" class="px-3 py-1 bg-red-600 text-white rounded text-sm">Delete</button>
                            <span class="text-sm text-gray-600">{{ log.status }}</span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { navigateWithLoading } from '@/utils/navigation';

const logs = ref([]);
const loading = ref(true);
const error = ref(null);

const page = usePage();
const currentUserId = () => page.props.auth?.user?.id ?? null;

// per-row navigation loading flags
const opening = ref({});
const continuing = ref({});

// helper to read CSRF token injected by Blade
function csrfToken() {
    const el = document.querySelector('meta[name="csrf-token"]');
    return el ? el.getAttribute('content') : null;
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const res = await fetch(route('api.workout.logs.index'), { credentials: 'same-origin' });
        if (!res.ok) throw new Error(await res.text() || res.statusText);
        const payload = await res.json();
        const data = payload.data ?? payload;
        logs.value = data;
    } catch (e) {
        error.value = e.message || 'Failed to load workouts';
    } finally {
        loading.value = false;
    }
}

async function deleteWorkout(id) {
    if (!confirm('Delete this workout? This action cannot be undone.')) return;
    try {
        const token = csrfToken();
        const res = await fetch(route('api.workout.logs.destroy', { id }), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
        });
        if (res.status === 204) {
            // remove locally
            logs.value = logs.value.filter(l => l.id !== id);
        } else {
            const text = await res.text();
            throw new Error(text || res.statusText);
        }
    } catch (e) {
        alert('Failed to delete workout: ' + e.message);
    }
}

onMounted(load);
</script>
