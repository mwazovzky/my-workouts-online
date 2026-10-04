import SwiftUI
import WorkoutsCore

/// The signed-out flow: Welcome → Log in / Register, with Forgot password off the login screen.
struct AuthFlowView: View {
    let reason: AppSession.SignOutReason?

    var body: some View {
        NavigationStack {
            WelcomeView(reason: reason)
        }
    }
}

struct WelcomeView: View {
    @Environment(DeepLinkCoordinator.self) private var deepLinks
    let reason: AppSession.SignOutReason?

    var body: some View {
        VStack(spacing: 20) {
            Spacer()
            Image(systemName: "figure.strengthtraining.traditional")
                .font(.system(size: 72))
                .foregroundStyle(.tint)
                .accessibilityHidden(true)
            Text("Workouts")
                .font(.largeTitle.bold())
                .accessibilityIdentifier("root.title")
            Text("Track your programs and log every set.")
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
            if reason == .sessionExpired {
                Text("Your session has expired. Please log in again.")
                    .font(.footnote)
                    .foregroundStyle(.orange)
                    .multilineTextAlignment(.center)
            }
            if deepLinks.notice == .signInToVerify || reason == .passwordReset || deepLinks.notice == .passwordReset {
                Text(
                    deepLinks.notice == .signInToVerify
                        ? "Sign in to finish verifying your email." : "Your password has been reset. Please log in."
                )
                .font(.footnote)
                .foregroundStyle(.green)
                .multilineTextAlignment(.center)
                .accessibilityIdentifier("welcome.notice")
            }
            Spacer()
            NavigationLink("Log in") { LoginView() }
                .buttonStyle(.borderedProminent)
                .controlSize(.large)
                .accessibilityIdentifier("welcome.login")
            NavigationLink("Register") { RegisterView() }
                .buttonStyle(.bordered)
                .controlSize(.large)
                .accessibilityIdentifier("welcome.register")
            RootFooter()
        }
        .padding()
    }
}

struct LoginView: View {
    @Environment(AppSession.self) private var session
    @Environment(\.dependencies) private var dependencies
    @State private var model: LoginViewModel?

    var body: some View {
        Group {
            if let model {
                @Bindable var model = model
                ScrollView {
                    VStack(spacing: 16) {
                        FormErrorBanner(general: model.errors.general)
                        FormField(
                            title: "Email", text: $model.email, error: model.errors["email"], contentType: .username,
                            keyboard: .emailAddress, identifier: "login.email")
                        FormField(
                            title: "Password", text: $model.password, error: model.errors["password"], isSecure: true,
                            contentType: .password, identifier: "login.password")
                        Toggle("Remember me", isOn: $model.rememberEmail)
                            .font(.subheadline)
                        SubmitButton(
                            title: "Log in", isBusy: model.isSubmitting, isEnabled: model.canSubmit,
                            identifier: "login.submit"
                        ) {
                            Task { await model.submit() }
                        }
                        NavigationLink("Forgot your password?") { ForgotPasswordView() }
                            .font(.footnote)
                        HStack(spacing: 4) {
                            Text("Don't have an account?")
                            NavigationLink("Register") { RegisterView() }
                        }
                        .font(.footnote)
                    }
                    .padding()
                }
            } else {
                ProgressView()
            }
        }
        .navigationTitle("Log in")
        .navigationBarTitleDisplayMode(.inline)
        .task {
            guard model == nil, let dependencies else { return }
            model = LoginViewModel(
                auth: dependencies.authRepository, session: session, memory: UserDefaultsCredentialsMemory())
        }
    }
}

struct RegisterView: View {
    @Environment(AppSession.self) private var session
    @Environment(\.dependencies) private var dependencies
    @State private var model: RegisterViewModel?

    var body: some View {
        Group {
            if let model {
                @Bindable var model = model
                ScrollView {
                    VStack(spacing: 16) {
                        FormErrorBanner(general: model.errors.general)
                        FormField(
                            title: "Name", text: $model.name, error: model.errors["name"], contentType: .name,
                            autocapitalization: .words, identifier: "register.name")
                        FormField(
                            title: "Email", text: $model.email, error: model.errors["email"], contentType: .username,
                            keyboard: .emailAddress, identifier: "register.email")
                        FormField(
                            title: "Password", text: $model.password, error: model.errors["password"], isSecure: true,
                            contentType: .newPassword, identifier: "register.password")
                        FormField(
                            title: "Confirm Password", text: $model.passwordConfirmation,
                            error: model.errors["password_confirmation"], isSecure: true, contentType: .newPassword,
                            identifier: "register.passwordConfirmation")
                        SubmitButton(
                            title: "Create account", isBusy: model.isSubmitting, isEnabled: model.canSubmit,
                            identifier: "register.submit"
                        ) {
                            Task { await model.submit() }
                        }
                        HStack(spacing: 4) {
                            Text("Already registered?")
                            NavigationLink("Log in") { LoginView() }
                        }
                        .font(.footnote)
                    }
                    .padding()
                }
            } else {
                ProgressView()
            }
        }
        .navigationTitle("Register")
        .navigationBarTitleDisplayMode(.inline)
        .task {
            guard model == nil, let dependencies else { return }
            model = RegisterViewModel(auth: dependencies.authRepository, session: session)
        }
    }
}

struct ForgotPasswordView: View {
    @Environment(\.dependencies) private var dependencies
    @State private var model: ForgotPasswordViewModel?

    var body: some View {
        Group {
            if let model {
                @Bindable var model = model
                ScrollView {
                    VStack(spacing: 16) {
                        Text(
                            "Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one."
                        )
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                        if model.wasSent {
                            Text("We have emailed your password reset link.")
                                .font(.subheadline)
                                .foregroundStyle(.green)
                                .accessibilityIdentifier("forgot.sent")
                        }
                        FormErrorBanner(general: model.errors.general)
                        FormField(
                            title: "Email", text: $model.email, error: model.errors["email"], contentType: .username,
                            keyboard: .emailAddress, identifier: "forgot.email")
                        SubmitButton(
                            title: "Email Password Reset Link", isBusy: model.isSubmitting, isEnabled: model.canSubmit,
                            identifier: "forgot.submit"
                        ) {
                            Task { await model.submit() }
                        }
                    }
                    .padding()
                }
            } else {
                ProgressView()
            }
        }
        .navigationTitle("Forgot Password")
        .navigationBarTitleDisplayMode(.inline)
        .task {
            guard model == nil, let dependencies else { return }
            model = ForgotPasswordViewModel(auth: dependencies.authRepository)
        }
    }
}
