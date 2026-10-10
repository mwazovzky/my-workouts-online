import Foundation
import OpenAPIRuntime
import WorkoutsAPI
import WorkoutsAPITestSupport

/// Loads JSON captured from the seeded dev API (`Tests/WorkoutsCoreTests/Fixtures`) and
/// serves it through the real generated client, so decoding goes through the same
/// runtime configuration the app uses.
enum Fixtures {
    static func data(_ name: String) throws -> Data {
        guard let url = Bundle.module.url(forResource: name, withExtension: "json", subdirectory: "Fixtures") else {
            throw NSError(domain: "fixtures", code: 1, userInfo: [NSLocalizedDescriptionKey: "missing fixture \(name)"])
        }
        return try Data(contentsOf: url)
    }

    static func client(returning name: String, status: Int = 200) throws -> Client {
        let contentType = status >= 400 ? "application/problem+json" : "application/json"
        let transport = StubTransport(responses: [
            StubTransport.StubResponse(status: status, body: try data(name), contentType: contentType)
        ])
        return Client(
            serverURL: URL(string: "http://localhost:8080")!, configuration: APIClientFactory.configuration,
            transport: transport, middlewares: [])
    }
}
