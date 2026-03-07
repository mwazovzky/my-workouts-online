# Product Overview

Workout tracking app for browsing training programs, starting workouts from templates, logging sets, and reviewing workout history.

## Domain Glossary

| Term | Definition |
| --- | --- |
| **Program** | Collection of workout templates, such as a weekly training split. |
| **Workout Template** | Reusable workout blueprint within a program, assigned to a weekday. |
| **Workout** | User workout session created from a template or repeated from a prior workout. |
| **Activity** | Exercise slot inside a template or workout. |
| **Set** | Ordered effort record inside an activity with effort, difficulty, and completion state. |
| **Exercise** | Catalog item with effort type, equipment, categories, and translations. |
| **Equipment** | Gear type that determines the difficulty unit, such as kilograms, pounds, plates, or none. |
| **Category** | Exercise classification such as chest, legs, or mobility. |

## Core User Flow

1. Browse available programs.
2. Open a program and inspect its workout templates.
3. Enroll in the program.
4. Start a workout from a template.
5. Edit activities and sets during the workout.
6. Save progress or complete the workout.
7. Review workout history and repeat a finished workout.

## Related

- [Architecture](architecture.md)
- [Pages & Routes](pages-and-routes.md)
