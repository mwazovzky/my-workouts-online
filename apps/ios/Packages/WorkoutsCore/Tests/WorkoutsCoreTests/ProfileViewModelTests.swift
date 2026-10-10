import Foundation
import Testing
import WorkoutsAPI

@testable import WorkoutsCore

@MainActor
private func make(user: User = .fixture) async -> (ProfileViewModel, AppSession, FakeMeRepository) {
    let me = FakeMeRepository()
    await me.set(user: user)
    let session = AppSession(
        authSession: AuthSession(store: InMemoryTokenStore(), refresher: FakeRefresher()), meRepository: me)
    session.apply(user: user)
    return (ProfileViewModel(me: me, session: session), session, me)
}

@Suite("ProfileViewModel")
@MainActor
struct ProfileViewModelTests {
    @Test("the form starts from the signed-in user and only saves when dirty")
    func initialState() async {
        let (model, _, _) = await make()
        #expect(model.name == "Test")
        #expect(model.email == "test@example.com")
        #expect(!model.canSaveProfile)
        model.name = "Tess"
        #expect(model.canSaveProfile)
    }

    @Test("saving a new name adopts the returned user; a new email lands on the verify gate")
    func saveProfile() async {
        let (model, session, me) = await make()
        model.name = " Tess "
        await model.saveProfile()
        #expect(session.user?.name == "Tess")
        #expect(session.state.isReady)
        #expect(model.notice == .profileSaved)
        #expect(await me.profileUpdates.last?.name == "Tess", "trimmed")

        model.email = "new@example.com"
        await model.saveProfile()
        guard case .unverified(let user) = session.state else {
            Issue.record("expected the verify gate, got \(session.state)")
            return
        }
        #expect(user.email == "new@example.com")
    }

    @Test("profile validation errors land on their fields")
    func saveProfileErrors() async {
        let (model, _, me) = await make()
        await me.set(
            updateError: .validation(
                message: "Invalid.", fields: ["email": [FieldError(code: "unique", message: "Taken.")]]))
        model.email = "taken@example.com"
        await model.saveProfile()
        #expect(model.profileErrors["email"] == "Taken.")
        #expect(model.email == "taken@example.com", "the form keeps the user's input")
    }

    @Test("a wrong current password shows under that field; success clears the fields")
    func password() async {
        let (model, _, me) = await make()
        model.currentPassword = "wrong"
        model.newPassword = "newpassword1"
        model.newPasswordConfirmation = "newpassword1"
        await me.set(
            updateError: .validation(
                message: "Invalid.",
                fields: [
                    "current_password": [FieldError(code: "current_password", message: "The password is incorrect.")]
                ]))
        await model.updatePassword()
        #expect(model.passwordErrors["current_password"] == "The password is incorrect.")

        await me.set(updateError: nil)
        model.currentPassword = "password"
        await model.updatePassword()
        #expect(model.notice == .passwordUpdated)
        #expect(model.currentPassword.isEmpty && model.newPassword.isEmpty)
    }

    @Test("theme changes apply to the session and ignore no-ops")
    func theme() async {
        let (model, session, me) = await make()
        await model.setTheme(.system)
        #expect(await me.themeUpdates.isEmpty, "already system")
        await model.setTheme(.dark)
        #expect(session.user?.themePreference == .dark)
        #expect(model.notice == .themeSaved)
    }

    @Test("delete with a wrong password shows the error; with the right one signs out as accountDeleted")
    func delete() async {
        let (model, session, me) = await make()
        await me.set(
            updateError: .validation(
                message: "Invalid.", fields: ["password": [FieldError(code: "current_password", message: "Wrong.")]]))
        model.deletePassword = "nope"
        #expect(await model.deleteAccount() == false)
        #expect(model.deleteErrors["password"] == "Wrong.")
        #expect(session.isSignedIn)

        await me.set(updateError: nil)
        model.deletePassword = "password"
        #expect(await model.deleteAccount())
        #expect(session.state == .signedOut(reason: .accountDeleted))
        #expect(await me.deletePasswords == ["nope", "password"])
    }

    @Test("resend for an unverified user reports sent; a 429 surfaces as the resend error")
    func resend() async {
        let (model, _, me) = await make(user: .unverified)
        await model.resendVerification()
        #expect(model.notice == .verificationSent)
        #expect(await me.resendCalls == 1)

        await me.set(resendError: .api(status: 429, code: "too_many_requests", message: "Slow down"))
        await model.resendVerification()
        #expect(model.resendError?.status == 429)
    }
}

@Suite("LocaleSync")
@MainActor
struct LocaleSyncTests {
    @Test("updates the server only when the resolved language differs, and adopts the result")
    func sync() async {
        let (_, session, me) = await make()  // locale "en"
        let sync = LocaleSync(me: me, session: session)

        #expect(await sync.syncIfNeeded(resolved: "en") == false)
        #expect(await me.localeUpdates.isEmpty)

        #expect(await sync.syncIfNeeded(resolved: "ru"))
        #expect(session.user?.locale == "ru")
        #expect(await me.localeUpdates == ["ru"])

        #expect(await sync.syncIfNeeded(resolved: "ru") == false, "now in step")
    }

    @Test("does nothing when signed out or when the server refuses")
    func noUserOrFailure() async {
        let me = FakeMeRepository()
        let session = AppSession(
            authSession: AuthSession(store: InMemoryTokenStore(), refresher: FakeRefresher()), meRepository: me)
        #expect(await LocaleSync(me: me, session: session).syncIfNeeded(resolved: "ru") == false)

        session.apply(user: .fixture)
        await me.set(updateError: .network(description: "offline"))
        #expect(await LocaleSync(me: me, session: session).syncIfNeeded(resolved: "ru") == false)
        #expect(session.user?.locale == "en")
    }
}
