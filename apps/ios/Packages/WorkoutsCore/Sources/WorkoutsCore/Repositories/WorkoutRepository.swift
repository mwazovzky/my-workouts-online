import Foundation
import WorkoutsAPI

/// Workout lifecycle calls. Stories 9–11 add get, save, complete, repeat, delete and the list.
public protocol WorkoutRepository: Sendable {
    /// Clones the template's activities and sets into a new in-progress workout.
    func create(templateId: Int) async throws -> Workout
}

public struct APIWorkoutRepository: WorkoutRepository {
    private let client: Client

    public init(client: Client) {
        self.client = client
    }

    public func create(templateId: Int) async throws -> Workout {
        let output = try await client.createWorkout(body: .json(.init(workoutTemplateId: templateId)))
        switch output {
        case .created(let response): return try Workout(api: try response.body.json.data)
        case .unauthorized(let response): throw APIError(status: 401, envelope: try response.body.json)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}
