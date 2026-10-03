import Foundation
import HTTPTypes
import OpenAPIRuntime
import Testing
import WorkoutsAPITestSupport

@testable import WorkoutsAPI

@Suite("Middlewares")
struct MiddlewareTests {
    private let baseURL = URL(string: "http://localhost:8080")!

    private func makeClient(_ transport: StubTransport, locale: String = "ru") -> Client {
        Client(
            serverURL: baseURL, configuration: APIClientFactory.configuration, transport: transport,
            middlewares: [
                LocaleMiddleware(resolve: { locale }),
                UserAgentMiddleware(version: "0.1.0", build: "7"),
            ]
        )
    }

    @Test("every request carries Accept-Language and User-Agent")
    func headers() async throws {
        let transport = StubTransport(responses: [
            .json(200, #"{"data":{"available_locales":{"en":"English"},"theme_preferences":["light"]}}"#)
        ])
        _ = try await makeClient(transport).getMeta()

        let request = try #require(await transport.requests.first)
        #expect(request.path == "/api/v1/meta")
        #expect(request.header(.acceptLanguage) == "ru")
        #expect(request.header(.userAgent) == "MyWorkouts-iOS/0.1.0 (7)")
    }

    @Test("the locale is resolved per request")
    func localeResolvedPerRequest() async throws {
        let transport = StubTransport(responses: [.empty(204), .empty(204)])
        let locales = Locales()
        let client = Client(
            serverURL: baseURL, configuration: APIClientFactory.configuration, transport: transport,
            middlewares: [LocaleMiddleware(resolve: { locales.next() })]
        )
        _ = try await client.logout()
        _ = try await client.logout()

        let seen = await transport.requests.map { $0.header(.acceptLanguage) }
        #expect(seen == ["en", "ru"])
    }

    @Test("the client posts native login bodies as the contract describes")
    func loginBody() async throws {
        let transport = StubTransport(responses: [
            .json(401, #"{"message":"Unauthenticated.","code":"unauthenticated"}"#)
        ])
        let output = try await makeClient(transport).login(
            body: .json(.init(email: "a@b.c", password: "secret", client: .native, deviceName: "iPhone"))
        )
        guard case .undocumented(let status, _) = output else {
            Issue.record("expected an undocumented 401, got \(output)")
            return
        }
        #expect(status == 401)

        let request = try #require(await transport.requests.first)
        let body = try JSONSerialization.jsonObject(with: try #require(request.body)) as? [String: Any]
        #expect(request.method == .post)
        #expect(body?["client"] as? String == "native")
        #expect(body?["device_name"] as? String == "iPhone")
        #expect(request.header(.contentType)?.hasPrefix("application/json") == true)
    }
}

/// Hands out "en" then "ru" to prove the resolver closure runs on every request.
private final class Locales: @unchecked Sendable {
    private let lock = NSLock()
    private var values = ["en", "ru"]

    func next() -> String {
        lock.lock()
        defer { lock.unlock() }
        return values.isEmpty ? "en" : values.removeFirst()
    }
}
