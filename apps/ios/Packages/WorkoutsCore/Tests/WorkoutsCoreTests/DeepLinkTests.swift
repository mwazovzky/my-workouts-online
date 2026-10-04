import Foundation
import Testing
import WorkoutsAPI

@testable import WorkoutsCore

@Suite("DeepLinkParser")
struct DeepLinkParserTests {
    @Test("parses a universal verification link")
    func verifyUniversal() throws {
        let url = try #require(
            URL(string: "https://my-workouts.online/verify-email/12/abc123?expires=1790000000&signature=sig"))
        #expect(
            DeepLinkParser.parse(url) == .verifyEmail(id: 12, hash: "abc123", expires: 1_790_000_000, signature: "sig"))
    }

    @Test("parses the custom-scheme forms used in the simulator")
    func customScheme() throws {
        let verify = try #require(URL(string: "myworkouts://verify-email/12/abc123?expires=1790000000&signature=sig"))
        #expect(
            DeepLinkParser.parse(verify)
                == .verifyEmail(id: 12, hash: "abc123", expires: 1_790_000_000, signature: "sig"))
        let reset = try #require(URL(string: "myworkouts://reset-password/tok?email=test%40example.com"))
        #expect(DeepLinkParser.parse(reset) == .resetPassword(token: "tok", email: "test@example.com"))
    }

    @Test("parses a universal reset link with an encoded email")
    func resetUniversal() throws {
        let url = try #require(URL(string: "https://my-workouts.online/reset-password/tok?email=a%2Bb%40example.com"))
        #expect(DeepLinkParser.parse(url) == .resetPassword(token: "tok", email: "a+b@example.com"))
    }

    @Test("rejects foreign paths and incomplete links")
    func rejects() throws {
        for raw in [
            "https://my-workouts.online/programs/3",
            "https://my-workouts.online/verify-email/12/abc123",
            "https://my-workouts.online/verify-email/notanumber/abc?expires=1&signature=s",
            "https://my-workouts.online/verify-email/12?expires=1&signature=s",
            "https://my-workouts.online/reset-password/tok",
            "https://my-workouts.online/reset-password/?email=a%40b.c",
            "https://my-workouts.online/",
        ] {
            #expect(DeepLinkParser.parse(try #require(URL(string: raw))) == nil, "\(raw)")
        }
    }
}

@Suite("DeepLinkCoordinator")
@MainActor
struct DeepLinkCoordinatorTests {
    private let verifyURL = URL(string: "https://my-workouts.online/verify-email/2/h?expires=1&signature=s")!

    private func make(user: User?) async -> (DeepLinkCoordinator, AppSession, FakeMeRepository) {
        let me = FakeMeRepository()
        let session = AppSession(
            authSession: AuthSession(store: InMemoryTokenStore(), refresher: FakeRefresher()), meRepository: me)
        if let user {
            await me.set(user: user)
            session.apply(user: user)
        }
        return (DeepLinkCoordinator(session: session, me: me), session, me)
    }

    @Test("a link for the signed-in unverified user verifies and moves the session to ready")
    func verifiesSignedInUser() async throws {
        let (coordinator, session, me) = await make(user: .unverified)

        #expect(coordinator.handle(verifyURL))
        try await waitUntil { coordinator.notice == .verified }

        #expect(await me.verifyCalls.map(\.id) == [2])
        #expect(session.state.isReady)
        #expect(coordinator.pendingVerification == nil)
    }

    @Test("a link opened while signed out waits for login, then verifies")
    func pendingAcrossLogin() async throws {
        let (coordinator, session, me) = await make(user: nil)

        coordinator.handle(verifyURL)
        try await waitUntil { coordinator.notice == .signInToVerify }
        #expect(await me.verifyCalls.isEmpty)

        await me.set(user: .unverified)
        session.apply(user: .unverified)
        await coordinator.sessionStateChanged()

        #expect(coordinator.notice == .verified)
        #expect(await me.verifyCalls.count == 1)
        #expect(session.state.isReady)
    }

    @Test("a link for another account is explained and kept until sign-out")
    func anotherAccount() async throws {
        let (coordinator, session, me) = await make(user: .fixture)  // id 1, link is for id 2

        coordinator.handle(verifyURL)
        try await waitUntil { coordinator.notice == .linkForAnotherAccount }
        #expect(await me.verifyCalls.isEmpty)

        await coordinator.signOutToSwitchAccount()
        #expect(session.state == .signedOut(reason: .userInitiated))
        #expect(coordinator.notice == .signInToVerify)
        #expect(coordinator.pendingVerification != nil)
    }

    @Test("an invalid link reports the server message and is dropped")
    func invalidLink() async throws {
        let (coordinator, _, me) = await make(user: .unverified)
        await me.set(verifyError: .api(status: 403, code: "invalid_signature", message: "Invalid signature."))

        coordinator.handle(verifyURL)
        try await waitUntil { coordinator.notice == .invalidLink(message: "Invalid signature.") }
        #expect(coordinator.pendingVerification == nil)
    }

    @Test("a reset link opens the sheet and a successful reset signs out with a notice")
    func resetFlow() async throws {
        let (coordinator, session, _) = await make(user: .fixture)
        let url = try #require(URL(string: "myworkouts://reset-password/tok?email=test%40example.com"))

        #expect(coordinator.handle(url))
        #expect(coordinator.resetLink == ResetPasswordLink(token: "tok", email: "test@example.com"))

        await coordinator.passwordWasReset()
        #expect(coordinator.resetLink == nil)
        #expect(session.state == .signedOut(reason: .passwordReset))
        #expect(coordinator.notice == .passwordReset)
    }

    @Test("unknown URLs are ignored")
    func unknown() async throws {
        let (coordinator, _, _) = await make(user: nil)
        #expect(!coordinator.handle(try #require(URL(string: "https://my-workouts.online/programs/1"))))
        #expect(coordinator.notice == nil)
    }
}

@Suite("ResetPasswordViewModel")
@MainActor
struct ResetPasswordViewModelTests {
    @Test("submits the token with the trimmed email and reports success")
    func success() async throws {
        let auth = FakeAuthRepository()
        let model = ResetPasswordViewModel(auth: auth, link: ResetPasswordLink(token: "tok", email: "test@example.com"))
        model.password = "newpassword1"
        model.passwordConfirmation = "newpassword1"

        #expect(await model.submit())
        #expect(await auth.resetCalls.first?.token == "tok")
    }

    @Test("an expired token shows the API message")
    func expiredToken() async {
        let auth = FakeAuthRepository()
        await auth.setReset(
            .validation(
                message: "Invalid.",
                fields: ["email": [FieldError(code: "invalid", message: "This password reset token is invalid.")]]))
        let model = ResetPasswordViewModel(auth: auth, link: ResetPasswordLink(token: "tok", email: "test@example.com"))
        model.password = "x"
        model.passwordConfirmation = "x"

        #expect(await model.submit() == false)
        #expect(model.errors["email"] == "This password reset token is invalid.")
    }
}

extension AppSession.State {
    var isReady: Bool {
        if case .ready = self { return true }
        return false
    }
}

/// Polls a main-actor condition; the coordinator starts its work in a detached Task.
@MainActor
func waitUntil(timeout: Duration = .seconds(2), _ condition: @MainActor () -> Bool) async throws {
    let deadline = ContinuousClock.now + timeout
    while !condition() {
        if ContinuousClock.now > deadline { throw TimeoutError() }
        try await Task.sleep(for: .milliseconds(10))
    }
}

struct TimeoutError: Error {}
