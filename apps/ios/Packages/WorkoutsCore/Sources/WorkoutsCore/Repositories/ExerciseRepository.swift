import Foundation
import WorkoutsAPI

public protocol ExerciseRepository: Sendable {
    func exercises() async throws -> [Exercise]
}

public struct APIExerciseRepository: ExerciseRepository {
    private let client: Client

    public init(client: Client) {
        self.client = client
    }

    public func exercises() async throws -> [Exercise] {
        let output = try await client.listExercises()
        switch output {
        case .ok(let response): return try response.body.json.data.map(Exercise.init(api:))
        case .unauthorized(let response): throw APIError(status: 401, envelope: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}
