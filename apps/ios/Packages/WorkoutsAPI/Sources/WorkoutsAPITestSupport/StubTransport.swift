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
        /// Extra response headers, e.g. `["Retry-After": "42"]`.
        public let headers: [String: String]

        public init(
            status: Int, body: Data? = nil, contentType: String = "application/json", headers: [String: String] = [:]
        ) {
            self.status = status
            self.body = body
            self.contentType = contentType
            self.headers = headers
        }

        public static func json(_ status: Int, _ json: String) -> StubResponse {
            StubResponse(status: status, body: Data(json.utf8))
        }

        public static func json(_ status: Int, data: Data) -> StubResponse {
            StubResponse(status: status, body: data)
        }

        /// An RFC 9457 problem body, the shape of every non-2xx API response.
        public static func problem(_ status: Int, _ json: String, headers: [String: String] = [:]) -> StubResponse {
            StubResponse(
                status: status, body: Data(json.utf8), contentType: "application/problem+json", headers: headers)
        }

        /// A minimal `Problem` for `status` and `code`; `detail` defaults to the code.
        public static func problem(
            _ status: Int, code: String, detail: String? = nil, headers: [String: String] = [:]
        ) -> StubResponse {
            let detail = detail ?? code
            let json = """
                {"type":"https://my-workouts.online/problems/\(code)","title":"\(code)","status":\(status),\
                "detail":"\(detail)","instance":"/api/v1/test#00000000-0000-4000-8000-000000000000","code":"\(code)"}
                """
            return problem(status, json, headers: headers)
        }

        public static func empty(_ status: Int) -> StubResponse {
            StubResponse(status: status, body: nil, contentType: "")
        }
    }

    public enum StubError: Error {
        case noResponseQueued(operationID: String)
    }

    private var queue: [StubResponse]
    private var perOperation: [String: [StubResponse]] = [:]
    private var failures: [any Error] = []
    public private(set) var requests: [RecordedRequest] = []

    public init(responses: [StubResponse] = []) {
        queue = responses
    }

    public func enqueue(_ response: StubResponse) {
        queue.append(response)
    }

    /// Answers only requests for `operationID`, ahead of the shared queue. Lets concurrent
    /// tests script e.g. three `getMe` 401s and one `refresh` 200 deterministically.
    public func enqueue(_ response: StubResponse, for operationID: String) {
        perOperation[operationID, default: []].append(response)
    }

    public func requests(for operationID: String) -> [RecordedRequest] {
        requests.filter { $0.operationID == operationID }
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
        let stub: StubResponse
        if var scoped = perOperation[operationID], !scoped.isEmpty {
            stub = scoped.removeFirst()
            perOperation[operationID] = scoped
        } else if !queue.isEmpty {
            stub = queue.removeFirst()
        } else {
            throw StubError.noResponseQueued(operationID: operationID)
        }
        var response = HTTPResponse(status: .init(code: stub.status))
        for (name, value) in stub.headers {
            if let field = HTTPField.Name(name) {
                response.headerFields[field] = value
            }
        }
        if let responseBody = stub.body {
            response.headerFields[.contentType] = stub.contentType
            return (response, HTTPBody(responseBody))
        }
        return (response, nil)
    }
}
