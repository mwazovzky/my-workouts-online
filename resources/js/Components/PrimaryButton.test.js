import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

import PrimaryButton from '@/Components/PrimaryButton.vue';

describe('PrimaryButton', () => {
  it('renders a button element', () => {
    const wrapper = mount(PrimaryButton);
    expect(wrapper.element.tagName).toBe('BUTTON');
  });

  it('renders slot content', () => {
    const wrapper = mount(PrimaryButton, { slots: { default: 'Log in' } });
    expect(wrapper.text()).toBe('Log in');
  });

  it('applies pill and primary styling', () => {
    const wrapper = mount(PrimaryButton);
    expect(wrapper.classes()).toContain('rounded-full');
    expect(wrapper.classes()).toContain('bg-primary');
  });
});
