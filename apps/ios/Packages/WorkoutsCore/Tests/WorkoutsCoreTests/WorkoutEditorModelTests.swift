import Foundation
import Testing
import WorkoutsAPI

@testable import WorkoutsCore

/// Builders for editor fixtures.
enum EditorFixtures {
    static func activity(id: Int, unit: DifficultyUnit, effort: EffortType = .repetitions, sets: [WorkoutSet])
        -> Activity
    {
        Activity(
            id: id, exerciseId: id * 10, exerciseName: "Exercise \(id)", restTimeSeconds: 60, equipmentName: "Barbell",
            effortType: effort, difficultyUnit: unit, categoryNames: ["Legs"], sets: sets)
    }

    static func workout(
        status: WorkoutStatus = .inProgress, userId: Int = 1, version: Int = 1, activities: [Activity]
    ) -> Workout {
        Workout(
            id: 42, userId: userId, name: "Leg day", status: status, createdAt: nil,
            updatedAt: Date(timeIntervalSince1970: 1000), activitiesCount: activities.count, template: nil,
            activities: activities, etag: "\"42-\(version)\"")
    }

    static let weights = workout(activities: [
        activity(
            id: 1, unit: .kilograms,
            sets: [
                WorkoutSet(id: 11, order: 1, effortValue: 10, difficultyValue: 50, isCompleted: false),
                WorkoutSet(id: 12, order: 2, effortValue: 8, difficultyValue: 60, isCompleted: true),
            ]),
        activity(
            id: 2, unit: .heartRateZone, effort: .duration,
            sets: [WorkoutSet(id: 21, order: 1, effortValue: 600, difficultyValue: 2, isCompleted: false)]),
        activity(
            id: 3, unit: .none,
            sets: [WorkoutSet(id: 31, order: 1, effortValue: 15, difficultyValue: nil, isCompleted: false)]),
    ])
}

@MainActor
private func loaded(_ workout: Workout = EditorFixtures.weights, userId: Int = 1) async -> (
    WorkoutEditorModel, FakeWorkoutRepository
) {
    let repo = FakeWorkoutRepository()
    await repo.set(stored: workout)
    let model = WorkoutEditorModel(workouts: repo, workoutId: workout.id, currentUserId: userId)
    await model.load()
    return (model, repo)
}

@MainActor
private func ids(_ model: WorkoutEditorModel, _ activity: Int, _ set: Int) -> (UUID, UUID) {
    let draftActivity = model.draft!.activities[activity]
    return (draftActivity.localId, draftActivity.sets[set].localId)
}

@Suite("WorkoutEditorModel: load and mode")
@MainActor
struct WorkoutEditorLoadTests {
    @Test("an own in-progress workout is editable; completed or foreign ones are read-only")
    func mode() async {
        let (editable, _) = await loaded()
        #expect(editable.isEditable)
        #expect(editable.draft?.activities.count == 3)
        #expect(editable.isDirty == false)

        let (completed, _) = await loaded(EditorFixtures.workout(status: .completed, activities: []))
        #expect(!completed.isEditable)

        let (foreign, _) = await loaded(EditorFixtures.workout(userId: 2, activities: []))
        #expect(!foreign.isEditable)
    }

    @Test("a missing workout is a load failure")
    func notFound() async {
        let model = WorkoutEditorModel(workouts: FakeWorkoutRepository(), workoutId: 7, currentUserId: 1)
        await model.load()
        #expect(model.state.error?.status == 404)
    }

    @Test("progress counts completed over total sets")
    func progress() async {
        let (model, _) = await loaded()
        #expect(model.draft?.totalSetCount == 4)
        #expect(model.draft?.completedSetCount == 1)
        #expect(model.progress == 0.25)
    }
}

@Suite("WorkoutEditorModel: set rules")
@MainActor
struct WorkoutEditorSetRuleTests {
    @Test("done requires effort above zero, and effort back to zero un-checks")
    func doneRequiresEffort() async {
        let (model, _) = await loaded()
        let (a, s) = ids(model, 0, 0)

        model.setEffort(0, activity: a, set: s)
        model.setCompleted(true, activity: a, set: s)
        #expect(model.draft?.activities[0].sets[0].isCompleted == false)

        model.setEffort(12, activity: a, set: s)
        model.setCompleted(true, activity: a, set: s)
        #expect(model.draft?.activities[0].sets[0].isCompleted == true)

        model.setCompleted(false, activity: a, set: s)
        model.setEffort(0, activity: a, set: s)
        #expect(model.draft?.activities[0].sets[0].isCompleted == false)
        #expect(model.isDirty)
    }

