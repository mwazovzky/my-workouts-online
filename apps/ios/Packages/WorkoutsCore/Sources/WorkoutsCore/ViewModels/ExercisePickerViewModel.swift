import Foundation
import Observation
import WorkoutsAPI

/// The catalog list behind "Add activity": loaded once, searched by name on the device.
@MainActor
@Observable
public final class ExercisePickerViewModel {
    public private(set) var state: LoadState<[Exercise]> = .idle
    public var searchText = ""

    private let exercises: any ExerciseRepository

    public init(exercises: any ExerciseRepository) {
        self.exercises = exercises
    }

    public var results: [Exercise] {
        let all = state.value ?? []
        let query = searchText.trimmingCharacters(in: .whitespaces)
        guard !query.isEmpty else { return all }
        return all.filter { $0.name.localizedCaseInsensitiveContains(query) }
    }

    public func loadIfNeeded() async {
        guard state.value == nil, !state.isLoading else { return }
        state = .loading
        do {
            state = .loaded(try await exercises.exercises())
        } catch {
            state = .failed(APIError.from(error))
        }
    }

    public func retry() async {
        state = .idle
        await loadIfNeeded()
    }
}
