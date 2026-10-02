import SwiftUI
import WorkoutsCore

/// Placeholder shell. Story 4 replaces it with the session-driven root
/// (sign-in flow, verify-email gate, main tabs).
struct RootView: View {
    var body: some View {
        VStack(spacing: 16) {
            Image(systemName: "figure.strengthtraining.traditional")
                .font(.system(size: 64))
                .foregroundStyle(.tint)
                .accessibilityHidden(true)
            Text("Workouts")
                .font(.largeTitle.bold())
                .accessibilityIdentifier("root.title")
            Text("Programs")
                .foregroundStyle(.secondary)
            Spacer()
            VStack(spacing: 4) {
                Text(AppConfig.apiBaseURL.absoluteString)
                Text(verbatim: "v\(AppConfig.version) · \(LocaleResolver.apiLocale(available: ["en", "ru"]))")
            }
            .font(.footnote.monospaced())
            .foregroundStyle(.tertiary)
            .accessibilityIdentifier("root.footer")
        }
        .padding()
        .padding(.top, 64)
    }
}

#Preview {
    RootView()
}
