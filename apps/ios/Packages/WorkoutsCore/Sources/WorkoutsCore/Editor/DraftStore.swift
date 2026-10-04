import Foundation

/// Where unsaved editor drafts live between launches. One file per workout.
public protocol DraftStore: Sendable {
    func load(workoutId: Int) async throws -> WorkoutDraft?
    func save(_ draft: WorkoutDraft) async throws
    func delete(workoutId: Int) async throws
    func deleteAll() async throws
}

/// JSON files under Application Support/Drafts, protected until first unlock like the Keychain.
public actor FileDraftStore: DraftStore {
    private let directory: URL
    private let encoder = JSONEncoder()
    private let decoder = JSONDecoder()

    public init(directory: URL? = nil) {
        let base = directory ?? FileManager.default.urls(for: .applicationSupportDirectory, in: .userDomainMask)[0]
        self.directory = base.appending(path: "Drafts", directoryHint: .isDirectory)
        encoder.dateEncodingStrategy = .iso8601
        decoder.dateDecodingStrategy = .iso8601
    }

    public func load(workoutId: Int) throws -> WorkoutDraft? {
        let url = fileURL(workoutId)
        guard FileManager.default.fileExists(atPath: url.path()) else { return nil }
        return try decoder.decode(WorkoutDraft.self, from: try Data(contentsOf: url))
    }

    public func save(_ draft: WorkoutDraft) throws {
        try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true)
        let data = try encoder.encode(draft)
        var options: Data.WritingOptions = [.atomic]
        #if os(iOS)
            options.insert(.completeFileProtectionUntilFirstUserAuthentication)
        #endif
        try data.write(to: fileURL(draft.workoutId), options: options)
    }

    public func delete(workoutId: Int) throws {
        let url = fileURL(workoutId)
        guard FileManager.default.fileExists(atPath: url.path()) else { return }
        try FileManager.default.removeItem(at: url)
    }

    public func deleteAll() throws {
        guard FileManager.default.fileExists(atPath: directory.path()) else { return }
        try FileManager.default.removeItem(at: directory)
    }

    private func fileURL(_ workoutId: Int) -> URL {
        directory.appending(path: "workout-\(workoutId).json")
    }
}

public actor InMemoryDraftStore: DraftStore {
    public private(set) var drafts: [Int: WorkoutDraft] = [:]
    public private(set) var saveCount = 0

    public init(_ drafts: [WorkoutDraft] = []) {
        for draft in drafts { self.drafts[draft.workoutId] = draft }
    }

    public func load(workoutId: Int) -> WorkoutDraft? { drafts[workoutId] }
    public func save(_ draft: WorkoutDraft) {
        drafts[draft.workoutId] = draft
        saveCount += 1
    }
    public func delete(workoutId: Int) { drafts[workoutId] = nil }
    public func deleteAll() { drafts = [:] }
}
