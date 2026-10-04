import SwiftUI
import WorkoutsAPI
import WorkoutsCore

struct DashboardView: View {
    @Environment(\.dependencies) private var dependencies
    @Environment(Toaster.self) private var toaster
    @Binding var path: [AppRoute]
    @State private var model: DashboardViewModel?

    var body: some View {
        Group {
            if let model {
                content(model)
            } else {
                LoadingStateView()
            }
        }
        .navigationTitle("Dashboard")
        .task {
            guard model == nil, let dependencies else { return }
            let model = DashboardViewModel(
                dashboards: dependencies.dashboardRepository, workouts: dependencies.workoutRepository)
            self.model = model
            await model.load()
        }
        .onChange(of: path) { _, newPath in
            if newPath.isEmpty, let model { Task { await model.load() } }
        }
    }

    @ViewBuilder
    private func content(_ model: DashboardViewModel) -> some View {
        switch model.state {
        case .idle, .loading:
            LoadingStateView()
        case .failed(let error):
            ErrorStateView(error: error) { Task { await model.load() } }
        case .loaded(let dashboard):
            ScrollView {
                VStack(alignment: .leading, spacing: 24) {
                    if let error = model.startError {
                        FormErrorBanner(general: .failed(message: error.userMessage))
                    }
                    if let workout = dashboard.inProgressWorkout {
                        inProgressCard(workout)
                    }
                    summary(dashboard.summary)
                    schedule(dashboard.upcomingSchedule, model: model)
                    recent(dashboard.recentWorkouts)
                }
                .padding()
            }
            .refreshable { await model.load() }
            .accessibilityIdentifier("dashboard")
        }
    }

    private func inProgressCard(_ workout: Workout) -> some View {
        Card {
            VStack(alignment: .leading, spacing: 8) {
                Text("Resume unfinished workout").font(.headline)
                Text(verbatim: workout.name).font(.subheadline)
                Text("Continue where you left off before starting something new.")
                    .font(.footnote)
                    .foregroundStyle(.secondary)
                Button("Continue") { path.append(.workoutEdit(workout.id)) }
                    .buttonStyle(.borderedProminent)
                    .accessibilityIdentifier("dashboard.continue")
            }
        }
    }

    private func summary(_ summary: DashboardSummary) -> some View {
        LazyVGrid(columns: [GridItem(.flexible()), GridItem(.flexible())], spacing: 12) {
            StatTile(title: "Programs enrolled", value: summary.enrolledProgramsCount, identifier: "summary.enrolled")
            StatTile(
                title: "Completed workouts", value: summary.completedWorkoutsCount, identifier: "summary.completed")
            StatTile(
                title: "Completed in last 7 days", value: summary.completedLast7DaysCount, identifier: "summary.recent")
            StatTile(title: "Upcoming workouts", value: summary.upcomingWorkoutsCount, identifier: "summary.upcoming")
        }
    }

    private func schedule(_ schedule: [ScheduledWorkout], model: DashboardViewModel) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            SectionHeader(title: "Coming up", subtitle: "Next 7 days")
            if schedule.isEmpty {
                EmptyStateView(
                    systemImage: "calendar", title: "No workouts scheduled for the next 7 days",
                    message: "Enroll in a program to build your upcoming schedule.")
            } else {
                ForEach(schedule) { slot in
                    Card {
                        HStack(alignment: .top) {
                            VStack(alignment: .leading, spacing: 4) {
                                Text(verbatim: slot.workoutName ?? String(localized: "Workout")).font(.headline)
                                Text(
                                    verbatim:
                                        "\(slot.weekdayLabel) · \(slot.scheduledAt.formatted(date: .abbreviated, time: .omitted))"
                                )
                                .font(.footnote)
                                .foregroundStyle(.secondary)
                                if let program = slot.programName {
                                    Text(verbatim: program).font(.footnote).foregroundStyle(.tertiary)
                                }
                            }
                            Spacer()
                            Button {
                                Task {
                                    if let workout = await model.startWorkout(templateId: slot.workoutTemplateId) {
                                        toaster.show(String(localized: "Workout started"))
                                        path.append(.workoutEdit(workout.id))
                                    }
                                }
                            } label: {
                                if model.startingTemplateId == slot.workoutTemplateId {
                                    ProgressView()
                                } else {
                                    Text("Start Workout")
                                }
                            }
                            .buttonStyle(.bordered)
                            .disabled(model.startingTemplateId != nil)
                            .accessibilityIdentifier("schedule.start.\(slot.workoutTemplateId)")
                        }
                    }
                }
            }
        }
    }

    private func recent(_ workouts: [Workout]) -> some View {
        VStack(alignment: .leading, spacing: 12) {
            SectionHeader(title: "Recent workouts", subtitle: nil)
            if workouts.isEmpty {
                EmptyStateView(
                    systemImage: "figure.run", title: "No workouts yet",
                    message: "Start a workout to see it listed here")
            } else {
                ForEach(workouts) { workout in
                    Button {
                        path.append(.workout(workout.id))
                    } label: {
                        WorkoutRow(workout: workout)
                    }
                    .buttonStyle(.plain)
                }
            }
        }
    }
}

struct Card<Content: View>: View {
    @ViewBuilder let content: Content

    var body: some View {
        content
            .frame(maxWidth: .infinity, alignment: .leading)
            .padding(16)
            .background(Color(.secondarySystemBackground), in: RoundedRectangle(cornerRadius: 12))
    }
}

struct SectionHeader: View {
    let title: LocalizedStringKey
    let subtitle: LocalizedStringKey?

    var body: some View {
        VStack(alignment: .leading, spacing: 2) {
            Text(title).font(.title3.bold())
            if let subtitle {
                Text(subtitle).font(.footnote).foregroundStyle(.secondary)
            }
        }
    }
}

struct StatTile: View {
    let title: LocalizedStringKey
    let value: Int
    let identifier: String

    var body: some View {
        Card {
            VStack(alignment: .leading, spacing: 4) {
                Text(verbatim: "\(value)")
                    .font(.title.bold())
                    .accessibilityIdentifier(identifier)
                Text(title).font(.footnote).foregroundStyle(.secondary)
            }
        }
    }
}

/// One workout in a list: name, date, activity count and status.
struct WorkoutRow: View {
    let workout: Workout

    var body: some View {
        Card {
            HStack {
                VStack(alignment: .leading, spacing: 4) {
                    Text(verbatim: workout.name).font(.headline)
                    HStack(spacing: 4) {
                        if let date = workout.createdAt {
                            Text(date, format: .dateTime.day().month().year())
                            Text(verbatim: "·")
                        }
                        Text("\(workout.activitiesCount ?? workout.activities.count) activities")
                    }
                    .font(.footnote)
                    .foregroundStyle(.secondary)
                }
                Spacer()
                Text(verbatim: workout.statusLabel)
                    .font(.caption.weight(.medium))
                    .padding(.horizontal, 8)
                    .padding(.vertical, 4)
                    .background(
                        workout.isInProgress ? Color.orange.opacity(0.15) : Color.green.opacity(0.15), in: Capsule())
            }
        }
    }
}
