// swift-tools-version: 6.0
import PackageDescription

// Domain models, session, locale, repositories and view models. Foundation-only so
// `swift test` runs on macOS without a simulator.
let package = Package(
    name: "WorkoutsCore",
    platforms: [.iOS(.v17), .macOS(.v14)],
    products: [
        .library(name: "WorkoutsCore", targets: ["WorkoutsCore"])
    ],
    targets: [
        .target(name: "WorkoutsCore"),
        .testTarget(name: "WorkoutsCoreTests", dependencies: ["WorkoutsCore"]),
    ]
)
