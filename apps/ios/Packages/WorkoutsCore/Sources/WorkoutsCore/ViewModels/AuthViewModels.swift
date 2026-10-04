import Foundation
import Observation
import WorkoutsAPI

@MainActor
@Observable
public final class LoginViewModel {
    public var email: String
    public var password = ""
    public var rememberEmail: Bool
    public private(set) var errors = FormErrors()
    public private(set) var isSubmitting = false

    private let auth: any AuthRepository
    private let session: AppSession
    private let memory: any CredentialsMemory

    public init(auth: any AuthRepository, session: AppSession, memory: any CredentialsMemory) {
        self.auth = auth
        self.session = session
        self.memory = memory
        let remembered = memory.rememberedEmail()
        email = remembered ?? ""
        rememberEmail = remembered != nil
    }

    public var canSubmit: Bool { !email.isEmpty && !password.isEmpty && !isSubmitting }

    /// On success the session changes state and the root view leaves the login screen.
    public func submit() async {
        guard canSubmit else { return }
        isSubmitting = true
        errors.clear()
        defer { isSubmitting = false }
        do {
            let grant = try await auth.login(email: email.trimmingCharacters(in: .whitespaces), password: password)
            memory.remember(email: rememberEmail ? email : nil)
            try await session.apply(grant: grant)
            password = ""
        } catch {
            errors.apply(error)
        }
    }
}

@MainActor
@Observable
public final class RegisterViewModel {
    public var name = ""
    public var email = ""
    public var password = ""
    public var passwordConfirmation = ""
    public private(set) var errors = FormErrors()
    public private(set) var isSubmitting = false

    private let auth: any AuthRepository
    private let session: AppSession

    public init(auth: any AuthRepository, session: AppSession) {
        self.auth = auth
        self.session = session
    }

    public var canSubmit: Bool {
        !name.isEmpty && !email.isEmpty && !password.isEmpty && !passwordConfirmation.isEmpty && !isSubmitting
    }

    /// On success the user is signed in but unverified, so the root view shows the gate.
    public func submit() async {
        guard canSubmit else { return }
        isSubmitting = true
        errors.clear()
        defer { isSubmitting = false }
        do {
            let grant = try await auth.register(
                name: name.trimmingCharacters(in: .whitespaces),
                email: email.trimmingCharacters(in: .whitespaces),
                password: password,
                passwordConfirmation: passwordConfirmation)
            try await session.apply(grant: grant)
        } catch {
            errors.apply(error)
        }
    }
}

@MainActor
@Observable
public final class ForgotPasswordViewModel {
    public var email = ""
    public private(set) var errors = FormErrors()
    public private(set) var isSubmitting = false
    /// True after the API accepted the request; the message is deliberately neutral.
    public private(set) var wasSent = false

    private let auth: any AuthRepository

    public init(auth: any AuthRepository) {
        self.auth = auth
    }

    public var canSubmit: Bool { !email.isEmpty && !isSubmitting }

    public func submit() async {
        guard canSubmit else { return }
        isSubmitting = true
        errors.clear()
        defer { isSubmitting = false }
        do {
            try await auth.forgotPassword(email: email.trimmingCharacters(in: .whitespaces))
            wasSent = true
        } catch {
            errors.apply(error)
        }
    }
}

@MainActor
@Observable
public final class VerifyEmailViewModel {
    public enum Notice: Sendable, Equatable {
        case sent
        case stillUnverified
    }

    public private(set) var notice: Notice?
    public private(set) var errors = FormErrors()
    public private(set) var isBusy = false

    private let me: any MeRepository
    private let session: AppSession

    public init(me: any MeRepository, session: AppSession) {
        self.me = me
        self.session = session
    }

    public func resend() async {
        isBusy = true
        errors.clear()
        notice = nil
        defer { isBusy = false }
        do {
            switch try await me.resendVerification() {
            case .sent:
                notice = .sent
            case .alreadyVerified:
                try await session.refreshUser()
            }
        } catch {
            errors.apply(error)
        }
    }

    /// Re-fetches `/me`; if the email is verified the session moves to `.ready` on its own.
    public func checkVerification() async {
        isBusy = true
        errors.clear()
        notice = nil
        defer { isBusy = false }
        do {
            try await session.refreshUser()
            if case .unverified = session.state { notice = .stillUnverified }
        } catch {
            errors.apply(error)
        }
    }

    public func signOut() async {
        await session.signOut()
    }
}

@MainActor
@Observable
public final class ResetPasswordViewModel {
    public var email: String
    public var password = ""
    public var passwordConfirmation = ""
    public private(set) var errors = FormErrors()
    public private(set) var isSubmitting = false

    private let auth: any AuthRepository
    private let token: String

    public init(auth: any AuthRepository, link: ResetPasswordLink) {
        self.auth = auth
        token = link.token
        email = link.email
    }

    public var canSubmit: Bool { !email.isEmpty && !password.isEmpty && !passwordConfirmation.isEmpty && !isSubmitting }

    /// Returns true when the password was reset; the caller then signs the session out.
    public func submit() async -> Bool {
        guard canSubmit else { return false }
        isSubmitting = true
        errors.clear()
        defer { isSubmitting = false }
        do {
            try await auth.resetPassword(
                token: token, email: email.trimmingCharacters(in: .whitespaces), password: password,
                passwordConfirmation: passwordConfirmation)
            return true
        } catch {
            errors.apply(error)
            return false
        }
    }
}
