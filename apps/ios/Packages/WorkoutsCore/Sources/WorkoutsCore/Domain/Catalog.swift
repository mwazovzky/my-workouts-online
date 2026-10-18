import Foundation

public struct Category: Sendable, Equatable, Identifiable, Hashable {
    public let id: Int
    public let name: String

    public init(id: Int, name: String) {
        self.id = id
        self.name = name
    }
}

public struct Exercise: Sendable, Equatable, Identifiable {
    public let id: Int
    public let name: String
    public let description: String?
    public let equipmentId: Int
    public let equipmentName: String?
    public let effortType: EffortType
    public let difficultyUnit: DifficultyUnit
    public let restTimeSeconds: Int?
    public let categories: [Category]

    public init(
        id: Int, name: String, description: String?, equipmentId: Int, equipmentName: String?,
        effortType: EffortType, difficultyUnit: DifficultyUnit,
        restTimeSeconds: Int?, categories: [Category]
    ) {
        self.id = id
        self.name = name
        self.description = description
        self.equipmentId = equipmentId
        self.equipmentName = equipmentName
        self.effortType = effortType
        self.difficultyUnit = difficultyUnit
        self.restTimeSeconds = restTimeSeconds
        self.categories = categories
    }
}

extension Exercise {
    /// The description to show, or nil when it is missing or blank.
    public var instructions: String? { description.nonBlank }
}

extension String? {
    /// The string trimmed, or nil when that leaves nothing.
    var nonBlank: String? {
        guard let trimmed = self?.trimmingCharacters(in: .whitespacesAndNewlines), !trimmed.isEmpty else { return nil }
        return trimmed
    }
}

public struct Program: Sendable, Equatable, Identifiable {
    public let id: Int
    public let name: String
    public let description: String?
    public var isEnrolled: Bool

    public init(id: Int, name: String, description: String?, isEnrolled: Bool) {
        self.id = id
        self.name = name
        self.description = description
        self.isEnrolled = isEnrolled
    }
}

public struct ProgramDetail: Sendable, Equatable {
    public var program: Program
    public let workoutTemplates: [WorkoutTemplate]

    public init(program: Program, workoutTemplates: [WorkoutTemplate]) {
        self.program = program
        self.workoutTemplates = workoutTemplates
    }
}

/// A template's place in a program's week; present only when the template came from a program.
public struct ScheduleSlot: Sendable, Equatable {
    public let weekday: Weekday

    public init(weekday: Weekday) {
        self.weekday = weekday
    }
}

public struct WorkoutTemplate: Sendable, Equatable, Identifiable {
    public let id: Int
    public let name: String
    public let description: String?
    public let activities: [Activity]
    public let schedule: ScheduleSlot?

    public init(id: Int, name: String, description: String?, activities: [Activity], schedule: ScheduleSlot?) {
        self.id = id
        self.name = name
        self.description = description
        self.activities = activities
        self.schedule = schedule
    }
}
