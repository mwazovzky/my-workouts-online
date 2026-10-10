import Foundation
import WorkoutsAPI

/// Per-field and general messages for a form, derived from an `APIError` the way the web
/// `useApiForm` composable does: the first message per field, everything else as one banner.
public struct FormErrors: Sendable, Equatable {
    public enum General: Sendable, Equatable {
        /// 429: the throttle message, with the `Retry-After` seconds when the API sent them.
        case tooManyAttempts(retryAfter: Int?)
        /// Any other failure, with the server's message when it sent one.
        case failed(message: String?)
        case offline
    }

    public private(set) var fields: [String: String] = [:]
    public private(set) var general: General?

    public init() {}

    public var isEmpty: Bool { fields.isEmpty && general == nil }

    public subscript(field: String) -> String? { fields[field] }

    public mutating func clear() {
        fields = [:]
        general = nil
    }

    public mutating func clear(field: String) {
        fields[field] = nil
    }

    /// A field error found on the device, before any request is sent.
    public mutating func set(field: String, message: String) {
        fields[field] = message
    }

    /// The API no longer receives a password confirmation, so forms compare the two fields
    /// themselves. Returns false (and records the error under `password_confirmation`) on a
    /// mismatch.
    public mutating func checkPasswordConfirmation(_ password: String, _ confirmation: String) -> Bool {
        guard password != confirmation else { return true }
        // Resolved against the app bundle's string catalog at runtime; the key in tests.
        set(
            field: "password_confirmation",
            message: String(localized: "The password confirmation does not match.", bundle: .main))
        return false
    }

    public mutating func apply(_ error: any Error) {
        clear()
        switch APIError.from(error) {
        case .validation(let message, let fieldErrors):
            for (field, errors) in fieldErrors {
                fields[field] = errors.first?.message
            }
            if fields.isEmpty { general = .failed(message: message) }
        case .api(let status, _, let message, let retryAfter):
            general = status == 429 ? .tooManyAttempts(retryAfter: retryAfter) : .failed(message: message)
        case .network:
            general = .offline
        case .unexpected, .decoding, .local:
            general = .failed(message: nil)
        }
    }
}
