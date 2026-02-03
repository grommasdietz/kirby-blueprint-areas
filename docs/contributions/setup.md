# Setup

This plugin uses the `playground` site for integration and browser tests.

---

## Composer

Install Composer dependencies for the repo and the playground:

```bash
composer run setup
```

> [!NOTE]
> PHPUnit loads `vendor/autoload.php` by default. If you only install dependencies in `playground`, the bootstrap loads `playground/vendor/autoload.php` as well.
> If you install `vlucas/phpdotenv`, the bootstrap loads `playground/.env` for tests.

---

## Node

Install Node dependencies and Playwright browsers (with system dependencies):

```bash
pnpm run setup
```

---

Next: Continue with [Structure](./structure.md)
