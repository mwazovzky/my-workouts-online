import SwiftUI
import WorkoutsCore

/// Every pushable destination, shared by all tabs' navigation stacks.
enum AppRoute: Hashable {
    case program(Int)
    case workoutTemplate(Int)
    case workout(Int)
    case workoutEdit(Int)
}

/// Resolves a route to its screen. Stories 8–11 replace the placeholders.
struct RouteDestinationView: View {
    @Environment(\.navigationPath) private var path
    let route: AppRoute

    var body: some View {
        switch route {
        case .program(let id):
            ProgramDetailView(programId: id)
        case .workoutTemplate(let id):
            WorkoutTemplateDetailView(path: path, templateId: id)
        case .workout(let id):
            WorkoutDetailView(workoutId: id)
        case .workoutEdit(let id):
            WorkoutEditorView(workoutId: id)
        }
    }
}

struct ComingSoonView: View {
    let title: LocalizedStringKey
    let detail: String

    var body: some View {
        VStack(spacing: 12) {
            Text(title).font(.title2.bold())
            Text(verbatim: detail).foregroundStyle(.secondary)
            Text("Coming soon").font(.footnote).foregroundStyle(.tertiary)
        }
        .frame(maxWidth: .infinity, maxHeight: .infinity)
        .accessibilityIdentifier("route.placeholder")
    }
}

/// The owning tab's navigation path, so pushed screens can push further (e.g. Start Workout
/// from a template pushes the editor) without threading a binding through every view.
private struct NavigationPathKey: EnvironmentKey {
    static let defaultValue: Binding<[AppRoute]> = .constant([])
}

extension EnvironmentValues {
    var navigationPath: Binding<[AppRoute]> {
        get { self[NavigationPathKey.self] }
        set { self[NavigationPathKey.self] = newValue }
    }
}