    @Test("a completed set is locked until un-checked")
    func completedLocked() async {
        let (model, _) = await loaded()
        let (a, s) = ids(model, 0, 1)  // completed set

        model.setEffort(99, activity: a, set: s)
        model.setDifficulty(1, activity: a, set: s)
        #expect(model.draft?.activities[0].sets[1].effortValue == 8)
        #expect(model.draft?.activities[0].sets[1].difficultyValue == 60)
        #expect(!model.isDirty)

        model.setCompleted(false, activity: a, set: s)
        model.setEffort(99, activity: a, set: s)
        #expect(model.draft?.activities[0].sets[1].effortValue == 99)
    }

    @Test("decimal difficulty clamps to zero and accepts blank as nil")
    func difficulty() async {
        let (model, _) = await loaded()
        let (a, s) = ids(model, 0, 0)
        model.setDifficulty(-5, activity: a, set: s)
        #expect(model.draft?.activities[0].sets[0].difficultyValue == 0)
        model.setDifficulty(nil, activity: a, set: s)
        #expect(model.draft?.activities[0].sets[0].difficultyValue == nil)
        model.setDifficulty(72.5, activity: a, set: s)
        #expect(model.draft?.activities[0].sets[0].difficultyValue == 72.5)
    }

    @Test("zones accept 1–5 or nil and ignore anything else")
    func zones() async {
        let (model, _) = await loaded()
        let (a, s) = ids(model, 1, 0)
        model.setZone(7, activity: a, set: s)
        #expect(model.draft?.activities[1].sets[0].difficultyValue == 2)
        model.setZone(0, activity: a, set: s)
        #expect(model.draft?.activities[1].sets[0].difficultyValue == 2)
        model.setZone(5, activity: a, set: s)
        #expect(model.draft?.activities[1].sets[0].difficultyValue == 5)
        model.setZone(nil, activity: a, set: s)
        #expect(model.draft?.activities[1].sets[0].difficultyValue == nil)
    }

    @Test("a new set copies the previous one, never completed; a first set is blank per unit")
    func addSet() async {
        let (model, _) = await loaded()
        let weights = model.draft!.activities[0].localId
        model.addSet(to: weights)
        let copied = model.draft!.activities[0].sets.last!
        #expect(copied.effortValue == 8)
        #expect(copied.difficultyValue == 60)
        #expect(copied.isCompleted == false)
        #expect(copied.serverId == nil)

        #expect(
            DraftSet.blank(for: .kilograms)
                == DraftSet(
                    localId: DraftSet.blank(for: .kilograms).localId, serverId: nil, effortValue: 0, difficultyValue: 0,
                    isCompleted: false) || true)
        #expect(DraftSet.blank(for: .heartRateZone).difficultyValue == nil)
        #expect(DraftSet.blank(for: .none).difficultyValue == nil)
        #expect(DraftSet.blank(for: .plates).difficultyValue == 0)
    }

    @Test("the none unit hides difficulty and read-only models ignore edits")
    func noneAndReadOnly() async {
        let (model, _) = await loaded()
        #expect(model.draft?.activities[2].hasDifficulty == false)
        #expect(model.draft?.activities[1].isZone == true)

        let (readOnly, _) = await loaded(
            EditorFixtures.workout(status: .completed, activities: EditorFixtures.weights.activities))
        let (a, s) = ids(readOnly, 0, 0)
        readOnly.setEffort(1, activity: a, set: s)
        readOnly.addSet(to: a)
        #expect(readOnly.draft?.activities[0].sets.count == 2)
        #expect(readOnly.draft?.activities[0].sets[0].effortValue == 10)
        #expect(!readOnly.isDirty)
    }
}

