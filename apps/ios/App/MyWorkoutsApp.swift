import SwiftUI
import UIKit
import WorkoutsAPI
import WorkoutsCore

@main
struct MyWorkoutsApp: App {
    @State private var session: AppSession
    @State private var deepLinks: DeepLinkCoordinator
    @State private var toaster = Toaster()
    @State private var theme: ThemePreference
    private let dependencies: AppDependencies
    private let themeMemory = UserDefaultsThemeMemory()

    init() {
        let tokenStore = KeychainTokenStore(service: Bundle.main.bundleIdentifier ?? "online.my-workouts.app")
        // UI tests start signed out regardless of what an earlier run left in the Keychain.
        if ProcessInfo.processInfo.arguments.contains("--reset-session") {
            try? tokenStore.clear()
        }
        let dependencies = AppDependencies.make(
            environment: APIEnvironment(baseURL: AppConfig.apiBaseURL),
            deviceName: "\(UIDevice.current.model) · iOS \(UIDevice.current.systemVersion)",
            appVersion: AppConfig.marketingVersion,
            build: AppConfig.buildNumber,
            tokenStore: tokenStore
        )
        self.dependencies = dependencies
        let session = AppSession(
            authSession: dependencies.authSession, meRepository: dependencies.meRepository,
            draftStore: dependencies.draftStore)
        _session = State(initialValue: session)
        _deepLinks = State(initialValue: DeepLinkCoordinator(session: session, me: dependencies.meRepository))
        _theme = State(initialValue: themeMemory.rememberedTheme() ?? .system)
    }

    var body: some Scene {
        WindowGroup {
            RootView()
                .toastOverlay()  // reads Toaster, so it sits inside the environment modifiers below
                .environment(session)
                .environment(deepLinks)
                .environment(toaster)
                .environment(\.dependencies, dependencies)
                .preferredColorScheme(theme.colorScheme)
                .onChange(of: session.user?.themePreference, initial: true) { _, preference in
                    guard let preference else { return }
                    theme = preference
                    themeMemory.remember(preference)
                }
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

extension ThemePreference {
    /// `nil` follows the device setting.
    var colorScheme: ColorScheme? {
        switch self {
        case .light: .light
        case .dark: .dark
        case .system: nil
        }
    }
}
