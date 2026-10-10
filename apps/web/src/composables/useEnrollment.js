import { toast } from 'vue-sonner';
import { api } from '@/lib/api';
import { useTranslation } from '@/composables/useTranslation';

/**
 * Composable for handling program enrollment via the API
 * (`PUT`/`DELETE /programs/{id}/enrollment`, both idempotent).
 * Each action returns true on success, false on failure (error is shown via toast).
 */
export function useEnrollment() {
  const { t } = useTranslation();

  async function enroll(programId) {
    try {
      await api.put(`/programs/${programId}/enrollment`);
      return true;
    } catch {
      toast.error(t('Failed to enroll in program'));
      return false;
    }
  }

  async function unenroll(programId) {
    try {
      await api.delete(`/programs/${programId}/enrollment`);
      return true;
    } catch {
      toast.error(t('Failed to unenroll from program'));
      return false;
    }
  }

  return { enroll, unenroll };
}
