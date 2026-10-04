import Foundation
import Observation
import WorkoutsAPI

/// The workout editor's state and rules, ported from the web editor (`WorkoutEdit.vue`,
/// `Activity.vue`, `Set.vue`). Views bind to the draft through the mutation methods so
/// every rule lives here and is unit-tested.
@MainActor
@Observable
public final class WorkoutEditorModel {
    public enum Mode: Sendable, Equatable {
        /// Own, in-progress workout.
        case editable
        /// Completed, or someone else's: shown read-only.
        case readOnly
    }

    public private(set) var state: LoadState<WorkoutDraft> = .idle
    public private(set) var mode: Mode = .readOnly
    public private(set) var workout: Workout?
    public private(set) var isDirty = false
    public private(set) var isSaving = false
    public private(set) var saveError: APIError?
    /// 422 messages keyed by `activities.{a}.sets.{s}.{field}` and `activities.{a}.{field}`.
    public private(set) var fieldErrors: [String: String] = [:]
    public private(set) var lastSavedAt: Date?

    private let workouts: any WorkoutRepository
    private let currentUserId: Int
    public let workoutId: Int

    public init(workouts: any WorkoutRepository, workoutId: Int, currentUserId: Int) {
        self.workouts = workouts
        self.workoutId = workoutId
        self.currentUserId = currentUserId
    }

    public var draft: WorkoutDraft? { state.value }
    public var isEditable: Bool { mode == .editable }

    public var progress: Double {
        guard let draft, draft.totalSetCount > 0 else { return 0 }
        return Double(draft.completedSetCount) / Double(draft.totalSetCount)
    }

    public func load() async {
        if state.value == nil { state = .loading }
        do {
            let workout = try await workouts.get(id: workoutId)
            apply(workout)
        } catch {
            state = .failed(APIError.from(error))
        }
    }

    // MARK: Set rules

    public func setEffort(_ value: Int, activity: UUID, set: UUID) {
        mutate(activity: activity, set: set) { draftSet in
            guard !draftSet.isCompleted else { return }
            draftSet.effortValue = max(0, value)
            if draftSet.effortValue == 0 { draftSet.isCompleted = false }
        }
    }

    /// Decimal difficulty (weight, plates); blank is nil, negatives clamp to 0.
    public func setDifficulty(_ value: Double?, activity: UUID, set: UUID) {
        mutate(activity: activity, set: set) { draftSet in
            guard !draftSet.isCompleted else { return }
            draftSet.difficultyValue = value.map { max(0, $0) }
        }
    }

    /// Heart-rate zone: 1–5 or nil; anything else is ignored.
    public func setZone(_ zone: Int?, activity: UUID, set: UUID) {
        mutate(activity: activity, set: set) { draftSet in
            guard !draftSet.isCompleted else { return }
            if let zone {
                guard (1...5).contains(zone) else { return }
                draftSet.difficultyValue = Double(zone)
            } else {
                draftSet.difficultyValue = nil
            }
        }
    }

    public func setCompleted(_ completed: Bool, activity: UUID, set: UUID) {
        mutate(activity: activity, set: set) { draftSet in
            if completed {
                guard draftSet.canComplete else { return }
                draftSet.isCompleted = true
            } else {
                draftSet.isCompleted = false
            }
        }
    }

    public func addSet(to activity: UUID) {
        mutateActivity(activity) { draftActivity in
            let newSet =
                draftActivity.sets.last.map(DraftSet.copying) ?? DraftSet.blank(for: draftActivity.difficultyUnit)
            draftActivity.sets.append(newSet)
        }
    }

    // MARK: Save

    /// Builds the bulk payload with orders renumbered from 1, saves, and adopts server ids.
    @discardableResult
    public func save() async -> Bool {
        guard isEditable, !isSaving, let draft else { return false }
        isSaving = true
        saveError = nil
        fieldErrors = [:]
        defer { isSaving = false }
        do {
            let saved = try await workouts.save(id: workoutId, payload: Self.payload(for: draft))
            adoptIds(from: saved, into: draft)
            isDirty = false
            lastSavedAt = Date()
            return true
        } catch {
            let apiError = APIError.from(error)
            saveError = apiError
            if case .validation(_, let fields) = apiError {
                fieldErrors = fields.compactMapValues { $0.first?.message }
            }
            return false
        }
    }

