import { toast } from 'vue-sonner';
import { useTranslation } from '@/composables/useTranslation';

/**
 * Composable for handling program enrollment via the API.
 * Returns true on success, false on failure (error is shown via toast).
 */
export function useEnrollment() {
  const { t } = useTranslation();

  async function enroll(programId) {
    try {
      await window.axios.post(`/api/v1/programs/${programId}/enroll`);
      return true;
    } catch {
      toast.error(t('Failed to enroll in program'));
      return false;
    }
  }

  return { enroll };
}
