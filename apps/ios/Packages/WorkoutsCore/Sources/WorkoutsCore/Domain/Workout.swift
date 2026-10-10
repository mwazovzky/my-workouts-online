import Foundation

public struct WorkoutSet: Sendable, Equatable, Identifiable {
    public let id: Int
    public var order: Int
    public var effortValue: Int
    public var difficultyValue: Double?
    public var isCompleted: Bool

    public init(id: Int, order: Int, effortValue: Int, difficultyValue: Double?, isCompleted: Bool) {
        self.id = id
        self.order = order
        self.effortValue = effortValue
        self.difficultyValue = difficultyValue
        self.isCompleted = isCompleted
    }
}

/// An exercise within a template or a workout, with its sets. Exercise details come from the
/// API's exercise snapshot so retired exercises still render; they are nil when the API omits it.
public struct Activity: Sendable, Equatable, Identifiable {
    public let id: Int
    public let exerciseId: Int
    public let exerciseName: String?
    public let restTimeSeconds: Int?
    public let equipmentName: String?
    public let effortType: EffortType?
    public let difficultyUnit: DifficultyUnit?
    public let categoryNames: [String]
    public var sets: [WorkoutSet]

    public init(
        id: Int, exerciseId: Int, exerciseName: String?, restTimeSeconds: Int?, equipmentName: String?,
        effortType: EffortType?, difficultyUnit: DifficultyUnit?, categoryNames: [String], sets: [WorkoutSet]
    ) {
        self.id = id
        self.exerciseId = exerciseId
        self.exerciseName = exerciseName
        self.restTimeSeconds = restTimeSeconds
        self.equipmentName = equipmentName
        self.effortType = effortType
        self.difficultyUnit = difficultyUnit
        self.categoryNames = categoryNames
        self.sets = sets
    }

    public var completedSetCount: Int { sets.filter(\.isCompleted).count }
}

public struct Workout: Sendable, Equatable, Identifiable {
    public let id: Int
    public let userId: Int
    public let name: String
    public let status: WorkoutStatus
    public let createdAt: Date?
    public let updatedAt: Date?
    public let activitiesCount: Int?
    /// Absent when the API omits it (template not loaded or deleted).
    public let template: WorkoutTemplate?
    public var activities: [Activity]
    /// The response's `ETag` (`"<id>-<version>"`), sent back as `If-Match` when the editor
    /// replaces the activities. Nil for workouts read from the list, which carries no tag.
    public var etag: String?

    public init(
        id: Int, userId: Int, name: String, status: WorkoutStatus, createdAt: Date?,
        updatedAt: Date?, activitiesCount: Int?, template: WorkoutTemplate?, activities: [Activity],
        etag: String? = nil
    ) {
        self.id = id
        self.userId = userId
        self.name = name
        self.status = status
        self.createdAt = createdAt
        self.updatedAt = updatedAt
        self.activitiesCount = activitiesCount
        self.template = template
        self.activities = activities
        self.etag = etag
    }

    public var isInProgress: Bool { status == .inProgress }
    public var totalSetCount: Int { activities.reduce(0) { $0 + $1.sets.count } }
    public var completedSetCount: Int { activities.reduce(0) { $0 + $1.completedSetCount } }
}

/// One Laravel-style paginated response.
public struct Page<Item: Sendable & Equatable>: Sendable, Equatable {
    public let items: [Item]
    public let currentPage: Int
    public let lastPage: Int
    public let total: Int?

    public init(items: [Item], currentPage: Int, lastPage: Int, total: Int?) {
        self.items = items
        self.currentPage = currentPage
        self.lastPage = lastPage
        self.total = total
    }

    public var hasMore: Bool { currentPage < lastPage }
    public var nextPage: Int? { hasMore ? currentPage + 1 : nil }
}

public struct ScheduledWorkout: Sendable, Equatable, Identifiable {
    public let id: String
    public let programId: Int
    public let programName: String?
    public let workoutTemplateId: Int
    public let workoutName: String?
    public let weekday: Weekday
    public let scheduledAt: Date
    /// `YYYY-MM-DD` in the user's calendar, as the API formats it.
    public let scheduledFor: String

    public init(
        id: String, programId: Int, programName: String?, workoutTemplateId: Int, workoutName: String?,
        weekday: Weekday, scheduledAt: Date, scheduledFor: String
    ) {
        self.id = id
        self.programId = programId
        self.programName = programName
        self.workoutTemplateId = workoutTemplateId
        self.workoutName = workoutName
        self.weekday = weekday
        self.scheduledAt = scheduledAt
        self.scheduledFor = scheduledFor
    }
}

public struct DashboardSummary: Sendable, Equatable {
    public let enrolledProgramsCount: Int
    public let completedWorkoutsCount: Int
    public let completedLast7DaysCount: Int
    public let upcomingWorkoutsCount: Int

    public init(
        enrolledProgramsCount: Int, completedWorkoutsCount: Int, completedLast7DaysCount: Int,
        upcomingWorkoutsCount: Int
    ) {
        self.enrolledProgramsCount = enrolledProgramsCount
        self.completedWorkoutsCount = completedWorkoutsCount
        self.completedLast7DaysCount = completedLast7DaysCount
        self.upcomingWorkoutsCount = upcomingWorkoutsCount
    }
}

public struct Dashboard: Sendable, Equatable {
    public let upcomingSchedule: [ScheduledWorkout]
    /// Absent when nothing is in progress (the API omits the key).
    public let inProgressWorkout: Workout?
    public let recentWorkouts: [Workout]
    public let summary: DashboardSummary

    public init(
        upcomingSchedule: [ScheduledWorkout], inProgressWorkout: Workout?, recentWorkouts: [Workout],
        summary: DashboardSummary
    ) {
        self.upcomingSchedule = upcomingSchedule
        self.inProgressWorkout = inProgressWorkout
        self.recentWorkouts = recentWorkouts
        self.summary = summary
    }
}
