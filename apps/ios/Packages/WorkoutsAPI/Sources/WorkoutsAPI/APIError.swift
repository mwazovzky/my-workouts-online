import Foundation
import OpenAPIRuntime

/// One field's validation failure, as the API reports it: a stable `code` plus a message
/// already localized by `Accept-Language`.
public struct FieldError: Sendable, Equatable, Hashable {
    public let code: String
    public let message: String

    public init(code: String, message: String) {
        self.code = code
        self.message = message
    }
}

/// Every failure the app surfaces, mapped from the API's RFC 9457 `Problem` body, the 422
/// `ValidationProblem` shape, or the transport.
public enum APIError: Error, Sendable, Equatable {
    /// 422. `fields` is keyed by the dotted path of the rejected field, e.g.
    /// `activities.0.sets.1.effort_value` (converted from the API's JSON Pointer).
    case validation(message: String, fields: [String: [FieldError]])
    /// Any other documented or undocumented error status with a `Problem` body. `retryAfter` is
    /// the `Retry-After` header in seconds when the response carried one (429s).
    case api(status: Int, code: String, message: String, retryAfter: Int? = nil)
    /// A non-2xx response whose body was not a `Problem`.
    case unexpected(status: Int)
    /// The request never completed (offline, timeout, TLS).
    case network(description: String)
    /// The response did not match the contract.
    case decoding(description: String)
    /// Something failed on the device around the call (Keychain, file storage, mapping), not the API.
    case local(description: String)

    public var code: String? {
        switch self {
        case .validation: "validation_failed"
        case .api(_, let code, _, _): code
        case .unexpected, .network, .decoding, .local: nil
        }
    }

    public var status: Int? {
        switch self {
        case .validation: 422
        case .api(let status, _, _, _), .unexpected(let status): status
        case .network, .decoding, .local: nil
        }
    }

    /// The first message for a field, for inline form errors.
    public func message(for field: String) -> String? {
        guard case .validation(_, let fields) = self else { return nil }
        return fields[field]?.first?.message
    }
}

extension APIError {
    /// Maps a decoded 422 body. Each `pointer` becomes a dotted field path.
    public init(validation body: Components.Schemas.ValidationProblem) {
        var fields: [String: [FieldError]] = [:]
        for entry in body.errors {
            fields[Self.pointerToPath(entry.pointer), default: []]
                .append(FieldError(code: entry.code.rawValue, message: entry.detail))
        }
        self = .validation(message: body.detail, fields: fields)
    }

    /// Maps a documented error response whose body is a `Problem`.
    public init(status: Int, envelope: Components.Schemas.Problem, retryAfter: Int? = nil) {
        self = .api(status: status, code: envelope.code.rawValue, message: envelope.detail, retryAfter: retryAfter)
    }

    /// Maps an `undocumented` response by decoding a `Problem` from its body.
    public static func from(status: Int, payload: UndocumentedPayload) async -> APIError {
        guard let body = payload.body, let data = try? await Data(collecting: body, upTo: 64 * 1024) else {
            return .unexpected(status: status)
        }
        let retryAfter = payload.headerFields[.retryAfter].flatMap(Int.init)
        return from(status: status, data: data, retryAfter: retryAfter)
    }

    /// Maps raw `Problem` bytes (also used by middlewares that inspect a response). The body
    /// is read loosely so a `code` outside the contract's enum still surfaces as `.api`.
    public static func from(status: Int, data: Data, retryAfter: Int? = nil) -> APIError {
        guard let problem = try? JSONDecoder().decode(LooseProblem.self, from: data) else {
            return .unexpected(status: status)
        }
        return .api(status: status, code: problem.code, message: problem.detail, retryAfter: retryAfter)
    }

    /// Maps anything thrown by the generated client. `APIError`s pass through unchanged.
    public static func from(_ error: any Error) -> APIError {
        if let apiError = error as? APIError {
            return apiError
        }
        let underlying = (error as? ClientError)?.underlyingError ?? error
        if underlying is DecodingError {
            return .decoding(description: String(describing: underlying))
        }
        if let urlError = underlying as? URLError {
            return .network(description: urlError.localizedDescription)
        }
        if error is ClientError {
            return .network(description: String(describing: underlying))
        }
        return .local(description: String(describing: error))
    }

    /// `/activities/0/sets/1/effort_value` → `activities.0.sets.1.effort_value`, unescaping
    /// `~1` to `/` and `~0` to `~` per RFC 6901.
    static func pointerToPath(_ pointer: String) -> String {
        pointer.split(separator: "/", omittingEmptySubsequences: false)
            .dropFirst()
            .map { $0.replacingOccurrences(of: "~1", with: "/").replacingOccurrences(of: "~0", with: "~") }
            .joined(separator: ".")
    }

    /// The `Problem` fields the client needs, decoded without the contract's `code` enum so an
    /// unknown code does not turn a readable problem into `.unexpected`.
    struct LooseProblem: Decodable {
        let detail: String
        let code: String
    }
}
