import Foundation
import Observation
import WorkoutsAPI

/// The history tab: newest first, loading further pages as the user scrolls.
@MainActor
@Observable
public final class WorkoutListViewModel {
    public private(set) var state: LoadState<[Workout]> = .idle
    public private(set) var currentPage = 0
    public private(set) var lastPage = 1
    public private(set) var isLoadingMore = false
    public private(set) var deleteError: APIError?
    public private(set) var deletingId: Int?

    private let workouts: any WorkoutRepository

    public init(workouts: any WorkoutRepository) {
        self.workouts = workouts
    }

    public var hasMore: Bool { currentPage < lastPage }

    /// Loads page 1; a reload keeps the current content visible.
    public func load() async {
        if state.value == nil { state = .loading }
        do {
            let page = try await workouts.list(page: 1)
            state = .loaded(page.items)
            currentPage = page.currentPage
            lastPage = page.lastPage
        } catch {
            state = .failed(APIError.from(error))
        }
    }

    /// Appends the next page once; concurrent calls and the last page are no-ops.
    public func loadMoreIfNeeded(currentItem: Workout) async {
        guard let items = state.value, items.last?.id == currentItem.id else { return }
        await loadMore()
    }

    public func loadMore() async {
        guard hasMore, !isLoadingMore, var items = state.value else { return }
        isLoadingMore = true
        defer { isLoadingMore = false }
        do {
            let page = try await workouts.list(page: currentPage + 1)
            let known = Set(items.map(\.id))
            items.append(contentsOf: page.items.filter { !known.contains($0.id) })
            state = .loaded(items)
            currentPage = page.currentPage
            lastPage = page.lastPage
        } catch {
            deleteError = nil
            state = .loaded(items)  // keep what we have; the footer offers retry
            loadMoreError = APIError.from(error)
        }
    }

    public private(set) var loadMoreError: APIError?

    /// Removes the row immediately and puts it back if the server refuses.
    public func delete(id: Int) async {
        guard deletingId == nil, var items = state.value, let index = items.firstIndex(where: { $0.id == id }) else {
            return
        }
        deletingId = id
        deleteError = nil
        defer { deletingId = nil }
        let removed = items.remove(at: index)
        state = .loaded(items)
        do {
            try await workouts.delete(id: id)
        } catch {
            items.insert(removed, at: min(index, items.count))
            state = .loaded(items)
            deleteError = APIError.from(error)
        }
    }

    public func dismissErrors() {
        deleteError = nil
        loadMoreError = nil
    }
}
