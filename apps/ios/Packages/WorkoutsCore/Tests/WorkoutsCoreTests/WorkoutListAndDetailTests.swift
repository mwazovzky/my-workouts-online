import Foundation
import Testing
import WorkoutsAPI

@testable import WorkoutsCore

private func workout(_ id: Int, status: WorkoutStatus = .completed, userId: Int = 1) -> Workout {
    Workout(
        id: id, userId: userId, name: "W\(id)", status: status, createdAt: nil,
        updatedAt: nil,
        activitiesCount: 1, template: nil, activities: [])
}

@Suite("WorkoutListViewModel")
@MainActor
struct WorkoutListViewModelTests {
    private func repo(pages: Int) async -> FakeWorkoutRepository {
        let repo = FakeWorkoutRepository()
        var all: [Int: Page<Workout>] = [:]
        for page in 1...pages {
            all[page] = Page(
                items: [workout(page * 10), workout(page * 10 + 1)], currentPage: page, lastPage: pages,
                total: pages * 2)
        }
        await repo.set(pages: all)
        return repo
    }

    @Test("loads page 1 and appends later pages exactly once each, stopping at the last")
    func pagination() async {
        let repo = await repo(pages: 2)
        let model = WorkoutListViewModel(workouts: repo)
        await model.load()
        #expect(model.state.value?.map(\.id) == [10, 11])
        #expect(model.hasMore)

        await model.loadMoreIfNeeded(currentItem: workout(10))
        #expect(await repo.listCalls == [1], "only the last visible row triggers a page load")

        await model.loadMoreIfNeeded(currentItem: workout(11))
        #expect(model.state.value?.map(\.id) == [10, 11, 20, 21])
        #expect(!model.hasMore)

        await model.loadMoreIfNeeded(currentItem: workout(21))
        #expect(await repo.listCalls == [1, 2], "the last page stops loading")
    }

    @Test("a failed page load keeps the rows and reports; reload resets to page 1")
    func loadMoreFailure() async {
        let repo = await repo(pages: 3)
        let model = WorkoutListViewModel(workouts: repo)
        await model.load()
        await repo.set(listError: .network(description: "offline"))
        await model.loadMore()
        #expect(model.state.value?.count == 2)
        #expect(model.loadMoreError == .network(description: "offline"))

        await repo.set(listError: nil)
        await model.load()
        #expect(model.currentPage == 1)
        #expect(model.loadMoreError == .network(description: "offline"), "reload does not clear the banner by itself")
        model.dismissErrors()
        #expect(model.loadMoreError == nil)
    }

    @Test("delete removes the row immediately and rolls back on failure")
    func delete() async {
        let repo = await repo(pages: 1)
        let model = WorkoutListViewModel(workouts: repo)
        await model.load()

        await model.delete(id: 10)
        #expect(model.state.value?.map(\.id) == [11])
        #expect(await repo.deleteCalls == [10])

        await repo.set(deleteError: .api(status: 404, code: "not_found", message: "Record not found."))
        await model.delete(id: 11)
        #expect(model.state.value?.map(\.id) == [11], "rolled back into place")
        #expect(model.deleteError?.status == 404)
    }

    @Test("an empty history loads to an empty list, not an error")
    func empty() async {
        let repo = FakeWorkoutRepository()
        await repo.set(pages: [1: Page(items: [], currentPage: 1, lastPage: 1, total: 0)])
        let model = WorkoutListViewModel(workouts: repo)
        await model.load()
        #expect(model.state.value?.isEmpty == true)
        #expect(!model.hasMore)
    }
}

@Suite("WorkoutDetailViewModel")
@MainActor
struct WorkoutDetailViewModelTests {
    private func make(_ stored: Workout, currentUserId: Int = 1) async -> (
        WorkoutDetailViewModel, FakeWorkoutRepository
    ) {
        let repo = FakeWorkoutRepository()
        await repo.set(stored: stored)
        let model = WorkoutDetailViewModel(workouts: repo, workoutId: stored.id, currentUserId: currentUserId)
        await model.load()
        return (model, repo)
    }

    @Test("action visibility follows ownership and status")
    func actions() async {
        let (ownInProgress, _) = await make(workout(1, status: .inProgress))
        #expect(ownInProgress.canEdit && !ownInProgress.canRepeat)

        let (ownCompleted, _) = await make(workout(2, status: .completed))
        #expect(!ownCompleted.canEdit && ownCompleted.canRepeat)

        let (foreignInProgress, _) = await make(workout(3, status: .inProgress, userId: 9))
        #expect(!foreignInProgress.canEdit && !foreignInProgress.canRepeat)

        let (foreignCompleted, _) = await make(workout(4, status: .completed, userId: 9))
        #expect(!foreignCompleted.canEdit && !foreignCompleted.canRepeat)
    }

    @Test("repeat returns the new in-progress workout; it is refused for in-progress ones")
    func repeatWorkout() async {
        let (completed, repo) = await make(workout(2, status: .completed))
        let repeated = await completed.repeatWorkout()
        #expect(repeated?.status == .inProgress)
        #expect(repeated?.id == 1002)
        #expect(await repo.repeatCalls == 1)

        let (inProgress, repo2) = await make(workout(1, status: .inProgress))
        #expect(await inProgress.repeatWorkout() == nil)
        #expect(await repo2.repeatCalls == 0)
    }

    @Test("a missing workout is a load failure")
    func notFound() async {
        let model = WorkoutDetailViewModel(workouts: FakeWorkoutRepository(), workoutId: 404, currentUserId: 1)
        await model.load()
        #expect(model.state.error?.status == 404)
    }
}
