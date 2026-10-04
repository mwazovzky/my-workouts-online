import SwiftUI
import WorkoutsCore

/// Switches on the session state the way the web router guards do. The signed-in branch is a
/// placeholder until story 7 adds the tab shell.
struct RootView: View {
    @Environment(AppSession.self) private var session

    var body: some View {
        switch session.state {
        case .bootstrapping:
            BootstrapView()
        case .signedOut(let reason):
            AuthFlowView(reason: reason)
        case .unverified(let user):
            VerifyEmailGateView(user: user)
        case .ready(let user):
            MainPlaceholderView(user: user)
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

private struct MainPlaceholderView: View {
    @Environment(AppSession.self) private var session
    let user: User

    var body: some View {
        VStack(spacing: 16) {
            Text("Dashboard")
                .font(.largeTitle.bold())
                .accessibilityIdentifier("root.title")
            Text(verbatim: user.name ?? user.email)
                .foregroundStyle(.secondary)
            Button("Log out") { Task { await session.signOut() } }
                .accessibilityIdentifier("main.logout")
            Spacer()
            RootFooter()
        }
        .padding()
        .padding(.top, 64)
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
