import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi, beforeEach } from 'vitest';

const { mockAxios } = vi.hoisted(() => ({
  mockAxios: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}));

vi.mock('@/lib/api', () => ({ api: mockAxios, configureApiAuth: vi.fn() }));

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => value }),
}));

vi.mock('vue-sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: vi.fn() }),
  onBeforeRouteLeave: vi.fn(),
}));

import { withPinia } from '@/test/utils';

import WorkoutEdit from '@/pages/WorkoutEdit.vue';

const workoutPayload = {
  id: 1,
  user_id: 7,
  status: 'in_progress',
  name: 'Push Day',
  activities: [
    {
      id: 10,
      exercise_id: 3,
      exercise: {
        id: 3,
        name: 'Bench Press',
        equipment_name: 'Barbell',
        effort_type: 'repetitions',
        difficulty_unit: 'kilograms',
        category_names: ['Chest'],
        rest_time_seconds: 90,
      },
      sets: [{ id: 100, order: 1, effort_value: 10, difficulty_value: 60, is_completed: false }],
    },
  ],
};

const exercisesPayload = [
  {
    id: 5,
    name: 'Push-up',
    equipment_name: 'Bodyweight',
    categories: [{ id: 2, name: 'Chest' }],
    effort_type: 'repetitions',
    difficulty_unit: 'none',
    rest_time_seconds: 45,
  },
  {
    id: 6,
    name: 'Deadlift',
    equipment_name: 'Barbell',
    categories: [{ id: 3, name: 'Back' }],
    effort_type: 'repetitions',
    difficulty_unit: 'kilograms',
    rest_time_seconds: 120,
  },
];

const stubs = {
  AuthenticatedLayout: { template: '<div><slot /><slot name="header-actions" /></div>' },
  PageLayout: { template: '<div><slot /></div>' },
  PageHeader: true,
  WorkoutCard: true,
  WorkoutFooter: { template: '<div><slot /></div>' },
  ConfirmDialog: true,
  Modal: { props: ['show'], template: '<div v-if="show"><slot /></div>' },
};

async function buildWrapper() {
  const wrapper = mount(WorkoutEdit, {
    props: { id: 1 },
    global: { plugins: [withPinia({ id: 7 })], stubs },
  });
  await flushPromises();

  return wrapper;
}

function findButtonByText(wrapper, text) {
  return wrapper.findAll('button').find(button => button.text() === text);
}

