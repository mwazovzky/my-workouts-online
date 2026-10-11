// Package clock is the time source every use case and handler reads instead
// of calling time.Now, so tests can fix the time.
package clock

import "time"

// Clock reports the current time.
type Clock interface {
	Now() time.Time
}

// System is the wall clock.
type System struct{}

// Now returns the current time in UTC.
func (System) Now() time.Time {
	return time.Now().UTC()
}
