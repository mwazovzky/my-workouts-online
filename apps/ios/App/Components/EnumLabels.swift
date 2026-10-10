import Foundation
import WorkoutsCore

// The API sends enum values only; display text for them lives in Localizable.xcstrings.

extension WorkoutStatus {
    var displayName: String {
        switch self {
        case .inProgress: String(localized: "In Progress")
        case .completed: String(localized: "Completed")
        }
    }
}

extension Weekday {
    var displayName: String {
        switch self {
        case .monday: String(localized: "Monday")
        case .tuesday: String(localized: "Tuesday")
        case .wednesday: String(localized: "Wednesday")
        case .thursday: String(localized: "Thursday")
        case .friday: String(localized: "Friday")
        case .saturday: String(localized: "Saturday")
        case .sunday: String(localized: "Sunday")
        }
    }
}

extension EffortType {
    /// Column header for a set's effort value.
    var displayName: String {
        switch self {
        case .repetitions: String(localized: "Reps")
        case .duration: String(localized: "Duration")
        }
    }
}

extension DifficultyUnit {
    /// Column header for a set's difficulty value; empty when the unit has no value.
    var displayName: String {
        switch self {
        case .kilograms: String(localized: "Weight (kg)")
        case .pounds: String(localized: "Weight (lbs)")
        case .plates: String(localized: "plates")
        case .heartRateZone: String(localized: "Heart-rate zone")
        case .none: ""
        }
    }
}
