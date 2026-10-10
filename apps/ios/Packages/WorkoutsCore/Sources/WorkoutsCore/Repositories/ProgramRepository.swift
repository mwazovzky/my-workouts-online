import Foundation
import WorkoutsAPI

public protocol ProgramRepository: Sendable {
    func programs() async throws -> [Program]
    func program(id: Int) async throws -> ProgramDetail
    func enroll(programId: Int) async throws
}

public struct APIProgramRepository: ProgramRepository {
    private let client: Client

    public init(client: Client) {
        self.client = client
    }

    public func programs() async throws -> [Program] {
        let output = try await client.listPrograms()
        switch output {
        case .ok(let response): return try response.body.json.data.map(Program.init(api:))
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func program(id: Int) async throws -> ProgramDetail {
        let output = try await client.getProgram(path: .init(programId: id))
        switch output {
        case .ok(let response): return try ProgramDetail(api: try response.body.json.data)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func enroll(programId: Int) async throws {
        let output = try await client.enrollProgram(path: .init(programId: programId))
        switch output {
        case .noContent: return
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}

public protocol TemplateRepository: Sendable {
    func template(id: Int) async throws -> WorkoutTemplate
}

public struct APITemplateRepository: TemplateRepository {
    private let client: Client

    public init(client: Client) {
        self.client = client
    }

    public func template(id: Int) async throws -> WorkoutTemplate {
        let output = try await client.getWorkoutTemplate(path: .init(workoutTemplateId: id))
        switch output {
        case .ok(let response): return try WorkoutTemplate(api: try response.body.json.data)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .notFound(let response): throw APIError(status: 404, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}
