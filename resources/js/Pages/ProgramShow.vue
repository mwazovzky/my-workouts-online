<script setup>
import { computed, toRefs } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Form, Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    program: {
        type: Object,
        required: true,
    },
    workouts: {
        type: Array,
        default: null,
    },
});

const { program, workouts } = toRefs(props);
const page = usePage();

const authUser = computed(() => page.props.auth?.user ?? null);

const isEnrolled = computed(() => {
    return program.value.is_enrolled ?? false;
});
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
                <div v-if="workouts">
                    <ul class="space-y-2 mt-2">
                        <li
                            v-for="workout in workouts"
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
                <div
                    v-else
                    class="mt-2 text-sm text-gray-500"
                >
                    Loading workouts...
                </div>
            </div>

            <div class="m-6">
                <Form
                    v-if="!isEnrolled"
                    :action="route('programs.enroll', { program: program.id })"
                    method="post"
                    preserve-scroll
                >
                    <button
                        type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white rounded text-sm"
                    >
                        Enroll
                    </button>
                </Form>
                <p v-else class="text-sm text-green-600">
                    You are already enrolled in this program.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
