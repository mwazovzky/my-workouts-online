import SwiftUI
import WorkoutsCore

/// Switches on the session state the way the web router guards do.
struct RootView: View {
    @Environment(AppSession.self) private var session
    @Environment(DeepLinkCoordinator.self) private var deepLinks

    var body: some View {
        @Bindable var deepLinks = deepLinks
        content
            .sheet(item: $deepLinks.resetLink) { link in ResetPasswordView(link: link) }
            .deepLinkAlerts(deepLinks)
            .overlay {
                if deepLinks.notice == .verifying {
                    ZStack {
                        Color.black.opacity(0.2).ignoresSafeArea()
                        VStack(spacing: 12) {
                            ProgressView()
                            Text("Verifying your email address…")
                        }
                        .padding(24)
                        .background(.regularMaterial, in: RoundedRectangle(cornerRadius: 12))
                    }
                }
            }
    }

    @ViewBuilder
    private var content: some View {
        switch session.state {
        case .bootstrapping:
            BootstrapView()
        case .signedOut(let reason):
            AuthFlowView(reason: reason)
        case .unverified(let user):
            VerifyEmailGateView(user: user)
        case .ready:
            MainTabView()
        }
    }
}

private struct BootstrapView: View {
    @Environment(AppSession.self) private var session

    var body: some View {
        VStack(spacing: 16) {
            if let error = session.bootstrapError {
                Text("Could not reach the server.")
                    .foregroundStyle(.secondary)
                Text(verbatim: String(describing: error))
                    .font(.footnote.monospaced())
                    .foregroundStyle(.tertiary)
                Button("Try again") { Task { await session.bootstrap() } }
                    .buttonStyle(.borderedProminent)
            } else {
                ProgressView()
            }
        }
        .padding()
    }
}

struct RootFooter: View {
    var body: some View {
        VStack(spacing: 4) {
            Text(AppConfig.apiBaseURL.absoluteString)
            Text(verbatim: "v\(AppConfig.version) · \(LocaleResolver.apiLocale(available: ["en", "ru"]))")
        }
        .font(.footnote.monospaced())
        .foregroundStyle(.tertiary)
        .accessibilityIdentifier("root.footer")
    }
}

extension View {
    /// Alerts for deep-link outcomes that need an acknowledgement or a decision.
    fileprivate func deepLinkAlerts(_ deepLinks: DeepLinkCoordinator) -> some View {
        modifier(DeepLinkAlerts(deepLinks: deepLinks))
    }
}

private struct DeepLinkAlerts: ViewModifier {
    @Bindable var deepLinks: DeepLinkCoordinator

    private var showsAnotherAccount: Binding<Bool> {
        Binding(
            get: { deepLinks.notice == .linkForAnotherAccount },
            set: { if !$0 { deepLinks.dismissNotice() } })
    }

    private var showsVerified: Binding<Bool> {
        Binding(get: { deepLinks.notice == .verified }, set: { if !$0 { deepLinks.dismissNotice() } })
    }

    private var invalidMessage: String? {
        if case .invalidLink(let message) = deepLinks.notice {
            return message ?? String(localized: "This verification link is invalid or has expired.")
        }
        return nil
    }

    private var showsInvalid: Binding<Bool> {
        Binding(get: { invalidMessage != nil }, set: { if !$0 { deepLinks.dismissNotice() } })
    }

    func body(content: Content) -> some View {
        content
            .alert("Email Verification", isPresented: showsAnotherAccount) {
                Button("Log out", role: .destructive) { Task { await deepLinks.signOutToSwitchAccount() } }
                Button("Cancel", role: .cancel) { deepLinks.discardPendingVerification() }
            } message: {
                Text("This link belongs to a different account. Log out and open it again.")
            }
            .alert("Email Verification", isPresented: showsVerified) {
                Button("OK") {}
            } message: {
                Text("Your email has been verified.")
            }
            .alert("Email Verification", isPresented: showsInvalid) {
                Button("OK") {}
            } message: {
                Text(verbatim: invalidMessage ?? "")
            }
    }
}
