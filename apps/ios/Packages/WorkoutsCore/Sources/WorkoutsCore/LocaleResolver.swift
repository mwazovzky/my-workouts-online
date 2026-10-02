import Foundation

/// Picks the API locale (`Accept-Language`) from the languages the user prefers and the
/// locales the API offers (`GET /meta` → `available_locales`). The UI language itself
/// follows iOS per-app language settings; this keeps API content in the same language.
public enum LocaleResolver {
    public static let fallback = "en"

    /// - Parameters:
    ///   - preferred: BCP 47 tags in the user's order, e.g. `["ru-RU", "en-US"]`.
    ///   - available: locale codes the API accepts, e.g. `["en", "ru"]`.
    /// - Returns: the first preferred base language the API offers, else `fallback`.
    public static func apiLocale(preferred: [String], available: [String]) -> String {
        let offered = Set(available.map { $0.lowercased() })
        for tag in preferred {
            let base = tag.split(whereSeparator: { $0 == "-" || $0 == "_" }).first.map(String.init)?.lowercased()
            if let base, offered.contains(base) {
                return base
            }
        }
        return offered.contains(fallback) ? fallback : (available.first ?? fallback)
    }

    /// Resolves against the languages this app bundle is localized into, in the user's order.
    public static func apiLocale(available: [String], bundle: Bundle = .main) -> String {
        apiLocale(preferred: bundle.preferredLocalizations, available: available)
    }
}
