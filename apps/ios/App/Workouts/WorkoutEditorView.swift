import SwiftUI
import WorkoutsAPI
import WorkoutsCore

/// Edits an in-progress workout: sets per activity with the two-axis rules, a progress bar
/// and a bulk Save. A completed or foreign workout renders read-only.
struct WorkoutEditorView: View {
    @Environment(\.dependencies) private var dependencies
    @Environment(AppSession.self) private var session
    @Environment(Toaster.self) private var toaster
    @Environment(\.navigationPath) private var path
    @Environment(\.scenePhase) private var scenePhase
    @Environment(\.dismiss) private var dismiss
    let workoutId: Int
    @State private var model: WorkoutEditorModel?
    @State private var showsPicker = false
    @State private var showsReorder = false
    @State private var confirmsComplete = false
    @State private var confirmsLeave = false
    @State private var pendingDeletion: PendingDeletion?

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
        .navigationBarBackButtonHidden(model?.isDirty == true)
        .toolbar {
            if let model, model.isDirty {
                ToolbarItem(placement: .cancellationAction) {
                    Button {
                        confirmsLeave = true
                    } label: {
                        Label("Back", systemImage: "chevron.backward")
                    }
                    .accessibilityIdentifier("editor.back")
                }
            }
            if let model, model.isEditable {
                ToolbarItem(placement: .primaryAction) {
                    Menu {
                        Button {
                            showsPicker = true
                        } label: {
                            Label("Add activity", systemImage: "plus")
                        }
                        .accessibilityIdentifier("editor.addActivity")
                        Button {
                            showsReorder = true
                        } label: {
                            Label("Reorder activities", systemImage: "arrow.up.arrow.down")
                        }
                        .disabled((model.draft?.activities.count ?? 0) < 2)
                        Divider()
                        Button {
                            confirmsComplete = true
                        } label: {
                            Label("Complete", systemImage: "checkmark.circle")
                        }
                        .accessibilityIdentifier("editor.complete")
                    } label: {
                        Image(systemName: "ellipsis.circle")
                    }
                    .accessibilityIdentifier("editor.menu")
                }
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
        .sheet(isPresented: $showsPicker) {
            ExercisePickerView { exercise in model?.addActivity(exercise: exercise) }
        }
        .sheet(isPresented: $showsReorder) {
            if let model { ReorderActivitiesView(model: model) }
        }
        .confirmationDialog("Complete", isPresented: $confirmsComplete, titleVisibility: .visible) {
            Button("Complete") {
                Task {
                    guard let model, let completed = await model.complete() else { return }
                    toaster.show(String(localized: "Workout completed"))
                    var newPath = path.wrappedValue
                    if !newPath.isEmpty { newPath.removeLast() }
                    newPath.append(.workout(completed.id))
                    path.wrappedValue = newPath
                }
            }
            .accessibilityIdentifier("editor.complete.confirm")
            Button("Cancel", role: .cancel) {}
        } message: {
            Text("This action cannot be undone.")
        }
        .confirmationDialog(
            "You have unsaved changes. Leave anyway?", isPresented: $confirmsLeave, titleVisibility: .visible
        ) {
            Button("Leave", role: .destructive) {
                Task {
                    await model?.persistNow()
                    dismiss()
                }
            }
            Button("Stay", role: .cancel) {}
        }
        .confirmationDialog(
            pendingDeletion?.deletesActivity == true ? "Delete activity?" : "Remove set?",
            isPresented: Binding(get: { pendingDeletion != nil }, set: { if !$0 { pendingDeletion = nil } }),
            titleVisibility: .visible
        ) {
            Button(pendingDeletion?.deletesActivity == true ? "Delete activity" : "Remove", role: .destructive) {
                guard let pendingDeletion, let model else { return }
                if let set = pendingDeletion.set {
                    model.removeSet(activity: pendingDeletion.activity, set: set)
                } else {
                    model.deleteActivity(pendingDeletion.activity)
                }
                self.pendingDeletion = nil
            }
            .accessibilityIdentifier("editor.delete.confirm")
            Button("Cancel", role: .cancel) { pendingDeletion = nil }
        } message: {
            if pendingDeletion?.set != nil, pendingDeletion?.deletesActivity == true {
                Text("Removing the last set will delete this activity.")
            } else {
                Text("This action cannot be undone.")
            }
        }
        .onChange(of: scenePhase) { _, phase in
            if phase == .background { Task { await model?.persistNow() } }
        }
        .task {
            guard model == nil, let dependencies, let user = session.user else { return }
            let model = WorkoutEditorModel(
                workouts: dependencies.workoutRepository, workoutId: workoutId, currentUserId: user.id,
                drafts: dependencies.draftStore)
            self.model = model
            await model.load()
        }
    }

    /// Asks before removing an activity or an activity's last set; other set removals are immediate.
    private func requestDeletion(activity: UUID, set: UUID?, model: WorkoutEditorModel) {
        let deletesActivity = set == nil || model.removingSetWouldDeleteActivity(activity)
        if deletesActivity, !model.canDeleteActivity(activity) {
            toaster.show(String(localized: "A workout must have at least one activity."))
            return
        }
        if let set, !deletesActivity {
            model.removeSet(activity: activity, set: set)
            return
        }
        pendingDeletion = PendingDeletion(activity: activity, set: set, deletesActivity: deletesActivity)
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
                    if let error = model.completeError, model.saveError == nil {
                        FormErrorBanner(general: .failed(message: error.userMessage))
                    }
                    if model.restoredDraft {
                        NoticeBanner(text: "Unsaved changes restored", actionTitle: "Discard") {
                            Task { await model.discardRestoredDraft() }
                        }
                        .accessibilityIdentifier("editor.restored")
                    }
                    if model.staleDraftDiscarded {
                        NoticeBanner(
                            text: "This workout was changed elsewhere; your unsaved edits were discarded.",
                            actionTitle: "OK"
                        ) {
                            model.dismissStaleDraftNotice()
                        }
                    }
                    ProgressHeader(completed: draft.completedSetCount, total: draft.totalSetCount)
                    ForEach(Array(draft.activities.enumerated()), id: \.element.id) { activityIndex, activity in
                        ActivityEditorCard(model: model, activity: activity, activityIndex: activityIndex) { set in
                            requestDeletion(activity: activity.localId, set: set, model: model)
                        }
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

struct PendingDeletion {
    let activity: UUID
    let set: UUID?
    let deletesActivity: Bool
}

struct NoticeBanner: View {
    let text: LocalizedStringKey
    let actionTitle: LocalizedStringKey
    let action: () -> Void

    var body: some View {
        HStack {
            Text(text).font(.footnote)
            Spacer()
            Button(actionTitle, action: action).font(.footnote.weight(.semibold))
        }
        .padding(12)
        .background(.blue.opacity(0.08), in: RoundedRectangle(cornerRadius: 8))
    }
}

struct ActivityEditorCard: View {
    let model: WorkoutEditorModel
    let activity: DraftActivity
    let activityIndex: Int
    /// `nil` means delete the whole activity.
    let requestDeletion: (UUID?) -> Void

    var body: some View {
        Card {
            VStack(alignment: .leading, spacing: 10) {
                VStack(alignment: .leading, spacing: 4) {
                    HStack {
                        Text(verbatim: activity.exerciseName).font(.headline)
                        Spacer()
                        if model.isEditable {
                            Button(role: .destructive) {
                                requestDeletion(nil)
                            } label: {
                                Image(systemName: "trash")
                            }
                            .buttonStyle(.borderless)
                            .accessibilityLabel(Text("Delete activity"))
                            .accessibilityIdentifier("activity.\(activityIndex).delete")
                        }
                    }
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
                        if model.isEditable { Text(verbatim: "") }
                    }
                    .font(.caption.weight(.semibold))
                    .foregroundStyle(.secondary)
                    ForEach(Array(activity.sets.enumerated()), id: \.element.id) { setIndex, set in
                        SetRowView(
                            model: model, activity: activity, set: set, activityIndex: activityIndex, setIndex: setIndex
                        ) {
                            requestDeletion(set.localId)
                        }
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
    let remove: () -> Void

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
            if model.isEditable {
                Button(role: .destructive, action: remove) { Image(systemName: "xmark.circle") }
                    .buttonStyle(.borderless)
                    .accessibilityLabel(Text("Remove"))
                    .accessibilityIdentifier("set.\(activityIndex).\(setIndex).remove")
            }
        }
        .opacity(set.isCompleted ? 0.7 : 1)
        if let message = model.fieldError(activity: activityIndex, set: setIndex, field: "effort_value")
            ?? model.fieldError(activity: activityIndex, set: setIndex, field: "difficulty_value")
        {
            GridRow {
                Text(verbatim: message)
                    .font(.caption)
                    .foregroundStyle(.red)
                    .gridCellColumns((activity.hasDifficulty ? 4 : 3) + (model.isEditable ? 1 : 0))
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