    public func dismissSaveError() {
        saveError = nil
    }

    public func fieldError(activity activityIndex: Int, set setIndex: Int, field: String) -> String? {
        fieldErrors["activities.\(activityIndex).sets.\(setIndex).\(field)"]
    }

    public static func payload(for draft: WorkoutDraft) -> WorkoutSavePayload {
        WorkoutSavePayload(
            activities: draft.activities.enumerated().map { activityIndex, activity in
                WorkoutSavePayload.Activity(
                    id: activity.serverId,
                    exerciseId: activity.exerciseId,
                    order: activityIndex + 1,
                    sets: activity.sets.enumerated().map { setIndex, set in
                        WorkoutSavePayload.Set(
                            id: set.serverId, order: setIndex + 1, effortValue: set.effortValue,
                            difficultyValue: set.difficultyValue, isCompleted: set.isCompleted)
                    })
            })
    }

    // MARK: Internals

    private func apply(_ workout: Workout) {
        self.workout = workout
        mode = workout.isInProgress && workout.userId == currentUserId ? .editable : .readOnly
        state = .loaded(WorkoutDraft(workout: workout))
        isDirty = false
    }

    /// Server rows come back in the order we sent them, so ids are matched positionally;
    /// the local identities (and any typing in flight) are preserved.
    private func adoptIds(from saved: Workout, into draft: WorkoutDraft) {
        var updated = draft
        for (activityIndex, activity) in saved.activities.enumerated() where activityIndex < updated.activities.count {
            updated.activities[activityIndex].serverId = activity.id
            for (setIndex, set) in activity.sets.enumerated()
            where setIndex < updated.activities[activityIndex].sets.count {
                updated.activities[activityIndex].sets[setIndex].serverId = set.id
            }
        }
        workout = saved
        state = .loaded(
            WorkoutDraft(
                workoutId: updated.workoutId, name: saved.name, baseUpdatedAt: saved.updatedAt,
                activities: updated.activities))
    }

    private func mutate(activity: UUID, set: UUID, _ change: (inout DraftSet) -> Void) {
        mutateActivity(activity) { draftActivity in
            guard let index = draftActivity.sets.firstIndex(where: { $0.localId == set }) else { return }
            change(&draftActivity.sets[index])
        }
    }

    func mutateActivity(_ activity: UUID, _ change: (inout DraftActivity) -> Void) {
        guard isEditable, var draft, let index = draft.activities.firstIndex(where: { $0.localId == activity }) else {
            return
        }
        let before = draft.activities[index]
        change(&draft.activities[index])
        guard draft.activities[index] != before else { return }
        state = .loaded(draft)
        isDirty = true
    }

    func replaceDraft(_ transform: (inout WorkoutDraft) -> Void) {
        guard isEditable, var draft else { return }
        let before = draft
        transform(&draft)
        guard draft != before else { return }
        state = .loaded(draft)
        isDirty = true
    }
}

/// The body of `PATCH /workouts/{id}/save`, in domain terms; the repository maps it to the
/// generated request type.
public struct WorkoutSavePayload: Sendable, Equatable {
    public struct Set: Sendable, Equatable {
        public let id: Int?
        public let order: Int
        public let effortValue: Int
        public let difficultyValue: Double?
        public let isCompleted: Bool

        public init(id: Int?, order: Int, effortValue: Int, difficultyValue: Double?, isCompleted: Bool) {
            self.id = id
            self.order = order
            self.effortValue = effortValue
            self.difficultyValue = difficultyValue
            self.isCompleted = isCompleted
        }
    }

    public struct Activity: Sendable, Equatable {
        public let id: Int?
        public let exerciseId: Int
        public let order: Int
        public let sets: [Set]

        public init(id: Int?, exerciseId: Int, order: Int, sets: [Set]) {
            self.id = id
            self.exerciseId = exerciseId
            self.order = order
            self.sets = sets
        }
    }

    public let activities: [Activity]

    public init(activities: [Activity]) {
        self.activities = activities
    }
}
