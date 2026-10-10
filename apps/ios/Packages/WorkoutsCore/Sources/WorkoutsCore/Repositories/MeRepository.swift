import Foundation
import WorkoutsAPI

public protocol MeRepository: Sendable {
    func me() async throws -> User
    /// Always accepted (202); the API sends nothing when the address is already verified.
    func resendVerification() async throws
    /// Posts the emailed link's parameters; the caller must be signed in as that user.
    func verifyEmail(id: Int, hash: String, expires: Int, signature: String) async throws
    /// Changing the email resets verification; the returned user says so.
    func updateProfile(name: String, email: String) async throws -> User
    func updatePassword(current: String, new: String, confirmation: String) async throws
    func updateLocale(_ locale: String) async throws -> User
    func updateTheme(_ theme: ThemePreference) async throws -> User
    /// Permanently deletes the account; the server revokes every session.
    func deleteProfile(password: String) async throws
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
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func resendVerification() async throws {
        let output = try await client.resendVerification()
        switch output {
        case .accepted: return
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .tooManyRequests(let response):
            throw APIError(status: 429, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func verifyEmail(id: Int, hash: String, expires: Int, signature: String) async throws {
        let output = try await client.verifyEmail(
            body: .json(.init(id: id, hash: hash, expires: expires, signature: signature)))
        switch output {
        case .noContent: return
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .forbidden(let response): throw APIError(status: 403, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .tooManyRequests(let response):
            throw APIError(status: 429, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func updateProfile(name: String, email: String) async throws -> User {
        let output = try await client.updateProfile(body: .json(.init(name: name, email: email)))
        switch output {
        case .ok(let response): return try User(api: try response.body.json.data)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func updatePassword(current: String, new: String, confirmation: String) async throws {
        let output = try await client.updatePassword(
            body: .json(.init(currentPassword: current, password: new, passwordConfirmation: confirmation)))
        switch output {
        case .noContent: return
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func updateLocale(_ locale: String) async throws -> User {
        guard let value = Components.Schemas.Locale(rawValue: locale) else {
            throw APIError.local(description: "unsupported locale \(locale)")
        }
        let output = try await client.updateLocale(body: .json(.init(locale: value)))
        switch output {
        case .ok(let response): return try User(api: try response.body.json.data)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func updateTheme(_ theme: ThemePreference) async throws -> User {
        guard let value = Components.Schemas.ThemePreference(rawValue: theme.rawValue) else {
            throw APIError.local(description: "unsupported theme \(theme.rawValue)")
        }
        let output = try await client.updateTheme(body: .json(.init(themePreference: value)))
        switch output {
        case .ok(let response): return try User(api: try response.body.json.data)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func deleteProfile(password: String) async throws {
        let output = try await client.deleteProfile(body: .json(.init(password: password)))
        switch output {
        case .noContent: return
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}
