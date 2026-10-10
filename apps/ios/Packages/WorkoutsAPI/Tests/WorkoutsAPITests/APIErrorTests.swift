import Foundation
import OpenAPIRuntime
import Testing
import WorkoutsAPITestSupport

@testable import WorkoutsAPI

@Suite("APIError")
struct APIErrorTests {
    private let baseURL = URL(string: "http://localhost:8080")!

    @Test("a 422 maps to per-field errors keyed by the dotted path of each pointer")
    func validation() async throws {
        let body = """
            {"type":"https://my-workouts.online/problems/validation_failed","title":"Validation failed","status":422,
             "detail":"The given data was invalid.","instance":"/api/v1/workouts#abc","code":"validation_failed",
             "errors":[
               {"pointer":"/activities/0/sets/1/effort_value","code":"out_of_range","detail":"Effort must be at least 0."},
               {"pointer":"/workout_template_id","code":"required","detail":"A workout template is required."},
               {"pointer":"/workout_template_id","code":"invalid_format","detail":"Must be an integer."}
             ]}
            """
        let transport = StubTransport(responses: [.problem(422, body)])
        let output = try await Client(
            serverURL: baseURL, configuration: APIClientFactory.configuration, transport: transport, middlewares: []
        )
        .createWorkout(body: .json(.init(workoutTemplateId: 1)))
        guard case .unprocessableContent(let response) = output else {
            Issue.record("expected 422, got \(output)")
            return
        }
        let error = APIError(validation: try response.body.applicationProblemJson)

        #expect(error.code == "validation_failed")
        #expect(error.status == 422)
        #expect(error.message(for: "activities.0.sets.1.effort_value") == "Effort must be at least 0.")
        #expect(error.message(for: "workout_template_id") == "A workout template is required.")
        guard case .validation(let message, let fields) = error else {
            Issue.record("wrong case")
            return
        }
        #expect(message == "The given data was invalid.")
        #expect(fields["workout_template_id"]?.map(\.code) == ["required", "invalid_format"])
    }

    @Test("JSON Pointers become dotted paths, unescaping ~1 and ~0")
    func pointers() {
        #expect(APIError.pointerToPath("/email") == "email")
        #expect(APIError.pointerToPath("/activities/0/sets/1/effort_value") == "activities.0.sets.1.effort_value")
        #expect(APIError.pointerToPath("/translations/en/name") == "translations.en.name")
        #expect(APIError.pointerToPath("/a~1b/c~0d") == "a/b.c~d")
        #expect(APIError.pointerToPath("") == "")
    }

    @Test("a documented problem maps to .api with its detail as the message")
    func documentedProblem() async throws {
        let transport = StubTransport(responses: [.problem(404, code: "not_found", detail: "Record not found.")])
        let output = try await Client(
            serverURL: baseURL, configuration: APIClientFactory.configuration, transport: transport, middlewares: []
        )
        .getWorkout(path: .init(workoutId: 999))
        guard case .notFound(let response) = output else {
            Issue.record("expected 404, got \(output)")
            return
        }
        let error = APIError(status: 404, envelope: try response.body.applicationProblemJson)
        #expect(error == .api(status: 404, code: "not_found", message: "Record not found."))
    }

    @Test("an undocumented status with a problem body maps to .api, without it to .unexpected")
    func undocumented() async {
        let withProblem = await APIError.from(
            status: 503,
            payload: UndocumentedPayload(
                headerFields: [:],
                body: HTTPBody(
                    #"{"type":"https://my-workouts.online/problems/maintenance","title":"Down","status":503,"detail":"Down","code":"maintenance"}"#
                ))
        )
        #expect(withProblem == .api(status: 503, code: "maintenance", message: "Down"))

        let html = await APIError.from(
            status: 502, payload: UndocumentedPayload(headerFields: [:], body: HTTPBody("<html>")))
        #expect(html == .unexpected(status: 502))

        let empty = await APIError.from(status: 500, payload: UndocumentedPayload(headerFields: [:], body: nil))
        #expect(empty == .unexpected(status: 500))
    }

    @Test("transport failures map to .network and bad payloads to .decoding")
    func thrownErrors() async throws {
        let offline = StubTransport()
        await offline.enqueueFailure(URLError(.notConnectedToInternet))
        do {
            _ = try await Client(
                serverURL: baseURL, configuration: APIClientFactory.configuration, transport: offline, middlewares: []
            ).getMeta()
            Issue.record("expected a throw")
        } catch {
            guard case .network = APIError.from(error) else {
                Issue.record("expected .network, got \(APIError.from(error))")
                return
            }
        }

        let garbage = StubTransport(responses: [.json(200, #"{"data":"not-an-object"}"#)])
        do {
            _ = try await Client(
                serverURL: baseURL, configuration: APIClientFactory.configuration, transport: garbage, middlewares: []
            ).getMeta()
            Issue.record("expected a throw")
        } catch {
            guard case .decoding = APIError.from(error) else {
                Issue.record("expected .decoding, got \(APIError.from(error))")
                return
            }
        }

        #expect(APIError.from(APIError.unexpected(status: 418)) == .unexpected(status: 418))

        struct KeychainFailure: Error {}
        guard case .local = APIError.from(KeychainFailure()) else {
            Issue.record("a non-transport error must map to .local, not .network")
            return
        }
    }
}