describe('WorkoutEdit — adding an activity', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAxios.get.mockImplementation(url =>
      url === '/exercises'
        ? Promise.resolve({ data: { data: exercisesPayload } })
        : Promise.resolve({ data: { data: workoutPayload }, headers: { etag: '"1-1"' } })
    );
  });

  it('fetches the exercise catalog only on the first picker open', async () => {
    const wrapper = await buildWrapper();

    expect(mockAxios.get).toHaveBeenCalledTimes(1);

    await findButtonByText(wrapper, 'Add activity').trigger('click');
    await flushPromises();

    expect(mockAxios.get).toHaveBeenCalledWith('/exercises', { params: {} });
    expect(mockAxios.get).toHaveBeenCalledTimes(2);

    // Close and reopen — the cached catalog is reused.
    await findButtonByText(wrapper, 'Cancel').trigger('click');
    await findButtonByText(wrapper, 'Add activity').trigger('click');
    await flushPromises();

    expect(mockAxios.get).toHaveBeenCalledTimes(2);
  });

  it('appends the chosen exercise as an activity with one blank set', async () => {
    const wrapper = await buildWrapper();

    await findButtonByText(wrapper, 'Add activity').trigger('click');
    await flushPromises();

    const pushUp = wrapper.findAll('li button').find(button => button.text().includes('Push-up'));
    await pushUp.trigger('click');

    const titles = wrapper.findAll('h3').map(title => title.text());
    expect(titles).toEqual(['Bench Press', 'Push-up']);

    // The new card carries exactly one set, and no difficulty field for the "none" unit.
    const newCard = wrapper.findAllComponents({ name: 'Activity' })[1];
    expect(newCard.props('activity').sets).toHaveLength(1);
    expect(newCard.findAll('input[step="0.5"]')).toHaveLength(0);
    expect(newCard.text()).toContain('Bodyweight');
  });

  it('sends the new activity with a null id and sequential order on save', async () => {
    mockAxios.put.mockResolvedValue({ data: { data: workoutPayload } });

    const wrapper = await buildWrapper();

    await findButtonByText(wrapper, 'Add activity').trigger('click');
    await flushPromises();
    await wrapper
      .findAll('li button')
      .find(button => button.text().includes('Deadlift'))
      .trigger('click');

    await findButtonByText(wrapper, 'Save').trigger('click');
    await flushPromises();

    const [url, payload, config] = mockAxios.put.mock.calls[0];
    expect(url).toBe('/workouts/1/activities');
    expect(config).toEqual({ headers: { 'If-Match': '"1-1"' } });
    expect(payload.activities).toHaveLength(2);
    expect(payload.activities[0]).toMatchObject({ id: 10, exercise_id: 3, order: 1 });
    expect(payload.activities[1]).toMatchObject({ exercise_id: 6, order: 2 });
    expect(payload.activities[1].id).toBeUndefined();
    expect(payload.activities[1].sets).toEqual([
      { id: undefined, order: 1, effort_value: 0, difficulty_value: 0, is_completed: false },
    ]);
  });

  it('seeds a null difficulty when the exercise has no difficulty unit', async () => {
    mockAxios.put.mockResolvedValue({ data: { data: workoutPayload } });

    const wrapper = await buildWrapper();

    await findButtonByText(wrapper, 'Add activity').trigger('click');
    await flushPromises();
    await wrapper
      .findAll('li button')
      .find(button => button.text().includes('Push-up'))
      .trigger('click');

    await findButtonByText(wrapper, 'Save').trigger('click');
    await flushPromises();

    const [, payload] = mockAxios.put.mock.calls[0];
    expect(payload.activities[1].sets[0].difficulty_value).toBeNull();
  });

  it('adopts persisted ids from the save response so a second save updates in place', async () => {
    const savedPayload = {
      ...workoutPayload,
      activities: [
        workoutPayload.activities[0],
        {
          id: 11,
          exercise_id: 6,
          exercise: {
            id: 6,
            name: 'Deadlift',
            equipment_name: 'Barbell',
            effort_type: 'repetitions',
            difficulty_unit: 'kilograms',
            category_names: ['Back'],
            rest_time_seconds: 120,
          },
          sets: [{ id: 101, order: 1, effort_value: 0, difficulty_value: 0, is_completed: false }],
        },
      ],
    };
    mockAxios.put.mockResolvedValue({ data: { data: savedPayload } });

    const wrapper = await buildWrapper();

    await findButtonByText(wrapper, 'Add activity').trigger('click');
    await flushPromises();
    await wrapper
      .findAll('li button')
      .find(button => button.text().includes('Deadlift'))
      .trigger('click');

    await findButtonByText(wrapper, 'Save').trigger('click');
    await flushPromises();

    // Dirty again so the Save button reappears, then save a second time.
    await wrapper.findAll('button[aria-label="Add set"]')[1].trigger('click');
    await findButtonByText(wrapper, 'Save').trigger('click');
    await flushPromises();

    const [, secondPayload] = mockAxios.put.mock.calls[1];
    expect(secondPayload.activities[1]).toMatchObject({ id: 11, exercise_id: 6, order: 2 });
    expect(secondPayload.activities[1].sets[0]).toMatchObject({ id: 101, order: 1 });
  });

  it('keeps the picker closed and warns when the catalog fails to load', async () => {
    const { toast } = await import('vue-sonner');
    const wrapper = await buildWrapper();

    mockAxios.get.mockRejectedValueOnce(new Error('boom'));

    await findButtonByText(wrapper, 'Add activity').trigger('click');
    await flushPromises();

    expect(toast.error).toHaveBeenCalledWith('Failed to load exercises');
    expect(wrapper.findAll('li button')).toHaveLength(0);
  });

  it('does not offer the add-activity button on a completed workout', async () => {
    mockAxios.get.mockResolvedValue({
      data: { data: { ...workoutPayload, status: 'completed' } },
    });

    const wrapper = await buildWrapper();

    expect(findButtonByText(wrapper, 'Add activity')).toBeUndefined();
  });
});

