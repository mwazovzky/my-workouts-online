import Foundation
import Observation
import WorkoutsAPI

@MainActor
@Observable
public final class ProgramListViewModel {
    public private(set) var state: LoadState<[Program]> = .idle
    /// The web "Enrolled programs only" switch; filtering is client-side.
    public var enrolledOnly = false
    public private(set) var enrollingId: Int?
    public private(set) var enrollError: APIError?

    private let programs: any ProgramRepository

    public init(programs: any ProgramRepository) {
        self.programs = programs
    }

    public var visiblePrograms: [Program] {
        let all = state.value ?? []
        return enrolledOnly ? all.filter(\.isEnrolled) : all
    }

    public func load() async {
        if state.value == nil { state = .loading }
        do {
            state = .loaded(try await programs.programs())
        } catch {
            state = .failed(APIError.from(error))
        }
    }

    /// Flips the badge immediately and reverts if the server refuses.
    public func enroll(programId: Int) async {
        guard enrollingId == nil, var all = state.value, let index = all.firstIndex(where: { $0.id == programId })
        else { return }
        enrollingId = programId
        enrollError = nil
        defer { enrollingId = nil }
        all[index].isEnrolled = true
        state = .loaded(all)
        do {
            try await programs.enroll(programId: programId)
        } catch {
            all[index].isEnrolled = false
            state = .loaded(all)
            enrollError = APIError.from(error)
        }
    }

    public func dismissEnrollError() {
        enrollError = nil
    }
}

/// Templates of a program grouped the way the web detail page shows them: one section per
/// weekday in calendar order, then templates without a slot under "Not scheduled".
public struct WeekdaySection: Sendable, Equatable, Identifiable {
    public let weekday: Weekday?
    public let label: String?
    public let templates: [WorkoutTemplate]
    public var id: String { weekday?.rawValue ?? "unscheduled" }
}

@MainActor
@Observable
public final class ProgramDetailViewModel {
    public private(set) var state: LoadState<ProgramDetail> = .idle
    public private(set) var isEnrolling = false
    public private(set) var enrollError: APIError?

    private let programs: any ProgramRepository
    public let programId: Int

    public init(programs: any ProgramRepository, programId: Int) {
        self.programs = programs
        self.programId = programId
    }

    public var sections: [WeekdaySection] {
        guard let templates = state.value?.workoutTemplates else { return [] }
        var sections = Weekday.allCases.compactMap { weekday -> WeekdaySection? in
            let matching = templates.filter { $0.schedule?.weekday == weekday }
            guard !matching.isEmpty else { return nil }
            return WeekdaySection(weekday: weekday, label: matching.first?.schedule?.weekdayLabel, templates: matching)
        }
        let unscheduled = templates.filter { $0.schedule == nil }
        if !unscheduled.isEmpty {
            sections.append(WeekdaySection(weekday: nil, label: nil, templates: unscheduled))
        }
        return sections
    }

    public func load() async {
        if state.value == nil { state = .loading }
        do {
            state = .loaded(try await programs.program(id: programId))
        } catch {
            state = .failed(APIError.from(error))
        }
    }

    public func enroll() async {
        guard !isEnrolling, var detail = state.value, !detail.program.isEnrolled else { return }
        isEnrolling = true
        enrollError = nil
        defer { isEnrolling = false }
        detail.program.isEnrolled = true
        state = .loaded(detail)
        do {
            try await programs.enroll(programId: programId)
        } catch {
            detail.program.isEnrolled = false
            state = .loaded(detail)
            enrollError = APIError.from(error)
        }
    }

    public func dismissEnrollError() {
        enrollError = nil
    }
}

@MainActor
@Observable
public final class WorkoutTemplateViewModel {
    public private(set) var state: LoadState<WorkoutTemplate> = .idle
    public private(set) var isStarting = false
    public private(set) var startError: APIError?

    private let templates: any TemplateRepository
    private let workouts: any WorkoutRepository
    public let templateId: Int

    public init(templates: any TemplateRepository, workouts: any WorkoutRepository, templateId: Int) {
        self.templates = templates
        self.workouts = workouts
        self.templateId = templateId
    }

    public func load() async {
        if state.value == nil { state = .loading }
        do {
            state = .loaded(try await templates.template(id: templateId))
        } catch {
            state = .failed(APIError.from(error))
        }
    }

    /// Creates a workout from this template; returns it for navigation.
    public func start() async -> Workout? {
        guard !isStarting else { return nil }
        isStarting = true
        startError = nil
        defer { isStarting = false }
        do {
            return try await workouts.create(templateId: templateId)
        } catch {
            startError = APIError.from(error)
            return nil
        }
    }

    public func dismissStartError() {
        startError = nil
    }
}
