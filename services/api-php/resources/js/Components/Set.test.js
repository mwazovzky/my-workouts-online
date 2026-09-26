import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => value }),
}));

import Set from '@/Components/Set.vue';

describe('Set', () => {
  function buildWrapper(props = {}) {
    return mount(Set, {
      props: {
        set: { id: 1, order: 1, effort_value: 1800, difficulty_value: null, is_completed: false },
        editable: true,
        effortType: 'duration',
        ...props,
      },
    });
  }

  it('renders a 1-5 zone select for the heart_rate_zone unit', () => {
    const wrapper = buildWrapper({ difficultyUnit: 'heart_rate_zone' });

    const select = wrapper.find('select');
    expect(select.exists()).toBe(true);
    // Options are "—" plus 1..5
    expect(select.findAll('option')).toHaveLength(6);
    expect(wrapper.find('input[type="number"][step="0.5"]').exists()).toBe(false);
  });

  it('right-aligns the zone select to match the other numeric columns', () => {
    const wrapper = buildWrapper({ difficultyUnit: 'heart_rate_zone' });

    const classes = wrapper.find('select').classes();
    // pr-8 keeps the value clear of the native dropdown chevron.
    expect(classes).toContain('text-right');
    expect(classes).toContain('pr-8');
  });

  it('renders a numeric difficulty input for weight units', () => {
    const wrapper = buildWrapper({ difficultyUnit: 'kilograms' });

    expect(wrapper.find('select').exists()).toBe(false);
    // step="0.5" input is the difficulty field (effort input has no step)
    expect(wrapper.find('input[step="0.5"]').exists()).toBe(true);
  });

  it('renders no difficulty field for the none unit', () => {
    const wrapper = buildWrapper({ difficultyUnit: 'none' });

    expect(wrapper.find('select').exists()).toBe(false);
    expect(wrapper.find('input[step="0.5"]').exists()).toBe(false);
  });

  it('emits the chosen zone as difficulty_value', async () => {
    const wrapper = buildWrapper({ difficultyUnit: 'heart_rate_zone' });

    await wrapper.find('select').setValue('2');

    const updates = wrapper.emitted('update');
    expect(updates).toBeTruthy();
    expect(updates.at(-1)[0].difficulty_value).toBe(2);
  });

  it('emits null when the zone is left as "—"', async () => {
    const wrapper = buildWrapper({ difficultyUnit: 'heart_rate_zone' });

    // Change effort to trigger an update while difficulty stays "—" (0 -> null)
    await wrapper.find('input[type="number"]').setValue('1200');

    const updates = wrapper.emitted('update');
    expect(updates.at(-1)[0].difficulty_value).toBeNull();
  });

  function completeButton(wrapper) {
    return wrapper.find('button[aria-label="Mark set :order as complete"]');
  }

  it('emits update and completion-toggled when the set is marked complete', async () => {
    const wrapper = buildWrapper({ difficultyUnit: 'kilograms' });

    await completeButton(wrapper).trigger('click');

    expect(wrapper.emitted('update').at(-1)[0]).toMatchObject({
      id: 1,
      order: 1,
      effort_value: 1800,
      is_completed: true,
    });
    expect(wrapper.emitted('completion-toggled').at(-1)[0]).toMatchObject({
      is_completed: true,
      previous: false,
    });
  });

  it('reports the previous completion state when unchecking', async () => {
    const wrapper = buildWrapper({
      set: { id: 1, order: 1, effort_value: 1800, difficulty_value: null, is_completed: true },
    });

    await wrapper.find('button[aria-label="Mark set :order as incomplete"]').trigger('click');

    const toggled = wrapper.emitted('completion-toggled').at(-1)[0];
    expect(toggled.is_completed).toBe(false);
    expect(toggled.previous).toBe(true);
  });

  it('cannot be completed while the effort value is zero', () => {
    const wrapper = buildWrapper({
      set: { id: 1, order: 1, effort_value: 0, difficulty_value: null, is_completed: false },
    });

    expect(completeButton(wrapper).attributes('disabled')).toBeDefined();
  });

  it('locks the inputs once the set is completed', () => {
    const wrapper = buildWrapper({
      difficultyUnit: 'kilograms',
      set: { id: 1, order: 1, effort_value: 1800, difficulty_value: 40, is_completed: true },
    });

    wrapper.findAll('input').forEach(input => {
      expect(input.attributes('disabled')).toBeDefined();
    });
  });

  it('follows the completion state when the parent updates the set prop', async () => {
    const wrapper = buildWrapper();

    await wrapper.setProps({
      set: { id: 1, order: 1, effort_value: 1800, difficulty_value: null, is_completed: true },
    });

    expect(completeButton(wrapper).exists()).toBe(false);
    expect(wrapper.find('button[aria-label="Mark set :order as incomplete"]').exists()).toBe(true);
  });

  it('emits remove with the set identity', async () => {
    const wrapper = buildWrapper();

    await wrapper.find('button[aria-label="Remove set :order"]').trigger('click');

    expect(wrapper.emitted('remove').at(-1)[0]).toEqual({ id: 1, order: 1 });
  });

  it('renders no action buttons and disables inputs when not editable', () => {
    const wrapper = buildWrapper({ editable: false, difficultyUnit: 'kilograms' });

    expect(wrapper.findAll('button')).toHaveLength(0);
    wrapper.findAll('input').forEach(input => {
      expect(input.attributes('disabled')).toBeDefined();
    });
  });
});
