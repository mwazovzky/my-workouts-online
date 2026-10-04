import Foundation

/// Remembers only the email the user last logged in with, never the password.
public protocol CredentialsMemory: Sendable {
    func rememberedEmail() -> String?
    func remember(email: String?)
}

// UserDefaults is thread-safe; the compiler just cannot see that.
public struct UserDefaultsCredentialsMemory: CredentialsMemory, @unchecked Sendable {
    private let key = "auth.rememberedEmail"
    private let defaults: UserDefaults

    public init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
    }

    public func rememberedEmail() -> String? {
        defaults.string(forKey: key)
    }

    public func remember(email: String?) {
        if let email, !email.isEmpty {
            defaults.set(email, forKey: key)
        } else {
            defaults.removeObject(forKey: key)
        }
    }
}

public final class InMemoryCredentialsMemory: CredentialsMemory, @unchecked Sendable {
    private let lock = NSLock()
    private var email: String?

    public init(email: String? = nil) {
        self.email = email
    }

    public func rememberedEmail() -> String? { lock.withLock { email } }
    public func remember(email: String?) { lock.withLock { self.email = email } }
}
