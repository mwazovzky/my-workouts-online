# Catalog golden files

`*.golden.json` are the PHP API's responses, captured for the Go handlers to
match: `{programs,program-1,program-2,workout-template-1,workout-template-2,exercises}.{en,ru}`.
Bodies are compared as JSON values, so key order and number formatting
(`100` and `100.0`) do not matter.

Both sides start from the same catalog:

- `php/GoGoldenCaptureTest.php` builds it in the PHP API and saves the responses;
- `catalog.sql` inserts the same rows, with the same ids, into the Go database.

A change to one needs the same change to the other and a new capture:

```bash
mkdir -p services/api-php/storage/golden
cp services/api-go/internal/catalog/adapters/http/testdata/php/GoGoldenCaptureTest.php services/api-php/tests/Feature/
docker compose exec -T app php artisan test --compact tests/Feature/GoGoldenCaptureTest.php
for f in services/api-php/storage/golden/*.json; do
  python3 -c "import json,sys; print(json.dumps(json.load(open(sys.argv[1])), ensure_ascii=False, indent=2, sort_keys=True))" "$f" \
    > "services/api-go/internal/catalog/adapters/http/testdata/$(basename "$f" .json).golden.json"
done
rm -r services/api-php/tests/Feature/GoGoldenCaptureTest.php services/api-php/storage/golden
```

The fixture covers the cases the story pins: a template assigned twice and
out of weekday order, a retired exercise inside a template and missing from
`/exercises`, sets whose ids do not follow their order, a `none` unit with
null difficulty, blank descriptions, missing translations (fallback to
English), and Latin, lowercase and Cyrillic names (`ё`) in the sort.

One known difference is avoided rather than captured: PHP lists an
exercise's categories in pivot-row order and Go by category id, so the
fixture attaches them in id order (see the README's catalog section).
