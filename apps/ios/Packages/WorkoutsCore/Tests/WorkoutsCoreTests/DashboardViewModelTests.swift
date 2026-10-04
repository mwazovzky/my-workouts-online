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

    func set(createError: APIError?) { self.createError = createError }

    func create(templateId: Int) async throws -> Workout {
        createdTemplateIds.append(templateId)
        if let createError { throw createError }
        return Workout(
            id: 100 + templateId, userId: 1, name: "New", status: .inProgress, statusLabel: "In progress",
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
