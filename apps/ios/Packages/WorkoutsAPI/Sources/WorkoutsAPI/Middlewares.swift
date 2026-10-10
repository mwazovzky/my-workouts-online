import Foundation
import HTTPTypes
import OpenAPIRuntime

/// Sets `Accept-Language` on every request so the API localizes content the same way the
/// UI is localized. The resolver runs per request because the user can change the app
/// language in iOS Settings while the app is suspended.
public struct LocaleMiddleware: ClientMiddleware {
    private let resolve: @Sendable () -> String

    public init(resolve: @escaping @Sendable () -> String) {
        self.resolve = resolve
    }

    public func intercept(
        _ request: HTTPRequest,
        body: HTTPBody?,
        baseURL: URL,
        operationID: String,
        next: @Sendable (HTTPRequest, HTTPBody?, URL) async throws -> (HTTPResponse, HTTPBody?)
    ) async throws -> (HTTPResponse, HTTPBody?) {
        var request = request
        request.headerFields[.acceptLanguage] = resolve()
        return try await next(request, body, baseURL)
    }
}

/// Identifies the client in server logs: `MyWorkouts-iOS/1.2.0 (34)`.
public struct UserAgentMiddleware: ClientMiddleware {
    private let value: String

    public init(appName: String = "MyWorkouts-iOS", version: String, build: String) {
        value = "\(appName)/\(version) (\(build))"
    }

    public func intercept(
        _ request: HTTPRequest,
        body: HTTPBody?,
        baseURL: URL,
        operationID: String,
        next: @Sendable (HTTPRequest, HTTPBody?, URL) async throws -> (HTTPResponse, HTTPBody?)
    ) async throws -> (HTTPResponse, HTTPBody?) {
        var request = request
        request.headerFields[.userAgent] = value
        return try await next(request, body, baseURL)
    }
}

/// Sends `If-Match` as written. The generated client URI-encodes header parameters, which
/// turns the quoted entity tag `"42-7"` into `%2242-7%22`, a tag that never matches; this
/// restores the literal value. Always installed by `APIClientFactory`.
public struct EntityTagMiddleware: ClientMiddleware {
    public init() {}

    public func intercept(
        _ request: HTTPRequest,
        body: HTTPBody?,
        baseURL: URL,
        operationID: String,
        next: @Sendable (HTTPRequest, HTTPBody?, URL) async throws -> (HTTPResponse, HTTPBody?)
    ) async throws -> (HTTPResponse, HTTPBody?) {
        var request = request
        if let value = request.headerFields[.ifMatch] {
            request.headerFields[.ifMatch] = value.removingPercentEncoding ?? value
        }
        return try await next(request, body, baseURL)
    }
}
