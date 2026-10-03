import Foundation
import HTTPTypes
import OpenAPIRuntime

/// A scripted `ClientTransport` for tests: returns queued responses in order and records
/// every request it saw. Shared by the WorkoutsAPI and WorkoutsCore test targets.
public actor StubTransport: ClientTransport {
    public struct RecordedRequest: Sendable {
        public let request: HTTPRequest
        public let body: Data?
        public let operationID: String

        public var path: String { request.path ?? "" }
        public var method: HTTPRequest.Method { request.method }
        public func header(_ name: HTTPField.Name) -> String? { request.headerFields[name] }
    }

    public struct StubResponse: Sendable {
        public let status: Int
        public let body: Data?
        public let contentType: String

        public init(status: Int, body: Data? = nil, contentType: String = "application/json") {
            self.status = status
            self.body = body
            self.contentType = contentType
        }

        public static func json(_ status: Int, _ json: String) -> StubResponse {
            StubResponse(status: status, body: Data(json.utf8))
        }

        public static func json(_ status: Int, data: Data) -> StubResponse {
            StubResponse(status: status, body: data)
        }

        public static func empty(_ status: Int) -> StubResponse {
            StubResponse(status: status, body: nil, contentType: "")
        }
    }

    public enum StubError: Error {
        case noResponseQueued(operationID: String)
    }

    private var queue: [StubResponse]
    private var failures: [any Error] = []
    public private(set) var requests: [RecordedRequest] = []

    public init(responses: [StubResponse] = []) {
        queue = responses
    }

    public func enqueue(_ response: StubResponse) {
        queue.append(response)
    }

    /// The next `send` throws this error instead of answering (simulates the network failing).
    public func enqueueFailure(_ error: any Error) {
        failures.append(error)
    }

    public func send(
        _ request: HTTPRequest,
        body: HTTPBody?,
        baseURL: URL,
        operationID: String
    ) async throws -> (HTTPResponse, HTTPBody?) {
        let data: Data? =
            if let body { try await Data(collecting: body, upTo: 1024 * 1024) } else { nil }
        requests.append(RecordedRequest(request: request, body: data, operationID: operationID))

        if !failures.isEmpty {
            throw failures.removeFirst()
        }
        guard !queue.isEmpty else {
            throw StubError.noResponseQueued(operationID: operationID)
        }
        let stub = queue.removeFirst()
        var response = HTTPResponse(status: .init(code: stub.status))
        if let responseBody = stub.body {
            response.headerFields[.contentType] = stub.contentType
            return (response, HTTPBody(responseBody))
        }
        return (response, nil)
    }
}
