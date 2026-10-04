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
    let route: AppRoute

    var body: some View {
        switch route {
        case .program(let id):
            ComingSoonView(title: "Program", detail: "#\(id)")
        case .workoutTemplate(let id):
            ComingSoonView(title: "Open template", detail: "#\(id)")
        case .workout(let id):
            ComingSoonView(title: "Workout", detail: "#\(id)")
        case .workoutEdit(let id):
            ComingSoonView(title: "Workout", detail: "#\(id)")
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
