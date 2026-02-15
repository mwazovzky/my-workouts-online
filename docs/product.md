# Product Overview

A workout tracking app. Users browse curated workout programs, enroll in them, start workouts from templates, track exercises and sets in real time, then mark workouts complete. Completed workouts can be repeated.

## Domain Glossary

| Term                 | Definition                                                                                                              |
| -------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **Program**          | A named collection of workout templates (e.g. "Push/Pull/Legs"). Users enroll in programs.                              |
| **Workout Template** | A reusable workout blueprint within a program, assigned to a weekday. Contains template activities.                     |
| **Workout**          | A user's concrete workout session, created from a template. Has status: `in_progress` → `completed`.                    |
| **Activity**         | An exercise slot inside a template or workout. Polymorphic — belongs to either a WorkoutTemplate or a Workout. Ordered. |
| **Set**              | A single set within an activity: repetitions, weight, completion flag. Ordered.                                         |
| **Exercise**         | Catalog entry: name, description, rest time. Belongs to one equipment, many categories.                                 |
| **Equipment**        | Gear type with a measurement unit (e.g. "Barbell", kg).                                                                 |
| **Category**         | Exercise classification (e.g. "Chest", "Legs"). Many-to-many with exercises.                                            |

## Features

**[Program Browsing](features/programs.md)** — Browse a catalog of workout programs and view their templates organized by weekday.

**[Enrollment](features/enrollment.md)** — Enroll in programs. Additive only (no unenroll).

**[Workout Logging](features/workout-logging.md)** — Start workouts from templates, track in real time, complete, repeat, or delete.

**[Activity & Set Tracking](features/activity-tracking.md)** — Edit sets (reps, weight, completion) and delete activities within in-progress workouts.

**[Auth & Profile](features/auth-and-profile.md)** — Registration, login, email verification, password reset, profile edit, account deletion.

## Core User Flow

1. **Browse programs** → `ProgramIndex` page lists all programs
2. **View program** → `ProgramShow` page shows templates by weekday
3. **Enroll** → POST enrolls the user in the program
4. **Start workout** → POST creates a workout from a template, redirects to edit
5. **Track workout** → `WorkoutEdit` page — update sets, mark them complete, reorder activities via drag-and-drop, delete activities
6. **Complete workout** → POST transitions status to `completed`
7. **Review history** → `WorkoutIndex` lists past workouts (paginated); `WorkoutShow` shows details
8. **Repeat workout** → POST on a completed workout creates a new in-progress copy
