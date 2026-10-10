import Foundation

/// How a set's effort is counted; from `Exercise.effort_type`.
public enum EffortType: String, Sendable, Codable, CaseIterable {
    case repetitions
    case duration
}

/// How a set's difficulty is measured; from `Equipment.difficulty_unit`.
public enum DifficultyUnit: String, Sendable, Codable, CaseIterable {
    case kilograms
    case pounds
    case plates
    case heartRateZone = "heart_rate_zone"
    case none

    /// Whether the difficulty field is shown at all.
    public var hasValue: Bool { self != .none }
}

public enum WorkoutStatus: String, Sendable, Codable, CaseIterable {
    case inProgress = "in_progress"
    case completed
}

public enum ThemePreference: String, Sendable, Codable, CaseIterable {
    case light
    case dark
    case system
}

public enum Weekday: String, Sendable, Codable, CaseIterable {
    case monday
    case tuesday
    case wednesday
    case thursday
    case friday
    case saturday
    case sunday
}
