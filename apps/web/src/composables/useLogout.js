import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

export function useLogout() {
  const auth = useAuthStore();
  const router = useRouter();

  async function logout() {
    try {
      await auth.logout();
    } finally {
      router.push({ name: 'welcome' });
    }
  }

  return { logout };
}
