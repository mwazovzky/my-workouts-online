import SwiftUI
import WorkoutsCore

enum AppTab: Hashable {
    case dashboard, programs, workouts, profile
}

/// The signed-in shell: four tabs mirroring the web bottom navigation, each with its own
/// navigation stack so switching tabs keeps the place in each.
struct MainTabView: View {
    @Environment(AppSession.self) private var session
    @State private var selected: AppTab = .dashboard

    var body: some View {
        TabView(selection: $selected) {
            DashboardTab()
                .tabItem { Label("Dashboard", systemImage: "square.grid.2x2") }
                .tag(AppTab.dashboard)
            ProgramsTab()
                .tabItem { Label("Programs", systemImage: "list.bullet.rectangle") }
                .tag(AppTab.programs)
            PlaceholderTab(title: "Workouts", identifier: "tab.workouts.title")
                .tabItem { Label("Workouts", systemImage: "figure.strengthtraining.traditional") }
                .tag(AppTab.workouts)
            ProfilePlaceholderTab()
                .tabItem { Label("Profile", systemImage: "person.crop.circle") }
                .tag(AppTab.profile)
        }
    }
}

private struct DashboardTab: View {
    @State private var path: [AppRoute] = []

    var body: some View {
        NavigationStack(path: $path) {
            DashboardView(path: $path)
                .navigationDestination(for: AppRoute.self) { RouteDestinationView(route: $0) }
                .environment(\.navigationPath, $path)
        }
    }
}

private struct PlaceholderTab: View {
    let title: LocalizedStringKey
    let identifier: String
    @State private var path: [AppRoute] = []

    var body: some View {
        NavigationStack(path: $path) {
            VStack(spacing: 12) {
                Text(title)
                    .font(.largeTitle.bold())
                    .accessibilityIdentifier(identifier)
                Text("Coming soon").foregroundStyle(.tertiary)
            }
            .navigationTitle(title)
            .navigationDestination(for: AppRoute.self) { RouteDestinationView(route: $0) }
        }
    }
}

private struct ProfilePlaceholderTab: View {
    @Environment(AppSession.self) private var session

    var body: some View {
        NavigationStack {
            VStack(spacing: 16) {
                if let user = session.user {
                    Text(verbatim: user.name ?? user.email)
                        .font(.title2.bold())
                        .accessibilityIdentifier("tab.profile.title")
                    Text(verbatim: user.email).foregroundStyle(.secondary)
                }
                Button("Log out", role: .destructive) { Task { await session.signOut() } }
                    .accessibilityIdentifier("main.logout")
                Spacer()
                RootFooter()
            }
            .padding()
            .navigationTitle("Profile")
        }
    }
}
