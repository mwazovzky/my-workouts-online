# Enrollment

Users enroll in workout programs to mark interest and access them from their dashboard.

## Current Behavior

1. User views a program on **ProgramShow**
2. User clicks "Enroll" → POST enrolls them via `syncWithoutDetaching`
3. Page reloads — enrollment badge updates; the program shows as enrolled on **ProgramIndex** too

## Business Rules

- Any authenticated user can enroll in any program
- Enrollment is idempotent — enrolling twice has no effect (`syncWithoutDetaching`)
- Enrollment status is exposed as `is_enrolled` on `ProgramResource`
- No authorization policy — only `auth` middleware

## Known Limitations

- No unenroll action
- Enrollment has no visible effect beyond the badge — no personalized dashboard, no "my programs" filter
- No enrollment count or social proof (how many users enrolled)
- No limit on how many programs a user can enroll in

## Pages & Routes

| Action | Method | Path | Name |
|---|---|---|---|
| Enroll | POST | `/programs/{program}/enroll` | `programs.enroll` |

## Related

- [Programs](programs.md) — browsing and viewing programs
- [Workout Logging](workout-logging.md) — starting workouts from enrolled programs
