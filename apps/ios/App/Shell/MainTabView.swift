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
            WorkoutsTab()
                .tabItem { Label("Workouts", systemImage: "figure.strengthtraining.traditional") }
                .tag(AppTab.workouts)
            ProfileTab()
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
        }
        .environment(\.navigationPath, $path)
    }
}
