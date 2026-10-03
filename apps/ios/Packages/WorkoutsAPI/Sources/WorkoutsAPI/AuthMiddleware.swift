import Foundation
import HTTPTypes
import OpenAPIRuntime

/// Adds the bearer token and transparently recovers from an expired access token: on a
/// 401 whose body code is `token_expired` it asks the session to refresh (single-flight,
/// owned by the caller) and replays the request once. Any other 401 passes through, as do
/// 401s from the auth operations themselves, so a failed refresh never loops.
public struct AuthMiddleware: ClientMiddleware {
    /// Operations that never carry a session or must not trigger a refresh.
    public static let excludedOperations: Set<String> = [
        "login", "register", "refresh", "forgotPassword", "resetPassword", "socialLogin", "getMeta", "getJwks",
    ]

    public static let refreshTriggerCode = "token_expired"

    private let accessToken: @Sendable () async -> String?
    private let refresh: @Sendable () async throws -> Void

    /// - Parameters:
    ///   - accessToken: returns the current access token, or nil when signed out.
    ///   - refresh: rotates the tokens; must coalesce concurrent callers and throw when the
    ///     session cannot be recovered (the original 401 is then returned to the caller).
    public init(
        accessToken: @escaping @Sendable () async -> String?,
        refresh: @escaping @Sendable () async throws -> Void
    ) {
        self.accessToken = accessToken
        self.refresh = refresh
    }

    public func intercept(
        _ request: HTTPRequest,
        body: HTTPBody?,
        baseURL: URL,
        operationID: String,
        next: @Sendable (HTTPRequest, HTTPBody?, URL) async throws -> (HTTPResponse, HTTPBody?)
    ) async throws -> (HTTPResponse, HTTPBody?) {
        var authorized = request
        if let token = await accessToken() {
            authorized.headerFields[.authorization] = "Bearer \(token)"
        }
        let (response, responseBody) = try await next(authorized, body, baseURL)

        guard response.status.code == 401, !Self.excludedOperations.contains(operationID) else {
            return (response, responseBody)
        }
        let bufferedBody = try await buffer(responseBody)
        guard Self.code(in: bufferedBody) == Self.refreshTriggerCode, body?.iterationBehavior != .single else {
            return (response, bufferedBody.map { HTTPBody($0) })
        }

        do {
            try await refresh()
        } catch {
            return (response, bufferedBody.map { HTTPBody($0) })
        }

        var retried = request
        if let token = await accessToken() {
            retried.headerFields[.authorization] = "Bearer \(token)"
        }
        return try await next(retried, body, baseURL)
    }

    private func buffer(_ body: HTTPBody?) async throws -> Data? {
        guard let body else { return nil }
        return try await Data(collecting: body, upTo: 64 * 1024)
    }

    private static func code(in data: Data?) -> String? {
        guard let data, let envelope = try? JSONDecoder().decode(Components.Schemas._Error.self, from: data) else {
            return nil
        }
        return envelope.code
    }
}

/// Sends one fixed bearer token; used for `logout`, which must carry the access token of
/// the session being closed without going through the refreshing middleware.
public struct StaticBearerMiddleware: ClientMiddleware {
    private let token: String

    public init(token: String) {
        self.token = token
    }

    public func intercept(
        _ request: HTTPRequest,
        body: HTTPBody?,
        baseURL: URL,
        operationID: String,
        next: @Sendable (HTTPRequest, HTTPBody?, URL) async throws -> (HTTPResponse, HTTPBody?)
    ) async throws -> (HTTPResponse, HTTPBody?) {
        var request = request
        request.headerFields[.authorization] = "Bearer \(token)"
        return try await next(request, body, baseURL)
    }
}
