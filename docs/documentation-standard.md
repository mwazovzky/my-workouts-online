# Documentation Standard

Rules for writing and maintaining project documentation. Applies to all files in `docs/`, `CLAUDE.md`, and `README.md`.

This is a solo-maintained project. The goal is not maximum coverage. The goal is fast context recovery, safe changes, and low maintenance overhead.

## Principles

- Write for future-you.
- Keep every document short enough to reread quickly.
- Give every fact one canonical home.
- Prefer links over repeated explanations.
- Document current behavior, not plans.
- Create a new file only when it reduces cognitive load.

## Document Roles

| Type | Purpose | File(s) |
| --- | --- | --- |
| Setup Quickstart | Clone, install, run locally, point to deeper docs | `README.md` |
| Docs Index | Table of contents for project docs | `docs/README.md` |
| Product Map | App summary, glossary, core flows, feature index | `docs/product.md` |
| Feature Doc | Behavior, rules, limits, owned surface area for one domain | `docs/features/*.md` |
| Page & Route Reference | Complete inventory of pages and endpoints | `docs/pages-and-routes.md` |
| Architecture | Cross-cutting technical patterns, data model, key decisions | `docs/architecture.md` |
| Runbook | Operational procedure for deployment and recovery | `docs/deployment.md` |
| Code Style | Project coding standards and conventions | `docs/code-style.md` |
| Testing Guide | Test suite structure, coverage rules, and project testing patterns | `docs/testing.md` |
| LLM Context | Condensed project summary for AI tools | `CLAUDE.md` |

## Single-Owner Rules

Every piece of information has one canonical location. Other docs may link to it but must not restate it.

| Information | Canonical Location | Not In |
| --- | --- | --- |
| App summary and glossary | `docs/product.md` | Feature docs, route reference |
| Detailed feature behavior and rules | `docs/features/*.md` | `docs/product.md`, `docs/pages-and-routes.md` |
| Full page and endpoint list | `docs/pages-and-routes.md` | Feature docs |
| Technical patterns and data model | `docs/architecture.md` | Feature docs, `docs/product.md` |
| Deployment steps and checks | `docs/deployment.md` | `README.md` |
| Test suite structure and testing patterns | `docs/testing.md` | `docs/code-style.md`, feature docs |
| Local setup commands | `README.md` | Feature docs |

## Product Doc Rules

`docs/product.md` is a map, not a manual.

It should contain:

- One-paragraph app summary
- Domain glossary
- Core user flows
- Feature index with one-line summaries and links

It should not contain:

- Detailed business rules
- Full route tables
- Per-page behavior details
- Deep technical explanations

## Feature Doc Template

Each feature doc is the canonical source for one feature's behavior.

### Mandatory Sections

- **Title** — `# Feature Name` + one-sentence summary.
- **Current Behavior** — Numbered steps from the user's perspective. What the app does today.
- **Business Rules** — Verifiable constraints, validation, authorization, and data integrity rules.
- **Known Limitations** — Concrete scope boundaries and notable missing capabilities.
- **Surface Area** — Only the page names and route names owned by the feature. Link to `docs/pages-and-routes.md` for the full reference.
- **Related** — 2-4 links to connected docs.

### Optional Sections

Include only when they add value.

- **Key Decisions** — Non-obvious product or domain choices.
- **Data Notes** — Feature-specific schema or relationships that are awkward to understand without a short explanation.

### Anti-patterns

- Full copies of method/path tables from `docs/pages-and-routes.md`
- Repeated glossary definitions from `docs/product.md`
- Deep technical explanations already owned by `docs/architecture.md`
- TODO lists, implementation plans, or aspirational roadmap notes
- Screenshots, mockups, or changelogs

## Pages & Routes Rules

`docs/pages-and-routes.md` is a lookup document.

Each page or endpoint entry should include:

- Name
- Path
- Method when applicable
- Access rule
- Owning feature

It should not include:

- User flow narrative
- Business rule explanations
- Known limitations
- Architectural rationale

## Architecture Rules

`docs/architecture.md` owns cross-cutting technical context.

It should contain:

- Stack summary
- Key patterns
- Data model overview
- Important technical decisions that affect multiple features

It should not contain:

- Full route listings
- Detailed step-by-step product behavior
- Deployment procedures

## Update Triggers

Update docs when:

- A feature gains or loses behavior — update its feature doc
- A page or route is added, removed, or renamed — update `docs/pages-and-routes.md`
- A new major capability appears — update `docs/product.md`
- A cross-cutting pattern changes — update `docs/architecture.md`
- Testing conventions or required coverage change — update `docs/testing.md`
- Deployment or release steps change — update `docs/deployment.md`
- Any of the above changes project summary or commands — update `CLAUDE.md` or `README.md` if needed

## File Naming

- Lowercase, hyphen-separated: `workout-logging.md`
- Name files after the domain concept, not the implementation detail
- No version suffixes

## Cross-Referencing

- Use relative markdown links
- Every feature doc has a `Related` section
- `docs/product.md` links to every active feature doc
- `docs/README.md` links to every maintained doc
- Link to the canonical source instead of repeating it

## Quality Checklist

- [ ] Concise — no filler, no repeated framing, no duplicate tables
- [ ] Accurate — matches current code and UI
- [ ] Single-owner — each fact has one canonical location
- [ ] Current — no stale routes, patterns, or limitations
- [ ] Scoped — each doc stays within its role
- [ ] Operational — a future change has one obvious place to update
- [ ] Complete — required sections are present
- [ ] Cross-referenced — related docs are linked
