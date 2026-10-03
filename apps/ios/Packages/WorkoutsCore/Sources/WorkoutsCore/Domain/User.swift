import Foundation

public struct User: Sendable, Equatable, Identifiable, Codable {
    public let id: Int
    public var name: String?
    public var email: String
    /// The stored profile preference, not necessarily the language the app is showing.
    public var locale: String
    public var themePreference: ThemePreference
    public let isAdmin: Bool
    public var emailVerified: Bool

    public init(
        id: Int, name: String?, email: String, locale: String, themePreference: ThemePreference,
        isAdmin: Bool, emailVerified: Bool
    ) {
        self.id = id
        self.name = name
        self.email = email
        self.locale = locale
        self.themePreference = themePreference
        self.isAdmin = isAdmin
        self.emailVerified = emailVerified
    }
}

/// What login, register and refresh return. The refresh token is present for native clients.
public struct AuthGrant: Sendable, Equatable {
    public let accessToken: String
    public let expiresIn: TimeInterval
    public let refreshToken: String?
    public let user: User

    public init(accessToken: String, expiresIn: TimeInterval, refreshToken: String?, user: User) {
        self.accessToken = accessToken
        self.expiresIn = expiresIn
        self.refreshToken = refreshToken
        self.user = user
    }
}

/// Public metadata from `GET /meta`.
public struct Meta: Sendable, Equatable {
    public struct AvailableLocale: Sendable, Equatable, Identifiable {
        public let code: String
        public let name: String
        public var id: String { code }

        public init(code: String, name: String) {
            self.code = code
            self.name = name
        }
    }

    public let availableLocales: [AvailableLocale]
    public let themePreferences: [ThemePreference]

    public init(availableLocales: [AvailableLocale], themePreferences: [ThemePreference]) {
        self.availableLocales = availableLocales
        self.themePreferences = themePreferences
    }
}
