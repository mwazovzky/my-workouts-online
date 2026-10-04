import Foundation
import WorkoutsAPI

/// Workout lifecycle calls. Stories 9–11 add get, save, complete, repeat, delete and the list.
public protocol WorkoutRepository: Sendable {
    /// Clones the template's activities and sets into a new in-progress workout.
    func create(templateId: Int) async throws -> Workout
    func get(id: Int) async throws -> Workout
    /// Replaces the workout's activities and sets in one request; returns the persisted rows.
    func save(id: Int, payload: WorkoutSavePayload) async throws -> Workout
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

    public func get(id: Int) async throws -> Workout {
        let output = try await client.getWorkout(path: .init(workout: id))
        switch output {
        case .ok(let response): return try Workout(api: try response.body.json.data)
        case .unauthorized(let response): throw APIError(status: 401, envelope: try response.body.json)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func save(id: Int, payload: WorkoutSavePayload) async throws -> Workout {
        let body = Components.Schemas.WorkoutSaveRequest(
            activities: payload.activities.map { activity in
                .init(
                    id: activity.id, exerciseId: activity.exerciseId, order: activity.order,
                    sets: activity.sets.map { set in
                        .init(
                            id: set.id, order: set.order, effortValue: set.effortValue,
                            difficultyValue: set.difficultyValue,
                            isCompleted: set.isCompleted)
                    })
            })
        let output = try await client.saveWorkout(path: .init(workout: id), body: .json(body))
        switch output {
        case .ok(let response): return try Workout(api: try response.body.json.data)
        case .unauthorized(let response): throw APIError(status: 401, envelope: try response.body.json)
        case .forbidden(let response): throw APIError(status: 403, envelope: try response.body.json)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.json)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}
