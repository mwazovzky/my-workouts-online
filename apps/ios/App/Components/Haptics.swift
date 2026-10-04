import UIKit

/// Light tactile confirmation for the moments that matter in a gym: a set done, a workout done.
enum Haptics {
    @MainActor
    static func setCompleted() {
        UIImpactFeedbackGenerator(style: .medium).impactOccurred()
    }

    @MainActor
    static func workoutCompleted() {
        UINotificationFeedbackGenerator().notificationOccurred(.success)
    }

    @MainActor
    static func warning() {
        UINotificationFeedbackGenerator().notificationOccurred(.warning)
    }
}
