import SwiftUI
import WorkoutsCore

struct ProgramDetailView: View {
    @Environment(\.dependencies) private var dependencies
    let programId: Int
    @State private var model: ProgramDetailViewModel?

    var body: some View {
        Group {
            if let model {
                content(model)
            } else {
                LoadingStateView()
            }
        }
        .navigationTitle(model?.state.value?.program.name ?? "")
        .navigationBarTitleDisplayMode(.inline)
        .task {
            guard model == nil, let dependencies else { return }
            let model = ProgramDetailViewModel(programs: dependencies.programRepository, programId: programId)
            self.model = model
            await model.load()
        }
    }

    @ViewBuilder
    private func content(_ model: ProgramDetailViewModel) -> some View {
        switch model.state {
        case .idle, .loading:
            LoadingStateView()
        case .failed(let error):
            ErrorStateView(error: error) { Task { await model.load() } }
        case .loaded(let detail):
            ScrollView {
                VStack(alignment: .leading, spacing: 20) {
                    if let error = model.enrollError {
                        FormErrorBanner(general: .failed(message: error.userMessage))
                    }
                    Card {
                        VStack(alignment: .leading, spacing: 10) {
                            HStack {
                                Text(verbatim: detail.program.name).font(.title2.bold())
                                Spacer()
                                if detail.program.isEnrolled { EnrolledBadge() }
                            }
                            Text(verbatim: detail.program.description ?? String(localized: "No description"))
                                .font(.subheadline)
                                .foregroundStyle(.secondary)
                            if !detail.program.isEnrolled {
                                Button {
                                    Task { await model.enroll() }
                                } label: {
                                    if model.isEnrolling { ProgressView() } else { Text("Enroll") }
                                }
                                .buttonStyle(.borderedProminent)
                                .disabled(model.isEnrolling)
                                .accessibilityIdentifier("program.enroll")
                            }
                        }
                    }
                    SectionHeader(title: "Program Workouts", subtitle: nil)
                    if detail.workoutTemplates.isEmpty {
                        EmptyStateView(systemImage: "calendar", title: "No workouts yet")
                    } else {
                        ForEach(model.sections) { section in
                            VStack(alignment: .leading, spacing: 8) {
                                Group {
                                    if let weekday = section.weekday {
                                        Text(verbatim: weekday.displayName)
                                    } else {
                                        Text("Not scheduled")
                                    }
                                }
                                .font(.subheadline.weight(.semibold))
                                .foregroundStyle(.secondary)
                                ForEach(section.templates) { template in
                                    NavigationLink(value: AppRoute.workoutTemplate(template.id)) {
                                        TemplateRow(template: template)
                                    }
                                    .buttonStyle(.plain)
                                    .accessibilityIdentifier("template.\(template.id)")
                                }
                            }
                        }
                    }
                }
                .padding()
            }
            .refreshable { await model.load() }
        }
    }
}

struct TemplateRow: View {
    let template: WorkoutTemplate

    var body: some View {
        Card {
            HStack {
                VStack(alignment: .leading, spacing: 4) {
                    Text(verbatim: template.name).font(.headline)
                    Text("\(template.activities.count) activities")
                        .font(.footnote)
                        .foregroundStyle(.secondary)
                }
                Spacer()
                Image(systemName: "chevron.right").foregroundStyle(.tertiary)
            }
        }
    }
}
