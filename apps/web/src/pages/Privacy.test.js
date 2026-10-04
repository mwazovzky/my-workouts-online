import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

import { RouterLinkStub } from '@/test/utils';

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => `translated:${value}` }),
}));

import Privacy from '@/pages/Privacy.vue';

describe('Privacy', () => {
  function buildWrapper() {
    return mount(Privacy, {
      global: {
        stubs: {
          RouterLink: RouterLinkStub,
          ApplicationLogo: { template: '<div />' },
          GuestLocaleSwitcher: { template: '<div />' },
        },
      },
    });
  }

  it('renders every section and the contact address', () => {
    const wrapper = buildWrapper();
    const text = wrapper.text();

    expect(text).toContain('translated:Privacy Policy');
    for (const title of [
      'What we collect',
      'What we use it for',
      'Who else processes it',
      'How long we keep it',
      'Your rights',
      'Contact',
    ]) {
      expect(text).toContain(`translated:${title}`);
    }
    expect(wrapper.find('a[href="mailto:support@my-workouts.online"]').exists()).toBe(true);
  });

  it('says that data is not sold and can be deleted in-app', () => {
    const text = buildWrapper().text();
    expect(text).toContain('never sell or share your data');
    expect(text).toContain('delete your account in the profile settings');
  });
});
