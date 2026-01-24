# Project Specific Guidelines

# Code Style Guidelines

- For each new endpoint, tests for “owner allowed” and “other user forbidden/404” **must** be added.
- Owner-scoped queries **must** be used for user-owned GET pages.

## Code Quality

- `vendor/bin/pint` **must** be run to ensure consistent code style.
- PHPDoc blocks **should** be added to all public methods, especially in services and query builders.
- Import (`use`) statements **should** be preferred for classes/resources.
- All pages and components **must** be mobile-first and fully responsive.
- The Request object **must not** be used in application logic (services, models, query builders, etc.); it is only allowed in middlewares, form requests, and controllers.
- Always use `$request->user()` to get the authenticated user.

## Testing

- Always use the `#[Test]` attribute for PHPUnit test methods.
- Always use the `#[DataProvider]` attribute for PHPUnit data provider methods.
