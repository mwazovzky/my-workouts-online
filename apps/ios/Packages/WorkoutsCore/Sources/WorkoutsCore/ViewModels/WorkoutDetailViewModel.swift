import Foundation
import Observation
import WorkoutsAPI

/// Read-only view of one workout with the lifecycle actions its state allows.
@MainActor
@Observable
public final class WorkoutDetailViewModel {
    public private(set) var state: LoadState<Workout> = .idle
    public private(set) var isRepeating = false
    public private(set) var actionError: APIError?

    private let workouts: any WorkoutRepository
    private let currentUserId: Int
    public let workoutId: Int

    public init(workouts: any WorkoutRepository, workoutId: Int, currentUserId: Int) {
        self.workouts = workouts
        self.workoutId = workoutId
        self.currentUserId = currentUserId
    }

    public var workout: Workout? { state.value }
    private var isOwner: Bool { workout?.userId == currentUserId }

    /// Edit: own and in progress. Repeat: own and completed.
    public var canEdit: Bool { isOwner && workout?.status == .inProgress }
    public var canRepeat: Bool { isOwner && workout?.status == .completed }

    public func load() async {
        if state.value == nil { state = .loading }
        do {
            state = .loaded(try await workouts.get(id: workoutId))
        } catch {
            state = .failed(APIError.from(error))
        }
    }

    /// Clones this workout; returns the new in-progress workout for navigation to the editor.
    public func repeatWorkout() async -> Workout? {
        guard canRepeat, !isRepeating else { return nil }
        isRepeating = true
        actionError = nil
        defer { isRepeating = false }
        do {
            return try await workouts.repeat(id: workoutId)
        } catch {
            actionError = APIError.from(error)
            return nil
        }
    }

    public func dismissError() {
        actionError = nil
    }
}
