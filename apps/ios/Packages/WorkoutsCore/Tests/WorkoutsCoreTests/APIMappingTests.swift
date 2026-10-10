import Foundation
import Testing
import WorkoutsAPI
import WorkoutsAPITestSupport

@testable import WorkoutsCore

@Suite("API fixtures decode through the generated client and map to domain models")
struct APIMappingTests {
    @Test("me")
    func me() async throws {
        let user = try User(api: try await Fixtures.client(returning: "me").getMe().ok.body.json.data)
        #expect(user.email == "test@example.com")
        #expect(user.isAdmin)
        #expect(user.emailVerified)
        #expect(["en", "ru"].contains(user.locale))
    }

    @Test("meta")
    func meta() async throws {
        let meta = try Meta(api: try await Fixtures.client(returning: "meta").getMeta().ok.body.json.data)
        #expect(meta.availableLocales.map(\.code) == ["en", "ru"])
        #expect(meta.availableLocales.first?.name == "English")
        #expect(meta.themePreferences == [.light, .dark, .system])
    }

    @Test("login 401 maps to invalid_credentials with the detail as the message")
    func loginInvalidCredentials() async throws {
        let transport = StubTransport(responses: [
            .problem(401, code: "invalid_credentials", detail: "These credentials do not match our records.")
        ])
        let repository = APIAuthRepository(
            client: Fixtures.client(transport: transport), makeClient: { _ in Fixtures.client(transport: transport) },
            deviceName: "test")
        await #expect(
            throws: APIError.api(
                status: 401, code: "invalid_credentials", message: "These credentials do not match our records.")
        ) {
            try await repository.login(email: "test@example.com", password: "nope")
        }
    }

    @Test("login 429 carries the Retry-After seconds")
    func loginLockout() async throws {
        let transport = StubTransport(responses: [
            .problem(429, code: "too_many_attempts", detail: "Slow down", headers: ["Retry-After": "42"])
        ])
        let repository = APIAuthRepository(
            client: Fixtures.client(transport: transport), makeClient: { _ in Fixtures.client(transport: transport) },
            deviceName: "test")
        await #expect(
            throws: APIError.api(status: 429, code: "too_many_attempts", message: "Slow down", retryAfter: 42)
        ) {
            try await repository.login(email: "test@example.com", password: "nope")
        }
    }

    @Test("login grant carries both tokens for a native client")
    func authGrant() async throws {
        let output = try await Fixtures.client(returning: "auth-login")
            .login(body: .json(.init(email: "test@example.com", password: "password", client: .native)))
        let grant = try AuthGrant(api: try output.ok.body.json)
        #expect(grant.accessToken == "eyJ.fixture.access")
        #expect(grant.refreshToken?.count == 64)
        #expect(grant.expiresIn == 900)
        #expect(grant.user.email == "test@example.com")
    }

    @Test("dashboard")
    func dashboard() async throws {
        let dashboard = try Dashboard(
            api: try await Fixtures.client(returning: "dashboard").getDashboard().ok.body.json.data)
        #expect(!dashboard.upcomingSchedule.isEmpty)
        #expect(dashboard.summary.enrolledProgramsCount >= 1)
        #expect(dashboard.recentWorkouts.count <= 5)
        let first = try #require(dashboard.upcomingSchedule.first)
        #expect(first.scheduledFor.count == 10)
        #expect(Weekday.allCases.contains(first.weekday))
    }

    @Test("programs and program detail")
    func programs() async throws {
        let programs = try await Fixtures.client(returning: "programs").listPrograms().ok.body.json.data.map(
            Program.init(api:))
        #expect(programs.count >= 2)
        #expect(programs.allSatisfy { !$0.name.isEmpty })

        let detail = try ProgramDetail(
            api: try await Fixtures.client(returning: "program-detail").getProgram(path: .init(programId: 1)).ok.body
                .json
                .data)
        #expect(!detail.workoutTemplates.isEmpty)
        let template = try #require(detail.workoutTemplates.first)
        #expect(template.schedule != nil, "templates inside a program carry their weekday")
    }

    @Test("workout template with activities and sets")
    func workoutTemplate() async throws {
        let template = try WorkoutTemplate(
            api: try await Fixtures.client(returning: "workout-template").getWorkoutTemplate(
                path: .init(workoutTemplateId: 1)
            ).ok.body
                .json.data)
        #expect(!template.activities.isEmpty)
        let activity = try #require(template.activities.first)
        #expect(activity.effortType != nil)
        #expect(activity.effortType != nil, "template activities carry the exercise snapshot")
        #expect(!activity.sets.isEmpty)
    }

    @Test("exercises carry the two-axis measurement metadata")
    func exercises() async throws {
        let exercises = try await Fixtures.client(returning: "exercises").listExercises().ok.body.json.data.map(
            Exercise.init(api:))
        #expect(exercises.count > 20)
        #expect(exercises.contains { $0.difficultyUnit == .heartRateZone })
        #expect(exercises.contains { $0.difficultyUnit == .none })
        #expect(exercises.contains { $0.effortType == .duration })
    }

    @Test("paginated workouts")
    func workoutsPage() async throws {
        let page = try Page<Workout>(
            api: try await Fixtures.client(returning: "workouts-page").listWorkouts(query: .init(page: 1)).ok.body.json)
        #expect(page.currentPage == 1)
        #expect(!page.items.isEmpty)
        #expect(page.lastPage >= 1)
        #expect(page.hasMore == (page.lastPage > 1))
    }

    @Test("a workout with its template, activities, sets, dates and ETag")
    func workout() async throws {
        let transport = StubTransport(responses: [
            .init(status: 200, body: try Fixtures.data("workout"), headers: ["ETag": "\"1-3\""])
        ])
        let workout = try await APIWorkoutRepository(client: Fixtures.client(transport: transport)).get(id: 1)
        #expect(workout.etag == "\"1-3\"")
        #expect(workout.template != nil)
        #expect(workout.activities.count == 2)
        #expect(workout.status == .completed)
        #expect(workout.createdAt != nil, "API dates with microseconds must decode")
        #expect(workout.totalSetCount > 0)
        #expect(workout.completedSetCount <= workout.totalSetCount)
    }

    @Test("replaceActivities sends If-Match on PUT …/activities and maps 412 and 428 to problems")
    func replaceActivities() async throws {
        let transport = StubTransport(responses: [
            .init(status: 200, body: try Fixtures.data("workout"), headers: ["ETag": "\"1-4\""]),
            .problem(412, code: "precondition_failed"),
            .problem(428, code: "precondition_required"),
        ])
        let repository = APIWorkoutRepository(client: Fixtures.client(transport: transport))
        let payload = WorkoutSavePayload(activities: [
            .init(
                id: 5, exerciseId: 7, order: 1,
                sets: [.init(id: nil, order: 1, effortValue: 10, difficultyValue: nil, isCompleted: false)])
        ])

        let saved = try await repository.replaceActivities(id: 1, ifMatch: "\"1-3\"", payload: payload)
        #expect(saved.etag == "\"1-4\"")
        let request = try #require(await transport.requests.first)
        #expect(request.method == .put)
        #expect(request.path == "/api/v1/workouts/1/activities")
        #expect(request.header(.ifMatch) == "\"1-3\"")

        await #expect(throws: APIError.api(status: 412, code: "precondition_failed", message: "precondition_failed")) {
            try await repository.replaceActivities(id: 1, ifMatch: "\"1-3\"", payload: payload)
        }
        await #expect(
            throws: APIError.api(status: 428, code: "precondition_required", message: "precondition_required")
        ) {
            try await repository.replaceActivities(id: 1, ifMatch: "", payload: payload)
        }
    }

    @Test("an omitted optional object decodes as nil")
    func omittedOptionalObject() async throws {
        let json = """
            {"data":{"upcoming_schedule":[],"recent_workouts":[],"summary":{"enrolled_programs_count":0,
            "completed_workouts_count":0,"completed_last_7_days_count":0,"upcoming_workouts_count":0}}}
            """
        let transport = StubTransport(responses: [.json(200, json)])
        let client = Client(
            serverURL: URL(string: "http://localhost:8080")!, configuration: APIClientFactory.configuration,
            transport: transport, middlewares: [])
        let dashboard = try Dashboard(api: try await client.getDashboard().ok.body.json.data)
        #expect(dashboard.inProgressWorkout == nil)
        #expect(dashboard.summary.enrolledProgramsCount == 0)
    }

    @Test("error fixtures map to APIError")
    func errors() async throws {
        let unauthorized = try await Fixtures.client(returning: "error-401", status: 401).getMe()
        guard case .unauthorized(let response) = unauthorized else {
            Issue.record("expected 401, got \(unauthorized)")
            return
        }
        #expect(APIError(status: 401, envelope: try response.body.applicationProblemJson).code == "unauthenticated")

        let notFound = try await Fixtures.client(returning: "error-404", status: 404).getWorkout(
            path: .init(workoutId: 999_999))
        guard case .notFound(let nf) = notFound else {
            Issue.record("expected 404, got \(notFound)")
            return
        }
        #expect(
            APIError(status: 404, envelope: try nf.body.applicationProblemJson)
                == .api(status: 404, code: "not_found", message: "Record not found."))

        let invalid = try await Fixtures.client(returning: "error-422", status: 422)
            .createWorkout(body: .json(.init(workoutTemplateId: 0)))
        guard case .unprocessableContent(let v) = invalid else {
            Issue.record("expected 422, got \(invalid)")
            return
        }
        let error = APIError(validation: try v.body.applicationProblemJson)
        #expect(error.message(for: "workout_template_id") == "A workout template is required to start a workout.")
        #expect(
            error.message(for: "activities.0.sets.1.effort_value") == "The effort value must be between 0 and 100000.")
        guard case .validation(let message, let fields) = error else {
            Issue.record("expected .validation, got \(error)")
            return
        }
        #expect(message.hasPrefix("A workout template is required"))
        #expect(fields["activities.0.sets.1.effort_value"]?.first?.code == "out_of_range")
    }

    @Test("updateMe sends a merge patch: PATCH /me with only the fields that are set")
    func updateMeMergePatch() async throws {
        let transport = StubTransport(responses: [.json(200, data: try Fixtures.data("me"))])
        let repository = APIMeRepository(client: Fixtures.client(transport: transport))

        _ = try await repository.updateMe(MePatch(locale: "ru"))

        let request = try #require(await transport.requests(for: "updateMe").first)
        #expect(request.method == .patch)
        #expect(request.path == "/api/v1/me")
        let body = try #require(request.body)
        let json = try #require(try JSONSerialization.jsonObject(with: body) as? [String: Any])
        #expect(json.keys.sorted() == ["locale"])
        #expect(json["locale"] as? String == "ru")
    }

    @Test("updatePassword sends no confirmation field")
    func updatePasswordBody() async throws {
        let transport = StubTransport(responses: [.empty(204)])
        let repository = APIMeRepository(client: Fixtures.client(transport: transport))

        try await repository.updatePassword(current: "old", new: "newpassword1")

        let request = try #require(await transport.requests(for: "updatePassword").first)
        #expect(request.method == .put)
        #expect(request.path == "/api/v1/me/password")
        let passwordBody = try #require(request.body)
        let json = try #require(try JSONSerialization.jsonObject(with: passwordBody) as? [String: Any])
        #expect(json.keys.sorted() == ["current_password", "password"])
    }
}
