import Foundation
import Testing
import WorkoutsAPI
import WorkoutsAPITestSupport

@testable import WorkoutsCore

/// Scripted auth and me repositories for view model tests.
actor FakeAuthRepository: AuthRepository {
    enum Script { case grant(AuthGrant), fail(APIError) }
    var loginScript: Script = .grant(.fixture)
    var registerScript: Script = .grant(.unverifiedFixture)
    var forgotScript: APIError?
    private(set) var loginCalls: [(email: String, password: String)] = []
    private(set) var forgotEmails: [String] = []

    func setLogin(_ script: Script) { loginScript = script }
    func setRegister(_ script: Script) { registerScript = script }
    func setForgot(_ error: APIError?) { forgotScript = error }

    func login(email: String, password: String) async throws -> AuthGrant {
        loginCalls.append((email, password))
        switch loginScript {
        case .grant(let grant): return grant
        case .fail(let error): throw error
        }
    }

    func register(name: String, email: String, password: String, passwordConfirmation: String) async throws -> AuthGrant
    {
        switch registerScript {
        case .grant(let grant): return grant
        case .fail(let error): throw error
        }
    }

    func forgotPassword(email: String) async throws {
        forgotEmails.append(email)
        if let forgotScript { throw forgotScript }
    }

    var resetError: APIError?
    private(set) var resetCalls: [(token: String, email: String)] = []
    func setReset(_ error: APIError?) { resetError = error }

    func resetPassword(token: String, email: String, password: String, passwordConfirmation: String) async throws {
        resetCalls.append((token, email))
        if let resetError { throw resetError }
    }

    func refresh(refreshToken: String) async throws -> AuthGrant { .fixture }
    func logout(accessToken: String) async throws {}
}

actor FakeMeRepository: MeRepository {
    var user: User = .fixture
    var resendResult: ResendVerificationResult = .sent
    var resendError: APIError?
    private(set) var resendCalls = 0
    private(set) var meCalls = 0

    func set(user: User) { self.user = user }
    func set(resend: ResendVerificationResult) { resendResult = resend }
    func set(resendError: APIError?) { self.resendError = resendError }

    func me() async throws -> User {
        meCalls += 1
        return user
    }

    func resendVerification() async throws -> ResendVerificationResult {
        resendCalls += 1
        if let resendError { throw resendError }
        return resendResult
    }

    var verifyError: APIError?
    private(set) var verifyCalls: [(id: Int, hash: String)] = []
    func set(verifyError: APIError?) { self.verifyError = verifyError }

    func verifyEmail(id: Int, hash: String, expires: Int, signature: String) async throws {
        verifyCalls.append((id, hash))
        if let verifyError { throw verifyError }
        user = User(
            id: user.id, name: user.name, email: user.email, locale: user.locale, themePreference: user.themePreference,
            isAdmin: user.isAdmin, emailVerified: true)
    }
}

extension AuthGrant {
    static let fixture = AuthGrant(accessToken: "access", expiresIn: 900, refreshToken: "refresh", user: .fixture)
    static let unverifiedFixture = AuthGrant(
        accessToken: "access", expiresIn: 900, refreshToken: "refresh", user: .unverified)
}

@MainActor
private func makeSession(me: FakeMeRepository = FakeMeRepository()) -> AppSession {
    AppSession(authSession: AuthSession(store: InMemoryTokenStore(), refresher: FakeRefresher()), meRepository: me)
}

private let lockoutError = APIError.validation(
    message: "Too many login attempts.",
    fields: [
        "email": [FieldError(code: "throttle", message: "Too many login attempts. Please try again in 60 seconds.")]
    ])
private let wrongPasswordError = APIError.validation(
    message: "Invalid.",
    fields: ["password": [FieldError(code: "invalid", message: "These credentials do not match our records.")]])

@Suite("LoginViewModel")
@MainActor
struct LoginViewModelTests {
    @Test("a successful login stores the grant, remembers the email and signs the session in")
    func success() async throws {
        let auth = FakeAuthRepository()
        let session = makeSession()
        let memory = InMemoryCredentialsMemory()
        let model = LoginViewModel(auth: auth, session: session, memory: memory)
        model.email = " test@example.com "
        model.password = "password"
        model.rememberEmail = true

        await model.submit()

        #expect(session.state == .ready(.fixture))
        #expect(memory.rememberedEmail() == " test@example.com ")
        #expect(await auth.loginCalls.first?.email == "test@example.com", "email is trimmed")
        #expect(model.password.isEmpty)
        #expect(model.errors.isEmpty)
    }

    @Test("a remembered email prefills the form")
    func prefill() {
        let model = LoginViewModel(
            auth: FakeAuthRepository(), session: makeSession(), memory: InMemoryCredentialsMemory(email: "a@b.c"))
        #expect(model.email == "a@b.c")
        #expect(model.rememberEmail)
    }

    @Test("a wrong password shows the API message under the password field")
    func wrongPassword() async throws {
        let auth = FakeAuthRepository()
        await auth.setLogin(.fail(wrongPasswordError))
        let session = makeSession()
        let model = LoginViewModel(auth: auth, session: session, memory: InMemoryCredentialsMemory())
        model.email = "test@example.com"
        model.password = "nope"

        await model.submit()

        #expect(model.errors["password"] == "These credentials do not match our records.")
        #expect(model.errors.general == nil)
        #expect(session.state == .bootstrapping, "session untouched")
    }

    @Test("the lockout arrives as a 422 on email")
    func lockout() async throws {
        let auth = FakeAuthRepository()
        await auth.setLogin(.fail(lockoutError))
        let model = LoginViewModel(auth: auth, session: makeSession(), memory: InMemoryCredentialsMemory())
        model.email = "test@example.com"
        model.password = "x"

        await model.submit()

        #expect(model.errors["email"]?.contains("Too many login attempts") == true)
    }

