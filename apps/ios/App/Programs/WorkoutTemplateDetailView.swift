import SwiftUI
import WorkoutsCore

/// Read-only view of a template's activities and planned sets, with Start Workout.
struct WorkoutTemplateDetailView: View {
    @Environment(\.dependencies) private var dependencies
    @Environment(Toaster.self) private var toaster
    @Binding var path: [AppRoute]
    let templateId: Int
    @State private var model: WorkoutTemplateViewModel?

    var body: some View {
        Group {
            if let model {
                content(model)
            } else {
                LoadingStateView()
            }
        }
        .navigationTitle(model?.state.value?.name ?? "")
        .navigationBarTitleDisplayMode(.inline)
        .task {
            guard model == nil, let dependencies else { return }
            let model = WorkoutTemplateViewModel(
                templates: dependencies.templateRepository, workouts: dependencies.workoutRepository,
                templateId: templateId)
            self.model = model
            await model.load()
        }
    }

    @ViewBuilder
    private func content(_ model: WorkoutTemplateViewModel) -> some View {
        switch model.state {
        case .idle, .loading:
            LoadingStateView()
        case .failed(let error):
            ErrorStateView(error: error) { Task { await model.load() } }
        case .loaded(let template):
            ScrollView {
                VStack(alignment: .leading, spacing: 20) {
                    if let error = model.startError {
                        FormErrorBanner(general: .failed(message: error.userMessage))
                    }
                    if let description = template.description {
                        Text(verbatim: description).font(.subheadline).foregroundStyle(.secondary)
                    }
                    Button {
                        Task {
                            if let workout = await model.start() {
                                toaster.show(String(localized: "Workout started"))
                                path.append(.workoutEdit(workout.id))
                            }
                        }
                    } label: {
                        HStack {
                            if model.isStarting { ProgressView().tint(.white) }
                            Text("Start Workout").frame(maxWidth: .infinity)
                        }
                        .padding(.vertical, 6)
                    }
                    .buttonStyle(.borderedProminent)
                    .disabled(model.isStarting)
                    .accessibilityIdentifier("template.start")
                    SectionHeader(title: "Activities", subtitle: nil)
                    if template.activities.isEmpty {
                        EmptyStateView(systemImage: "list.bullet", title: "No activities yet")
                    }
                    ForEach(template.activities) { activity in
                        ActivitySummaryCard(activity: activity)
                    }
                }
                .padding()
            }
        }
    }
}

/// An activity with its planned sets, read-only. The editor (story 9) has the editable twin.
struct ActivitySummaryCard: View {
    let activity: Activity

    var body: some View {
        Card {
            VStack(alignment: .leading, spacing: 8) {
                Text(verbatim: activity.exerciseName ?? String(localized: "Workout")).font(.headline)
                HStack(spacing: 8) {
                    if let equipment = activity.equipmentName {
                        Text(verbatim: equipment)
                    }
                    if let rest = activity.restTimeSeconds {
                        Text("Rest: \(rest) s")
                    }
                }
                .font(.footnote)
                .foregroundStyle(.secondary)
                if !activity.categoryNames.isEmpty {
                    Text(verbatim: activity.categoryNames.joined(separator: " · "))
                        .font(.caption)
                        .foregroundStyle(.tertiary)
                }
                Grid(alignment: .leading, horizontalSpacing: 16, verticalSpacing: 4) {
                    GridRow {
                        Text(verbatim: "#")
                        if activity.difficultyUnit?.hasValue ?? false {
                            Text(verbatim: activity.difficultyLabel ?? "")
                        }
                        Text(verbatim: activity.effortLabel)
                    }
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(.secondary)
                    ForEach(activity.sets) { set in
                        GridRow {
                            Text(verbatim: "\(set.order)")
                            if activity.difficultyUnit?.hasValue ?? false {
                                Text(verbatim: set.difficultyValue.map { Self.format($0) } ?? "—")
                            }
                            Text(verbatim: "\(set.effortValue)")
                        }
                        .font(.subheadline.monospacedDigit())
                    }
                }
            }
        }
    }

    static func format(_ value: Double) -> String {
        value.formatted(.number.precision(.fractionLength(0...1)))
    }
}
