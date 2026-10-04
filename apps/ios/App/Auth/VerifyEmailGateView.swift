import SwiftUI
import WorkoutsCore

/// Shown while `email_verified` is false. The link in the email opens the web app (or, from
/// story 6, this app); "I have verified" re-checks `/me`.
struct VerifyEmailGateView: View {
    @Environment(AppSession.self) private var session
    @Environment(\.dependencies) private var dependencies
    let user: User
    @State private var model: VerifyEmailViewModel?

    var body: some View {
        NavigationStack {
            VStack(spacing: 20) {
                Image(systemName: "envelope.badge")
                    .font(.system(size: 56))
                    .foregroundStyle(.tint)
                    .accessibilityHidden(true)
                Text("Verify your email")
                    .font(.title.bold())
                    .accessibilityIdentifier("verify.title")
                Text(
                    "Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn't receive the email, we will gladly send you another."
                )
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
                Text("Signed in as \(user.email)")
                    .font(.footnote)
                    .foregroundStyle(.tertiary)

                if let model {
                    if model.notice == .sent {
                        Text("A new verification link has been sent to your email address.")
                            .font(.footnote)
                            .foregroundStyle(.green)
                            .multilineTextAlignment(.center)
                            .accessibilityIdentifier("verify.sent")
                    }
                    if model.notice == .stillUnverified {
                        Text("Your email address is unverified.")
                            .font(.footnote)
                            .foregroundStyle(.orange)
                    }
                    FormErrorBanner(general: model.errors.general)
                    SubmitButton(
                        title: "I have verified my email", isBusy: model.isBusy, isEnabled: true,
                        identifier: "verify.check"
                    ) {
                        Task { await model.checkVerification() }
                    }
                    Button("Resend Verification Email") { Task { await model.resend() } }
                        .disabled(model.isBusy)
                        .accessibilityIdentifier("verify.resend")
                    Button("Log out", role: .destructive) { Task { await model.signOut() } }
                        .accessibilityIdentifier("verify.logout")
                } else {
                    ProgressView()
                }
                Spacer()
                RootFooter()
            }
            .padding()
            .navigationTitle("Email Verification")
            .navigationBarTitleDisplayMode(.inline)
            .task {
                guard model == nil, let dependencies else { return }
                model = VerifyEmailViewModel(me: dependencies.meRepository, session: session)
            }
        }
    }
}
