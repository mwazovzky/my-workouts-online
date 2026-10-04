import SwiftUI
import WorkoutsCore

/// Read-only workout with Edit (own, in progress) and Repeat (own, completed).
struct WorkoutDetailView: View {
    @Environment(\.dependencies) private var dependencies
    @Environment(AppSession.self) private var session
    @Environment(Toaster.self) private var toaster
    @Environment(\.navigationPath) private var path
    let workoutId: Int
    @State private var model: WorkoutDetailViewModel?

    var body: some View {
        Group {
            if let model {
                content(model)
            } else {
                LoadingStateView()
            }
        }
        .navigationTitle(model?.workout?.name ?? "")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            if let model, model.canEdit {
                ToolbarItem(placement: .primaryAction) {
                    Button("Edit") { path.wrappedValue.append(.workoutEdit(workoutId)) }
                        .accessibilityIdentifier("detail.edit")
                }
            }
        }
        .task {
            guard model == nil, let dependencies, let user = session.user else { return }
            let model = WorkoutDetailViewModel(
                workouts: dependencies.workoutRepository, workoutId: workoutId, currentUserId: user.id)
            self.model = model
            await model.load()
        }
        .onChange(of: path.wrappedValue) { _, newPath in
            // Coming back from the editor: refresh so the sets reflect the saved state.
            if newPath.last == .workout(workoutId), let model { Task { await model.load() } }
        }
    }

    @ViewBuilder
    private func content(_ model: WorkoutDetailViewModel) -> some View {
        switch model.state {
        case .idle, .loading:
            LoadingStateView()
        case .failed(let error):
            ErrorStateView(error: error) { Task { await model.load() } }
        case .loaded(let workout):
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    if let error = model.actionError {
                        FormErrorBanner(general: .failed(message: error.userMessage))
                    }
                    HStack {
                        VStack(alignment: .leading, spacing: 4) {
                            if let date = workout.createdAt {
                                Text(date, format: .dateTime.day().month().year().hour().minute())
                                    .font(.footnote)
                                    .foregroundStyle(.secondary)
                            }
                            Text("\(workout.completedSetCount)/\(workout.totalSetCount) sets")
                                .font(.footnote)
                                .foregroundStyle(.secondary)
                        }
                        Spacer()
                        Text(verbatim: workout.statusLabel)
                            .font(.caption.weight(.medium))
                            .padding(.horizontal, 8)
                            .padding(.vertical, 4)
                            .background(
                                workout.isInProgress ? Color.orange.opacity(0.15) : Color.green.opacity(0.15),
                                in: Capsule()
                            )
                            .accessibilityIdentifier("detail.status")
                    }
                    if model.canRepeat {
                        Button {
                            Task {
                                if let repeated = await model.repeatWorkout() {
                                    toaster.show(String(localized: "Workout started"))
                                    path.wrappedValue.append(.workoutEdit(repeated.id))
                                }
                            }
                        } label: {
                            HStack {
                                if model.isRepeating { ProgressView().tint(.white) }
                                Text("Repeat workout").frame(maxWidth: .infinity)
                            }
                            .padding(.vertical, 6)
                        }
                        .buttonStyle(.borderedProminent)
                        .disabled(model.isRepeating)
                        .accessibilityIdentifier("detail.repeat")
                    }
                    if model.canEdit {
                        Button("Continue") { path.wrappedValue.append(.workoutEdit(workoutId)) }
                            .buttonStyle(.borderedProminent)
                            .accessibilityIdentifier("detail.continue")
                    }
                    SectionHeader(title: "Activities", subtitle: nil)
                    ForEach(workout.activities) { activity in
                        ActivitySummaryCard(activity: activity)
                    }
                }
                .padding()
            }
            .refreshable { await model.load() }
            .accessibilityIdentifier("detail")
        }
    }
}
