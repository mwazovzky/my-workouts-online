import Foundation
import Observation
import WorkoutsAPI

/// The Profile tab: account details, password, theme, deletion. Every successful update
/// adopts the user the server returned, so the rest of the app reflects it at once.
@MainActor
@Observable
public final class ProfileViewModel {
    public enum Notice: Sendable, Equatable {
        case profileSaved
        case passwordUpdated
        case verificationSent
        case themeSaved
    }

    public var name: String
    public var email: String
    public private(set) var profileErrors = FormErrors()
    public private(set) var isSavingProfile = false

    public var currentPassword = ""
    public var newPassword = ""
    public var newPasswordConfirmation = ""
    public private(set) var passwordErrors = FormErrors()
    public private(set) var isUpdatingPassword = false

    public private(set) var isSavingTheme = false
    public private(set) var themeError: APIError?

    public var deletePassword = ""
    public private(set) var deleteErrors = FormErrors()
    public private(set) var isDeleting = false

    public private(set) var isResending = false
    public private(set) var resendError: APIError?
    public private(set) var notice: Notice?

    private let me: any MeRepository
    private let session: AppSession

    public init(me: any MeRepository, session: AppSession) {
        self.me = me
        self.session = session
        name = session.user?.name ?? ""
        email = session.user?.email ?? ""
    }

    public var user: User? { session.user }
    public var theme: ThemePreference { session.user?.themePreference ?? .system }
    public var isProfileDirty: Bool {
        name.trimmingCharacters(in: .whitespaces) != (session.user?.name ?? "")
            || email.trimmingCharacters(in: .whitespaces) != (session.user?.email ?? "")
    }
    public var canSaveProfile: Bool { isProfileDirty && !name.isEmpty && !email.isEmpty && !isSavingProfile }
    public var canUpdatePassword: Bool {
        !currentPassword.isEmpty && !newPassword.isEmpty && !newPasswordConfirmation.isEmpty && !isUpdatingPassword
    }

    public func dismissNotice() {
        notice = nil
    }

    /// A changed email comes back unverified, so the session moves to the verify gate.
    public func saveProfile() async {
        guard canSaveProfile else { return }
        isSavingProfile = true
        profileErrors.clear()
        notice = nil
        defer { isSavingProfile = false }
        do {
            let user = try await me.updateProfile(
                name: name.trimmingCharacters(in: .whitespaces), email: email.trimmingCharacters(in: .whitespaces))
            session.apply(user: user)
            name = user.name ?? ""
            email = user.email
            notice = .profileSaved
        } catch {
            profileErrors.apply(error)
        }
    }

    public func updatePassword() async {
        guard canUpdatePassword else { return }
        isUpdatingPassword = true
        passwordErrors.clear()
        notice = nil
        defer { isUpdatingPassword = false }
        do {
            try await me.updatePassword(
                current: currentPassword, new: newPassword, confirmation: newPasswordConfirmation)
            currentPassword = ""
            newPassword = ""
            newPasswordConfirmation = ""
            notice = .passwordUpdated
        } catch {
            passwordErrors.apply(error)
        }
    }

    public func setTheme(_ theme: ThemePreference) async {
        guard theme != self.theme, !isSavingTheme else { return }
        isSavingTheme = true
        themeError = nil
        defer { isSavingTheme = false }
        do {
            session.apply(user: try await me.updateTheme(theme))
            notice = .themeSaved
        } catch {
            themeError = APIError.from(error)
        }
    }

    public func resendVerification() async {
        guard !isResending else { return }
        isResending = true
        resendError = nil
        defer { isResending = false }
        do {
            try await me.resendVerification()
            notice = .verificationSent
        } catch {
            resendError = APIError.from(error)
        }
    }

    /// Returns true when the account is gone; the session is then signed out locally.
    public func deleteAccount() async -> Bool {
        guard !deletePassword.isEmpty, !isDeleting else { return false }
        isDeleting = true
        deleteErrors.clear()
        defer { isDeleting = false }
        do {
            try await me.deleteProfile(password: deletePassword)
            deletePassword = ""
            await session.signOut(reason: .accountDeleted)
            return true
        } catch {
            deleteErrors.apply(error)
            return false
        }
    }

    public func signOut() async {
        await session.signOut()
    }
}

/// Keeps the profile's stored locale in step with the language the app actually runs in,
/// so the web shows the same language. Runs on launch and when the app becomes active.
@MainActor
public struct LocaleSync {
    private let me: any MeRepository
    private let session: AppSession

    public init(me: any MeRepository, session: AppSession) {
        self.me = me
        self.session = session
    }

    /// - Parameter resolved: the API locale resolved from the device language.
    /// - Returns: true when the server was updated.
    @discardableResult
    public func syncIfNeeded(resolved: String) async -> Bool {
        guard let user = session.user, user.locale != resolved else { return false }
        guard let updated = try? await me.updateLocale(resolved) else { return false }
        session.apply(user: updated)
        return true
    }
}
