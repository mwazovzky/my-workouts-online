<script setup>
import { computed, ref, toRefs } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const props = defineProps({
    program: {
        type: Object,
        required: true,
    },
});

const { program } = toRefs(props);
const page = usePage();

const authUser = computed(() => page.props.auth?.user ?? null);

const isEnrolled = ref(
    authUser.value && Array.isArray(program.value.users)
        ? program.value.users.some(user => user.id === authUser.value.id)
        : false,
);

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
</script>

<template>
    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Program Details
            </h2>
        </template>

        <div>
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
                        v-for="workout in program.workout_templates"
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
                        <div class="text-sm text-gray-600">
                            Scheduled: {{ workout.pivot?.weekday ?? 'Not scheduled' }}
                        </div>
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
    </AuthenticatedLayout>
</template>
