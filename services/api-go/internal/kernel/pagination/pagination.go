// Package pagination is the page-number pagination of the contract's *Page
// schemas: `page` and `per_page` in, `links` with absolute URLs and `meta`
// with Laravel's fields out.
package pagination

import (
	"math"
	"net/url"
	"strconv"
)

// Page is the requested page.
type Page struct {
	// Number is 1-based.
	Number int
	// Size is the items per page.
	Size int
}

// NewPage returns the page the optional `page` and `per_page` query values
// ask for, with defaults when they are absent. The contract bounds both
// (request validation rejects page < 1 and per_page outside 1..max), so this
// only clamps as a second line of defence.
func NewPage(number, size *int, defaultSize, maxSize int) Page {
	p := Page{Number: 1, Size: defaultSize}

	if number != nil && *number > 0 {
		p.Number = *number
	}

	if size != nil && *size > 0 {
		p.Size = min(*size, maxSize)
	}

	return p
}

// Offset is the number of items before the page. It saturates instead of
// overflowing, so a page far past the end is simply empty.
func (p Page) Offset() int {
	if p.Number-1 > math.MaxInt/max(p.Size, 1) {
		return math.MaxInt
	}

	return (p.Number - 1) * p.Size
}

// Result is one page of items plus the total across all pages.
type Result[T any] struct {
	Items []T
	Total int
	Page  Page
}

// LastPage is the number of the last page; 1 when there are no items, as in
// Laravel.
func (r Result[T]) LastPage() int {
	return max(1, (r.Total+r.Page.Size-1)/r.Page.Size)
}

// Meta is the contract's PageMeta.
type Meta struct {
	CurrentPage int
	// From and To are the 1-based positions of the first and last item on
	// the page; nil on an empty page.
	From     *int
	LastPage int
	PerPage  int
	To       *int
	Total    int
}

// Meta returns the page's meta.
func (r Result[T]) Meta() Meta {
	m := Meta{CurrentPage: r.Page.Number, LastPage: r.LastPage(), PerPage: r.Page.Size, Total: r.Total}

	if len(r.Items) > 0 {
		from := r.Page.Offset() + 1
		to := r.Page.Offset() + len(r.Items)
		m.From, m.To = &from, &to
	}

	return m
}

// Links is the contract's PageLinks: absolute URLs, nil when there is no
// such page.
type Links struct {
	First, Last, Prev, Next *string
}

// Links returns the page's links. Each is requestURL (absolute) with `page`
// replaced, keeping every other query parameter, so per_page and filters
// survive navigation.
func (r Result[T]) Links(requestURL *url.URL) Links {
	at := func(number int) *string {
		u := *requestURL
		q := u.Query()
		q.Set("page", strconv.Itoa(number))
		u.RawQuery = q.Encode()
		s := u.String()

		return &s
	}

	last := r.LastPage()
	links := Links{First: at(1), Last: at(last)}

	if r.Page.Number > 1 {
		links.Prev = at(min(r.Page.Number-1, last))
	}

	if r.Page.Number < last {
		links.Next = at(r.Page.Number + 1)
	}

	return links
}
