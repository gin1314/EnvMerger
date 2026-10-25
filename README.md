# EnvMerger (`gin/env-merger`)

> Smart, format-preserving `.env` merger with interactive conflict resolution and Laravel Artisan integration.

[![PHP Version](https://img.shields.io/badge/php-%5E8.2-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-Pest%20PHP-purple)](https://pestphp.com)

`gin/env-merger` allows you to merge existing `.env` files with environment-specific files (e.g. `.env.production`, `.env.staging`, `.env.local`) via command-line switches or a rich interactive merge wizard.

---

## ✨ Features

- 🧠 **Format & Comment Preservation**: Retains all comments, section headers, blank lines, quotes, and inline comments in the base `.env` file.
- ⚡ **Framework-Agnostic Binary**: Works out-of-the-box in any PHP project or globally via `vendor/bin/env-merge`.
- 🚀 **Laravel Auto-Discovery**: Automatically exposes `php artisan env:merge` inside Laravel applications.
- 💬 **Interactive Conflict Wizard**: Powered by `laravel/prompts` to inspect side-by-side diffs and choose between Base, Incoming, Custom values, or bulk accept.
- 🔒 **Secret Masking**: Sensitive keys (`PASSWORD`, `SECRET`, `KEY`, `TOKEN`, `CREDENTIAL`, etc.) are masked as `••••••` by default in terminal outputs and diffs for security.
- 🛡️ **Safety Backups**: Automatically creates timestamped backup copies (`.env.backup.YYYYMMDD_His`) before modifying files.
- 🔍 **Dry-Run Preview**: Inspect diffs and affected keys without altering any files on disk.

---

## 📦 Installation

### In an existing project (Laravel, Symfony, or Generic PHP)
```bash
composer require gin/env-merger --dev
```

### Globally via Composer
```bash
composer global require gin/env-merger
```

---

## 🚀 Usage

### Quick Start
Merge `.env.production` into `.env`:
```bash
./vendor/bin/env-merge .env .env.production
```

Or inside Laravel:
```bash
php artisan env:merge .env .env.production
```

If you run without arguments, `env-merge` automatically scans for available `.env.*` files in the current directory and lets you pick one.

### Interactive Mode
Run the step-by-step interactive resolution wizard when conflicts exist:
```bash
./vendor/bin/env-merge -i
```
Or:
```bash
./vendor/bin/env-merge .env .env.staging --interactive
```

For each differing key, you will see a prompt:
```
Conflict [1/3] for key: DB_PASSWORD
  Base value:     ••••••••••
  Incoming value: ••••••••••

Select resolution for DB_PASSWORD:
  ❯ Use Incoming [••••••••••]
    Keep Base [••••••••••]
    Enter Custom Value...
    Accept All Remaining Incoming
    Keep All Remaining Base
```

### Dry-Run Preview
Preview all changes without touching any files:
```bash
./vendor/bin/env-merge .env .env.production --dry-run
```

### Revealing Secrets in Output
By default, sensitive credentials are obfuscated. To display plain-text values:
```bash
./vendor/bin/env-merge .env .env.production --show-secrets
```

### Specifying Output File
Write the merged result to a new file instead of updating in-place:
```bash
./vendor/bin/env-merge .env .env.production --output=.env.deploy
```

### Non-Interactive Conflict Strategies
Configure how conflicts are resolved in CI/CD pipelines:
- `incoming` *(default)*: Target/incoming file overwrites base values.
- `keep-base`: Retains base values; only appends new keys from target.
- `fail`: Aborts with an error code if any conflicting values exist.

```bash
./vendor/bin/env-merge .env .env.production --strategy=keep-base
```

---

## 🛠 Command Reference

```bash
env-merge [base] [target] [options]
```

| Argument / Option | Default | Description |
| :--- | :--- | :--- |
| `base` | `.env` | Path to the base environment file |
| `target` | auto | Path to incoming environment file (e.g. `.env.production`) |
| `-o, --output=PATH` | in-place | Destination file path for merged output |
| `-i, --interactive` | `false` | Launch interactive conflict resolution wizard |
| `-s, --strategy=NAME` | `incoming` | Conflict strategy: `incoming`, `keep-base`, `fail` |
| `--dry-run` | `false` | Preview changes and diff without modifying files |
| `--backup / --no-backup` | `true` | Create timestamped backup file before overwriting |
| `--show-secrets` | `false` | Display unmasked secret values in diffs and prompts |

---

## 🧪 Testing

Run the test suite using [Pest PHP](https://pestphp.com):
```bash
./vendor/bin/pest
```

---

## 📄 License

This package is open-source software licensed under the [MIT license](LICENSE).
