// Package clock is the time source every use case and handler reads instead
// of calling time.Now, so tests can fix the time.
package clock

import (
	"sync"
	"time"
)

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

// Fake is a settable clock for tests. It is safe for concurrent use.
type Fake struct {
	mu  sync.Mutex
	now time.Time
}

// NewFake returns a Fake reading now.
func NewFake(now time.Time) *Fake {
	return &Fake{now: now}
}

// Now returns the fake time.
func (f *Fake) Now() time.Time {
	f.mu.Lock()
	defer f.mu.Unlock()

	return f.now
}

// Advance moves the fake time forward by d.
func (f *Fake) Advance(d time.Duration) {
	f.mu.Lock()
	defer f.mu.Unlock()

	f.now = f.now.Add(d)
}
