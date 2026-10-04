import Foundation
import Testing
import WorkoutsAPI

@testable import WorkoutsCore

private let barbell = Exercise(
    id: 77, name: "Deadlift", description: nil, equipmentId: 1, equipmentName: "Barbell", effortType: .repetitions,
    effortLabel: "Reps", difficultyUnit: .kilograms, difficultyLabel: "kg", restTimeSeconds: 120,
    categories: [Category(id: 1, name: "Back")])

@MainActor
private func loaded(
    _ workout: Workout = EditorFixtures.weights, drafts: (any DraftStore)? = nil, delay: Duration = .milliseconds(10)
) async -> (WorkoutEditorModel, FakeWorkoutRepository) {
    let repo = FakeWorkoutRepository()
    await repo.set(stored: workout)
    let model = WorkoutEditorModel(
        workouts: repo, workoutId: workout.id, currentUserId: 1, drafts: drafts, persistDelay: delay)
    await model.load()
    return (model, repo)
}

@Suite("WorkoutEditorModel: activities")
@MainActor
struct WorkoutEditorActivityTests {
    @Test("a picked exercise is appended with one blank set carrying its metadata")
    func addActivity() async {
        let (model, _) = await loaded()
        model.addActivity(exercise: barbell)
        let added = model.draft!.activities.last!
        #expect(added.exerciseId == 77)
        #expect(added.exerciseName == "Deadlift")
        #expect(added.serverId == nil)
        #expect(added.sets.count == 1)
        #expect(added.sets[0].effortValue == 0)
        #expect(added.sets[0].difficultyValue == 0)
        #expect(model.isDirty)
        #expect(WorkoutEditorModel.payload(for: model.draft!).activities.last?.id == nil)
    }

    @Test("removing a set removes just that set; removing the last set removes the activity")
    func removeSet() async {
        let (model, _) = await loaded()
        let weights = model.draft!.activities[0].localId
        let first = model.draft!.activities[0].sets[0].localId
        #expect(!model.removingSetWouldDeleteActivity(weights))
        model.removeSet(activity: weights, set: first)
        #expect(model.draft!.activities[0].sets.count == 1)

        #expect(model.removingSetWouldDeleteActivity(weights))
        model.removeSet(activity: weights, set: model.draft!.activities[0].sets[0].localId)
        #expect(model.draft!.activities.count == 2)
        #expect(model.draft!.activities.first?.exerciseId != 10)
    }

    @Test("the only remaining activity cannot be deleted")
    func onlyActivity() async {
        let (model, _) = await loaded(EditorFixtures.workout(activities: [EditorFixtures.weights.activities[0]]))
        let only = model.draft!.activities[0].localId
        #expect(!model.canDeleteActivity(only))
        model.deleteActivity(only)
        model.removeSet(activity: only, set: model.draft!.activities[0].sets[0].localId)
        model.removeSet(activity: only, set: model.draft!.activities[0].sets[0].localId)
        #expect(model.draft!.activities.count == 1)
        #expect(model.draft!.activities[0].sets.count == 1, "the last set of the only activity stays")
    }

    @Test("reordering changes the saved order values")
    func reorder() async {
        let (model, repo) = await loaded()
        model.moveActivities(fromOffsets: IndexSet(integer: 2), toOffset: 0)
        #expect(model.draft!.activities.map(\.exerciseId) == [30, 10, 20])
        _ = await model.save()
        let payload = await repo.savedPayloads.last
        #expect(
            payload?.activities.map { ($0.exerciseId, $0.order) }.map { "\($0.0):\($0.1)" } == ["30:1", "10:2", "20:3"])
    }
}

@Suite("WorkoutEditorModel: complete")
@MainActor
struct WorkoutEditorCompleteTests {
    @Test("complete saves a dirty draft first, then completes and becomes read-only")
    func completeSavesFirst() async {
        let (model, repo) = await loaded()
        model.addSet(to: model.draft!.activities[0].localId)

        let completed = await model.complete()

        #expect(completed?.status == .completed)
        #expect(await repo.savedPayloads.count == 1)
        #expect(await repo.completeCalls == 1)
        #expect(!model.isEditable)
        #expect(!model.isDirty)
    }

    @Test("a failed save blocks completion and surfaces the error")
    func saveFailureBlocksComplete() async {
        let (model, repo) = await loaded()
        model.addSet(to: model.draft!.activities[0].localId)
        await repo.set(saveError: .network(description: "offline"))

        #expect(await model.complete() == nil)
        #expect(await repo.completeCalls == 0)
        #expect(model.completeError == .network(description: "offline"))
        #expect(model.isDirty)
    }

    @Test("a clean draft completes without saving; a 403 is reported")
    func completeDirectly() async {
        let (model, repo) = await loaded()
        await repo.set(completeError: .api(status: 403, code: "forbidden", message: "Nope"))
        #expect(await model.complete() == nil)
        #expect(await repo.savedPayloads.isEmpty)
        #expect(model.completeError?.status == 403)
        model.dismissCompleteError()
        #expect(model.completeError == nil)
    }
}

