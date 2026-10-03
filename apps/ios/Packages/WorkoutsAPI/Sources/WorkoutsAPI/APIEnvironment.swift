import Foundation

/// Where the API lives. The generated client takes this as its server URL because the
/// contract's `servers` entry is `/`, relative to the web origin, and every path already
/// starts with `/api/v1`.
public struct APIEnvironment: Sendable, Equatable {
    public let baseURL: URL

    public init(baseURL: URL) {
        self.baseURL = baseURL
    }
}
