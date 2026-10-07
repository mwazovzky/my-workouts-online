import Foundation
import WorkoutsAPI

// Converts generated contract types (`Components.Components.Schemas.*`) into domain models. Every
// field the app uses is referenced here, so a contract change that drops or renames one
// is a compile error in this file rather than a silent `nil` at runtime.

/// Thrown when a server enum value is outside the contract's enum (should not happen; the
/// generated decoder rejects unknown raw values first).
public struct UnknownEnumValue: Error, Sendable, Equatable {
    public let type: String
    public let value: String
}

extension EffortType {
    init(api: Components.Schemas.EffortType) throws {
        guard let value = EffortType(rawValue: api.rawValue) else {
            throw UnknownEnumValue(type: "EffortType", value: api.rawValue)
        }
        self = value
    }

    init(api: Components.Schemas.Activity.ExerciseEffortTypePayload) throws {
        guard let value = EffortType(rawValue: api.rawValue) else {
            throw UnknownEnumValue(type: "EffortType", value: api.rawValue)
        }
        self = value
    }
}

extension DifficultyUnit {
    init(api: Components.Schemas.DifficultyUnit) throws {
        guard let value = DifficultyUnit(rawValue: api.rawValue) else {
            throw UnknownEnumValue(type: "DifficultyUnit", value: api.rawValue)
        }
        self = value
    }

    init(api: Components.Schemas.Activity.ExerciseDifficultyUnitPayload) throws {
        guard let value = DifficultyUnit(rawValue: api.rawValue) else {
            throw UnknownEnumValue(type: "DifficultyUnit", value: api.rawValue)
        }
        self = value
    }
}

extension WorkoutStatus {
    init(api: Components.Schemas.WorkoutStatus) throws {
        guard let value = WorkoutStatus(rawValue: api.rawValue) else {
            throw UnknownEnumValue(type: "WorkoutStatus", value: api.rawValue)
        }
        self = value
    }
}

extension ThemePreference {
    init(api: Components.Schemas.ThemePreference) throws {
        guard let value = ThemePreference(rawValue: api.rawValue) else {
            throw UnknownEnumValue(type: "ThemePreference", value: api.rawValue)
        }
        self = value
    }
}

extension Weekday {
    init(api: Components.Schemas.Weekday) throws {
        guard let value = Weekday(rawValue: api.rawValue) else {
            throw UnknownEnumValue(type: "Weekday", value: api.rawValue)
        }
        self = value
    }
}

extension User {
    public init(api: Components.Schemas.User) throws {
        self.init(
            id: api.id,
            name: api.name,
            email: api.email,
            locale: api.locale.rawValue,
            themePreference: try ThemePreference(api: api.themePreference),
            isAdmin: api.isAdmin,
            emailVerified: api.emailVerified
        )
    }
}

extension AuthGrant {
    public init(api: Components.Schemas.AuthResponse) throws {
        self.init(
            accessToken: api.accessToken,
            expiresIn: TimeInterval(api.expiresIn),
            refreshToken: api.refreshToken,
            user: try User(api: api.user)
        )
    }
}

extension Category {
    public init(api: Components.Schemas.Category) {
        self.init(id: api.id, name: api.name ?? "")
    }
}

extension Exercise {
    public init(api: Components.Schemas.Exercise) throws {
        self.init(
            id: api.id,
            name: api.name ?? "",
            description: api.description,
            equipmentId: api.equipmentId,
            equipmentName: api.equipmentName,
            effortType: try EffortType(api: api.effortType),
            effortLabel: api.effortLabel,
            difficultyUnit: try api.difficultyUnit.map(DifficultyUnit.init(api:)) ?? .none,
            difficultyLabel: api.difficultyLabel,
            restTimeSeconds: api.restTimeSeconds,
            categories: (api.categories ?? []).map(Category.init(api:))
        )
    }
}

extension Program {
    public init(api: Components.Schemas.Program) {
        self.init(id: api.id, name: api.name ?? "", description: api.description, isEnrolled: api.isEnrolled)
    }
}

extension ProgramDetail {
    public init(api: Components.Schemas.ProgramDetail) throws {
        self.init(
            program: Program(api: api.value1),
            workoutTemplates: try api.value2.workoutTemplates.map(WorkoutTemplate.init(api:))
        )
    }
}

