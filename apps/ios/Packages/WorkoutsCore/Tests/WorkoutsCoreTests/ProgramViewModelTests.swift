import Foundation
import Testing
import WorkoutsAPI

@testable import WorkoutsCore

actor FakeProgramRepository: ProgramRepository {
    var list: [Program]
    var detail: ProgramDetail?
    var enrollError: APIError?
    private(set) var enrollCalls: [Int] = []

    init(list: [Program] = [], detail: ProgramDetail? = nil) {
        self.list = list
        self.detail = detail
    }

    func set(enrollError: APIError?) { self.enrollError = enrollError }

    func programs() async throws -> [Program] { list }

    func program(id: Int) async throws -> ProgramDetail {
        guard let detail else { throw APIError.api(status: 404, code: "not_found", message: "Record not found.") }
        return detail
    }

    func enroll(programId: Int) async throws {
        enrollCalls.append(programId)
        if let enrollError { throw enrollError }
    }
}

actor FakeTemplateRepository: TemplateRepository {
    var template: WorkoutTemplate?

    init(template: WorkoutTemplate? = nil) {
        self.template = template
    }

    func template(id: Int) async throws -> WorkoutTemplate {
        guard let template else { throw APIError.api(status: 404, code: "not_found", message: "Record not found.") }
        return template
    }
}

private let strength = Program(id: 1, name: "Strength", description: "Lift", isEnrolled: false)
private let cardio = Program(id: 2, name: "Cardio", description: nil, isEnrolled: true)

private func template(_ id: Int, weekday: Weekday?) -> WorkoutTemplate {
    WorkoutTemplate(
        id: id, name: "T\(id)", description: nil, activities: [],
        schedule: weekday.map { ScheduleSlot(weekday: $0, weekdayLabel: $0.rawValue) })
}

@Suite("ProgramListViewModel")
@MainActor
struct ProgramListViewModelTests {
    @Test("loads and filters to enrolled programs client-side")
    func loadAndFilter() async {
        let model = ProgramListViewModel(programs: FakeProgramRepository(list: [strength, cardio]))
        await model.load()
        #expect(model.visiblePrograms.map(\.id) == [1, 2])
        model.enrolledOnly = true
        #expect(model.visiblePrograms.map(\.id) == [2])
    }

    @Test("enroll flips the badge immediately and keeps it on success")
    func enrollSuccess() async {
        let repo = FakeProgramRepository(list: [strength, cardio])
        let model = ProgramListViewModel(programs: repo)
        await model.load()

        await model.enroll(programId: 1)

        #expect(model.state.value?.first?.isEnrolled == true)
        #expect(await repo.enrollCalls == [1])
        #expect(model.enrollError == nil)
        #expect(model.enrollingId == nil)
    }

    @Test("a failed enroll reverts the badge and reports the error")
    func enrollRollback() async {
        let repo = FakeProgramRepository(list: [strength])
        await repo.set(enrollError: .api(status: 404, code: "not_found", message: "Record not found."))
        let model = ProgramListViewModel(programs: repo)
        await model.load()

        await model.enroll(programId: 1)

        #expect(model.state.value?.first?.isEnrolled == false)
        #expect(model.enrollError == .api(status: 404, code: "not_found", message: "Record not found."))
        model.dismissEnrollError()
        #expect(model.enrollError == nil)
    }

    @Test("an unverified user gets the 403 as a load failure")
    func unverified() async {
        actor Forbidden: ProgramRepository {
            func programs() async throws -> [Program] {
                throw APIError.api(status: 403, code: "email_unverified", message: "Verify")
            }
            func program(id: Int) async throws -> ProgramDetail { throw APIError.unexpected(status: 500) }
            func enroll(programId: Int) async throws {}
        }
        let model = ProgramListViewModel(programs: Forbidden())
        await model.load()
        #expect(model.state.error?.code == "email_unverified")
    }
}

@Suite("ProgramDetailViewModel")
@MainActor
struct ProgramDetailViewModelTests {
    @Test("templates are grouped by weekday in calendar order, unscheduled last")
    func sections() async {
        let detail = ProgramDetail(
            program: strength,
            workoutTemplates: [
                template(3, weekday: .friday), template(1, weekday: .monday), template(9, weekday: nil),
                template(2, weekday: .monday),
            ])
        let model = ProgramDetailViewModel(programs: FakeProgramRepository(detail: detail), programId: 1)
        await model.load()

        let sections = model.sections
        #expect(sections.map(\.id) == ["Monday", "Friday", "unscheduled"])
        #expect(sections[0].templates.map(\.id) == [1, 2])
        #expect(sections[2].label == nil)
    }

    @Test("enroll is optimistic and rolls back on failure")
    func enroll() async {
        let repo = FakeProgramRepository(detail: ProgramDetail(program: strength, workoutTemplates: []))
        let model = ProgramDetailViewModel(programs: repo, programId: 1)
        await model.load()

        await model.enroll()
        #expect(model.state.value?.program.isEnrolled == true)

        await repo.set(enrollError: .network(description: "offline"))
        let fresh = ProgramDetailViewModel(programs: repo, programId: 1)
        await fresh.load()
        await fresh.enroll()
        #expect(fresh.state.value?.program.isEnrolled == false)
        #expect(fresh.enrollError == .network(description: "offline"))
    }

    @Test("a missing program is a load failure")
    func notFound() async {
        let model = ProgramDetailViewModel(programs: FakeProgramRepository(), programId: 404)
        await model.load()
        #expect(model.state.error?.status == 404)
    }
}

@Suite("WorkoutTemplateViewModel")
@MainActor
struct WorkoutTemplateViewModelTests {
    @Test("loads the template and starts a workout from it")
    func loadAndStart() async {
        let workouts = FakeWorkoutRepository()
        let model = WorkoutTemplateViewModel(
            templates: FakeTemplateRepository(template: template(5, weekday: .monday)), workouts: workouts,
            templateId: 5)
        await model.load()
        #expect(model.state.value?.id == 5)

        let started = await model.start()
        #expect(started?.id == 105)
        #expect(await workouts.createdTemplateIds == [5])
    }

    @Test("a failed start reports the error and returns nil")
    func startFails() async {
        let workouts = FakeWorkoutRepository()
        await workouts.set(createError: .validation(message: "Template required.", fields: [:]))
        let model = WorkoutTemplateViewModel(
            templates: FakeTemplateRepository(template: template(5, weekday: nil)), workouts: workouts, templateId: 5)
        await model.load()
        #expect(await model.start() == nil)
        #expect(model.startError == .validation(message: "Template required.", fields: [:]))
    }
}
