import SwiftUI
import WorkoutsCore

/// A labelled text field with the API's message for that field underneath.
struct FormField: View {
    let title: LocalizedStringKey
    @Binding var text: String
    var error: String?
    var isSecure = false
    var contentType: UITextContentType?
    var keyboard: UIKeyboardType = .default
    var autocapitalization: TextInputAutocapitalization = .never
    var identifier: String

    var body: some View {
        VStack(alignment: .leading, spacing: 6) {
            Text(title)
                .font(.subheadline.weight(.medium))
            Group {
                if isSecure {
                    SecureField("", text: $text)
                } else {
                    TextField("", text: $text)
                }
            }
            .textFieldStyle(.roundedBorder)
            .textContentType(contentType)
            .keyboardType(keyboard)
            .textInputAutocapitalization(autocapitalization)
            .autocorrectionDisabled()
            .accessibilityIdentifier(identifier)
            if let error {
                Text(verbatim: error)
                    .font(.footnote)
                    .foregroundStyle(.red)
                    .accessibilityIdentifier("\(identifier).error")
            }
        }
    }
}

/// The non-field failure of a form, as a banner.
struct FormErrorBanner: View {
    let general: FormErrors.General?

    var body: some View {
        if let general {
            Text(message(for: general))
                .font(.footnote)
                .foregroundStyle(.red)
                .frame(maxWidth: .infinity, alignment: .leading)
                .padding(12)
                .background(.red.opacity(0.08), in: RoundedRectangle(cornerRadius: 8))
                .accessibilityIdentifier("form.error")
        }
    }

    private func message(for general: FormErrors.General) -> String {
        switch general {
        case .tooManyAttempts(let retryAfter):
            if let retryAfter {
                String(localized: "Too many attempts. Try again in \(retryAfter) seconds.")
            } else {
                String(localized: "Too many attempts. Please try again later.")
            }
        case .offline: String(localized: "Could not reach the server.")
        case .failed(let message): message ?? String(localized: "Something went wrong. Please try again.")
        }
    }
}

struct SubmitButton: View {
    let title: LocalizedStringKey
    let isBusy: Bool
    let isEnabled: Bool
    let identifier: String
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            HStack {
                if isBusy { ProgressView().tint(.white) }
                Text(title).frame(maxWidth: .infinity)
            }
            .padding(.vertical, 6)
        }
        .buttonStyle(.borderedProminent)
        .disabled(!isEnabled || isBusy)
        .accessibilityIdentifier(identifier)
    }
}
