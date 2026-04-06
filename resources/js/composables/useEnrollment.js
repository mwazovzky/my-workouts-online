import { useTranslation } from '@/composables/useTranslation';

/**
 * Composable for handling program enrollment via the API.
 * Returns a promise so callers can update local state on success.
 */
export function useEnrollment() {
  const { t } = useTranslation();

  async function enroll(programId) {
    try {
      await window.axios.post(`/api/v1/programs/${programId}/enroll`);
    } catch {
      alert(t('Failed to enroll in program'));
      throw new Error('Enrollment failed');
    }
  }

  return { enroll };
}
