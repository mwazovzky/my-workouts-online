import Foundation
import Testing
import WorkoutsAPI

@testable import WorkoutsCore

actor FakeDashboardRepository: DashboardRepository {
    var result: Result<Dashboard, APIError>
    private(set) var calls = 0

    init(_ result: Result<Dashboard, APIError>) {
        self.result = result
    }

    func set(_ result: Result<Dashboard, APIError>) { self.result = result }

    func dashboard() async throws -> Dashboard {
        calls += 1
        return try result.get()
    }
}

actor FakeWorkoutRepository: WorkoutRepository {
    var createError: APIError?
    private(set) var createdTemplateIds: [Int] = []
    var stored: Workout?
    var saveError: APIError?
    private(set) var savedPayloads: [WorkoutSavePayload] = []
    /// What `save` returns; defaults to the stored workout with ids assigned positionally.
    var saveResult: Workout?

    func set(createError: APIError?) { self.createError = createError }
    func set(stored: Workout?) { self.stored = stored }
    func set(saveError: APIError?) { self.saveError = saveError }
    func set(saveResult: Workout?) { self.saveResult = saveResult }

    func get(id: Int) async throws -> Workout {
        guard let stored, stored.id == id else {
            throw APIError.api(status: 404, code: "not_found", message: "Record not found.")
        }
        return stored
    }

    func save(id: Int, payload: WorkoutSavePayload) async throws -> Workout {
        savedPayloads.append(payload)
        if let saveError { throw saveError }
        if let saveResult { return saveResult }
        guard let stored else { throw APIError.unexpected(status: 500) }
        return stored
    }

    var completeError: APIError?
    private(set) var completeCalls = 0
    private(set) var repeatCalls = 0
    func set(completeError: APIError?) { self.completeError = completeError }

    func complete(id: Int) async throws -> Workout {
        completeCalls += 1
        if let completeError { throw completeError }
        guard let stored else { throw APIError.unexpected(status: 500) }
        return Workout(
            id: stored.id, userId: stored.userId, name: stored.name, status: .completed,
            createdAt: stored.createdAt, updatedAt: Date(), activitiesCount: stored.activitiesCount,
            template: stored.template,
            activities: stored.activities)
    }

    var pages: [Int: Page<Workout>] = [:]
    var listError: APIError?
    private(set) var listCalls: [Int] = []
    var deleteError: APIError?
    private(set) var deleteCalls: [Int] = []
    func set(pages: [Int: Page<Workout>]) { self.pages = pages }
    func set(listError: APIError?) { self.listError = listError }
    func set(deleteError: APIError?) { self.deleteError = deleteError }

    func list(page: Int) async throws -> Page<Workout> {
        listCalls.append(page)
        if let listError { throw listError }
        guard let result = pages[page] else { throw APIError.api(status: 404, code: "not_found", message: "No page") }
        return result
    }

    func delete(id: Int) async throws {
        deleteCalls.append(id)
        if let deleteError { throw deleteError }
    }

    func `repeat`(id: Int) async throws -> Workout {
        repeatCalls += 1
        guard let stored else { throw APIError.unexpected(status: 500) }
        return Workout(
            id: stored.id + 1000, userId: stored.userId, name: stored.name, status: .inProgress,
            createdAt: nil, updatedAt: nil, activitiesCount: stored.activitiesCount, template: stored.template,
            activities: stored.activities)
    }

    func create(templateId: Int) async throws -> Workout {
        createdTemplateIds.append(templateId)
        if let createError { throw createError }
        return Workout(
            id: 100 + templateId, userId: 1, name: "New", status: .inProgress,
            createdAt: nil,
            updatedAt: nil, activitiesCount: 0, template: nil, activities: [])
    }
}

extension Dashboard {
    static let empty = Dashboard(
        upcomingSchedule: [], inProgressWorkout: nil, recentWorkouts: [],
        summary: DashboardSummary(
            enrolledProgramsCount: 0, completedWorkoutsCount: 0, completedLast7DaysCount: 0, upcomingWorkoutsCount: 0))
}

@Suite("DashboardViewModel")
@MainActor
struct DashboardViewModelTests {
    @Test("load goes loading then loaded; a reload keeps the content visible")
    func load() async throws {
        let dashboards = FakeDashboardRepository(.success(.empty))
        let model = DashboardViewModel(dashboards: dashboards, workouts: FakeWorkoutRepository())
        #expect(model.state == .idle)

        await model.load()
        #expect(model.state == .loaded(.empty))

        await dashboards.set(.failure(.network(description: "offline")))
        await model.load()
        #expect(model.state == .failed(.network(description: "offline")))

        await dashboards.set(.success(.empty))
        await model.load()
        #expect(model.state.value == .empty)
        #expect(await dashboards.calls == 3)
    }

    @Test("starting a workout returns it, and reports a failure without navigating")
    func startWorkout() async throws {
        let workouts = FakeWorkoutRepository()
        let model = DashboardViewModel(dashboards: FakeDashboardRepository(.success(.empty)), workouts: workouts)

        let started = await model.startWorkout(templateId: 7)
        #expect(started?.id == 107)
        #expect(model.startingTemplateId == nil)
        #expect(model.startError == nil)

        await workouts.set(createError: .api(status: 404, code: "not_found", message: "Record not found."))
        let failed = await model.startWorkout(templateId: 8)
        #expect(failed == nil)
        #expect(model.startError == .api(status: 404, code: "not_found", message: "Record not found."))
        model.dismissStartError()
        #expect(model.startError == nil)
        #expect(await workouts.createdTemplateIds == [7, 8])
    }
}
