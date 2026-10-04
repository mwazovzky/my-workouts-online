import SwiftUI
import WorkoutsCore

/// Presented as a sheet when a reset-password link opens the app. Success signs the user
/// out (the server revoked every session) and sends them back to log in.
struct ResetPasswordView: View {
    @Environment(\.dependencies) private var dependencies
    @Environment(DeepLinkCoordinator.self) private var deepLinks
    @Environment(\.dismiss) private var dismiss
    let link: ResetPasswordLink
    @State private var model: ResetPasswordViewModel?

    var body: some View {
        NavigationStack {
            Group {
                if let model {
                    @Bindable var model = model
                    ScrollView {
                        VStack(spacing: 16) {
                            FormErrorBanner(general: model.errors.general)
                            FormField(
                                title: "Email", text: $model.email, error: model.errors["email"],
                                contentType: .username,
                                keyboard: .emailAddress, identifier: "reset.email")
                            FormField(
                                title: "New Password", text: $model.password, error: model.errors["password"],
                                isSecure: true,
                                contentType: .newPassword, identifier: "reset.password")
                            FormField(
                                title: "Confirm Password", text: $model.passwordConfirmation,
                                error: model.errors["password_confirmation"], isSecure: true, contentType: .newPassword,
                                identifier: "reset.passwordConfirmation")
                            if let tokenError = model.errors["token"] {
                                Text(verbatim: tokenError)
                                    .font(.footnote)
                                    .foregroundStyle(.red)
                            }
                            SubmitButton(
                                title: "Reset Password", isBusy: model.isSubmitting, isEnabled: model.canSubmit,
                                identifier: "reset.submit"
                            ) {
                                Task {
                                    if await model.submit() {
                                        await deepLinks.passwordWasReset()
                                    }
                                }
                            }
                        }
                        .padding()
                    }
                } else {
                    ProgressView()
                }
            }
            .navigationTitle("Reset your password")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Cancel") {
                        deepLinks.resetLink = nil
                        dismiss()
                    }
                    .accessibilityIdentifier("reset.cancel")
                }
            }
            .task {
                guard model == nil, let dependencies else { return }
                model = ResetPasswordViewModel(auth: dependencies.authRepository, link: link)
            }
        }
    }
}