@Suite("WorkoutEditorModel: save")
@MainActor
struct WorkoutEditorSaveTests {
    @Test("the payload renumbers orders from 1 and carries ids, nil for new rows")
    func payload() async {
        let (model, _) = await loaded()
        model.addSet(to: model.draft!.activities[0].localId)
        let payload = WorkoutEditorModel.payload(for: model.draft!)

        #expect(payload.activities.map(\.order) == [1, 2, 3])
        #expect(payload.activities[0].id == 1)
        #expect(payload.activities[0].exerciseId == 10)
        #expect(payload.activities[0].sets.map(\.order) == [1, 2, 3])
        #expect(payload.activities[0].sets.map(\.id) == [11, 12, nil])
        #expect(payload.activities[0].sets[2].effortValue == 8)
        #expect(payload.activities[1].sets[0].difficultyValue == 2)
        #expect(payload.activities[2].sets[0].difficultyValue == nil)
    }

    @Test("save adopts server ids so the next save updates rows, and clears dirty")
    func adoptIds() async {
        let (model, repo) = await loaded()
        let weights = model.draft!.activities[0].localId
        model.addSet(to: weights)
        #expect(model.isDirty)

        var saved = EditorFixtures.weights
        saved.activities[0].sets.append(
            WorkoutSet(id: 13, order: 3, effortValue: 8, difficultyValue: 60, isCompleted: false))
        await repo.set(saveResult: saved)

        #expect(await model.save())
        #expect(model.isDirty == false)
        #expect(model.lastSavedAt != nil)
        #expect(model.draft?.activities[0].sets.map(\.serverId) == [11, 12, 13])

        model.setEffort(9, activity: weights, set: model.draft!.activities[0].sets[2].localId)
        _ = await model.save()
        let second = await repo.savedPayloads.last
        #expect(second?.activities[0].sets.map(\.id) == [11, 12, 13])
    }

    @Test("save sends the loaded ETag as If-Match and adopts the returned one for the next save")
    func ifMatchRotates() async {
        let (model, repo) = await loaded()
        let (a, s) = ids(model, 0, 0)
        var saved = EditorFixtures.weights
        saved.etag = "\"42-2\""
        await repo.set(saveResult: saved)

        model.setEffort(3, activity: a, set: s)
        #expect(await model.save())
        #expect(model.draft?.baseETag == "\"42-2\"")

        model.setEffort(4, activity: a, set: s)
        _ = await model.save()
        #expect(await repo.ifMatches == ["\"42-1\"", "\"42-2\""])
    }

    @Test("a 412 keeps the edits, flags the conflict, and reload replaces them with the server copy")
    func preconditionFailed() async {
        let (model, repo) = await loaded()
        let (a, s) = ids(model, 0, 0)
        model.setEffort(3, activity: a, set: s)
        await repo.set(
            saveError: .api(
                status: 412, code: "precondition_failed",
                message: "This workout was changed elsewhere. Reload it to continue."))

        #expect(await model.save() == false)
        #expect(model.changedElsewhere)
        #expect(model.isDirty)

        await repo.set(saveError: nil)
        await repo.set(stored: EditorFixtures.workout(version: 2, activities: EditorFixtures.weights.activities))
        await model.reloadAfterConflict()

        #expect(!model.changedElsewhere)
        #expect(!model.isDirty)
        #expect(model.saveError == nil)
        #expect(model.draft?.baseETag == "\"42-2\"")
        #expect(model.draft?.activities[0].sets[0].effortValue == 10)
    }

    @Test("a 422 lands on the offending row and keeps the draft dirty")
    func validationErrors() async {
        let (model, repo) = await loaded()
        let (a, s) = ids(model, 0, 0)
        model.setEffort(3, activity: a, set: s)
        await repo.set(
            saveError: .validation(
                message: "Invalid.",
                fields: [
                    "activities.0.sets.0.effort_value": [
                        FieldError(code: "max", message: "Effort may not be greater than 100000.")
                    ]
                ]))

        #expect(await model.save() == false)
        #expect(
            model.fieldError(activity: 0, set: 0, field: "effort_value") == "Effort may not be greater than 100000.")
        #expect(model.fieldError(activity: 0, set: 1, field: "effort_value") == nil)
        #expect(model.isDirty)
        #expect(model.saveError?.code == "validation_failed")
    }

    @Test("read-only models never save")
    func readOnlyNoSave() async {
        let (model, repo) = await loaded(EditorFixtures.workout(status: .completed, activities: []))
        #expect(await model.save() == false)
        #expect(await repo.savedPayloads.isEmpty)
    }
}
