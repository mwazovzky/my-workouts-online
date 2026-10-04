import SwiftUI
import UIKit
import WorkoutsAPI
import WorkoutsCore

@main
struct MyWorkoutsApp: App {
    @State private var session: AppSession
    @State private var deepLinks: DeepLinkCoordinator
    private let dependencies: AppDependencies

    init() {
        let dependencies = AppDependencies.make(
            environment: APIEnvironment(baseURL: AppConfig.apiBaseURL),
            deviceName: "\(UIDevice.current.model) · iOS \(UIDevice.current.systemVersion)",
            appVersion: AppConfig.marketingVersion,
            build: AppConfig.buildNumber,
            tokenStore: KeychainTokenStore(service: Bundle.main.bundleIdentifier ?? "online.my-workouts.app")
        )
        self.dependencies = dependencies
        let session = AppSession(authSession: dependencies.authSession, meRepository: dependencies.meRepository)
        _session = State(initialValue: session)
        _deepLinks = State(initialValue: DeepLinkCoordinator(session: session, me: dependencies.meRepository))
    }

    var body: some Scene {
        WindowGroup {
            RootView()
                .environment(session)
                .environment(deepLinks)
                .environment(\.dependencies, dependencies)
                .task { await session.bootstrap() }
                .onOpenURL { url in deepLinks.handle(url) }
                .onChange(of: session.state) { Task { await deepLinks.sessionStateChanged() } }
        }
    }
}

/// Lets views reach repositories without a global.
private struct DependenciesKey: EnvironmentKey {
    static let defaultValue: AppDependencies? = nil
}

extension EnvironmentValues {
    var dependencies: AppDependencies? {
        get { self[DependenciesKey.self] }
        set { self[DependenciesKey.self] = newValue }
    }
}
