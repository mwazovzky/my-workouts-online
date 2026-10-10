import Foundation
import HTTPTypes
import Testing
import WorkoutsAPI
import WorkoutsAPITestSupport

@testable import WorkoutsCore

/// Runs the real `Client` + `AuthMiddleware` + `AuthSession` against a stub transport.
@Suite("AuthMiddleware with AuthSession")
struct AuthMiddlewareTests {
    private static let me =
        #"{"data":{"id":1,"name":"Test","email":"test@example.com","locale":"en","theme_preference":"system","is_admin":false,"email_verified":true}}"#
    private static let expired =
        #"{"type":"https://my-workouts.online/problems/token_expired","title":"Access token expired","status":401,"detail":"Token has expired.","instance":"/api/v1/me#abc","code":"token_expired"}"#
    private static let unauthenticated =
        #"{"type":"https://my-workouts.online/problems/unauthenticated","title":"Unauthenticated","status":401,"detail":"Unauthenticated.","instance":"/api/v1/me#abc","code":"unauthenticated"}"#
    private static func grant(_ n: Int) -> String {
        #"{"access_token":"access-\#(n)","token_type":"Bearer","expires_in":900,"refresh_token":"refresh-\#(n)","user":\#(me.dropFirst(8).dropLast())}"#
    }

    private func make(store: TokenPair?) -> (AppDependencies, StubTransport) {
        let transport = StubTransport()
        let dependencies = AppDependencies.make(
            environment: APIEnvironment(baseURL: URL(string: "http://localhost:8080")!),
            deviceName: "test", appVersion: "0", build: "0",
            tokenStore: InMemoryTokenStore(store), transport: transport)
        return (dependencies, transport)
    }

    @Test("an expired token is refreshed once and the request replayed with the new bearer")
    func refreshAndRetry() async throws {
        let (deps, transport) = make(store: .fixture(fresh: true))
        await transport.enqueue(.problem(401, Self.expired), for: "getMe")
        await transport.enqueue(.json(200, Self.me), for: "getMe")
        await transport.enqueue(.json(200, Self.grant(1)), for: "refresh")

        let user = try await deps.meRepository.me()

        #expect(user.email == "test@example.com")
        let requests = await transport.requests
        #expect(requests.map(\.operationID) == ["getMe", "refresh", "getMe"])
        #expect(requests[0].header(.authorization) == "Bearer access-0")
        #expect(requests[1].header(.authorization) == nil, "refresh carries no bearer")
        #expect(requests[2].header(.authorization) == "Bearer access-1")
        let refreshBody = try JSONSerialization.jsonObject(with: try #require(requests[1].body)) as? [String: Any]
        #expect(refreshBody?["refresh_token"] as? String == "refresh-0")
    }

    @Test("three concurrent expired requests cause exactly one refresh")
    func concurrentRefresh() async throws {
        let (deps, transport) = make(store: .fixture(fresh: true))
        for _ in 0..<3 { await transport.enqueue(.problem(401, Self.expired), for: "getMe") }
        for _ in 0..<3 { await transport.enqueue(.json(200, Self.me), for: "getMe") }
        await transport.enqueue(.json(200, Self.grant(1)), for: "refresh")

        try await withThrowingTaskGroup(of: User.self) { group in
            for _ in 0..<3 { group.addTask { try await deps.meRepository.me() } }
            for try await _ in group {}
        }

        #expect(await transport.requests(for: "refresh").count == 1)
        #expect(await transport.requests(for: "getMe").count == 6)
    }

    @Test("a 401 that is not token_expired passes through untouched")
    func otherUnauthorizedPassesThrough() async throws {
        let (deps, transport) = make(store: .fixture(fresh: true))
        await transport.enqueue(.problem(401, Self.unauthenticated), for: "getMe")

        await #expect(throws: APIError.api(status: 401, code: "unauthenticated", message: "Unauthenticated.")) {
            try await deps.meRepository.me()
        }
        #expect(await transport.requests(for: "refresh").isEmpty)
    }

    @Test("a rejected refresh returns the original 401 and signs the session out")
    func rejectedRefresh() async throws {
        let (deps, transport) = make(store: .fixture(fresh: true))
        await transport.enqueue(.problem(401, Self.expired), for: "getMe")
        await transport.enqueue(
            .problem(401, code: "refresh_token_reused", detail: "Refresh token reused."), for: "refresh")

        await #expect(throws: APIError.api(status: 401, code: "token_expired", message: "Token has expired.")) {
            try await deps.meRepository.me()
        }
        #expect(await deps.authSession.hasSession == false)
        #expect(await transport.requests(for: "getMe").count == 1, "no replay without a new token")
    }

    @Test("login sends client native and the device name, and the grant can be stored")
    func login() async throws {
        let (deps, transport) = make(store: nil)
        await transport.enqueue(.json(200, Self.grant(7)), for: "login")

        let grant = try await deps.authRepository.login(email: "test@example.com", password: "password")
        try await deps.authSession.signIn(grant)

        let body =
            try JSONSerialization.jsonObject(with: try #require(await transport.requests.first?.body)) as? [String: Any]
        #expect(body?["client"] as? String == "native")
        #expect(body?["device_name"] as? String == "test")
        #expect(await deps.authSession.currentAccessToken() == "access-7")
    }

    @Test("logout carries the bearer of the session being closed")
    func logout() async throws {
        let (deps, transport) = make(store: .fixture(fresh: true))
        await transport.enqueue(.empty(204), for: "logout")

        await deps.authSession.signOut(revoke: true)

        let request = try #require(await transport.requests(for: "logout").first)
        #expect(request.header(.authorization) == "Bearer access-0")
        #expect(await deps.authSession.hasSession == false)
    }
}
