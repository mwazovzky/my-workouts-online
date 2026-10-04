import Foundation
import WorkoutsAPI

/// The lifecycle of one screen's data, so every view renders loading, empty, error and
/// loaded states the same way.
public enum LoadState<Value: Sendable & Equatable>: Sendable, Equatable {
    case idle
    case loading
    case loaded(Value)
    case failed(APIError)

    public var value: Value? {
        if case .loaded(let value) = self { return value }
        return nil
    }

    public var error: APIError? {
        if case .failed(let error) = self { return error }
        return nil
    }

    public var isLoading: Bool { self == .loading }
}
