import SwiftUI
import WorkoutsCore

/// "Add activity": search the catalog and pick an exercise.
struct ExercisePickerView: View {
    @Environment(\.dependencies) private var dependencies
    @Environment(\.dismiss) private var dismiss
    let onPick: (Exercise) -> Void
    @State private var model: ExercisePickerViewModel?

    var body: some View {
        NavigationStack {
            Group {
                if let model {
                    content(model)
                } else {
                    LoadingStateView()
                }
            }
            .navigationTitle("Add activity")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") { dismiss() }.accessibilityIdentifier("picker.cancel")
                }
            }
            .task {
                guard model == nil, let dependencies else { return }
                let model = ExercisePickerViewModel(exercises: dependencies.exerciseRepository)
                self.model = model
                await model.loadIfNeeded()
            }
        }
    }

    @ViewBuilder
    private func content(_ model: ExercisePickerViewModel) -> some View {
        @Bindable var model = model
        switch model.state {
        case .idle, .loading:
            LoadingStateView()
        case .failed(let error):
            ErrorStateView(error: error) { Task { await model.retry() } }
        case .loaded:
            List {
                if model.results.isEmpty {
                    EmptyStateView(systemImage: "magnifyingglass", title: "No exercises found")
                } else {
                    ForEach(model.results) { exercise in
                        Button {
                            onPick(exercise)
                            dismiss()
                        } label: {
                            VStack(alignment: .leading, spacing: 2) {
                                Text(verbatim: exercise.name).font(.body)
                                HStack(spacing: 6) {
                                    if let equipment = exercise.equipmentName { Text(verbatim: equipment) }
                                    Text(verbatim: exercise.categories.map(\.name).joined(separator: " · "))
                                }
                                .font(.caption)
                                .foregroundStyle(.secondary)
                                if let instructions = exercise.instructions {
                                    Text(verbatim: instructions)
                                        .font(.caption)
                                        .foregroundStyle(.tertiary)
                                        .lineLimit(2)
                                }
                            }
                        }
                        .accessibilityIdentifier("picker.exercise.\(exercise.id)")
                    }
                }
            }
            .listStyle(.plain)
            .searchable(text: $model.searchText, prompt: Text("Search exercises…"))
            .accessibilityIdentifier("picker.list")
        }
    }
}

/// Drag-to-reorder the activities; orders are renumbered on save.
struct ReorderActivitiesView: View {
    @Environment(\.dismiss) private var dismiss
    let model: WorkoutEditorModel

    var body: some View {
        NavigationStack {
            List {
                ForEach(model.draft?.activities ?? []) { activity in
                    Text(verbatim: activity.exerciseName)
                }
                .onMove { source, destination in
                    model.moveActivities(fromOffsets: source, toOffset: destination)
                }
            }
            .environment(\.editMode, .constant(.active))
            .navigationTitle("Reorder activities")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    Button("Done") { dismiss() }.accessibilityIdentifier("reorder.done")
                }
            }
        }
    }
}