extension WorkoutTemplate {
    public init(api: Components.Schemas.WorkoutTemplate) throws {
        self.init(
            id: api.id,
            name: api.name ?? "",
            description: api.description,
            activities: try api.activities.map(Activity.init(api:)),
            schedule: try api.pivot.map {
                ScheduleSlot(weekday: try Weekday(api: $0.weekday), weekdayLabel: $0.weekdayLabel)
            }
        )
    }
}

extension WorkoutSet {
    public init(api: Components.Schemas.Set) {
        self.init(
            id: api.id,
            order: api.order,
            effortValue: api.effortValue,
            difficultyValue: api.difficultyValue,
            isCompleted: api.isCompleted
        )
    }
}

extension Activity {
    public init(api: Components.Schemas.Activity) throws {
        self.init(
            id: api.id,
            exerciseId: api.exerciseId,
            exerciseName: api.exerciseName,
            restTimeSeconds: api.restTimeSeconds,
            equipmentName: api.exerciseEquipmentName,
            effortType: try api.exerciseEffortType.map(EffortType.init(api:)),
            effortLabel: api.exerciseEffortLabel ?? "",
            difficultyUnit: try api.exerciseDifficultyUnit.map(DifficultyUnit.init(api:)),
            difficultyLabel: api.exerciseDifficultyLabel,
            categoryNames: api.exerciseCategoryNames ?? [],
            sets: api.sets.map(WorkoutSet.init(api:))
        )
    }
}

extension Workout {
    public init(api: Components.Schemas.Workout) throws {
        self.init(
            id: api.id,
            userId: api.userId,
            name: api.name,
            status: try WorkoutStatus(api: api.status),
            statusLabel: api.statusLabel,
            createdAt: api.createdAt,
            updatedAt: api.updatedAt,
            activitiesCount: api.activitiesCount,
            template: try api.workoutTemplate.map(WorkoutTemplate.init(api:)),
            activities: try api.activities.map(Activity.init(api:))
        )
    }
}

extension Page where Item == Workout {
    public init(api: Components.Schemas.WorkoutPage) throws {
        self.init(
            items: try api.data.map(Workout.init(api:)),
            currentPage: api.meta.currentPage,
            lastPage: api.meta.lastPage,
            total: api.meta.total
        )
    }
}

extension ScheduledWorkout {
    public init(api: Components.Schemas.Dashboard.UpcomingSchedulePayloadPayload) throws {
        self.init(
            id: api.id,
            programId: api.programId,
            programName: api.programName,
            workoutTemplateId: api.workoutTemplateId,
            workoutName: api.workoutName,
            weekday: try Weekday(api: api.weekday),
            weekdayLabel: api.weekdayLabel,
            scheduledAt: api.scheduledAt,
            scheduledFor: api.scheduledFor
        )
    }
}

extension DashboardSummary {
    public init(api: Components.Schemas.Dashboard.SummaryPayload) {
        self.init(
            enrolledProgramsCount: api.enrolledProgramsCount,
            completedWorkoutsCount: api.completedWorkoutsCount,
            completedLast7DaysCount: api.completedLast7DaysCount,
            upcomingWorkoutsCount: api.upcomingWorkoutsCount
        )
    }
}

extension Dashboard {
    public init(api: Components.Schemas.Dashboard) throws {
        self.init(
            upcomingSchedule: try api.upcomingSchedule.map(ScheduledWorkout.init(api:)),
            inProgressWorkout: try api.inProgressWorkout.map(Workout.init(api:)),
            recentWorkouts: try api.recentWorkouts.map(Workout.init(api:)),
            summary: DashboardSummary(api: api.summary)
        )
    }
}

extension Meta {
    public init(api: Operations.GetMeta.Output.Ok.Body.JsonPayload.DataPayload) throws {
        self.init(
            availableLocales: api.availableLocales.additionalProperties
                .map { Meta.AvailableLocale(code: $0.key, name: $0.value) }
                .sorted { $0.code < $1.code },
            themePreferences: try api.themePreferences.map(ThemePreference.init(api:))
        )
    }
}
