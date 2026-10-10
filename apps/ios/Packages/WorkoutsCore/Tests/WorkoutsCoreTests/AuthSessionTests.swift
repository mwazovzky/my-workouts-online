import Foundation
import Testing
import WorkoutsAPI

@testable import WorkoutsCore

/// A scripted refresher that counts calls and can delay, fail, or revoke.
actor FakeRefresher: TokenRefreshing {
    enum Mode { case succeed, revoke(code: String), offline }
    var mode: Mode
    private(set) var refreshCalls = 0
    private(set) var logoutTokens: [String] = []
    var logoutFails = false
    let delay: Duration

    init(mode: Mode = .succeed, delay: Duration = .zero) {
        self.mode = mode
        self.delay = delay
    }

    func set(_ mode: Mode) { self.mode = mode }
    func failLogout() { logoutFails = true }

    func refresh(refreshToken: String) async throws -> AuthGrant {
        refreshCalls += 1
        try await Task.sleep(for: delay)
        switch mode {
        case .succeed:
            return AuthGrant(
                accessToken: "access-\(refreshCalls)", expiresIn: 900, refreshToken: "refresh-\(refreshCalls)",
                user: .fixture)
        case .revoke(let code):
            throw APIError.api(status: 401, code: code, message: "Refresh token invalid.")
        case .offline:
            throw APIError.network(description: "offline")
        }
    }

    func logout(accessToken: String) async throws {
        logoutTokens.append(accessToken)
        if logoutFails { throw APIError.network(description: "offline") }
    }
}

extension User {
    static let fixture = User(
        id: 1, name: "Test", email: "test@example.com", locale: "en", themePreference: .system, isAdmin: false,
        emailVerifiedAt: Date(timeIntervalSince1970: 0))
    static let unverified = User(
        id: 2, name: "New", email: "new@example.com", locale: "en", themePreference: .system, isAdmin: false,
        emailVerifiedAt: nil)
}

extension TokenPair {
    static func fixture(fresh: Bool) -> TokenPair {
        TokenPair(
            accessToken: "access-0", refreshToken: "refresh-0",
            accessExpiresAt: Date().addingTimeInterval(fresh ? 900 : -10))
    }
}

@Suite("AuthSession")
struct AuthSessionTests {
    @Test("signIn persists both tokens and the expiry")
    func signIn() async throws {
        let store = InMemoryTokenStore()
        let session = AuthSession(store: store, refresher: FakeRefresher(), now: { Date(timeIntervalSince1970: 1000) })
        try await session.signIn(AuthGrant(accessToken: "a", expiresIn: 900, refreshToken: "r", user: .fixture))

        let saved = try #require(try store.load())
        #expect(saved.accessToken == "a")
        #expect(saved.refreshToken == "r")
        #expect(saved.accessExpiresAt == Date(timeIntervalSince1970: 1900))
        #expect(await session.currentAccessToken() == "a")
    }

    @Test("signIn without a refresh token is rejected")
    func signInWithoutRefreshToken() async {
        let session = AuthSession(store: InMemoryTokenStore(), refresher: FakeRefresher())
        await #expect(throws: AuthSession.Failure.refreshTokenMissing) {
            try await session.signIn(AuthGrant(accessToken: "a", expiresIn: 900, refreshToken: nil, user: .fixture))
        }
    }

    @Test("concurrent refreshes share one network call")
    func singleFlight() async throws {
        let refresher = FakeRefresher(delay: .milliseconds(50))
        let store = InMemoryTokenStore(.fixture(fresh: false))
        let session = AuthSession(store: store, refresher: refresher)

        let results = try await withThrowingTaskGroup(of: TokenPair.self) { group in
            for _ in 0..<5 { group.addTask { try await session.refresh() } }
            return try await group.reduce(into: [TokenPair]()) { $0.append($1) }
        }

        #expect(await refresher.refreshCalls == 1)
        #expect(Set(results.map(\.accessToken)) == ["access-1"])
        #expect(try store.load()?.refreshToken == "refresh-1")
    }

    @Test("a rejected refresh clears the store and notifies")
    func revoked() async throws {
        let refresher = FakeRefresher(mode: .revoke(code: "refresh_token_reused"))
        let store = InMemoryTokenStore(.fixture(fresh: false))
        let session = AuthSession(store: store, refresher: refresher)
        let revokedCode = RevocationRecorder()
        await session.setRevocationHandler { code in revokedCode.record(code) }

        await #expect(throws: AuthSession.Failure.sessionRevoked(code: "refresh_token_reused")) {
            try await session.refresh()
        }
        #expect(try store.load() == nil)
        #expect(await session.hasSession == false)
        #expect(revokedCode.value == "refresh_token_reused")
    }

    @Test("a network failure keeps the stored pair")
    func offline() async throws {
        let store = InMemoryTokenStore(.fixture(fresh: false))
        let session = AuthSession(store: store, refresher: FakeRefresher(mode: .offline))

        await #expect(throws: APIError.network(description: "offline")) { try await session.refresh() }
        #expect(try store.load()?.refreshToken == "refresh-0")
        #expect(await session.hasSession)
    }

    @Test("signOut revokes with the current access token and clears even when the call fails")
    func signOut() async throws {
        let refresher = FakeRefresher()
        await refresher.failLogout()
        let store = InMemoryTokenStore(.fixture(fresh: true))
        let session = AuthSession(store: store, refresher: refresher)

        await session.signOut(revoke: true)

        #expect(await refresher.logoutTokens == ["access-0"])
        #expect(try store.load() == nil)
        #expect(await session.currentAccessToken() == nil)
    }
}

final class RevocationRecorder: @unchecked Sendable {
    private let lock = NSLock()
    private var stored: String?
    var value: String? { lock.withLock { stored } }
    func record(_ code: String) { lock.withLock { stored = code } }
}
