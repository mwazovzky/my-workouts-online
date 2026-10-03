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
    public let effortLabel: String
    public let difficultyUnit: DifficultyUnit
    public let difficultyLabel: String?
    public let restTimeSeconds: Int?
    public let categories: [Category]

    public init(
        id: Int, name: String, description: String?, equipmentId: Int, equipmentName: String?,
        effortType: EffortType, effortLabel: String, difficultyUnit: DifficultyUnit, difficultyLabel: String?,
        restTimeSeconds: Int?, categories: [Category]
    ) {
        self.id = id
        self.name = name
        self.description = description
        self.equipmentId = equipmentId
        self.equipmentName = equipmentName
        self.effortType = effortType
        self.effortLabel = effortLabel
        self.difficultyUnit = difficultyUnit
        self.difficultyLabel = difficultyLabel
        self.restTimeSeconds = restTimeSeconds
        self.categories = categories
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
    public let weekdayLabel: String

    public init(weekday: Weekday, weekdayLabel: String) {
        self.weekday = weekday
        self.weekdayLabel = weekdayLabel
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
