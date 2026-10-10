import Foundation
import WorkoutsAPI

public protocol DashboardRepository: Sendable {
    func dashboard() async throws -> Dashboard
}

public struct APIDashboardRepository: DashboardRepository {
    private let client: Client

    public init(client: Client) {
        self.client = client
    }

    public func dashboard() async throws -> Dashboard {
        let output = try await client.getDashboard()
        switch output {
        case .ok(let response): return try Dashboard(api: try response.body.json.data)
        case .unauthorized(let response):
            throw APIError(status: 401, envelope: try response.body.applicationProblemJson)
        case .forbidden(let response): throw APIError(status: 403, envelope: try response.body.applicationProblemJson)
        case .undocumented(let status, let payload): throw await APIError.from(status: status, payload: payload)
        }
    }
}
