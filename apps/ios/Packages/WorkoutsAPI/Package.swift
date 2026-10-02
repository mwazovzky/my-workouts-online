// swift-tools-version: 6.0
import PackageDescription

// The API client. Story 3 adds swift-openapi-generator here, reading contracts/openapi.yaml.
let package = Package(
    name: "WorkoutsAPI",
    platforms: [.iOS(.v17), .macOS(.v14)],
    products: [
        .library(name: "WorkoutsAPI", targets: ["WorkoutsAPI"])
    ],
    targets: [
        .target(name: "WorkoutsAPI"),
        .testTarget(name: "WorkoutsAPITests", dependencies: ["WorkoutsAPI"]),
    ]
)
