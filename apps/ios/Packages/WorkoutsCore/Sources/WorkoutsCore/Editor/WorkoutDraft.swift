import Foundation

/// The editable copy of a workout. Ids are nil for rows created on the device until the
/// save response assigns them. Exercise metadata is carried so rows render offline.
public struct WorkoutDraft: Sendable, Equatable, Codable {
    public let workoutId: Int
    public let name: String
    /// The server's `updated_at` when this draft was taken; story 10 uses it to detect
    /// edits made elsewhere before restoring a persisted draft.
    public let baseUpdatedAt: Date?
    public var activities: [DraftActivity]

    public init(workoutId: Int, name: String, baseUpdatedAt: Date?, activities: [DraftActivity]) {
        self.workoutId = workoutId
        self.name = name
        self.baseUpdatedAt = baseUpdatedAt
        self.activities = activities
    }

    public init(workout: Workout) {
        self.init(
            workoutId: workout.id, name: workout.name, baseUpdatedAt: workout.updatedAt,
            activities: workout.activities.map(DraftActivity.init(activity:)))
    }

    public var totalSetCount: Int { activities.reduce(0) { $0 + $1.sets.count } }
    public var completedSetCount: Int { activities.reduce(0) { $0 + $1.sets.filter(\.isCompleted).count } }
}

public struct DraftActivity: Sendable, Equatable, Codable, Identifiable {
    /// Stable identity for SwiftUI across saves, independent of the server id.
    public let localId: UUID
    public var serverId: Int?
    public let exerciseId: Int
    public let exerciseName: String
    public let equipmentName: String?
    public let restTimeSeconds: Int?
    public let categoryNames: [String]
    public let effortType: EffortType
    public let difficultyUnit: DifficultyUnit
    public var sets: [DraftSet]

    public var id: UUID { localId }

    public init(
        localId: UUID = UUID(), serverId: Int?, exerciseId: Int, exerciseName: String, equipmentName: String?,
        restTimeSeconds: Int?, categoryNames: [String], effortType: EffortType, difficultyUnit: DifficultyUnit,
        sets: [DraftSet]
    ) {
        self.localId = localId
        self.serverId = serverId
        self.exerciseId = exerciseId
        self.exerciseName = exerciseName
        self.equipmentName = equipmentName
        self.restTimeSeconds = restTimeSeconds
        self.categoryNames = categoryNames
        self.effortType = effortType
        self.difficultyUnit = difficultyUnit
        self.sets = sets
    }

    public init(activity: Activity) {
        self.init(
            serverId: activity.id,
            exerciseId: activity.exerciseId,
            exerciseName: activity.exerciseName ?? "",
            equipmentName: activity.equipmentName,
            restTimeSeconds: activity.restTimeSeconds,
            categoryNames: activity.categoryNames,
            effortType: activity.effortType ?? .repetitions,
            difficultyUnit: activity.difficultyUnit ?? .none,
            sets: activity.sets.map(DraftSet.init(set:)))
    }

    /// Builds an activity from the exercise picker (story 10) with one blank set.
    public init(exercise: Exercise) {
        self.init(
            serverId: nil,
            exerciseId: exercise.id,
            exerciseName: exercise.name,
            equipmentName: exercise.equipmentName,
            restTimeSeconds: exercise.restTimeSeconds,
            categoryNames: exercise.categories.map(\.name),
            effortType: exercise.effortType,
            difficultyUnit: exercise.difficultyUnit,
            sets: [])
        sets = [DraftSet.blank(for: difficultyUnit)]
    }

    /// Whether the difficulty column is shown at all.
    public var hasDifficulty: Bool { difficultyUnit.hasValue }
    public var isZone: Bool { difficultyUnit == .heartRateZone }
}

public struct DraftSet: Sendable, Equatable, Codable, Identifiable {
    public let localId: UUID
    public var serverId: Int?
    public var effortValue: Int
    public var difficultyValue: Double?
    public var isCompleted: Bool

    public var id: UUID { localId }

    public init(localId: UUID = UUID(), serverId: Int?, effortValue: Int, difficultyValue: Double?, isCompleted: Bool) {
        self.localId = localId
        self.serverId = serverId
        self.effortValue = effortValue
        self.difficultyValue = difficultyValue
        self.isCompleted = isCompleted
    }

    public init(set: WorkoutSet) {
        self.init(
            serverId: set.id, effortValue: set.effortValue, difficultyValue: set.difficultyValue,
            isCompleted: set.isCompleted)
    }

    /// The web default for a first set: effort 0, difficulty 0, or nil for zone and none.
    public static func blank(for unit: DifficultyUnit) -> DraftSet {
        DraftSet(
            serverId: nil, effortValue: 0, difficultyValue: unit == .heartRateZone || unit == .none ? nil : 0,
            isCompleted: false)
    }

    /// A new set copies the previous set's values but is never already completed.
    public static func copying(_ previous: DraftSet) -> DraftSet {
        DraftSet(
            serverId: nil, effortValue: previous.effortValue, difficultyValue: previous.difficultyValue,
            isCompleted: false)
    }

    public var canComplete: Bool { effortValue > 0 }
}