describe('WorkoutEdit — concurrency', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockAxios.get.mockResolvedValue({ data: { data: workoutPayload }, headers: { etag: '"1-1"' } });
  });

  async function editAndSave(wrapper) {
    await wrapper.findAll('button[aria-label="Add set"]')[0].trigger('click');
    await findButtonByText(wrapper, 'Save').trigger('click');
    await flushPromises();
  }

  it('sends the ETag from each save response as the next If-Match', async () => {
    mockAxios.put
      .mockResolvedValueOnce({ data: { data: workoutPayload }, headers: { etag: '"1-2"' } })
      .mockResolvedValueOnce({ data: { data: workoutPayload }, headers: { etag: '"1-3"' } });

    const wrapper = await buildWrapper();
    await editAndSave(wrapper);
    await editAndSave(wrapper);

    expect(mockAxios.put.mock.calls[0][2]).toEqual({ headers: { 'If-Match': '"1-1"' } });
    expect(mockAxios.put.mock.calls[1][2]).toEqual({ headers: { 'If-Match': '"1-2"' } });
  });

  it('keeps the edits and offers a reload when the workout changed elsewhere', async () => {
    const { toast } = await import('vue-sonner');
    mockAxios.put.mockRejectedValueOnce({
      response: { status: 412, data: { code: 'precondition_failed' } },
    });

    const wrapper = await buildWrapper();
    await editAndSave(wrapper);

    expect(wrapper.find('[role="alert"]').text()).toContain(
      'This workout was changed elsewhere. Reload it to continue.'
    );
    expect(toast.error).not.toHaveBeenCalled();
    expect(wrapper.findComponent({ name: 'Activity' }).props('activity').sets).toHaveLength(2);
    expect(findButtonByText(wrapper, 'Save').attributes('disabled')).toBeDefined();
  });

  it('reloads the server copy, discards the edits and adopts the new ETag', async () => {
    mockAxios.put.mockRejectedValueOnce({
      response: { status: 412, data: { code: 'precondition_failed' } },
    });

    const wrapper = await buildWrapper();
    await editAndSave(wrapper);

    mockAxios.get.mockResolvedValueOnce({
      data: { data: workoutPayload },
      headers: { etag: '"1-5"' },
    });
    await findButtonByText(wrapper, 'Reload').trigger('click');
    await flushPromises();

    expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    expect(wrapper.findComponent({ name: 'Activity' }).props('activity').sets).toHaveLength(1);
    expect(findButtonByText(wrapper, 'Save')).toBeUndefined();

    mockAxios.put.mockResolvedValueOnce({
      data: { data: workoutPayload },
      headers: { etag: '"1-6"' },
    });
    await editAndSave(wrapper);

    expect(mockAxios.put.mock.calls[1][2]).toEqual({ headers: { 'If-Match': '"1-5"' } });
  });

  it('does not complete the workout when the save before it is refused', async () => {
    mockAxios.put.mockRejectedValueOnce({
      response: { status: 412, data: { code: 'precondition_failed' } },
    });

    const wrapper = await buildWrapper();
    await wrapper.findAll('button[aria-label="Add set"]')[0].trigger('click');
    await findButtonByText(wrapper, 'Complete').trigger('click');
    await flushPromises();

    expect(mockAxios.post).not.toHaveBeenCalled();
    expect(wrapper.find('[role="alert"]').exists()).toBe(true);
  });
});
