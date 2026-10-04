import SwiftUI
import WorkoutsCore

struct ProgramsTab: View {
    @State private var path: [AppRoute] = []

    var body: some View {
        NavigationStack(path: $path) {
            ProgramListView(path: $path)
                .navigationDestination(for: AppRoute.self) { RouteDestinationView(route: $0) }
                .environment(\.navigationPath, $path)
        }
    }
}

struct ProgramListView: View {
    @Environment(\.dependencies) private var dependencies
    @Binding var path: [AppRoute]
    @State private var model: ProgramListViewModel?

    var body: some View {
        Group {
            if let model {
                content(model)
            } else {
                LoadingStateView()
            }
        }
        .navigationTitle("Programs")
        .task {
            guard model == nil, let dependencies else { return }
            let model = ProgramListViewModel(programs: dependencies.programRepository)
            self.model = model
            await model.load()
        }
        .onChange(of: path) { _, newPath in
            if newPath.isEmpty, let model { Task { await model.load() } }
        }
    }

    @ViewBuilder
    private func content(_ model: ProgramListViewModel) -> some View {
        @Bindable var model = model
        switch model.state {
        case .idle, .loading:
            LoadingStateView()
        case .failed(let error):
            ErrorStateView(error: error) { Task { await model.load() } }
        case .loaded(let all):
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    if let error = model.enrollError {
                        FormErrorBanner(general: .failed(message: error.userMessage))
                    }
                    Toggle("Enrolled programs only", isOn: $model.enrolledOnly)
                        .font(.subheadline)
                        .accessibilityIdentifier("programs.enrolledOnly")
                    if all.isEmpty {
                        EmptyStateView(systemImage: "list.bullet.rectangle", title: "No programs found")
                    } else if model.visiblePrograms.isEmpty {
                        EmptyStateView(
                            systemImage: "line.3.horizontal.decrease.circle",
                            title: "There are no programs matching your filter")
                    } else {
                        ForEach(model.visiblePrograms) { program in
                            ProgramCard(program: program, isEnrolling: model.enrollingId == program.id) {
                                path.append(.program(program.id))
                            } enroll: {
                                Task { await model.enroll(programId: program.id) }
                            }
                        }
                    }
                }
                .padding()
            }
            .refreshable { await model.load() }
            .accessibilityIdentifier("programs.list")
        }
    }
}

struct ProgramCard: View {
    let program: Program
    let isEnrolling: Bool
    let open: () -> Void
    let enroll: () -> Void

    var body: some View {
        Card {
            VStack(alignment: .leading, spacing: 10) {
                Button(action: open) {
                    VStack(alignment: .leading, spacing: 4) {
                        HStack {
                            Text(verbatim: program.name).font(.headline)
                            Spacer()
                            if program.isEnrolled {
                                EnrolledBadge()
                            }
                        }
                        Text(verbatim: program.description ?? String(localized: "No description"))
                            .font(.subheadline)
                            .foregroundStyle(.secondary)
                            .lineLimit(3)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                    .contentShape(Rectangle())
                }
                .buttonStyle(.plain)
                .accessibilityIdentifier("program.\(program.id)")
                HStack {
                    Button("Show", action: open).buttonStyle(.bordered)
                    if !program.isEnrolled {
                        Button(action: enroll) {
                            if isEnrolling { ProgressView() } else { Text("Enroll") }
                        }
                        .buttonStyle(.borderedProminent)
                        .disabled(isEnrolling)
                        .accessibilityIdentifier("program.\(program.id).enroll")
                    }
                }
            }
        }
    }
}

struct EnrolledBadge: View {
    var body: some View {
        Text("Enrolled")
            .font(.caption.weight(.medium))
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(Color.green.opacity(0.15), in: Capsule())
            .accessibilityIdentifier("badge.enrolled")
    }
}
