import Foundation
import WorkoutsAPI

/// Workout lifecycle calls. Stories 9–11 add get, save, complete, repeat, delete and the list.
public protocol WorkoutRepository: Sendable {
    /// Clones the template's activities and sets into a new in-progress workout.
    func create(templateId: Int) async throws -> Workout
    func get(id: Int) async throws -> Workout
    /// Replaces the workout's activities and sets in one request; returns the persisted rows
    /// with the new `etag`. `ifMatch` is the tag of the version being edited: a workout changed
    /// since answers 412 `precondition_failed` and nothing is written.
    func replaceActivities(id: Int, ifMatch: String, payload: WorkoutSavePayload) async throws -> Workout
    func complete(id: Int) async throws -> Workout
    /// Clones a workout into a new in-progress one.
    func `repeat`(id: Int) async throws -> Workout
    /// Newest first, Laravel-paginated.
    func list(page: Int) async throws -> Page<Workout>
    func delete(id: Int) async throws
}

public struct APIWorkoutRepository: WorkoutRepository {
    private let client: Client

    public init(client: Client) {
        self.client = client
    }

    public func create(templateId: Int) async throws -> Workout {
        let output = try await client.createWorkout(body: .json(.init(workoutTemplateId: templateId)))
        switch output {
        case .created(let response): return try Workout(response: response)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func get(id: Int) async throws -> Workout {
        let output = try await client.getWorkout(path: .init(workoutId: id))
        switch output {
        case .ok(let response): return try Workout(response: response)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func replaceActivities(id: Int, ifMatch: String, payload: WorkoutSavePayload) async throws -> Workout {
        let body = Components.Schemas.WorkoutActivitiesWrite(
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
        let output = try await client.replaceWorkoutActivities(
            path: .init(workoutId: id), headers: .init(ifMatch: ifMatch), body: .json(body))
        switch output {
        case .ok(let response): return try Workout(response: response)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .forbidden(let response): throw APIError(status: 403, envelope: try response.body.applicationProblemJson)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.applicationProblemJson)
        case .preconditionFailed(let response):
            throw APIError(status: 412, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .preconditionRequired(let response):
            throw APIError(status: 428, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func complete(id: Int) async throws -> Workout {
        let output = try await client.completeWorkout(path: .init(workoutId: id))
        switch output {
        case .ok(let response): return try Workout(response: response)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .forbidden(let response): throw APIError(status: 403, envelope: try response.body.applicationProblemJson)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func list(page: Int) async throws -> Page<Workout> {
        let output = try await client.listWorkouts(query: .init(page: page))
        switch output {
        case .ok(let response): return try Page<Workout>(api: try response.body.json)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func delete(id: Int) async throws {
        let output = try await client.deleteWorkout(path: .init(workoutId: id))
        switch output {
        case .noContent: return
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func `repeat`(id: Int) async throws -> Workout {
        let output = try await client.repeatWorkout(path: .init(workoutId: id))
        switch output {
        case .created(let response): return try Workout(response: response)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .forbidden(let response): throw APIError(status: 403, envelope: try response.body.applicationProblemJson)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}

extension Workout {
    /// A single-workout response: the body plus its `ETag`.
    init(response: Components.Responses.WorkoutResponse) throws {
        self = try Workout(api: try response.body.json.data)
        etag = response.headers.eTag
    }
}
