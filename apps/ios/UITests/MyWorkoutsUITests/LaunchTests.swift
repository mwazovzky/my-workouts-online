import XCTest

/// Smoke flows against the real app. The login flow talks to the dev stack at the Debug
/// `API_BASE_URL` (docker compose up -d) with the seeded demo admin, so it is skipped
/// unless `UITEST_LIVE_BACKEND=1` is set in the environment.
final class LaunchTests: XCTestCase {
    /// Launches with the stored session cleared so every test starts on the welcome screen.
    @MainActor
    static func launchSignedOut() -> XCUIApplication {
        let app = XCUIApplication()
        app.launchArguments = ["--reset-session"]
        // UITEST_CONTENT_SIZE=UICTContentSizeCategoryAccessibilityXXXL runs the flows at the largest text size.
        if let size = ProcessInfo.processInfo.environment["UITEST_CONTENT_SIZE"], !size.isEmpty {
            app.launchArguments += ["-UIPreferredContentSizeCategoryName", size]
        }
        app.launch()
        return app
    }

    @MainActor
    func testWelcomeScreenLaunches() {
        let app = Self.launchSignedOut()

        XCTAssertTrue(app.staticTexts["root.title"].waitForExistence(timeout: 10))
        XCTAssertTrue(app.buttons["welcome.login"].exists)
        XCTAssertTrue(app.buttons["welcome.register"].exists)
    }

    @MainActor
    func testLoginHappyPathReachesTheMainView() throws {
        try XCTSkipUnless(
            ProcessInfo.processInfo.environment["UITEST_LIVE_BACKEND"] == "1",
            "needs the dev stack; run with UITEST_LIVE_BACKEND=1")

        let app = Self.launchSignedOut()
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

        XCTAssertTrue(app.navigationBars["Dashboard"].waitForExistence(timeout: 15))
        XCTAssertTrue(
            app.staticTexts["summary.completed"].waitForExistence(timeout: 15), "summary tiles load from the API")
        for tab in ["Programs", "Workouts", "Profile", "Dashboard"] {
            app.tabBars.buttons[tab].tap()
            XCTAssertTrue(app.navigationBars[tab].waitForExistence(timeout: 5), "\(tab) tab renders")
        }

        app.tabBars.buttons["Programs"].tap()
        let firstProgram = app.buttons.matching(
            NSPredicate(format: "identifier BEGINSWITH 'program.' AND NOT identifier ENDSWITH '.enroll'")
        ).firstMatch
        XCTAssertTrue(firstProgram.waitForExistence(timeout: 10), "programs load from the API")
        firstProgram.tap()
        XCTAssertTrue(app.staticTexts["Program Workouts"].waitForExistence(timeout: 10))
        let firstTemplate = app.buttons.matching(NSPredicate(format: "identifier BEGINSWITH 'template.'")).firstMatch
        if firstTemplate.waitForExistence(timeout: 5) {
            firstTemplate.tap()
            XCTAssertTrue(app.buttons["template.start"].waitForExistence(timeout: 10))
            XCTAssertTrue(app.staticTexts["Activities"].exists)

            app.buttons["template.start"].tap()
            XCTAssertTrue(
                app.staticTexts["editor.progress"].waitForExistence(timeout: 15), "starting a workout opens the editor")
            XCTAssertTrue(app.buttons["editor.save"].exists)
            XCTAssertFalse(app.buttons["editor.save"].isEnabled, "nothing to save yet")
            app.buttons["activity.0.info"].tap()
            XCTAssertTrue(
                app.staticTexts["exerciseInfo.instructions"].waitForExistence(timeout: 5),
                "the info button shows how to do the exercise")
            app.buttons["exerciseInfo.done"].tap()
            app.buttons["activity.0.addSet"].tap()
            XCTAssertTrue(app.buttons["editor.save"].isEnabled, "adding a set makes the draft dirty")
            app.buttons["editor.save"].tap()
            XCTAssertTrue(app.staticTexts["toast"].waitForExistence(timeout: 15), "save confirms with a toast")

            app.buttons["editor.menu"].tap()
            XCTAssertTrue(app.buttons["editor.complete"].waitForExistence(timeout: 5))
            app.buttons["editor.complete"].tap()
            let confirm = app.buttons.matching(identifier: "editor.complete.confirm").firstMatch
            XCTAssertTrue(confirm.waitForExistence(timeout: 5))
            confirm.tap()
            XCTAssertTrue(
                app.buttons["detail.repeat"].waitForExistence(timeout: 15),
                "completing shows the workout detail with Repeat")
            XCTAssertFalse(app.buttons["detail.edit"].exists, "a completed workout is not editable")
        }

        app.tabBars.buttons["Workouts"].tap()
        let firstRow = app.buttons.matching(NSPredicate(format: "identifier BEGINSWITH 'workouts.row.'")).firstMatch
        XCTAssertTrue(firstRow.waitForExistence(timeout: 10), "history loads from the API")
        firstRow.tap()
        XCTAssertTrue(app.staticTexts["detail.status"].waitForExistence(timeout: 10))

        app.tabBars.buttons["Profile"].tap()
        XCTAssertTrue(app.textFields["profile.email"].waitForExistence(timeout: 5))
        XCTAssertEqual(app.textFields["profile.email"].value as? String, "test@example.com")
        let logout = app.buttons["main.logout"]
        var swipes = 0
        while !logout.exists, swipes < 6 {
            app.swipeUp()
            swipes += 1
        }
        XCTAssertTrue(logout.waitForExistence(timeout: 5), "Log Out is reachable by scrolling the profile form")
        logout.tap()
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
        let app = Self.launchSignedOut()
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
        let app = Self.launchSignedOut()
        XCTAssertTrue(app.buttons["welcome.login"].waitForExistence(timeout: 10))

        try open("myworkouts://verify-email/1/abc?expires=1&signature=s", into: app)

        let notice = app.staticTexts["welcome.notice"]
        XCTAssertTrue(notice.waitForExistence(timeout: 10))
        XCTAssertEqual(notice.label, "Sign in to finish verifying your email.")
    }
}
