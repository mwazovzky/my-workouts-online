import XCTest

/// Smoke flows against the real app. The login flow talks to the dev stack at the Debug
/// `API_BASE_URL` (docker compose up -d) with the seeded demo admin, so it is skipped
/// unless `UITEST_LIVE_BACKEND=1` is set in the environment.
final class LaunchTests: XCTestCase {
    @MainActor
    func testWelcomeScreenLaunches() {
        let app = XCUIApplication()
        app.launch()

        XCTAssertTrue(app.staticTexts["root.title"].waitForExistence(timeout: 10))
        XCTAssertTrue(app.buttons["welcome.login"].exists)
        XCTAssertTrue(app.buttons["welcome.register"].exists)
    }

    @MainActor
    func testLoginHappyPathReachesTheMainView() throws {
        try XCTSkipUnless(
            ProcessInfo.processInfo.environment["UITEST_LIVE_BACKEND"] == "1",
            "needs the dev stack; run with UITEST_LIVE_BACKEND=1")

        let app = XCUIApplication()
        app.launch()
        XCTAssertTrue(app.buttons["welcome.login"].waitForExistence(timeout: 10))
        app.buttons["welcome.login"].tap()

        let email = app.textFields["login.email"]
        XCTAssertTrue(email.waitForExistence(timeout: 5))
        email.tap()
        email.typeText("test@example.com")
        let password = app.secureTextFields["login.password"]
        password.tap()
        password.typeText("password")
        app.buttons["login.submit"].tap()

        let dashboard = app.staticTexts["root.title"]
        XCTAssertTrue(dashboard.waitForExistence(timeout: 15))
        XCTAssertEqual(dashboard.label, "Dashboard")

        app.buttons["main.logout"].tap()
        XCTAssertTrue(app.buttons["welcome.login"].waitForExistence(timeout: 10))
    }

    /// Opens a `myworkouts://` URL from the system and accepts the "Open in My Workouts?" prompt.
    @MainActor
    private func open(_ url: String, into app: XCUIApplication) throws {
        XCUIDevice.shared.system.open(try XCTUnwrap(URL(string: url)))
        let springboard = XCUIApplication(bundleIdentifier: "com.apple.springboard")
        let openButton = springboard.buttons["Open"]
        if openButton.waitForExistence(timeout: 5) {
            openButton.tap()
        }
        XCTAssertTrue(app.wait(for: .runningForeground, timeout: 10))
    }

    @MainActor
    func testResetPasswordLinkOpensTheResetSheet() throws {
        let app = XCUIApplication()
        app.launch()
        XCTAssertTrue(app.buttons["welcome.login"].waitForExistence(timeout: 10))

        try open("myworkouts://reset-password/tok123?email=test%40example.com", into: app)

        let email = app.textFields["reset.email"]
        XCTAssertTrue(email.waitForExistence(timeout: 10))
        XCTAssertEqual(email.value as? String, "test@example.com")
        XCTAssertTrue(app.buttons["reset.submit"].exists)
        app.buttons["reset.cancel"].tap()
        XCTAssertTrue(app.buttons["welcome.login"].waitForExistence(timeout: 5))
    }

    @MainActor
    func testVerificationLinkWhileSignedOutAsksToSignIn() throws {
        let app = XCUIApplication()
        app.launch()
        XCTAssertTrue(app.buttons["welcome.login"].waitForExistence(timeout: 10))

        try open("myworkouts://verify-email/1/abc?expires=1&signature=s", into: app)

        let notice = app.staticTexts["welcome.notice"]
        XCTAssertTrue(notice.waitForExistence(timeout: 10))
        XCTAssertEqual(notice.label, "Sign in to finish verifying your email.")
    }
}
