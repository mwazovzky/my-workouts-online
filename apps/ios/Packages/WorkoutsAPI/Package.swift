// swift-tools-version: 6.0
import PackageDescription

// The API client, generated at build time from contracts/openapi.yaml (symlinked into
// Sources/WorkoutsAPI) by swift-openapi-generator. Nothing generated is committed, so a
// contract change the app cannot absorb fails the build instead of drifting silently.
let package = Package(
    name: "WorkoutsAPI",
    platforms: [.iOS(.v17), .macOS(.v14)],
    products: [
        .library(name: "WorkoutsAPI", targets: ["WorkoutsAPI"]),
        .library(name: "WorkoutsAPITestSupport", targets: ["WorkoutsAPITestSupport"]),
    ],
    dependencies: [
        .package(url: "https://github.com/apple/swift-openapi-generator", from: "1.10.0"),
        .package(url: "https://github.com/apple/swift-openapi-runtime", from: "1.8.0"),
        .package(url: "https://github.com/apple/swift-openapi-urlsession", from: "1.1.0"),
        .package(url: "https://github.com/apple/swift-http-types", from: "1.0.0"),
    ],
    targets: [
        .target(
            name: "WorkoutsAPI",
            dependencies: [
                .product(name: "OpenAPIRuntime", package: "swift-openapi-runtime"),
                .product(name: "OpenAPIURLSession", package: "swift-openapi-urlsession"),
                .product(name: "HTTPTypes", package: "swift-http-types"),
            ],
            plugins: [
                .plugin(name: "OpenAPIGenerator", package: "swift-openapi-generator")
            ]
        ),
        .target(
            name: "WorkoutsAPITestSupport",
            dependencies: [
                "WorkoutsAPI",
                .product(name: "OpenAPIRuntime", package: "swift-openapi-runtime"),
                .product(name: "HTTPTypes", package: "swift-http-types"),
            ]
        ),
        .testTarget(
            name: "WorkoutsAPITests",
            dependencies: ["WorkoutsAPI", "WorkoutsAPITestSupport"]
        ),
    ]
)
