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
