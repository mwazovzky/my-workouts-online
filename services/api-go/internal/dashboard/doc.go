// Package dashboard is the read model behind GET /dashboard: the signed-in
// user's upcoming schedule, in-progress and recent workouts and counts,
// composed from the catalog and training through catalogapi and
// trainingapi. It owns no tables and never writes (ADR 0011).
//
//	app/           the use case (Service) and the schedule's date arithmetic
//	adapters/http  getDashboard
package dashboard
