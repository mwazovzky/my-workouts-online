import Foundation

/// Where the API lives. The server URL is injected at runtime because the contract's
/// `servers` entry is relative to the web origin.
public struct APIEnvironment: Sendable, Equatable {
    public let baseURL: URL

    public init(baseURL: URL) {
        self.baseURL = baseURL
    }

    /// `/api/v1`, the only path prefix clients use.
    public var apiV1URL: URL {
        baseURL.appending(path: "api/v1")
    }
}
