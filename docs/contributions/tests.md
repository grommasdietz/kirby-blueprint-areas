# Tests

Run tests after PHP or Panel changes and add coverage for bug fixes.

> [!IMPORTANT]
> Complete the [Setup](./setup.md) steps first.

---

## Quick sweep

Run the full lint/analysis/test set:

```bash
composer run verify
pnpm run verify
```

---

## PHP (PHPUnit)

```bash
composer test
```

Use the shared `tests/TestCase.php` base class to boot Kirby with the playground roots. It wraps `tests/Support/TestEnvironment.php` and lets you override config values when needed.

```php
final class ExampleTest extends TestCase
{
    public function testBootsKirby(): void
    {
        $kirby = $this->bootKirby(['options' => ['debug' => true]]);

        $this->assertSame('Kirby Playground', $kirby->site()->title()->value());
    }
}
```

Targeted runs:

```bash
composer test:unit
composer test:integration
composer test -- --filter ExampleTest
```

Coverage (requires Xdebug or PCOV):

```bash
composer test:coverage
```

---

## Static analysis (Psalm)

```bash
composer psalm
```

---

## JS lint

```bash
pnpm lint
```

---

## Browser tests (Playwright)

First run (installs browser binaries with OS dependencies):

```bash
pnpm run setup
```

Run browser tests:

```bash
pnpm test:browser
```

### Configuration

Playwright starts a PHP server on `127.0.0.1:8787` by default. Override with environment variables:

- `PLAYWRIGHT_BASE_URL` (full URL, e.g. `http://localhost:3000`)
- `PLAYWRIGHT_WEB_PORT` (port only)

### Panel login for browser tests

Playwright creates a temporary Panel user before the suite and removes it afterwards. Default credentials:

- Email: `playwright@kirby-blueprint-areas.test`
- Password: `playwright`

Override with environment variables:

- `KIRBY_USER_EMAIL`
- `KIRBY_USER_PASSWORD`

Notes:

- Test user files are written to `playground/site/accounts`
- Playwright uses the built‑in PHP server defined in `playwright.config.ts`
- Playwright does not load `.env` files automatically

---

## Fixtures

Keep fixtures under `playground/site/**` minimal and focused on the behavior under test. Avoid committing runtime data or caches.

---

Next: Continue with [Kirby internals](./kirby-internals.md)
