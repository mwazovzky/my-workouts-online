import Foundation
import OpenAPIRuntime
import OpenAPIURLSession

/// Builds the generated `Client` over URLSession with the app's middlewares.
/// Middlewares run in array order on the way out and in reverse on the way back.
public enum APIClientFactory {
    /// Runtime configuration every `Client` must use, in the app and in tests, so fixtures
    /// decode exactly as production responses do.
    public static let configuration = Configuration(dateTranscoder: APIDateTranscoder())

    public static func make(
        environment: APIEnvironment,
        middlewares: [any ClientMiddleware],
        session: URLSession = .shared
    ) -> Client {
        Client(
            serverURL: environment.baseURL,
            configuration: configuration,
            transport: URLSessionTransport(configuration: .init(session: session)),
            middlewares: middlewares
        )
    }
}
