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
    public private(set) var isCompleting = false
    public private(set) var completeError: APIError?
    /// A persisted draft was applied on load; the banner offers Discard.
    public private(set) var restoredDraft = false
    /// A persisted draft was dropped because the server copy had changed since it was taken.
    public private(set) var staleDraftDiscarded = false
    /// The last save was refused with 412: the workout changed elsewhere since it was loaded.
    /// Nothing was written; the view offers `reloadAfterConflict()`.
    public private(set) var changedElsewhere = false

    private let workouts: any WorkoutRepository
    private let drafts: (any DraftStore)?
    private let currentUserId: Int
    private let persistDelay: Duration
    private var persistTask: Task<Void, Never>?
    public let workoutId: Int

    public init(
        workouts: any WorkoutRepository, workoutId: Int, currentUserId: Int, drafts: (any DraftStore)? = nil,
        persistDelay: Duration = .milliseconds(500)
    ) {
        self.workouts = workouts
        self.workoutId = workoutId
        self.currentUserId = currentUserId
        self.drafts = drafts
        self.persistDelay = persistDelay
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
            await restoreDraftIfAny(for: workout)
        } catch {
            state = .failed(APIError.from(error))
        }
    }

    // MARK: Activities

    /// Appends the picked exercise with one blank set, like the web picker.
    public func addActivity(exercise: Exercise) {
        replaceDraft { $0.activities.append(DraftActivity(exercise: exercise)) }
    }

    /// Removing the last set removes the whole activity; the view confirms first using this.
    public func removingSetWouldDeleteActivity(_ activity: UUID) -> Bool {
        draft?.activities.first { $0.localId == activity }?.sets.count == 1
    }

    /// The only remaining activity cannot be deleted.
    public func canDeleteActivity(_ activity: UUID) -> Bool {
        (draft?.activities.count ?? 0) > 1
    }

    public func removeSet(activity: UUID, set: UUID) {
        guard let activityIndex = draft?.activities.firstIndex(where: { $0.localId == activity }) else { return }
        if draft?.activities[activityIndex].sets.count == 1 {
            deleteActivity(activity)
            return
        }
        mutateActivity(activity) { $0.sets.removeAll { $0.localId == set } }
    }

    public func deleteActivity(_ activity: UUID) {
        guard canDeleteActivity(activity) else { return }
        replaceDraft { $0.activities.removeAll { $0.localId == activity } }
    }

    public func moveActivities(fromOffsets source: IndexSet, toOffset destination: Int) {
        replaceDraft { $0.activities.moveElements(fromOffsets: source, toOffset: destination) }
    }

    // MARK: Complete

    /// Saves first when dirty, then completes. Returns the completed workout for navigation.
    public func complete() async -> Workout? {
        guard isEditable, !isCompleting else { return nil }
        isCompleting = true
        completeError = nil
        defer { isCompleting = false }
        if isDirty, await !save() {
            completeError = saveError
            return nil
        }
        do {
            let completed = try await workouts.complete(id: workoutId)
            workout = completed
            mode = .readOnly
            state = .loaded(WorkoutDraft(workout: completed))
            isDirty = false
            await deletePersistedDraft()
            return completed
        } catch {
            completeError = APIError.from(error)
            return nil
        }
    }

    public func dismissCompleteError() {
        completeError = nil
    }

    // MARK: Persisted draft

    /// Writes the current draft immediately (call when the app goes to the background).
    public func persistNow() async {
        persistTask?.cancel()
        guard isDirty, let draft, let drafts else { return }
        try? await drafts.save(draft)
    }

    /// Reloads the server copy, dropping the restored draft.
    public func discardRestoredDraft() async {
        restoredDraft = false
        await deletePersistedDraft()
        state = .idle
        await load()
    }

    public func dismissStaleDraftNotice() {
        staleDraftDiscarded = false
    }

    /// Drops the local edits (and any persisted draft) and loads the server's current copy.
    public func reloadAfterConflict() async {
        changedElsewhere = false
        saveError = nil
        fieldErrors = [:]
        await deletePersistedDraft()
        state = .idle
        await load()
    }

    private func restoreDraftIfAny(for workout: Workout) async {
        guard isEditable, let drafts, let saved = try? await drafts.load(workoutId: workoutId) else { return }
        if let base = saved.baseETag, base == workout.etag {
            state = .loaded(saved)
            isDirty = true
            restoredDraft = true
        } else {
            try? await drafts.delete(workoutId: workoutId)
            staleDraftDiscarded = true
        }
    }

    private func schedulePersist() {
        guard let drafts else { return }
        persistTask?.cancel()
        let delay = persistDelay
        persistTask = Task { [weak self] in
            try? await Task.sleep(for: delay)
            guard !Task.isCancelled, let self, self.isDirty, let draft = self.draft else { return }
            try? await drafts.save(draft)
        }
    }

    private func deletePersistedDraft() async {
        persistTask?.cancel()
        try? await drafts?.delete(workoutId: workoutId)
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
            // Never `*`: a draft without a tag must not bypass the check, so it sends an empty
            // tag that cannot match, and the 412 leads to a reload.
            let saved = try await workouts.replaceActivities(
                id: workoutId, ifMatch: draft.baseETag ?? "", payload: Self.payload(for: draft))
            adoptIds(from: saved, into: draft)
            isDirty = false
            lastSavedAt = Date()
            await deletePersistedDraft()
            return true
        } catch {
            let apiError = APIError.from(error)
            saveError = apiError
            changedElsewhere = apiError.code == "precondition_failed"
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
                workoutId: updated.workoutId, name: saved.name, baseETag: saved.etag,
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
        schedulePersist()
    }

    func replaceDraft(_ transform: (inout WorkoutDraft) -> Void) {
        guard isEditable, var draft else { return }
        let before = draft
        transform(&draft)
        guard draft != before else { return }
        state = .loaded(draft)
        isDirty = true
        schedulePersist()
    }
}

/// The body of `PUT /workouts/{id}/activities`, in domain terms; the repository maps it to the
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

extension Array {
    /// Foundation-only equivalent of SwiftUI's `move(fromOffsets:toOffset:)`, with the same
    /// semantics: `destination` is an index in the array before removal.
    mutating func moveElements(fromOffsets source: IndexSet, toOffset destination: Int) {
        let moving = source.map { self[$0] }
        let removedBefore = source.filter { $0 < destination }.count
        for index in source.sorted(by: >) { remove(at: index) }
        let target = Swift.min(Swift.max(destination - removedBefore, 0), count)
        insert(contentsOf: moving, at: target)
    }
}
