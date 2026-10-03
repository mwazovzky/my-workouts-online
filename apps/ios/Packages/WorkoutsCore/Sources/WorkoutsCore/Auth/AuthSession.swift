import Foundation
import WorkoutsAPI

/// The part of the auth API the session needs; `AuthRepository` implements it.
public protocol TokenRefreshing: Sendable {
    func refresh(refreshToken: String) async throws -> AuthGrant
    func logout(accessToken: String) async throws
}

/// Owns the token pair. Refreshes are single-flight: concurrent callers await the same
/// rotation, which matters because presenting a rotated refresh token revokes the family.
public actor AuthSession {
    public enum Failure: Error, Equatable {
        case noSession
        case refreshTokenMissing
        /// The server rejected the refresh token (`refresh_token_invalid|expired|reused`): the
        /// stored pair has been cleared and the user must sign in again.
        case sessionRevoked(code: String)
    }

    private let store: any TokenStore
    private let refresher: any TokenRefreshing
    private let now: @Sendable () -> Date
    private var pair: TokenPair?
    private var inflight: Task<TokenPair, Error>?
    private var onRevoked: (@Sendable (String) -> Void)?

    public init(store: any TokenStore, refresher: any TokenRefreshing, now: @escaping @Sendable () -> Date = { Date() })
    {
        self.store = store
        self.refresher = refresher
        self.now = now
        pair = try? store.load()
    }

    public var hasSession: Bool { pair != nil }

    public func currentAccessToken() -> String? { pair?.accessToken }

    public func isAccessTokenFresh() -> Bool {
        pair?.isAccessTokenFresh(now: now()) ?? false
    }

    /// Called when a refresh is rejected by the server, after the store is cleared.
    public func setRevocationHandler(_ handler: @escaping @Sendable (String) -> Void) {
        onRevoked = handler
    }

    /// Persists the tokens from a login, register or refresh response.
    public func signIn(_ grant: AuthGrant) throws {
        guard let refreshToken = grant.refreshToken else { throw Failure.refreshTokenMissing }
        let newPair = TokenPair(
            accessToken: grant.accessToken,
            refreshToken: refreshToken,
            accessExpiresAt: now().addingTimeInterval(grant.expiresIn)
        )
        try store.save(newPair)
        pair = newPair
    }

    /// Rotates the token pair. Concurrent callers share one network call. A server
    /// rejection clears the session and throws `Failure.sessionRevoked`; a transport
    /// failure keeps the stored pair and rethrows so the caller can retry later.
    @discardableResult
    public func refresh() async throws -> TokenPair {
        if let inflight {
            return try await inflight.value
        }
        guard let current = pair else { throw Failure.noSession }

        let task = Task<TokenPair, Error> {
            let grant = try await refresher.refresh(refreshToken: current.refreshToken)
            guard let refreshToken = grant.refreshToken else { throw Failure.refreshTokenMissing }
            return TokenPair(
                accessToken: grant.accessToken,
                refreshToken: refreshToken,
                accessExpiresAt: now().addingTimeInterval(grant.expiresIn)
            )
        }
        inflight = task
        defer { inflight = nil }

        do {
            let newPair = try await task.value
            try store.save(newPair)
            pair = newPair
            return newPair
        } catch {
            if let code = Self.revocationCode(error) {
                clear()
                onRevoked?(code)
                throw Failure.sessionRevoked(code: code)
            }
            throw error
        }
    }

    /// Ends the session. `revoke` tells the server to drop the refresh family (best effort);
    /// local state is cleared either way.
    public func signOut(revoke: Bool) async {
        if revoke, let token = pair?.accessToken {
            try? await refresher.logout(accessToken: token)
        }
        clear()
    }

    private func clear() {
        try? store.clear()
        pair = nil
    }

    private static func revocationCode(_ error: any Error) -> String? {
        guard case .api(let status, let code, _) = APIError.from(error), status == 401 || status == 403 else {
            return nil
        }
        return code.hasPrefix("refresh_token") ? code : nil
    }
}
