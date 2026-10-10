import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { RouterLinkStub, withPinia } from '@/test/utils';

const { mockGet, mockEnroll, mockUnenroll, mockPush } = vi.hoisted(() => ({
  mockGet: vi.fn(),
  mockEnroll: vi.fn(),
  mockUnenroll: vi.fn(),
  mockPush: vi.fn(),
}));

vi.mock('@/composables/useTranslation', () => ({
  useTranslation: () => ({ t: value => value }),
}));
vi.mock('@/composables/useApi', () => ({ useApi: () => ({ get: mockGet }) }));
vi.mock('@/composables/useEnrollment', () => ({
  useEnrollment: () => ({ enroll: mockEnroll, unenroll: mockUnenroll }),
}));
vi.mock('vue-router', async importOriginal => ({
  ...(await importOriginal()),
  useRouter: () => ({ push: mockPush }),
}));
vi.mock('vue-sonner', () => ({ toast: { error: vi.fn() } }));

import ProgramShow from '@/pages/ProgramShow.vue';

const ConfirmDialogStub = {
  name: 'ConfirmDialog',
  props: ['open', 'title', 'description', 'confirmLabel'],
  emits: ['confirm', 'cancel'],
  template: `
    <div v-if="open" data-testid="confirm-dialog">
      <p>{{ description }}</p>
      <button data-testid="confirm" @click="$emit('confirm')">{{ confirmLabel }}</button>
      <button data-testid="cancel" @click="$emit('cancel')">Cancel</button>
    </div>
  `,
};

function program(overrides = {}) {
  return {
    id: 3,
    name: 'Strength',
    description: 'Lift',
    is_enrolled: true,
    workout_templates: [],
    ...overrides,
  };
}

async function buildWrapper(data) {
  mockGet.mockResolvedValueOnce({ data: { data } });

  const wrapper = mount(ProgramShow, {
    props: { id: 3 },
    global: {
      plugins: [withPinia({ name: 'Ada', email: 'ada@example.com' })],
      stubs: {
        AuthenticatedLayout: { template: '<div><slot name="header" /><slot /></div>' },
        PageHeader: { template: '<div />' },
        RouterLink: RouterLinkStub,
        ConfirmDialog: ConfirmDialogStub,
      },
    },
  });
  await flushPromises();

  return wrapper;
}

function buttonWithText(wrapper, text) {
  return wrapper.findAll('button').find(button => button.text() === text);
}

describe('ProgramShow', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('enrolls and shows the enrolled state', async () => {
    mockEnroll.mockResolvedValueOnce(true);
    const wrapper = await buildWrapper(program({ is_enrolled: false }));

    await buttonWithText(wrapper, 'Enroll in Program').trigger('click');
    await flushPromises();

    expect(mockEnroll).toHaveBeenCalledWith(3);
    expect(wrapper.text()).toContain('Enrolled');
    expect(buttonWithText(wrapper, 'Unenroll')).toBeDefined();
  });

  it('asks for confirmation before unenrolling', async () => {
    const wrapper = await buildWrapper(program());

    await buttonWithText(wrapper, 'Unenroll').trigger('click');

    expect(wrapper.find('[data-testid="confirm-dialog"]').text()).toContain(
      'Unenroll from this program? Your workouts are kept.'
    );
    expect(mockUnenroll).not.toHaveBeenCalled();
  });

  it('unenrolls once confirmed', async () => {
    mockUnenroll.mockResolvedValueOnce(true);
    const wrapper = await buildWrapper(program());

    await buttonWithText(wrapper, 'Unenroll').trigger('click');
    await wrapper.find('[data-testid="confirm"]').trigger('click');
    await flushPromises();

    expect(mockUnenroll).toHaveBeenCalledWith(3);
    expect(wrapper.find('[data-testid="confirm-dialog"]').exists()).toBe(false);
    expect(buttonWithText(wrapper, 'Enroll in Program')).toBeDefined();
  });

  it('keeps the enrollment when the dialog is cancelled', async () => {
    const wrapper = await buildWrapper(program());

    await buttonWithText(wrapper, 'Unenroll').trigger('click');
    await wrapper.find('[data-testid="cancel"]').trigger('click');

    expect(mockUnenroll).not.toHaveBeenCalled();
    expect(wrapper.find('[data-testid="confirm-dialog"]').exists()).toBe(false);
    expect(wrapper.text()).toContain('Enrolled');
  });

  it('stays enrolled when unenrolling fails', async () => {
    mockUnenroll.mockResolvedValueOnce(false);
    const wrapper = await buildWrapper(program());

    await buttonWithText(wrapper, 'Unenroll').trigger('click');
    await wrapper.find('[data-testid="confirm"]').trigger('click');
    await flushPromises();

    expect(wrapper.text()).toContain('Enrolled');
    expect(buttonWithText(wrapper, 'Unenroll')).toBeDefined();
  });
});
