import Foundation
import WorkoutsAPI

public protocol MeRepository: Sendable {
    func me() async throws -> User
    /// Always accepted (202); the API sends nothing when the address is already verified.
    func resendVerification() async throws
    /// Posts the emailed link's parameters; the caller must be signed in as that user.
    func verifyEmail(id: Int, hash: String, expires: Int, signature: String) async throws
    /// `PATCH /me`: changes only the fields set in `patch`. Changing the email resets
    /// verification; the returned user says so.
    func updateMe(_ patch: MePatch) async throws -> User
    func updatePassword(current: String, new: String) async throws
    /// Permanently deletes the account; the server revokes every session.
    func deleteMe(password: String) async throws
}

/// A merge patch for the current user: `nil` fields are left out of the request and stay
/// unchanged on the server.
public struct MePatch: Sendable, Equatable {
    public var name: String?
    public var email: String?
    public var locale: String?
    public var themePreference: ThemePreference?

    public init(
        name: String? = nil, email: String? = nil, locale: String? = nil, themePreference: ThemePreference? = nil
    ) {
        self.name = name
        self.email = email
        self.locale = locale
        self.themePreference = themePreference
    }
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

    public func updateMe(_ patch: MePatch) async throws -> User {
        var body = Components.Schemas.MePatch(name: patch.name, email: patch.email)
        if let locale = patch.locale {
            guard let value = Components.Schemas.Locale(rawValue: locale) else {
                throw APIError.local(description: "unsupported locale \(locale)")
            }
            body.locale = value
        }
        if let theme = patch.themePreference {
            guard let value = Components.Schemas.ThemePreference(rawValue: theme.rawValue) else {
                throw APIError.local(description: "unsupported theme \(theme.rawValue)")
            }
            body.themePreference = value
        }
        let output = try await client.updateMe(body: .json(body))
        switch output {
        case .ok(let response): return try User(api: try response.body.json.data)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func updatePassword(current: String, new: String) async throws {
        let output = try await client.updatePassword(body: .json(.init(currentPassword: current, password: new)))
        switch output {
        case .noContent: return
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }

    public func deleteMe(password: String) async throws {
        let output = try await client.deleteMe(body: .json(.init(password: password)))
        switch output {
        case .noContent: return
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .unprocessableContent(let response): throw APIError(validation: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}
