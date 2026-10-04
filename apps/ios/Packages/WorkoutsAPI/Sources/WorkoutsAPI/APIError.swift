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

/// Every failure the app surfaces, mapped from the API's `{ message, code }` envelope,
/// the 422 `ValidationError` shape, or the transport.
public enum APIError: Error, Sendable, Equatable {
    /// 422. `fields` is keyed by the dot path the API uses, e.g. `activities.0.sets.1.effort_value`.
    case validation(message: String, fields: [String: [FieldError]])
    /// Any other documented or undocumented error status with the standard envelope.
    case api(status: Int, code: String, message: String)
    /// A non-2xx response whose body was not the standard envelope.
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
        case .api(_, let code, _): code
        case .unexpected, .network, .decoding, .local: nil
        }
    }

    public var status: Int? {
        switch self {
        case .validation: 422
        case .api(let status, _, _), .unexpected(let status): status
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
    /// Maps a decoded 422 body.
    public init(validation body: Components.Schemas.ValidationError) {
        var fields: [String: [FieldError]] = [:]
        for (path, errors) in body.value2.errors.additionalProperties {
            fields[path] = errors.map { FieldError(code: $0.code, message: $0.message) }
        }
        self = .validation(message: body.value1.message, fields: fields)
    }

    /// Maps a documented error response whose body is the standard envelope.
    public init(status: Int, envelope: Components.Schemas._Error) {
        self = .api(status: status, code: envelope.code, message: envelope.message)
    }

    /// Maps an `undocumented` response by decoding the standard envelope from its body.
    public static func from(status: Int, payload: UndocumentedPayload) async -> APIError {
        guard let body = payload.body, let data = try? await Data(collecting: body, upTo: 64 * 1024) else {
            return .unexpected(status: status)
        }
        return from(status: status, data: data)
    }

    /// Maps raw envelope bytes (also used by middlewares that inspect a response).
    public static func from(status: Int, data: Data) -> APIError {
        guard let envelope = try? JSONDecoder().decode(Components.Schemas._Error.self, from: data) else {
            return .unexpected(status: status)
        }
        return .api(status: status, code: envelope.code, message: envelope.message)
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
}
