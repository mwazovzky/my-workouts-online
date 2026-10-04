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
    public let effortLabel: String
    public let difficultyUnit: DifficultyUnit
    public let difficultyLabel: String?
    public var sets: [DraftSet]

    public var id: UUID { localId }

    public init(
        localId: UUID = UUID(), serverId: Int?, exerciseId: Int, exerciseName: String, equipmentName: String?,
        restTimeSeconds: Int?, categoryNames: [String], effortType: EffortType, effortLabel: String,
        difficultyUnit: DifficultyUnit, difficultyLabel: String?, sets: [DraftSet]
    ) {
        self.localId = localId
        self.serverId = serverId
        self.exerciseId = exerciseId
        self.exerciseName = exerciseName
        self.equipmentName = equipmentName
        self.restTimeSeconds = restTimeSeconds
        self.categoryNames = categoryNames
        self.effortType = effortType
        self.effortLabel = effortLabel
        self.difficultyUnit = difficultyUnit
        self.difficultyLabel = difficultyLabel
        self.sets = sets
    }

    public init(activity: Activity) {
        self.init(
            serverId: activity.id,
            exerciseId: activity.exerciseId ?? 0,
            exerciseName: activity.exerciseName ?? "",
            equipmentName: activity.equipmentName,
            restTimeSeconds: activity.restTimeSeconds,
            categoryNames: activity.categoryNames,
            effortType: activity.effortType ?? .repetitions,
            effortLabel: activity.effortLabel.isEmpty ? "Reps" : activity.effortLabel,
            difficultyUnit: activity.difficultyUnit ?? .none,
            difficultyLabel: activity.difficultyLabel,
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
            effortLabel: exercise.effortLabel,
            difficultyUnit: exercise.difficultyUnit,
            difficultyLabel: exercise.difficultyLabel,
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
