import Foundation
import Testing

@testable import MyWorkouts

@Suite("AppConfig")
struct AppConfigTests {
    @Test("reads the API base URL from the build configuration")
    func apiBaseURL() {
        let url = AppConfig.apiBaseURL
        #expect(url.scheme == "http" || url.scheme == "https")
        #expect(url.host() != nil)
    }

    @Test("exposes a version string")
    func version() {
        #expect(AppConfig.version.contains("("))
    }
}