@Suite("WorkoutEditorModel: persisted draft")
@MainActor
struct WorkoutEditorDraftTests {
    @Test("edits are persisted after the debounce and removed after a save")
    func persistAndClear() async throws {
        let store = InMemoryDraftStore()
        let (model, _) = await loaded(drafts: store)
        let weights = model.draft!.activities[0].localId
        model.addSet(to: weights)
        model.addSet(to: weights)

        try await waitUntilAsync { await store.load(workoutId: 42) != nil }
        #expect(await store.load(workoutId: 42)?.activities[0].sets.count == 4)
        #expect(await store.saveCount == 1, "debounced: two edits, one write")

        _ = await model.save()
        #expect(await store.load(workoutId: 42) == nil)
    }

    @Test("persistNow writes immediately while dirty")
    func persistNow() async {
        let store = InMemoryDraftStore()
        let (model, _) = await loaded(drafts: store, delay: .seconds(60))
        model.addSet(to: model.draft!.activities[0].localId)
        await model.persistNow()
        #expect(await store.load(workoutId: 42) != nil)
    }

    @Test("a draft matching the server copy is restored and can be discarded")
    func restore() async {
        var draft = WorkoutDraft(workout: EditorFixtures.weights)
        draft.activities[0].sets.append(
            DraftSet(serverId: nil, effortValue: 5, difficultyValue: 70, isCompleted: false))
        let store = InMemoryDraftStore([draft])
        let (model, _) = await loaded(drafts: store)

        #expect(model.restoredDraft)
        #expect(model.isDirty)
        #expect(model.draft?.activities[0].sets.count == 3)

        await model.discardRestoredDraft()
        #expect(!model.restoredDraft)
        #expect(!model.isDirty)
        #expect(model.draft?.activities[0].sets.count == 2)
        #expect(await store.load(workoutId: 42) == nil)
    }

    @Test("a draft taken before the server copy changed is dropped with a notice")
    func stale() async {
        var newer = EditorFixtures.weights
        newer = Workout(
            id: newer.id, userId: newer.userId, name: newer.name, status: newer.status, statusLabel: newer.statusLabel,
            createdAt: nil, updatedAt: Date(timeIntervalSince1970: 2000), activitiesCount: nil, template: nil,
            activities: newer.activities)
        let stale = WorkoutDraft(workout: EditorFixtures.weights)  // baseUpdatedAt 1000
        let store = InMemoryDraftStore([stale])
        let (model, _) = await loaded(newer, drafts: store)

        #expect(model.staleDraftDiscarded)
        #expect(!model.restoredDraft)
        #expect(!model.isDirty)
        #expect(await store.load(workoutId: 42) == nil)
    }

    @Test("read-only workouts never restore drafts")
    func readOnlyIgnoresDraft() async {
        let store = InMemoryDraftStore([WorkoutDraft(workout: EditorFixtures.weights)])
        let (model, _) = await loaded(
            EditorFixtures.workout(status: .completed, activities: EditorFixtures.weights.activities), drafts: store)
        #expect(!model.restoredDraft)
    }
}

@Suite("FileDraftStore")
struct FileDraftStoreTests {
    @Test("round-trips, deletes one and deletes all")
    func roundTrip() async throws {
        let dir = FileManager.default.temporaryDirectory.appending(path: "drafts-\(UUID().uuidString)")
        let store = FileDraftStore(directory: dir)
        let draft = WorkoutDraft(workout: EditorFixtures.weights)

        #expect(try await store.load(workoutId: 42) == nil)
        try await store.save(draft)
        #expect(try await store.load(workoutId: 42) == draft)
        try await store.delete(workoutId: 42)
        #expect(try await store.load(workoutId: 42) == nil)
        try await store.save(draft)
        try await store.deleteAll()
        #expect(try await store.load(workoutId: 42) == nil)
    }
}

@Suite("ExercisePickerViewModel")
@MainActor
struct ExercisePickerViewModelTests {
    actor Repo: ExerciseRepository {
        private(set) var calls = 0
        func exercises() async throws -> [Exercise] {
            calls += 1
            return [
                barbell,
                Exercise(
                    id: 2, name: "Plank", description: nil, equipmentId: 2, equipmentName: nil, effortType: .duration,
                    effortLabel: "Seconds", difficultyUnit: .none, difficultyLabel: nil, restTimeSeconds: nil,
                    categories: []),
            ]
        }
    }

    @Test("loads once and filters by name, case-insensitively")
    func filter() async {
        let repo = Repo()
        let model = ExercisePickerViewModel(exercises: repo)
        await model.loadIfNeeded()
        await model.loadIfNeeded()
        #expect(await repo.calls == 1)
        #expect(model.results.count == 2)
        model.searchText = "dead"
        #expect(model.results.map(\.id) == [77])
        model.searchText = "  "
        #expect(model.results.count == 2)
    }
}

/// Polls an async condition (the draft store is an actor).
@MainActor
func waitUntilAsync(timeout: Duration = .seconds(2), _ condition: () async -> Bool) async throws {
    let deadline = ContinuousClock.now + timeout
    while await !condition() {
        if ContinuousClock.now > deadline { throw TimeoutError() }
        try await Task.sleep(for: .milliseconds(10))
    }
}
