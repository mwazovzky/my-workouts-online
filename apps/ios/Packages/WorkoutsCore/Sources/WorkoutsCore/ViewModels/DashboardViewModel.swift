import Foundation
import Observation
import WorkoutsAPI

@MainActor
@Observable
public final class DashboardViewModel {
    public private(set) var state: LoadState<Dashboard> = .idle
    /// Set while a workout is being created from the schedule.
    public private(set) var startingTemplateId: Int?
    public private(set) var startError: APIError?

    private let dashboards: any DashboardRepository
    private let workouts: any WorkoutRepository

    public init(dashboards: any DashboardRepository, workouts: any WorkoutRepository) {
        self.dashboards = dashboards
        self.workouts = workouts
    }

    /// Loads on first appearance and again whenever the screen comes back; a reload keeps
    /// the current content visible instead of flashing a spinner.
    public func load() async {
        if state.value == nil { state = .loading }
        do {
            state = .loaded(try await dashboards.dashboard())
        } catch {
            state = .failed(APIError.from(error))
        }
    }

    /// Starts a workout from a scheduled template. Returns the new workout to navigate to.
    public func startWorkout(templateId: Int) async -> Workout? {
        guard startingTemplateId == nil else { return nil }
        startingTemplateId = templateId
        startError = nil
        defer { startingTemplateId = nil }
        do {
            return try await workouts.create(templateId: templateId)
        } catch {
            startError = APIError.from(error)
            return nil
        }
    }

    public func dismissStartError() {
        startError = nil
    }
}
