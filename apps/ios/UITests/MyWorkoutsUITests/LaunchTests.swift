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
}
