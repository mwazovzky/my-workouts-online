// swift-tools-version: 6.0
import PackageDescription

// Domain models, mapping from the generated API types, session, locale, repositories and
// view models. Foundation-only so `swift test` runs on macOS without a simulator.
let package = Package(
    name: "WorkoutsCore",
    platforms: [.iOS(.v17), .macOS(.v14)],
    products: [
        .library(name: "WorkoutsCore", targets: ["WorkoutsCore"])
    ],
    dependencies: [
        .package(path: "../WorkoutsAPI")
    ],
    targets: [
        .target(
            name: "WorkoutsCore",
            dependencies: [
                .product(name: "WorkoutsAPI", package: "WorkoutsAPI")
            ]
        ),
        .testTarget(
            name: "WorkoutsCoreTests",
            dependencies: [
                "WorkoutsCore",
                .product(name: "WorkoutsAPI", package: "WorkoutsAPI"),
                .product(name: "WorkoutsAPITestSupport", package: "WorkoutsAPI"),
            ],
            resources: [.copy("Fixtures")]
        ),
    ]
)
