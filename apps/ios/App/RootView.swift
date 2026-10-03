import SwiftUI
import WorkoutsCore

/// Switches on the session state the way the web router guards do. The three branches are
/// placeholders until stories 5 (auth screens) and 7 (tab shell) replace them.
struct RootView: View {
    @Environment(AppSession.self) private var session

    var body: some View {
        switch session.state {
        case .bootstrapping:
            BootstrapView()
        case .signedOut(let reason):
            SignedOutPlaceholderView(reason: reason)
        case .unverified(let user):
            VerifyEmailPlaceholderView(user: user)
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

private struct SignedOutPlaceholderView: View {
    let reason: AppSession.SignOutReason?

    var body: some View {
        VStack(spacing: 16) {
            Image(systemName: "figure.strengthtraining.traditional")
                .font(.system(size: 64))
                .foregroundStyle(.tint)
                .accessibilityHidden(true)
            Text("Workouts")
                .font(.largeTitle.bold())
                .accessibilityIdentifier("root.title")
            if reason == .sessionExpired {
                Text("Your session has expired. Please log in again.")
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
            }
            Text("Log in")
                .foregroundStyle(.secondary)
            Spacer()
            RootFooter()
        }
        .padding()
        .padding(.top, 64)
    }
}

private struct VerifyEmailPlaceholderView: View {
    @Environment(AppSession.self) private var session
    let user: User

    var body: some View {
        VStack(spacing: 16) {
            Text("Verify your email")
                .font(.title.bold())
            Text(verbatim: user.email)
                .foregroundStyle(.secondary)
            Button("Log out") { Task { await session.signOut() } }
            Spacer()
            RootFooter()
        }
        .padding()
        .padding(.top, 64)
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
            Spacer()
            RootFooter()
        }
        .padding()
        .padding(.top, 64)
    }
}

private struct RootFooter: View {
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
