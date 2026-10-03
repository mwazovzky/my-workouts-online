import Foundation
import Testing

@testable import WorkoutsAPI

@Suite("APIDateTranscoder")
struct APIDateTranscoderTests {
    private let transcoder = APIDateTranscoder()
    /// 2026-09-21T06:13:20Z, built from components so the test does not depend on epoch arithmetic.
    private let expected = DateComponents(
        calendar: Calendar(identifier: .gregorian), timeZone: TimeZone(identifier: "UTC"),
        year: 2026, month: 9, day: 21, hour: 6, minute: 13, second: 20
    ).date!

    @Test("accepts Laravel's six fractional digits, millisecond, plain and offset forms")
    func decodes() throws {
        #expect(try transcoder.decode("2026-09-21T06:13:20.000000Z") == expected)
        #expect(try transcoder.decode("2026-09-21T06:13:20.000Z") == expected)
        #expect(try transcoder.decode("2026-09-21T06:13:20Z") == expected)
        #expect(try transcoder.decode("2026-09-21T08:13:20+02:00") == expected)
        #expect(try transcoder.decode("2026-09-21T06:13:20.123456Z").timeIntervalSince(expected) > 0.12)
    }

    @Test("rejects garbage")
    func rejects() {
        #expect(throws: DecodingError.self) { try transcoder.decode("yesterday") }
        #expect(throws: DecodingError.self) { try transcoder.decode("2026-09-21") }
    }

    @Test("encodes round-trippable strings")
    func encodes() throws {
        let encoded = try transcoder.encode(expected)
        #expect(encoded == "2026-09-21T06:13:20.000Z")
        #expect(try transcoder.decode(encoded) == expected)
    }

    @Test("trims only over-long fractions")
    func trimming() {
        #expect(APIDateTranscoder.trimmedToMilliseconds("2026-01-01T00:00:00.123456Z") == "2026-01-01T00:00:00.123Z")
        #expect(APIDateTranscoder.trimmedToMilliseconds("2026-01-01T00:00:00.12Z") == "2026-01-01T00:00:00.12Z")
        #expect(APIDateTranscoder.trimmedToMilliseconds("2026-01-01T00:00:00+02:00") == "2026-01-01T00:00:00+02:00")
    }
}
