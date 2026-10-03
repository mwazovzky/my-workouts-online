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
            api: try await Fixtures.client(returning: "program-detail").getProgram(path: .init(program: 1)).ok.body.json
                .data)
        #expect(!detail.workoutTemplates.isEmpty)
        let template = try #require(detail.workoutTemplates.first)
        #expect(template.schedule != nil, "templates inside a program carry their weekday")
    }

    @Test("workout template with activities and sets")
    func workoutTemplate() async throws {
        let template = try WorkoutTemplate(
            api: try await Fixtures.client(returning: "workout-template").getWorkoutTemplate(path: .init(id: 1)).ok.body
                .json.data)
        #expect(!template.activities.isEmpty)
        let activity = try #require(template.activities.first)
        #expect(activity.effortType != nil)
        #expect(!activity.effortLabel.isEmpty)
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
        #expect(exercises.allSatisfy { !$0.effortLabel.isEmpty })
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

    @Test("a workout with its template, activities, sets and dates")
    func workout() async throws {
        let workout = try Workout(
            api: try await Fixtures.client(returning: "workout").getWorkout(path: .init(workout: 1)).ok.body.json.data)
        #expect(workout.template != nil)
        #expect(workout.activities.count == 2)
        #expect(workout.status == .completed)
        #expect(workout.createdAt != nil, "API dates with microseconds must decode")
        #expect(workout.totalSetCount > 0)
        #expect(workout.completedSetCount <= workout.totalSetCount)
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
        #expect(APIError(status: 401, envelope: try response.body.json).code == "unauthenticated")

        let notFound = try await Fixtures.client(returning: "error-404", status: 404).getWorkout(
            path: .init(workout: 999_999))
        guard case .notFound(let nf) = notFound else {
            Issue.record("expected 404, got \(notFound)")
            return
        }
        #expect(
            APIError(status: 404, envelope: try nf.body.json)
                == .api(status: 404, code: "not_found", message: "Record not found."))

        let invalid = try await Fixtures.client(returning: "error-422", status: 422)
            .createWorkout(body: .json(.init(workoutTemplateId: 0)))
        guard case .unprocessableContent(let v) = invalid else {
            Issue.record("expected 422, got \(invalid)")
            return
        }
        let error = APIError(validation: try v.body.json)
        #expect(error.message(for: "workout_template_id") != nil)
    }
}