    @Test("a 429 and a network failure become general messages")
    func generalErrors() async throws {
        let auth = FakeAuthRepository()
        let model = LoginViewModel(auth: auth, session: makeSession(), memory: InMemoryCredentialsMemory())
        model.email = "test@example.com"
        model.password = "x"

        await auth.setLogin(.fail(.api(status: 429, code: "too_many_requests", message: "Slow down")))
        await model.submit()
        #expect(model.errors.general == .tooManyAttempts)

        await auth.setLogin(.fail(.network(description: "offline")))
        await model.submit()
        #expect(model.errors.general == .offline)
    }

    @Test("submit is a no-op with empty fields")
    func emptyFields() async throws {
        let auth = FakeAuthRepository()
        let model = LoginViewModel(auth: auth, session: makeSession(), memory: InMemoryCredentialsMemory())
        await model.submit()
        #expect(await auth.loginCalls.isEmpty)
        #expect(!model.canSubmit)
    }
}

@Suite("RegisterViewModel")
@MainActor
struct RegisterViewModelTests {
    private func filled(_ auth: FakeAuthRepository, session: AppSession) -> RegisterViewModel {
        let model = RegisterViewModel(auth: auth, session: session)
        model.name = "New"
        model.email = "new@example.com"
        model.password = "password123"
        model.passwordConfirmation = "password123"
        return model
    }

    @Test("a successful registration lands on the verify gate")
    func success() async {
        let session = makeSession()
        let model = filled(FakeAuthRepository(), session: session)
        await model.submit()
        #expect(session.state == .unverified(.unverified))
    }

    @Test("validation errors land on their fields")
    func validation() async {
        let auth = FakeAuthRepository()
        await auth.setRegister(
            .fail(
                .validation(
                    message: "Invalid.",
                    fields: [
                        "email": [FieldError(code: "unique", message: "The email has already been taken.")],
                        "password": [
                            FieldError(code: "confirmed", message: "The password confirmation does not match.")
                        ],
                    ])))
        let model = filled(auth, session: makeSession())
        await model.submit()
        #expect(model.errors["email"] == "The email has already been taken.")
        #expect(model.errors["password"] == "The password confirmation does not match.")
        #expect(model.errors["name"] == nil)
    }
}

@Suite("ForgotPasswordViewModel")
@MainActor
struct ForgotPasswordViewModelTests {
    @Test("an accepted request shows the neutral confirmation")
    func accepted() async throws {
        let auth = FakeAuthRepository()
        let model = ForgotPasswordViewModel(auth: auth)
        model.email = "test@example.com"
        await model.submit()
        #expect(model.wasSent)
        #expect(await auth.forgotEmails == ["test@example.com"])
    }

    @Test("a 429 shows the throttle message and no confirmation")
    func throttled() async {
        let auth = FakeAuthRepository()
        await auth.setForgot(.api(status: 429, code: "too_many_requests", message: "Slow down"))
        let model = ForgotPasswordViewModel(auth: auth)
        model.email = "test@example.com"
        await model.submit()
        #expect(!model.wasSent)
        #expect(model.errors.general == .tooManyAttempts)
    }
}

@Suite("VerifyEmailViewModel")
@MainActor
struct VerifyEmailViewModelTests {
    @Test("resend 202 shows the sent notice")
    func resendSent() async {
        let me = FakeMeRepository()
        let model = VerifyEmailViewModel(me: me, session: makeSession(me: me))
        await model.resend()
        #expect(model.notice == .sent)
        #expect(await me.resendCalls == 1)
    }

    @Test("resend 204 means already verified: /me is refreshed and the session becomes ready")
    func resendAlreadyVerified() async {
        let me = FakeMeRepository()
        await me.set(resend: .alreadyVerified)
        let session = makeSession(me: me)
        let model = VerifyEmailViewModel(me: me, session: session)
        await model.resend()
        #expect(session.state == .ready(.fixture))
        #expect(model.notice == nil)
    }

    @Test("resend 429 shows the throttle message")
    func resendThrottled() async {
        let me = FakeMeRepository()
        await me.set(resendError: .api(status: 429, code: "too_many_requests", message: "Slow down"))
        let model = VerifyEmailViewModel(me: me, session: makeSession(me: me))
        await model.resend()
        #expect(model.errors.general == .tooManyAttempts)
    }

    @Test("checking while still unverified says so; once verified the session moves on")
    func check() async {
        let me = FakeMeRepository()
        await me.set(user: .unverified)
        let session = makeSession(me: me)
        let model = VerifyEmailViewModel(me: me, session: session)

        await model.checkVerification()
        #expect(session.state == .unverified(.unverified))
        #expect(model.notice == .stillUnverified)

        await me.set(user: .fixture)
        await model.checkVerification()
        #expect(session.state == .ready(.fixture))
        #expect(model.notice == nil)
    }
}

@Suite("FormErrors")
struct FormErrorsTests {
    @Test("keeps the first message per field and clears per field")
    func fields() {
        var errors = FormErrors()
        errors.apply(
            APIError.validation(
                message: "Invalid.",
                fields: ["email": [FieldError(code: "a", message: "first"), FieldError(code: "b", message: "second")]]))
        #expect(errors["email"] == "first")
        errors.clear(field: "email")
        #expect(errors.isEmpty)
    }

    @Test("a validation error without fields becomes a general message")
    func validationWithoutFields() {
        var errors = FormErrors()
        errors.apply(APIError.validation(message: "Nope.", fields: [:]))
        #expect(errors.general == .failed(message: "Nope."))
    }
}
