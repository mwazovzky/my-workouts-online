import Foundation
import Observation
import WorkoutsAPI

/// A reset link waiting to be shown as a sheet.
public struct ResetPasswordLink: Sendable, Equatable, Identifiable {
    public let token: String
    public let email: String
    public var id: String { token }

    public init(token: String, email: String) {
        self.token = token
        self.email = email
    }
}

/// Turns an opened URL into the right flow. Verification needs the link's user to be signed
/// in, so a link tapped while signed out is kept until login; a link for another account is
/// explained instead of silently failing.
@MainActor
@Observable
public final class DeepLinkCoordinator {
    public enum Notice: Sendable, Equatable {
        /// A verification link is waiting; shown on the welcome and login screens.
        case signInToVerify
        case verifying
        case verified
        case invalidLink(message: String?)
        /// The signed-in user is not the one the link was issued for.
        case linkForAnotherAccount
        /// Shown on the welcome screen after a successful reset signed the user out.
        case passwordReset
    }

    public private(set) var notice: Notice?
    public var resetLink: ResetPasswordLink?
    public private(set) var pendingVerification: DeepLink?

    private let session: AppSession
    private let me: any MeRepository

    public init(session: AppSession, me: any MeRepository) {
        self.session = session
        self.me = me
    }

    /// Returns false for URLs the app does not understand.
    @discardableResult
    public func handle(_ url: URL) -> Bool {
        guard let link = DeepLinkParser.parse(url) else { return false }
        switch link {
        case .verifyEmail:
            pendingVerification = link
            Task { await attemptVerification() }
        case .resetPassword(let token, let email):
            resetLink = ResetPasswordLink(token: token, email: email)
        }
        return true
    }

    /// Call when `AppSession.state` changes so a link kept across login completes.
    public func sessionStateChanged() async {
        guard pendingVerification != nil, session.isSignedIn else { return }
        await attemptVerification()
    }

    public func dismissNotice() {
        if notice != .verifying { notice = nil }
    }

    /// For the "another account" alert: sign out so the right account can log in; the link is kept.
    public func signOutToSwitchAccount() async {
        await session.signOut()
        notice = .signInToVerify
    }

    public func discardPendingVerification() {
        pendingVerification = nil
        if notice == .signInToVerify || notice == .linkForAnotherAccount { notice = nil }
    }

    /// The server revoked every refresh family on reset, so sign out locally without a logout call.
    public func passwordWasReset() async {
        resetLink = nil
        await session.signOut(reason: .passwordReset)
        notice = .passwordReset
    }

    private func attemptVerification() async {
        guard case .verifyEmail(let id, let hash, let expires, let signature)? = pendingVerification else { return }
        guard let user = session.user else {
            notice = .signInToVerify
            return
        }
        guard user.id == id else {
            notice = .linkForAnotherAccount
            return
        }
        notice = .verifying
        do {
            try await me.verifyEmail(id: id, hash: hash, expires: expires, signature: signature)
            try await session.refreshUser()
            pendingVerification = nil
            notice = .verified
        } catch {
            pendingVerification = nil
            if case .api(_, _, let message, _) = APIError.from(error) {
                notice = .invalidLink(message: message)
            } else {
                notice = .invalidLink(message: nil)
            }
        }
    }
}
