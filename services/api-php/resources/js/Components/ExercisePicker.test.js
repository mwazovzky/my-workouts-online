import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => value }),
}));

import ExercisePicker from '@/Components/ExercisePicker.vue';

const exercises = [
  {
    id: 1,
    name: 'Bench Press',
    equipment_name: 'Barbell',
    categories: [{ id: 1, name: 'Chest' }],
  },
  {
    id: 2,
    name: 'Deadlift',
    equipment_name: 'Barbell',
    categories: [{ id: 2, name: 'Back' }],
  },
  {
    id: 3,
    name: 'Push-up',
    equipment_name: 'Bodyweight',
    categories: [],
  },
];

describe('ExercisePicker', () => {
  function buildWrapper(props = {}) {
    return mount(ExercisePicker, {
      props: { show: true, exercises, loading: false, ...props },
      global: { stubs: { teleport: true } },
    });
  }

  it('lists every exercise with its equipment and categories', () => {
    const wrapper = buildWrapper();

    const rows = wrapper.findAll('li button');
    expect(rows).toHaveLength(3);
    expect(rows[0].text()).toContain('Bench Press');
    expect(rows[0].text()).toContain('Barbell · Chest');
  });

  it('filters exercises by the search query, case-insensitively', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('input[type="search"]').setValue('press');

    const rows = wrapper.findAll('li button');
    expect(rows).toHaveLength(1);
    expect(rows[0].text()).toContain('Bench Press');
  });

  it('shows an empty state when nothing matches', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('input[type="search"]').setValue('nonexistent');

    expect(wrapper.findAll('li button')).toHaveLength(0);
    expect(wrapper.text()).toContain('No exercises found');
  });

  it('emits select with the chosen exercise', async () => {
    const wrapper = buildWrapper();

    await wrapper.findAll('li button')[1].trigger('click');

    expect(wrapper.emitted('select')).toBeTruthy();
    expect(wrapper.emitted('select').at(-1)[0]).toMatchObject({ id: 2, name: 'Deadlift' });
  });

  it('shows skeletons instead of the list while loading', () => {
    const wrapper = buildWrapper({ exercises: [], loading: true });

    expect(wrapper.findAll('li button')).toHaveLength(0);
    expect(wrapper.text()).not.toContain('No exercises found');
  });

  it('emits close from the cancel button', async () => {
    const wrapper = buildWrapper();

    const cancel = wrapper.findAll('button').find(button => button.text() === 'Cancel');
    await cancel.trigger('click');

    expect(wrapper.emitted('close')).toBeTruthy();
  });

  // ExerciseResource omits `categories` unless the relation is loaded, exposes
  // `equipment_name` the same way, and returns a null `name` when the
  // translation is missing — so the picker has to survive all three.
  it('renders exercises with missing name, equipment and categories', async () => {
    const wrapper = buildWrapper({
      exercises: [
        { id: 9, name: null, equipment_name: null },
        { id: 10, name: 'Sled Push' },
      ],
    });

    const rows = wrapper.findAll('li button');
    expect(rows).toHaveLength(2);
    // Nothing to describe for either row, so both fall back to the placeholder.
    expect(rows[0].text()).toContain('—');
    expect(rows[1].text()).toContain('Sled Push');
    expect(rows[1].text()).toContain('—');

    // Filtering must not throw on the null name, and must not match it.
    await wrapper.find('input[type="search"]').setValue('sled');

    const filtered = wrapper.findAll('li button');
    expect(filtered).toHaveLength(1);
    expect(filtered[0].text()).toContain('Sled Push');
  });

  it('resets the search query when reopened', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('input[type="search"]').setValue('press');
    expect(wrapper.findAll('li button')).toHaveLength(1);

    await wrapper.setProps({ show: false });
    await wrapper.setProps({ show: true });

    expect(wrapper.find('input[type="search"]').element.value).toBe('');
    expect(wrapper.findAll('li button')).toHaveLength(3);
  });
});
