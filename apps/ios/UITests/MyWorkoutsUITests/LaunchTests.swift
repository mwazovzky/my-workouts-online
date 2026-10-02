import XCTest

final class LaunchTests: XCTestCase {
    @MainActor
    func testPlaceholderShellLaunches() {
        let app = XCUIApplication()
        app.launch()

        XCTAssertTrue(app.staticTexts["root.title"].waitForExistence(timeout: 5))
        XCTAssertTrue(app.otherElements["root.footer"].exists || app.staticTexts["root.footer"].exists)
    }
}
