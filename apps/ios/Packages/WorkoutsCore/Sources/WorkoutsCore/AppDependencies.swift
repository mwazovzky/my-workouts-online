import Foundation
import OpenAPIRuntime
import OpenAPIURLSession
import WorkoutsAPI

/// Wires the clients, session and repositories. Built once at launch; tests build their own
/// with a stub transport.
public struct AppDependencies: Sendable {
    public let authSession: AuthSession
    public let authRepository: any AuthRepository
    public let meRepository: any MeRepository
    public let dashboardRepository: any DashboardRepository
    public let workoutRepository: any WorkoutRepository
    public let apiClient: Client

    public init(
        authSession: AuthSession, authRepository: any AuthRepository, meRepository: any MeRepository,
        dashboardRepository: any DashboardRepository, workoutRepository: any WorkoutRepository, apiClient: Client
    ) {
        self.authSession = authSession
        self.authRepository = authRepository
        self.meRepository = meRepository
        self.dashboardRepository = dashboardRepository
        self.workoutRepository = workoutRepository
        self.apiClient = apiClient
    }

    /// - Parameters:
    ///   - environment: the API origin.
    ///   - deviceName: stored on the refresh-token family, e.g. `iPhone · iOS 27.0`.
    ///   - appVersion / build: for `User-Agent`.
    ///   - availableLocales: the API's locales; `Accept-Language` is resolved against them per request.
    ///   - tokenStore: Keychain in the app, memory in tests.
    ///   - transport: URLSession by default; a stub in tests.
    public static func make(
        environment: APIEnvironment,
        deviceName: String,
        appVersion: String,
        build: String,
        availableLocales: @escaping @Sendable () -> [String] = { ["en", "ru"] },
        tokenStore: any TokenStore,
        transport: any ClientTransport = URLSessionTransport()
    ) -> AppDependencies {
        let common: [any ClientMiddleware] = [
            LocaleMiddleware(resolve: { LocaleResolver.apiLocale(available: availableLocales()) }),
            UserAgentMiddleware(version: appVersion, build: build),
        ]
        let makeClient: @Sendable ([any ClientMiddleware]) -> Client = { extra in
            Client(
                serverURL: environment.baseURL,
                configuration: APIClientFactory.configuration,
                transport: transport,
                middlewares: common + extra
            )
        }
        let authRepository = APIAuthRepository(client: makeClient([]), makeClient: makeClient, deviceName: deviceName)
        let authSession = AuthSession(store: tokenStore, refresher: authRepository)
        let apiClient = makeClient([
            AuthMiddleware(
                accessToken: { await authSession.currentAccessToken() },
                refresh: { try await authSession.refresh() }
            )
        ])
        return AppDependencies(
            authSession: authSession,
            authRepository: authRepository,
            meRepository: APIMeRepository(client: apiClient),
            dashboardRepository: APIDashboardRepository(client: apiClient),
            workoutRepository: APIWorkoutRepository(client: apiClient),
            apiClient: apiClient
        )
    }
}
