import SwiftUI
import UIKit
import WorkoutsCore

struct ProfileTab: View {
    var body: some View {
        NavigationStack {
            ProfileView()
        }
    }
}

struct ProfileView: View {
    @Environment(\.dependencies) private var dependencies
    @Environment(AppSession.self) private var session
    @Environment(Toaster.self) private var toaster
    @State private var model: ProfileViewModel?
    @State private var confirmsDelete = false

    var body: some View {
        Group {
            if let model {
                content(model)
            } else {
                LoadingStateView()
            }
        }
        .navigationTitle("Profile")
        .task {
            guard model == nil, let dependencies else { return }
            model = ProfileViewModel(me: dependencies.meRepository, session: session)
        }
    }

    private func content(_ model: ProfileViewModel) -> some View {
        @Bindable var model = model
        return Form {
            Section {
                FormField(
                    title: "Name", text: $model.name, error: model.profileErrors["name"], contentType: .name,
                    autocapitalization: .words, identifier: "profile.name")
                FormField(
                    title: "Email", text: $model.email, error: model.profileErrors["email"], contentType: .emailAddress,
                    keyboard: .emailAddress, identifier: "profile.email")
                if model.user?.emailVerified == false {
                    VStack(alignment: .leading, spacing: 6) {
                        Text("Your email address is unverified.").font(.footnote).foregroundStyle(.orange)
                        Button("Click here to re-send the verification email.") {
                            Task { await model.resendVerification() }
                        }
                        .font(.footnote)
                        .disabled(model.isResending)
                        if let error = model.resendError {
                            Text(verbatim: error.userMessage).font(.footnote).foregroundStyle(.red)
                        }
                    }
                }
                FormErrorBanner(general: model.profileErrors.general)
                SubmitButton(
                    title: "Save", isBusy: model.isSavingProfile, isEnabled: model.canSaveProfile,
                    identifier: "profile.save"
                ) {
                    Task { await model.saveProfile() }
                }
            } header: {
                Text("Profile Information")
            } footer: {
                Text("Update your account's profile information and email address.")
            }

            Section {
                FormField(
                    title: "Current Password", text: $model.currentPassword,
                    error: model.passwordErrors["current_password"], isSecure: true, contentType: .password,
                    identifier: "password.current")
                FormField(
                    title: "New Password", text: $model.newPassword, error: model.passwordErrors["password"],
                    isSecure: true, contentType: .newPassword, identifier: "password.new")
                FormField(
                    title: "Confirm Password", text: $model.newPasswordConfirmation,
                    error: model.passwordErrors["password_confirmation"], isSecure: true, contentType: .newPassword,
                    identifier: "password.confirm")
                FormErrorBanner(general: model.passwordErrors.general)
                SubmitButton(
                    title: "Update Password", isBusy: model.isUpdatingPassword, isEnabled: model.canUpdatePassword,
                    identifier: "password.submit"
                ) {
                    Task { await model.updatePassword() }
                }
            } header: {
                Text("Update Password")
            } footer: {
                Text("Ensure your account is using a long, random password to stay secure.")
            }

            Section {
                Button {
                    if let url = URL(string: UIApplication.openSettingsURLString) { UIApplication.shared.open(url) }
                } label: {
                    HStack {
                        Text("Language")
                        Spacer()
                        Text(verbatim: Self.effectiveLanguageName)
                            .foregroundStyle(.secondary)
                        Image(systemName: "arrow.up.forward.app").foregroundStyle(.tertiary)
                    }
                }
                .accessibilityIdentifier("profile.language")
            } footer: {
                Text("Select your preferred language.")
            }

            Section {
                Picker(
                    "Theme",
                    selection: Binding(get: { model.theme }, set: { theme in Task { await model.setTheme(theme) } })
                ) {
                    Text("Light").tag(ThemePreference.light)
                    Text("Dark").tag(ThemePreference.dark)
                    Text("Match device").tag(ThemePreference.system)
                }
                .pickerStyle(.segmented)
                .disabled(model.isSavingTheme)
                .accessibilityIdentifier("profile.theme")
                if let error = model.themeError {
                    Text(verbatim: error.userMessage).font(.footnote).foregroundStyle(.red)
                }
            } header: {
                Text("Theme")
            } footer: {
                Text("Choose how the app should handle light and dark appearance.")
            }

            Section {
                Button("Log Out", role: .destructive) { Task { await model.signOut() } }
                    .accessibilityIdentifier("main.logout")
                Button("Delete Account", role: .destructive) { confirmsDelete = true }
                    .accessibilityIdentifier("profile.delete")
            }

            Section {
                HStack {
                    Text("About")
                    Spacer()
                    Text(verbatim: "v\(AppConfig.version)").foregroundStyle(.secondary)
                }
                Text(AppConfig.apiBaseURL.absoluteString).font(.footnote.monospaced()).foregroundStyle(.tertiary)
                Link(destination: AppConfig.apiBaseURL.appending(path: "privacy")) {
                    HStack {
                        Text("Privacy Policy")
                        Spacer()
                        Image(systemName: "arrow.up.forward.app").foregroundStyle(.tertiary)
                    }
                }
                .accessibilityIdentifier("profile.privacy")
                Link(destination: AppConfig.apiBaseURL.appending(path: "support")) {
                    HStack {
                        Text("Support")
                        Spacer()
                        Image(systemName: "arrow.up.forward.app").foregroundStyle(.tertiary)
                    }
                }
                .accessibilityIdentifier("profile.support")
            }
        }
        .onChange(of: model.notice) { _, notice in
            guard let notice else { return }
            toaster.show(Self.message(for: notice))
            model.dismissNotice()
        }
        .sheet(isPresented: $confirmsDelete) {
            DeleteAccountSheet(model: model)
        }
    }

    /// The language the app is actually showing, in that language.
    static var effectiveLanguageName: String {
        let code = Bundle.main.preferredLocalizations.first ?? "en"
        return Locale(identifier: code).localizedString(forLanguageCode: code)?.capitalized ?? code
    }

    static func message(for notice: ProfileViewModel.Notice) -> String {
        switch notice {
        case .profileSaved, .themeSaved: String(localized: "Saved.")
        case .passwordUpdated: String(localized: "Password updated.")
        case .verificationSent: String(localized: "A new verification link has been sent to your email address.")
        }
    }
}

/// Confirms account deletion with the password, as the web does.
struct DeleteAccountSheet: View {
    @Environment(\.dismiss) private var dismiss
    let model: ProfileViewModel

    var body: some View {
        @Bindable var model = model
        return NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 16) {
                    Text("Are you sure you want to delete your account?").font(.headline)
                    Text(
                        "Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account."
                    )
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
                    FormErrorBanner(general: model.deleteErrors.general)
                    FormField(
                        title: "Password", text: $model.deletePassword, error: model.deleteErrors["password"],
                        isSecure: true, contentType: .password, identifier: "delete.password")
                    Button(role: .destructive) {
                        Task {
                            if await model.deleteAccount() { dismiss() }
                        }
                    } label: {
                        HStack {
                            if model.isDeleting { ProgressView() }
                            Text("Delete Account").frame(maxWidth: .infinity)
                        }
                        .padding(.vertical, 6)
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(.red)
                    .disabled(model.deletePassword.isEmpty || model.isDeleting)
                    .accessibilityIdentifier("delete.confirm")
                }
                .padding()
            }
            .navigationTitle("Delete Account")
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) { Button("Cancel") { dismiss() } }
            }
        }
    }
}
