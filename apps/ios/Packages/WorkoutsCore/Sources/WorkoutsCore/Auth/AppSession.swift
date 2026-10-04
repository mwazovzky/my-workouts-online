import Foundation
import Observation
import WorkoutsAPI

/// What the UI observes: who is signed in and whether they may use the app. Drives the
/// root view the way the web app's router guards do (`guest`, `auth`, `verified`).
@MainActor
@Observable
public final class AppSession {
    public enum SignOutReason: Sendable, Equatable {
        case userInitiated
        /// The server revoked the refresh family (expired, reused, or revoked elsewhere).
        case sessionExpired
        case accountDeleted
        /// A password reset succeeded; the server already revoked every refresh family.
        case passwordReset
    }

    public enum State: Sendable, Equatable {
        case bootstrapping
        case signedOut(reason: SignOutReason?)
        /// Signed in but `email_verified` is false: only the verify gate and profile are allowed.
        case unverified(User)
        case ready(User)
    }

    public private(set) var state: State = .bootstrapping
    /// Set when bootstrap could not reach the API (offline); the stored session is kept.
    public private(set) var bootstrapError: APIError?

    private let authSession: AuthSession
    private let meRepository: any MeRepository

    public init(authSession: AuthSession, meRepository: any MeRepository) {
        self.authSession = authSession
        self.meRepository = meRepository
    }

    public var user: User? {
        switch state {
        case .unverified(let user), .ready(let user): user
        case .bootstrapping, .signedOut: nil
        }
    }

    public var isSignedIn: Bool { user != nil }

    /// Restores the session on launch: refresh if the access token is stale, then `GET /me`.
    /// Also installs the handler that signs the user out when a later refresh is rejected.
    public func bootstrap() async {
        await authSession.setRevocationHandler { [weak self] _ in
            Task { @MainActor [weak self] in self?.state = .signedOut(reason: .sessionExpired) }
        }
        bootstrapError = nil
        guard await authSession.hasSession else {
            state = .signedOut(reason: nil)
            return
        }
        do {
            if await !authSession.isAccessTokenFresh() {
                try await authSession.refresh()
            }
            apply(user: try await meRepository.me())
        } catch let failure as AuthSession.Failure {
            state = .signedOut(reason: failure == .noSession ? nil : .sessionExpired)
        } catch {
            let apiError = APIError.from(error)
            switch apiError {
            case .api(let status, _, _) where status == 401 || status == 403:
                await authSession.signOut(revoke: false)
                state = .signedOut(reason: .sessionExpired)
            default:
                bootstrapError = apiError
            }
        }
    }

    /// Stores the tokens from a login or register response and shows the user.
    public func apply(grant: AuthGrant) async throws {
        try await authSession.signIn(grant)
        apply(user: grant.user)
    }

    /// Re-fetches `/me`, e.g. after verifying the email or editing the profile.
    public func refreshUser() async throws {
        apply(user: try await meRepository.me())
    }

    /// Replaces the user with one the server already returned (profile updates).
    public func apply(user: User) {
        state = user.emailVerified ? .ready(user) : .unverified(user)
    }

    public func signOut(reason: SignOutReason = .userInitiated) async {
        await authSession.signOut(revoke: reason == .userInitiated)
        state = .signedOut(reason: reason)
    }
}
