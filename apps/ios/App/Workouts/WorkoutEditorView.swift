import SwiftUI
import WorkoutsAPI
import WorkoutsCore

/// Edits an in-progress workout: sets per activity with the two-axis rules, a progress bar
/// and a bulk Save. A completed or foreign workout renders read-only.
struct WorkoutEditorView: View {
    @Environment(\.dependencies) private var dependencies
    @Environment(AppSession.self) private var session
    @Environment(Toaster.self) private var toaster
    let workoutId: Int
    @State private var model: WorkoutEditorModel?

    var body: some View {
        Group {
            if let model {
                content(model)
            } else {
                LoadingStateView()
            }
        }
        .navigationTitle(model?.draft?.name ?? "")
        .navigationBarTitleDisplayMode(.inline)
        .toolbar {
            if let model, model.isEditable {
                ToolbarItem(placement: .confirmationAction) {
                    Button {
                        Task {
                            if await model.save() { toaster.show(String(localized: "Workout saved")) }
                        }
                    } label: {
                        if model.isSaving { ProgressView() } else { Text("Save") }
                    }
                    .disabled(!model.isDirty || model.isSaving)
                    .accessibilityIdentifier("editor.save")
                }
            }
        }
        .task {
            guard model == nil, let dependencies, let user = session.user else { return }
            let model = WorkoutEditorModel(
                workouts: dependencies.workoutRepository, workoutId: workoutId, currentUserId: user.id)
            self.model = model
            await model.load()
        }
    }

    @ViewBuilder
    private func content(_ model: WorkoutEditorModel) -> some View {
        switch model.state {
        case .idle, .loading:
            LoadingStateView()
        case .failed(let error):
            ErrorStateView(error: error) { Task { await model.load() } }
        case .loaded(let draft):
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    if let error = model.saveError, model.fieldErrors.isEmpty {
                        FormErrorBanner(general: .failed(message: error.userMessage))
                    }
                    ProgressHeader(completed: draft.completedSetCount, total: draft.totalSetCount)
                    ForEach(Array(draft.activities.enumerated()), id: \.element.id) { activityIndex, activity in
                        ActivityEditorCard(model: model, activity: activity, activityIndex: activityIndex)
                    }
                }
                .padding()
            }
            .scrollDismissesKeyboard(.interactively)
            .accessibilityIdentifier("editor")
        }
    }
}

struct ProgressHeader: View {
    let completed: Int
    let total: Int

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text("\(completed)/\(total) sets")
                .font(.subheadline.weight(.medium))
                .accessibilityIdentifier("editor.progress")
            ProgressView(value: total == 0 ? 0 : Double(completed) / Double(total))
        }
    }
}

struct ActivityEditorCard: View {
    let model: WorkoutEditorModel
    let activity: DraftActivity
    let activityIndex: Int

    var body: some View {
        Card {
            VStack(alignment: .leading, spacing: 10) {
                VStack(alignment: .leading, spacing: 4) {
                    Text(verbatim: activity.exerciseName).font(.headline)
                    HStack(spacing: 8) {
                        if let equipment = activity.equipmentName { Text(verbatim: equipment) }
                        if let rest = activity.restTimeSeconds { Text("Rest: \(rest) s") }
                    }
                    .font(.footnote)
                    .foregroundStyle(.secondary)
                    if !activity.categoryNames.isEmpty {
                        Text(verbatim: activity.categoryNames.joined(separator: " · ")).font(.caption).foregroundStyle(
                            .tertiary)
                    }
                }
                Grid(alignment: .leading, horizontalSpacing: 12, verticalSpacing: 8) {
                    GridRow {
                        Text(verbatim: "#")
                        if activity.hasDifficulty { Text(verbatim: activity.difficultyLabel ?? "") }
                        Text(verbatim: activity.effortLabel)
                        Text("Done")
                    }
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(.secondary)
                    ForEach(Array(activity.sets.enumerated()), id: \.element.id) { setIndex, set in
                        SetRowView(
                            model: model, activity: activity, set: set, activityIndex: activityIndex, setIndex: setIndex
                        )
                    }
                }
                if model.isEditable {
                    Button {
                        model.addSet(to: activity.localId)
                    } label: {
                        Label("Add set", systemImage: "plus")
                    }
                    .buttonStyle(.bordered)
                    .controlSize(.small)
                    .accessibilityIdentifier("activity.\(activityIndex).addSet")
                }
            }
        }
    }
}

/// One set: order, difficulty (hidden for `none`, a 1–5 picker for zones, decimal otherwise),
/// effort, and the done toggle. Completed sets are locked.
struct SetRowView: View {
    let model: WorkoutEditorModel
    let activity: DraftActivity
    let set: DraftSet
    let activityIndex: Int
    let setIndex: Int

    private var locked: Bool { !model.isEditable || set.isCompleted }

    var body: some View {
        GridRow {
            Text(verbatim: "\(setIndex + 1)")
                .font(.subheadline.monospacedDigit())
                .foregroundStyle(.secondary)
            if activity.hasDifficulty {
                difficultyField
            }
            effortField
            Toggle(
                "",
                isOn: Binding(
                    get: { set.isCompleted },
                    set: { model.setCompleted($0, activity: activity.localId, set: set.localId) })
            )
            .labelsHidden()
            .disabled(!model.isEditable || !set.canComplete)
            .accessibilityIdentifier("set.\(activityIndex).\(setIndex).done")
        }
        .opacity(set.isCompleted ? 0.7 : 1)
        if let message = model.fieldError(activity: activityIndex, set: setIndex, field: "effort_value")
            ?? model.fieldError(activity: activityIndex, set: setIndex, field: "difficulty_value")
        {
            GridRow {
                Text(verbatim: message)
                    .font(.caption)
                    .foregroundStyle(.red)
                    .gridCellColumns(activity.hasDifficulty ? 4 : 3)
            }
        }
    }

    @ViewBuilder
    private var difficultyField: some View {
        if activity.isZone {
            Picker(
                "",
                selection: Binding(
                    get: { set.difficultyValue.map { Int($0) } ?? 0 },
                    set: { model.setZone($0 == 0 ? nil : $0, activity: activity.localId, set: set.localId) })
            ) {
                Text(verbatim: "—").tag(0)
                ForEach(1...5, id: \.self) { zone in Text(verbatim: "\(zone)").tag(zone) }
            }
            .labelsHidden()
            .pickerStyle(.menu)
            .disabled(locked)
            .accessibilityIdentifier("set.\(activityIndex).\(setIndex).zone")
        } else {
            TextField(
                "",
                value: Binding(
                    get: { set.difficultyValue },
                    set: { model.setDifficulty($0, activity: activity.localId, set: set.localId) }),
                format: .number.precision(.fractionLength(0...1))
            )
            .keyboardType(.decimalPad)
            .textFieldStyle(.roundedBorder)
            .frame(width: 80)
            .disabled(locked)
            .accessibilityIdentifier("set.\(activityIndex).\(setIndex).difficulty")
        }
    }

    private var effortField: some View {
        TextField(
            "",
            value: Binding(
                get: { set.effortValue },
                set: { model.setEffort($0, activity: activity.localId, set: set.localId) }),
            format: .number
        )
        .keyboardType(.numberPad)
        .textFieldStyle(.roundedBorder)
        .frame(width: 72)
        .disabled(locked)
        .accessibilityIdentifier("set.\(activityIndex).\(setIndex).effort")
    }
}
