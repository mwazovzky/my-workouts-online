// Package httpapi is the HTTP platform every module's handlers run on: the
// generated server from the contract (gen), mounted on a ServeMux behind one
// middleware chain, and the responder that turns errors into RFC 9457
// Problem Details.
//
// The chain, outermost first:
//
//	otelhttp      the request's span (probes excluded), renamed to the route pattern after routing
//	RequestID     accept a UUID X-Request-Id or mint one; echo it on every response; request_id log field
//	AccessLog     one structured log line per request and the request metrics (duration, count by route)
//	Recover       a panic becomes a 500 Problem; the stack goes to the log
//	Locale        Accept-Language → context; Content-Language and Vary out
//	RateLimit     fixed window per client under /api/ (API_RATE_LIMIT_PER_MINUTE)
//	routes        ServeMux; an unmatched path is a 404 Problem, a wrong method a 405 with Allow
//
// and per contract operation, inside the mux:
//
//	authorize     bearer token → principal for non-public operations (401), user_id log field; Admin-tagged ones need an admin (403)
//	throttle      per-operation limits (register, reauth, …), per user or client IP (429)
//	Validate      openapi3filter against the embedded contract; failures are 422 pointer errors
//	strict server generated decoding and encoding; a returned error goes to the responder
package httpapi
