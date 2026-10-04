import Foundation

/// Caches the signed-in user's theme so the pre-login screens already use it next launch.
public protocol ThemeMemory: Sendable {
    func rememberedTheme() -> ThemePreference?
    func remember(_ theme: ThemePreference)
}

// UserDefaults is thread-safe; the compiler just cannot see that.
public struct UserDefaultsThemeMemory: ThemeMemory, @unchecked Sendable {
    private let key = "theme.preference"
    private let defaults: UserDefaults

    public init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
    }

    public func rememberedTheme() -> ThemePreference? {
        defaults.string(forKey: key).flatMap(ThemePreference.init(rawValue:))
    }

    public func remember(_ theme: ThemePreference) {
        defaults.set(theme.rawValue, forKey: key)
    }
}
