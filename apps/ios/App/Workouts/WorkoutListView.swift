import SwiftUI
import WorkoutsCore

struct WorkoutsTab: View {
    @State private var path: [AppRoute] = []

    var body: some View {
        NavigationStack(path: $path) {
            WorkoutListView(path: $path)
                .navigationDestination(for: AppRoute.self) { RouteDestinationView(route: $0) }
        }
        .environment(\.navigationPath, $path)
    }
}

/// Paginated history, newest first, with swipe to delete.
struct WorkoutListView: View {
    @Environment(\.dependencies) private var dependencies
    @Binding var path: [AppRoute]
    @State private var model: WorkoutListViewModel?
    @State private var pendingDelete: Workout?

    var body: some View {
        Group {
            if let model {
                content(model)
            } else {
                LoadingStateView()
            }
        }
        .navigationTitle("Workouts")
        .task {
            guard model == nil, let dependencies else { return }
            let model = WorkoutListViewModel(workouts: dependencies.workoutRepository)
            self.model = model
            await model.load()
        }
        .onChange(of: path) { _, newPath in
            if newPath.isEmpty, let model { Task { await model.load() } }
        }
        .confirmationDialog(
            "Delete workout?",
            isPresented: Binding(get: { pendingDelete != nil }, set: { if !$0 { pendingDelete = nil } }),
            titleVisibility: .visible
        ) {
            Button("Delete", role: .destructive) {
                guard let pendingDelete, let model else { return }
                self.pendingDelete = nil
                Task { await model.delete(id: pendingDelete.id) }
            }
            .accessibilityIdentifier("workouts.delete.confirm")
            Button("Cancel", role: .cancel) { pendingDelete = nil }
        } message: {
            Text("This action cannot be undone.")
        }
    }

    @ViewBuilder
    private func content(_ model: WorkoutListViewModel) -> some View {
        switch model.state {
        case .idle, .loading:
            LoadingStateView()
        case .failed(let error):
            ErrorStateView(error: error) { Task { await model.load() } }
        case .loaded(let workouts):
            if workouts.isEmpty {
                ScrollView {
                    EmptyStateView(
                        systemImage: "figure.run", title: "No workouts yet",
                        message: "Start a workout to see it listed here"
                    )
                    .padding()
                }
                .refreshable { await model.load() }
            } else {
                List {
                    if let error = model.deleteError ?? model.loadMoreError {
                        FormErrorBanner(general: .failed(message: error.userMessage))
                            .listRowSeparator(.hidden)
                    }
                    ForEach(workouts) { workout in
                        Button {
                            path.append(.workout(workout.id))
                        } label: {
                            WorkoutRow(workout: workout)
                        }
                        .buttonStyle(.plain)
                        .listRowSeparator(.hidden)
                        .listRowInsets(EdgeInsets(top: 6, leading: 16, bottom: 6, trailing: 16))
                        .accessibilityIdentifier("workouts.row.\(workout.id)")
                        .swipeActions(edge: .trailing) {
                            Button(role: .destructive) {
                                pendingDelete = workout
                            } label: {
                                Label("Delete", systemImage: "trash")
                            }
                        }
                        .onAppear { Task { await model.loadMoreIfNeeded(currentItem: workout) } }
                    }
                    if model.isLoadingMore {
                        ProgressView()
                            .frame(maxWidth: .infinity)
                            .listRowSeparator(.hidden)
                            .accessibilityIdentifier("workouts.loadingMore")
                    }
                }
                .listStyle(.plain)
                .refreshable { await model.load() }
                .accessibilityIdentifier("workouts.list")
            }
        }
    }
}
