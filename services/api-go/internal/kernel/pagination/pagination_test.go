package pagination_test

import (
	"math"
	"net/url"
	"testing"

	"github.com/google/go-cmp/cmp"

	"github.com/mwazovzky/my-workouts-online/services/api-go/internal/kernel/pagination"
)

func ptr[T any](v T) *T { return &v }

func TestNewPage(t *testing.T) {
	t.Parallel()

	tests := []struct {
		name         string
		number, size *int
		want         pagination.Page
	}{
		{"defaults", nil, nil, pagination.Page{Number: 1, Size: 20}},
		{"explicit", ptr(3), ptr(50), pagination.Page{Number: 3, Size: 50}},
		{"page zero", ptr(0), nil, pagination.Page{Number: 1, Size: 20}},
		{"per_page over the maximum", nil, ptr(500), pagination.Page{Number: 1, Size: 100}},
		{"per_page zero", nil, ptr(0), pagination.Page{Number: 1, Size: 20}},
	}

	for _, tt := range tests {
		if got := pagination.NewPage(tt.number, tt.size, 20, 100); got != tt.want {
			t.Errorf("%s: NewPage() = %+v, want %+v", tt.name, got, tt.want)
		}
	}
}

func TestMetaAndLinks(t *testing.T) {
	t.Parallel()

	request, err := url.Parse("https://my-workouts.example/api/v1/workouts?per_page=20&page=2")
	if err != nil {
		t.Fatal(err)
	}

	link := func(page string) *string {
		s := "https://my-workouts.example/api/v1/workouts?page=" + page + "&per_page=20"

		return &s
	}

	tests := []struct {
		name      string
		page      pagination.Page
		items     int
		total     int
		wantMeta  pagination.Meta
		wantLinks pagination.Links
	}{
		{
			name:      "middle page",
			page:      pagination.Page{Number: 2, Size: 20},
			items:     20,
			total:     47,
			wantMeta:  pagination.Meta{CurrentPage: 2, From: ptr(21), LastPage: 3, PerPage: 20, To: ptr(40), Total: 47},
			wantLinks: pagination.Links{First: link("1"), Last: link("3"), Prev: link("1"), Next: link("3")},
		},
		{
			name:      "last, partial page",
			page:      pagination.Page{Number: 3, Size: 20},
			items:     7,
			total:     47,
			wantMeta:  pagination.Meta{CurrentPage: 3, From: ptr(41), LastPage: 3, PerPage: 20, To: ptr(47), Total: 47},
			wantLinks: pagination.Links{First: link("1"), Last: link("3"), Prev: link("2")},
		},
		{
			name:      "no items",
			page:      pagination.Page{Number: 1, Size: 20},
			wantMeta:  pagination.Meta{CurrentPage: 1, LastPage: 1, PerPage: 20},
			wantLinks: pagination.Links{First: link("1"), Last: link("1")},
		},
		{
			name:      "past the end",
			page:      pagination.Page{Number: 9, Size: 20},
			total:     47,
			wantMeta:  pagination.Meta{CurrentPage: 9, LastPage: 3, PerPage: 20, Total: 47},
			wantLinks: pagination.Links{First: link("1"), Last: link("3"), Prev: link("3")},
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			t.Parallel()

			result := pagination.Result[int]{Items: make([]int, tt.items), Total: tt.total, Page: tt.page}

			if diff := cmp.Diff(tt.wantMeta, result.Meta()); diff != "" {
				t.Errorf("Meta() (-want +got):\n%s", diff)
			}

			if diff := cmp.Diff(tt.wantLinks, result.Links(request)); diff != "" {
				t.Errorf("Links() (-want +got):\n%s", diff)
			}
		})
	}

	if got := (pagination.Page{Number: math.MaxInt, Size: 100}).Offset(); got != math.MaxInt {
		t.Errorf("Offset() of a huge page = %d, want it saturated", got)
	}

	if got := (pagination.Page{Number: 3, Size: 20}).Offset(); got != 40 {
		t.Errorf("Offset() = %d, want 40", got)
	}
}
