import Foundation

/// The two tokens of a native session plus when the access token stops being usable.
public struct TokenPair: Sendable, Equatable, Codable {
    public let accessToken: String
    public let refreshToken: String
    public let accessExpiresAt: Date

    public init(accessToken: String, refreshToken: String, accessExpiresAt: Date) {
        self.accessToken = accessToken
        self.refreshToken = refreshToken
        self.accessExpiresAt = accessExpiresAt
    }

    /// True while the access token has more than `leeway` seconds left.
    public func isAccessTokenFresh(now: Date = Date(), leeway: TimeInterval = 60) -> Bool {
        accessExpiresAt.timeIntervalSince(now) > leeway
    }
}

/// Where the token pair lives between launches. The app uses the Keychain; tests use memory.
public protocol TokenStore: Sendable {
    func load() throws -> TokenPair?
    func save(_ pair: TokenPair) throws
    func clear() throws
}

public final class InMemoryTokenStore: TokenStore, @unchecked Sendable {
    private let lock = NSLock()
    private var pair: TokenPair?

    public init(_ pair: TokenPair? = nil) {
        self.pair = pair
    }

    public func load() throws -> TokenPair? {
        lock.withLock { pair }
    }

    public func save(_ pair: TokenPair) throws {
        lock.withLock { self.pair = pair }
    }

    public func clear() throws {
        lock.withLock { pair = nil }
    }
}
