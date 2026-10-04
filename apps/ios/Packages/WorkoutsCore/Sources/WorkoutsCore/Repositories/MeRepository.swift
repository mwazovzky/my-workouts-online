import Foundation
import WorkoutsAPI

public enum ResendVerificationResult: Sendable, Equatable {
    case sent
    case alreadyVerified
}

public protocol MeRepository: Sendable {
    func me() async throws -> User
    func resendVerification() async throws -> ResendVerificationResult
}

public struct APIMeRepository: MeRepository {
    private let client: Client

    /// - Parameter client: a client with `AuthMiddleware`.
    public init(client: Client) {
        self.client = client
    }

    public func me() async throws -> User {
        let output = try await client.getMe()
        switch output {
        case .ok(let response): return try User(api: try response.body.json.data)
        case .unauthorized(let response): throw APIError(status: 401, envelope: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func resendVerification() async throws -> ResendVerificationResult {
        let output = try await client.resendVerification()
        switch output {
        case .accepted: return .sent
        case .noContent: return .alreadyVerified
        case .unauthorized(let response): throw APIError(status: 401, envelope: try response.body.json)
        case .tooManyRequests(let response): throw APIError(status: 429, envelope: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}
