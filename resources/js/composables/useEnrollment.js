import { router } from '@inertiajs/vue3';

/**
 * Composable for handling program enrollment functionality
 * @param {Object} options - Configuration options
 * @param {string[]} options.only - Array of props to reload after enrollment
 * @returns {Object} Enrollment utilities
 */
export function useEnrollment(options = {}) {
  const { only = [] } = options;

  /**
   * Enroll a user in a program
   * @param {number} programId - The ID of the program to enroll in
   */
  function enroll(programId) {
    router.post(
      route('programs.enroll', { program: programId }),
      {},
      {
        preserveScroll: true,
        only,
        onError: () => {
          alert('Failed to enroll in program');
        },
      }
    );
  }

  return {
    enroll,
  };
}
