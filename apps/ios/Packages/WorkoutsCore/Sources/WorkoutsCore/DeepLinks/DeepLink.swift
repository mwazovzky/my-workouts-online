import Foundation

/// The two emailed links the app handles. Both also keep working on the web.
public enum DeepLink: Sendable, Equatable {
    /// `/verify-email/{id}/{hash}?expires=&signature=`; posted to `/auth/email/verify` as the same user.
    case verifyEmail(id: Int, hash: String, expires: Int, signature: String)
    /// `/reset-password/{token}?email=`; opens the reset form.
    case resetPassword(token: String, email: String)
}

/// Parses Universal Links (`https://{domain}/verify-email/...`) and the simulator-only custom
/// scheme (`myworkouts://verify-email/...`), which has the first path segment as its host.
public enum DeepLinkParser {
    public static let customScheme = "myworkouts"

    public static func parse(_ url: URL) -> DeepLink? {
        guard let components = URLComponents(url: url, resolvingAgainstBaseURL: false) else { return nil }
        var segments = components.path.split(separator: "/").map(String.init)
        if components.scheme?.lowercased() == customScheme, let host = components.host, !host.isEmpty {
            segments.insert(host, at: 0)
        }
        let query = Dictionary(
            (components.queryItems ?? []).map { ($0.name, $0.value ?? "") }, uniquingKeysWith: { first, _ in first })

        switch segments.first {
        case "verify-email":
            guard segments.count == 3, let id = Int(segments[1]), !segments[2].isEmpty,
                let expiresRaw = query["expires"], let expires = Int(expiresRaw),
                let signature = query["signature"], !signature.isEmpty
            else { return nil }
            return .verifyEmail(id: id, hash: segments[2], expires: expires, signature: signature)
        case "reset-password":
            guard segments.count == 2, !segments[1].isEmpty, let email = query["email"], !email.isEmpty else {
                return nil
            }
            return .resetPassword(token: segments[1], email: email)
        default:
            return nil
        }
    }
}
