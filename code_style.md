# Code Style Guidelines

## Models & Eloquent
- Relationship methods **must** have proper type hints and return types.
- Attribute casting **must** use the `casts()` method (not `$casts` property) in Laravel 12+.
- All models **must** define `$fillable` for mass assignment protection.

## Controllers & Routing
- Validation **must** use dedicated Form Request classes.
- Web (Inertia) routes **must** use controller methods, not closures.
- Route model binding and dependency injection **should** be used where possible.
- Controllers **must** return appropriate HTTP status codes (201, 204, 403, etc.).
- Mutating endpoints **must** be protected with Policies and explicit controller authorization.

## Form Requests & Validation
- Default Laravel validation messages **should** be preferred; use `attributes()` for friendly field names.
- The `messages()` method **should** only be used for key domain-specific rules.
- Form Requests **must** focus on validation; authorization for mutations **must** be handled in controllers via Policies.
- Modern array syntax `[]` **must** be used for validation rules.

## Services & Query Builders
- Builder methods **should** be focused and composable (e.g., `forUser(...)`, `withSummary(...)`, `latestFirst(...)`).
- Query helpers **should** be small and chainable.
- Multi-step operations **must** use `DB::transaction()`.
- Complex business logic **must** be extracted to Service classes.
- Services must implement interfaces and controllers should depend on interfaces rather than concrete service classes. This hides service implementations and improves testability and maintainability.

## Vue & Inertia
- Vue 3 Composition API with `<script setup>` **must** be used.
- Components **should** be organized for reusability.
- The `route()` helper **must** be used for URL generation.
- All data **must** flow through Inertia props; direct HTTP requests that bypass Inertia **must not** be used.
- Inertia’s router and form helpers **must** be used for mutations.
- Pagination **must** be applied to large datasets.

## API Resources
- All API responses **must** use Eloquent Resources.
- `whenLoaded()` **must** be used for relationships and `whenCounted()` for `*_count` attributes.

## Testing
- The PHPUnit `#[Test]` attribute **must** be used for test methods.
- The PHPUnit `#[DataProvider]` attribute **must** be used for data providers.
- Tests **must** be organized into Feature (full HTTP stack) and Unit (isolated) tests.
- All Policy methods **must** be tested with allowed, denied, and edge cases.
- Database transaction rollback tests **should** be added for complex service operations.
- Test naming and structure **must** be consistent.

## Configuration & Middleware
- Flash messages and app-wide configuration **should** be shared via Inertia middleware.
- User permissions/roles **can** be shared if needed.

## Database & Migrations
- Foreign keys **must** use `foreignId()` and proper constraints.
- Cascade deletion **must** be implemented via the service layer and DB constraints.
- Query performance **should** be monitored and indexes added as needed.

## Security & Authorization
- Policies **must** be used for all mutating endpoints.
- Explicit controller authorization **must** be required for mutations, even with Form Requests.
- Form Requests **must** be validation-only; access control **must not** be handled in Form Requests.
- For each new endpoint, tests for “owner allowed” and “other user forbidden/404” **must** be added.
- Owner-scoped queries **must** be used for user-owned GET pages.

## Code Quality
- `vendor/bin/pint` **must** be run to ensure consistent code style.
- PHPDoc blocks **should** be added to all public methods, especially in services and query builders.
- Import (`use`) statements **should** be preferred for classes/resources.
- All pages and components **must** be mobile-first and fully responsive.
