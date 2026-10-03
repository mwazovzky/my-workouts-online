import Foundation

/// Build-time configuration read from Info.plist, which the xcconfig files fill in
/// (`Config/Debug.xcconfig` points at the dev stack, `Config/Release.xcconfig` at production).
enum AppConfig {
    static var apiBaseURL: URL {
        guard
            let raw = Bundle.main.object(forInfoDictionaryKey: "APIBaseURL") as? String,
            let url = URL(string: raw), url.scheme != nil
        else {
            preconditionFailure("APIBaseURL is missing from Info.plist; check Config/*.xcconfig")
        }
        return url
    }

    static var marketingVersion: String {
        Bundle.main.object(forInfoDictionaryKey: "CFBundleShortVersionString") as? String ?? "0"
    }

    static var buildNumber: String {
        Bundle.main.object(forInfoDictionaryKey: "CFBundleVersion") as? String ?? "0"
    }

    static var version: String {
        "\(marketingVersion) (\(buildNumber))"
    }
}
