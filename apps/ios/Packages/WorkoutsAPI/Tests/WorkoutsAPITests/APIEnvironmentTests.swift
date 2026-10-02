import Foundation
import Testing

@testable import WorkoutsAPI

@Suite("APIEnvironment")
struct APIEnvironmentTests {
    @Test("appends the v1 prefix to the origin")
    func v1URL() throws {
        let environment = APIEnvironment(baseURL: try #require(URL(string: "http://localhost:8080")))
        #expect(environment.apiV1URL.absoluteString == "http://localhost:8080/api/v1")
    }
}
