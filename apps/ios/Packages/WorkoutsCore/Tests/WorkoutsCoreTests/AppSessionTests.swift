import Foundation
import Testing
import WorkoutsAPI
import WorkoutsAPITestSupport

@testable import WorkoutsCore

@Suite("AppSession")
@MainActor
struct AppSessionTests {
    private static let me =
        #"{"data":{"id":1,"name":"Test","email":"test@example.com","locale":"en","theme_preference":"system","is_admin":false,"email_verified":true}}"#
    private static let unverifiedMe =
        #"{"data":{"id":2,"name":"New","email":"new@example.com","locale":"en","theme_preference":"system","is_admin":false,"email_verified":false}}"#
    private static let grant =
        #"{"access_token":"access-1","token_type":"Bearer","expires_in":900,"refresh_token":"refresh-1","user":{"id":1,"name":"Test","email":"test@example.com","locale":"en","theme_preference":"system","is_admin":false,"email_verified":true}}"#

    private func make(store: TokenPair?) -> (AppSession, AppDependencies, StubTransport) {
        let transport = StubTransport()
        let deps = AppDependencies.make(
            environment: APIEnvironment(baseURL: URL(string: "http://localhost:8080")!),
            deviceName: "test", appVersion: "0", build: "0",
            tokenStore: InMemoryTokenStore(store), transport: transport)
        return (AppSession(authSession: deps.authSession, meRepository: deps.meRepository), deps, transport)
    }

    @Test("no stored session lands on signedOut without touching the network")
    func noSession() async {
        let (session, _, transport) = make(store: nil)
        await session.bootstrap()
        #expect(session.state == .signedOut(reason: nil))
        #expect(await transport.requests.isEmpty)
    }

    @Test("a fresh access token goes straight to /me")
    func freshToken() async throws {
        let (session, _, transport) = make(store: .fixture(fresh: true))
        await transport.enqueue(.json(200, Self.me), for: "getMe")

        await session.bootstrap()

        #expect(session.state == .ready(.fixture))
        #expect(await transport.requests.map(\.operationID) == ["getMe"])
    }

    @Test("an expired access token is refreshed exactly once before /me")
    func expiredToken() async throws {
        let (session, _, transport) = make(store: .fixture(fresh: false))
        await transport.enqueue(.json(200, Self.grant), for: "refresh")
        await transport.enqueue(.json(200, Self.me), for: "getMe")

        await session.bootstrap()

        #expect(session.state == .ready(.fixture))
        #expect(await transport.requests.map(\.operationID) == ["refresh", "getMe"])
    }

    @Test("an unverified user lands on the verify gate")
    func unverified() async throws {
        let (session, _, transport) = make(store: .fixture(fresh: true))
        await transport.enqueue(.json(200, Self.unverifiedMe), for: "getMe")

        await session.bootstrap()

        #expect(session.state == .unverified(.unverified))
        #expect(session.isSignedIn)
    }

    @Test("a revoked refresh token signs out with sessionExpired and clears the store")
    func revoked() async throws {
        let (session, deps, transport) = make(store: .fixture(fresh: false))
        await transport.enqueue(.problem(401, code: "refresh_token_reused", detail: "Reused."), for: "refresh")

        await session.bootstrap()

        #expect(session.state == .signedOut(reason: .sessionExpired))
        #expect(await deps.authSession.hasSession == false)
    }

    @Test("being offline keeps the session and reports a retryable error")
    func offline() async throws {
        let (session, deps, transport) = make(store: .fixture(fresh: false))
        await transport.enqueueFailure(URLError(.notConnectedToInternet))

        await session.bootstrap()

        #expect(session.state == .bootstrapping)
        guard case .network = session.bootstrapError else {
            Issue.record("expected a network error, got \(String(describing: session.bootstrapError))")
            return
        }
        #expect(await deps.authSession.hasSession)
    }

    @Test("applying a grant stores tokens and shows the user; signOut clears everything")
    func applyAndSignOut() async throws {
        let (session, deps, transport) = make(store: nil)
        await transport.enqueue(.json(200, Self.grant), for: "login")
        await transport.enqueue(.empty(204), for: "logout")

        let grant = try await deps.authRepository.login(email: "test@example.com", password: "password")
        try await session.apply(grant: grant)
        #expect(session.state == .ready(.fixture))
        #expect(await deps.authSession.currentAccessToken() == "access-1")

        await session.signOut()
        #expect(session.state == .signedOut(reason: .userInitiated))
        #expect(await deps.authSession.hasSession == false)
        #expect(await transport.requests(for: "logout").count == 1)
    }
}
