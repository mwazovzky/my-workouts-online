<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActivitiesList from '@/Components/ActivitiesList.vue';
import { Form } from '@inertiajs/vue3';

const props = defineProps({
  workout: {
    type: Object,
    required: true,
  },
});
</script>

<template>
  <AuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Workout Template Details
      </h2>
    </template>

    <div>
      <div class="p-4 bg-white dark:bg-gray-800 rounded shadow-sm">
        <h3 class="font-semibold text-lg dark:text-gray-100">{{ workout.name }}</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400">{{ workout.description }}</p>
      </div>

      <div class="mt-6">
        <h4 class="font-semibold text-md dark:text-gray-200">Activities</h4>
        <div class="mt-2">
          <ActivitiesList :activities="workout.activities ?? []" :editable="false" />
        </div>
      </div>

      <div class="mt-6">
        <Form
          v-slot="{ errors, processing }"
          :action="route('workout.logs.store')"
          method="post"
          preserve-scroll
        >
          <input type="hidden" name="workout_template_id" :value="workout.id" />
          <button
            type="submit"
            class="inline-block px-4 py-2 bg-indigo-600 dark:bg-indigo-500 text-white text-sm font-medium rounded hover:bg-indigo-700 dark:hover:bg-indigo-600 disabled:opacity-75"
            :disabled="processing"
          >
            <span v-if="!processing">Start Workout</span>
            <span v-else>Starting…</span>
          </button>
          <p v-if="errors.workout_template_id" class="mt-2 text-sm text-red-600 dark:text-red-400">
            {{ errors.workout_template_id }}
          </p>
        </Form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
