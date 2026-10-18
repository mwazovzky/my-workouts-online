import Foundation
import Testing

@testable import WorkoutsCore

@Suite("Exercise instructions")
struct ExerciseInstructionsTests {
    private func activity(description: String?) -> Activity {
        Activity(
            id: 1, exerciseId: 7, exerciseName: "Squat", exerciseDescription: description, restTimeSeconds: 90,
            equipmentName: "Barbell", effortType: .repetitions, difficultyUnit: .kilograms, categoryNames: ["Legs"],
            sets: [])
    }

    @Test(
        "instructions are the trimmed description, nil when missing or blank",
        arguments: [
            (String?.none, String?.none), ("", nil), ("  \n", nil), ("  Sit back. ", "Sit back."),
        ])
    func instructions(description: String?, expected: String?) {
        #expect(activity(description: description).instructions == expected)
        #expect(DraftActivity(activity: activity(description: description)).instructions == expected)
    }

    @Test("a draft built from the picker carries the exercise description")
    func fromExercise() {
        let exercise = Exercise(
            id: 7, name: "Squat", description: "Sit back.", equipmentId: 1, equipmentName: "Barbell",
            effortType: .repetitions, difficultyUnit: .kilograms, restTimeSeconds: 90, categories: [])
        #expect(exercise.instructions == "Sit back.")
        #expect(DraftActivity(exercise: exercise).exerciseDescription == "Sit back.")
    }

    @Test("a stored draft keeps the description, and one stored before it existed decodes it as nil")
    func storedDraft() throws {
        let draft = DraftActivity(activity: activity(description: "Sit back."))
        let encoded = try JSONEncoder().encode(draft)
        #expect(try JSONDecoder().decode(DraftActivity.self, from: encoded) == draft)

        var json = try #require(try JSONSerialization.jsonObject(with: encoded) as? [String: Any])
        json["exerciseDescription"] = nil
        let legacy = try JSONDecoder().decode(
            DraftActivity.self, from: try JSONSerialization.data(withJSONObject: json))
        #expect(legacy.exerciseDescription == nil)
        #expect(legacy.exerciseName == "Squat")
    }
}
