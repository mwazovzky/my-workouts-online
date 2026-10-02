#!/usr/bin/env swift  // Seeds App/Localizable.xcstrings from the web UI catalogs so both clients share one
// set of English keys and Russian translations.
//
//   swift apps/ios/scripts/seed-string-catalog.swift        (from the repo root)
//
// Merge rules: a key missing from the catalog is added; an existing translation is never
// overwritten; Laravel-style `:name` placeholders become positional `%1$@` and are listed
// for review, because Swift interpolation decides the real format specifier per call site.
import Foundation

let scriptDirectory = URL(fileURLWithPath: CommandLine.arguments[0]).deletingLastPathComponent()
let iosRoot = scriptDirectory.deletingLastPathComponent()
let repoRoot = iosRoot.deletingLastPathComponent().deletingLastPathComponent()
let webLocales = repoRoot.appending(path: "apps/web/src/i18n/locales")
let catalogURL = iosRoot.appending(path: "App/Localizable.xcstrings")
let sourceLanguage = "en"
let targetLanguages = ["ru"]

func loadCatalog(_ language: String) throws -> [String: String] {
    let data = try Data(contentsOf: webLocales.appending(path: "\(language).json"))
    guard let json = try JSONSerialization.jsonObject(with: data) as? [String: String] else {
        throw NSError(
            domain: "seed", code: 1, userInfo: [NSLocalizedDescriptionKey: "\(language).json is not a flat string map"])
    }
    return json
}

/// `:completed/:total sets` -> `%1$@/%2$@ sets`
func convertPlaceholders(_ text: String) -> (String, Bool) {
    let pattern = try! NSRegularExpression(pattern: #":([A-Za-z_][A-Za-z0-9_]*)"#)
    let matches = pattern.matches(in: text, range: NSRange(text.startIndex..., in: text))
    guard !matches.isEmpty else { return (text, false) }
    var result = text
    for (index, match) in matches.enumerated().reversed() {
        let range = Range(match.range, in: result)!
        result.replaceSubrange(range, with: "%\(index + 1)$@")
    }
    return (result, true)
}

let english = try loadCatalog(sourceLanguage)
var translations: [String: [String: String]] = [:]
for language in targetLanguages {
    translations[language] = try loadCatalog(language)
}

var catalog: [String: Any] = ["sourceLanguage": sourceLanguage, "version": "1.0", "strings": [String: Any]()]
if let data = try? Data(contentsOf: catalogURL),
    let existing = try? JSONSerialization.jsonObject(with: data) as? [String: Any]
{
    catalog = existing
}
var strings = catalog["strings"] as? [String: Any] ?? [:]

var added = 0
var converted: [String] = []
for (key, englishValue) in english {
    let (swiftKey, didConvert) = convertPlaceholders(key)
    if didConvert { converted.append("\(key)  ->  \(swiftKey)") }
    var entry = strings[swiftKey] as? [String: Any] ?? [:]
    var localizations = entry["localizations"] as? [String: Any] ?? [:]
    if swiftKey != englishValue, localizations[sourceLanguage] == nil {
        let (englishConverted, _) = convertPlaceholders(englishValue)
        localizations[sourceLanguage] = ["stringUnit": ["state": "translated", "value": englishConverted]]
    }
    for language in targetLanguages {
        guard let value = translations[language]?[key], !value.isEmpty, value != key else { continue }
        if localizations[language] != nil { continue }
        let (valueConverted, _) = convertPlaceholders(value)
        localizations[language] = ["stringUnit": ["state": "translated", "value": valueConverted]]
    }
    if strings[swiftKey] == nil { added += 1 }
    entry["localizations"] = localizations
    if localizations.isEmpty { entry.removeValue(forKey: "localizations") }
    strings[swiftKey] = entry
}
catalog["strings"] = strings

let output = try JSONSerialization.data(
    withJSONObject: catalog, options: [.prettyPrinted, .sortedKeys, .withoutEscapingSlashes])
try output.write(to: catalogURL)

print("Localizable.xcstrings: \(strings.count) keys, \(added) added")
if !converted.isEmpty {
    print("Placeholder keys converted (review the format specifier at each call site):")
    converted.sorted().forEach { print("  \($0)") }
}
