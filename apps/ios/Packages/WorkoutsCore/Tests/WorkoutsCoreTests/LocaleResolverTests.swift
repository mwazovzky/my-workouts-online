import Testing

@testable import WorkoutsCore

@Suite("LocaleResolver")
struct LocaleResolverTests {
    @Test("picks the first preferred language the API offers")
    func picksFirstOffered() {
        #expect(LocaleResolver.apiLocale(preferred: ["ru-RU", "en-US"], available: ["en", "ru"]) == "ru")
        #expect(LocaleResolver.apiLocale(preferred: ["de-DE", "en-GB"], available: ["en", "ru"]) == "en")
    }

    @Test("falls back to English when nothing matches")
    func fallsBack() {
        #expect(LocaleResolver.apiLocale(preferred: ["de", "fr"], available: ["en", "ru"]) == "en")
        #expect(LocaleResolver.apiLocale(preferred: [], available: ["en", "ru"]) == "en")
    }

    @Test("handles underscores and case")
    func normalizes() {
        #expect(LocaleResolver.apiLocale(preferred: ["RU_ru"], available: ["EN", "RU"]) == "ru")
    }

    @Test("falls back to the first offered locale when English is not offered")
    func fallsBackToFirstOffered() {
        #expect(LocaleResolver.apiLocale(preferred: ["de"], available: ["ru"]) == "ru")
    }
}
