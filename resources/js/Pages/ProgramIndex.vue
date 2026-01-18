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
                    <input type="checkbox" v-model="filterEnrolled" class="form-checkbox" />
                    <span>Show only enrolled programs</span>
                </label>
            </div>

            <!-- Content for the Programs page -->
            <div class="space-y-4">
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
                                    v-if="isProgramEnrolled(program)"
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
    </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    programs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const filterEnrolled = ref(false);

const isProgramEnrolled = program => {
    const user = page.props.auth?.user;

    if (!user || !Array.isArray(program.users)) {
        return false;
    }

    return program.users.some(u => u.id === user.id);
};

const filteredPrograms = computed(() => {
    if (!filterEnrolled.value) {
        return props.programs;
    }

    return props.programs.filter(program => isProgramEnrolled(program));
});
</script>
