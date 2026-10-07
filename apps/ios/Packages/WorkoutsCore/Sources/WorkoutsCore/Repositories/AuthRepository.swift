import Foundation
import OpenAPIRuntime
import WorkoutsAPI

public protocol AuthRepository: TokenRefreshing {
    func login(email: String, password: String) async throws -> AuthGrant
    func register(name: String, email: String, password: String, passwordConfirmation: String) async throws -> AuthGrant
    /// Always succeeds for a well-formed email; the API never reveals whether the account exists.
    func forgotPassword(email: String) async throws
    /// Consumes the emailed token; on success the server revokes every refresh family.
    func resetPassword(token: String, email: String, password: String, passwordConfirmation: String) async throws
}

/// Talks to the auth endpoints with a client that carries no session (login, register and
/// refresh must never trigger a refresh themselves). `logout` builds a one-off client that
/// sends the access token of the session being closed.
public struct APIAuthRepository: AuthRepository {
    private let client: Client
    private let makeClient: @Sendable ([any ClientMiddleware]) -> Client
    private let deviceName: String

    /// - Parameters:
    ///   - client: a client without `AuthMiddleware`.
    ///   - makeClient: builds a client with the given extra middlewares appended (used for logout).
    ///   - deviceName: stored by the API on the refresh-token family.
    public init(client: Client, makeClient: @escaping @Sendable ([any ClientMiddleware]) -> Client, deviceName: String)
    {
        self.client = client
        self.makeClient = makeClient
        self.deviceName = deviceName
    }

    public func login(email: String, password: String) async throws -> AuthGrant {
        let output = try await client.login(
            body: .json(.init(email: email, password: password, client: .native, deviceName: deviceName)))
        switch output {
        case .ok(let response): return try AuthGrant(api: try response.body.json)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.json)
        case .tooManyRequests(let response): throw APIError(status: 429, envelope: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func register(name: String, email: String, password: String, passwordConfirmation: String) async throws
        -> AuthGrant
    {
        let output = try await client.register(
            body: .json(
                .init(
                    name: name, email: email, password: password, passwordConfirmation: passwordConfirmation,
                    client: .native, deviceName: deviceName)))
        switch output {
        case .created(let response): return try AuthGrant(api: try response.body.json)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.json)
        case .tooManyRequests(let response): throw APIError(status: 429, envelope: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func forgotPassword(email: String) async throws {
        let output = try await client.forgotPassword(body: .json(.init(email: email)))
        switch output {
        case .accepted: return
        case .unprocessableContent(let response): throw APIError(validation: try response.body.json)
        case .tooManyRequests(let response): throw APIError(status: 429, envelope: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func resetPassword(token: String, email: String, password: String, passwordConfirmation: String) async throws
    {
        let output = try await client.resetPassword(
            body: .json(
                .init(token: token, email: email, password: password, passwordConfirmation: passwordConfirmation)))
        switch output {
        case .noContent: return
        case .unprocessableContent(let response): throw APIError(validation: try response.body.json)
        case .tooManyRequests(let response): throw APIError(status: 429, envelope: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func refresh(refreshToken: String) async throws -> AuthGrant {
        // The header is only required by the server for the cookie-based web flow, but the contract
        // marks it required, so native clients send it too.
        let output = try await client.refresh(
            headers: .init(xRequestedWith: .xmlHttpRequest), body: .json(.init(refreshToken: refreshToken)))
        switch output {
        case .ok(let response): return try AuthGrant(api: try response.body.json)
        case .unauthorized(let response): throw APIError(status: 401, envelope: try response.body.json)
        case .forbidden(let response): throw APIError(status: 403, envelope: try response.body.json)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func logout(accessToken: String) async throws {
        let output = try await makeClient([StaticBearerMiddleware(token: accessToken)]).logout()
        switch output {
        case .noContent: return
        case .unauthorized(let response): throw APIError(status: 401, envelope: try response.body.json)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}
