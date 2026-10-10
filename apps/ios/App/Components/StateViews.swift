import SwiftUI
import WorkoutsAPI
import WorkoutsCore

/// Centered spinner for a screen that has nothing to show yet.
struct LoadingStateView: View {
    var body: some View {
        ProgressView()
            .frame(maxWidth: .infinity, minHeight: 160)
            .accessibilityIdentifier("state.loading")
    }
}

struct EmptyStateView: View {
    let systemImage: String
    let title: LocalizedStringKey
    var message: LocalizedStringKey? = nil

    var body: some View {
        VStack(spacing: 8) {
            Image(systemName: systemImage)
                .font(.system(size: 32))
                .foregroundStyle(.tertiary)
                .accessibilityHidden(true)
            Text(title).font(.headline)
            if let message {
                Text(message)
                    .font(.subheadline)
                    .foregroundStyle(.secondary)
                    .multilineTextAlignment(.center)
            }
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 24)
    }
}

/// A failed load with a retry button; shown instead of the content when nothing loaded yet.
struct ErrorStateView: View {
    let error: APIError
    let retry: () -> Void

    var body: some View {
        VStack(spacing: 12) {
            Image(systemName: "exclamationmark.triangle")
                .font(.system(size: 32))
                .foregroundStyle(.orange)
                .accessibilityHidden(true)
            Text(error.userMessage)
                .font(.subheadline)
                .foregroundStyle(.secondary)
                .multilineTextAlignment(.center)
            Button("Try again", action: retry)
                .buttonStyle(.bordered)
                .accessibilityIdentifier("state.retry")
        }
        .frame(maxWidth: .infinity)
        .padding(.vertical, 24)
        .accessibilityIdentifier("state.error")
    }
}

/// A short-lived message at the bottom of the screen, like the web toasts.
@MainActor
@Observable
final class Toaster {
    private(set) var message: String?
    private var dismissal: Task<Void, Never>?

    func show(_ message: String, for duration: Duration = .seconds(3)) {
        self.message = message
        dismissal?.cancel()
        dismissal = Task {
            try? await Task.sleep(for: duration)
            if !Task.isCancelled { self.message = nil }
        }
    }
}

struct ToastOverlay: ViewModifier {
    @Environment(Toaster.self) private var toaster

    func body(content: Content) -> some View {
        content.overlay(alignment: .bottom) {
            if let message = toaster.message {
                Text(verbatim: message)
                    .font(.subheadline)
                    .padding(.horizontal, 16)
                    .padding(.vertical, 10)
                    .background(.regularMaterial, in: Capsule())
                    .padding(.bottom, 72)  // above the tab bar
                    .transition(.move(edge: .bottom).combined(with: .opacity))
                    .allowsHitTesting(false)  // a toast must never swallow taps meant for what is under it
                    .accessibilityIdentifier("toast")
            }
        }
        .animation(.default, value: toaster.message)
    }
}

extension View {
    func toastOverlay() -> some View { modifier(ToastOverlay()) }
}

extension APIError {
    /// What a user should read for a failure that is not tied to a form field.
    var userMessage: String {
        switch self {
        case .validation(let message, _): message
        case .api(_, _, let message, _): message
        case .network: String(localized: "Could not reach the server.")
        case .unexpected, .decoding, .local: String(localized: "Something went wrong. Please try again.")
        }
    }
}
