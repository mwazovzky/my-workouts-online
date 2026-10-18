import SwiftUI

/// The info button beside an exercise name; it opens a sheet with how to perform the exercise.
/// Callers show it only when there are instructions.
struct ExerciseInfoButton: View {
    let name: String
    let instructions: String
    let equipmentName: String?
    let categoryNames: [String]
    let restTimeSeconds: Int?

    @State private var isPresented = false

    var body: some View {
        Button {
            isPresented = true
        } label: {
            Image(systemName: "info.circle")
        }
        .buttonStyle(.borderless)
        .accessibilityLabel(Text("How to do this exercise"))
        .sheet(isPresented: $isPresented) {
            ExerciseInfoSheet(
                name: name, instructions: instructions, equipmentName: equipmentName,
                categoryNames: categoryNames, restTimeSeconds: restTimeSeconds)
        }
    }
}

private struct ExerciseInfoSheet: View {
    let name: String
    let instructions: String
    let equipmentName: String?
    let categoryNames: [String]
    let restTimeSeconds: Int?

    @Environment(\.dismiss) private var dismiss

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 12) {
                    Text(verbatim: instructions)
                        .font(.body)
                        .accessibilityIdentifier("exerciseInfo.instructions")
                    VStack(alignment: .leading, spacing: 4) {
                        if let equipment = equipmentName { Text(verbatim: equipment) }
                        if !categoryNames.isEmpty { Text(verbatim: categoryNames.joined(separator: " · ")) }
                        if let rest = restTimeSeconds { Text("Rest: \(rest) s") }
                    }
                    .font(.footnote)
                    .foregroundStyle(.secondary)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding()
            }
            .navigationTitle(Text(verbatim: name))
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .confirmationAction) {
                    Button("Done") { dismiss() }.accessibilityIdentifier("exerciseInfo.done")
                }
            }
        }
        .presentationDetents([.medium, .large])
    }
}
