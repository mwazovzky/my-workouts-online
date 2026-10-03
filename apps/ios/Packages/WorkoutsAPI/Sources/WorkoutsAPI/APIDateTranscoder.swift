import Foundation
import OpenAPIRuntime

/// Decodes the date strings this API actually sends. Laravel serializes timestamps as
/// `2026-10-02T17:30:00.000000Z` (six fractional digits), which the runtime's default
/// ISO 8601 transcoder rejects; this one accepts any number of fractional digits, none,
/// and numeric offsets. Encoding uses millisecond precision, which the API accepts.
public struct APIDateTranscoder: DateTranscoder {
    // ISO8601DateFormatter is documented as thread-safe, so sharing two immutable instances is sound.
    nonisolated(unsafe) private static let fractional: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime, .withFractionalSeconds]
        return formatter
    }()

    nonisolated(unsafe) private static let plain: ISO8601DateFormatter = {
        let formatter = ISO8601DateFormatter()
        formatter.formatOptions = [.withInternetDateTime]
        return formatter
    }()

    public init() {}

    public func encode(_ date: Date) throws -> String {
        Self.fractional.string(from: date)
    }

    public func decode(_ dateString: String) throws -> Date {
        if let date = Self.fractional.date(from: dateString) ?? Self.plain.date(from: dateString) {
            return date
        }
        if let date = Self.fractional.date(from: Self.trimmedToMilliseconds(dateString)) {
            return date
        }
        throw DecodingError.dataCorrupted(
            .init(codingPath: [], debugDescription: "Expected an ISO 8601 date, got '\(dateString)'")
        )
    }

    /// `…:00.000000Z` → `…:00.000Z`, keeping the zone designator.
    static func trimmedToMilliseconds(_ value: String) -> String {
        guard let dot = value.lastIndex(of: ".") else { return value }
        let afterDot = value[value.index(after: dot)...]
        let digits = afterDot.prefix { $0.isNumber }
        guard digits.count > 3 else { return value }
        let rest = afterDot.dropFirst(digits.count)
        return String(value[...dot]) + digits.prefix(3) + rest
    }
}
