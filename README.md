# Domain Checker

CLI tool to check domain availability via WHOIS.

## Supported TLDs

- `.cz` - Czech Republic
- `.com` - Commercial
- `.net` - Network
- `.eu` - European Union
- `.org` - Organization
- `.dev` - Development
- `.ai` - Anguilla (popular for AI)
- `.info` - Information
- `.de` - Germany
- `.at` - Austria
- `.es` - Spain
- `.us` - United States

## Requirements

- PHP 8.2+
- Composer
- Docker (optional)

## Installation

```bash
composer install
```

## Usage

### With Docker (recommended)

1. Copy the example file and add your domains:
   ```bash
   cp domains.csv.example domains.csv
   ```

2. Edit `domains.csv` with your domains (one per line)

3. Run:
   ```bash
   ./check.sh
   ```

   Or with options:
   ```bash
   ./check.sh -o csv    # output as CSV
   ./check.sh -o json   # output as JSON
   ```

### Without Docker

```bash
php bin/console
```

## CSV Format

The `domains.csv` file should have a header row with `domain` and one domain per line:

```csv
domain
example.com
example.org
example.cz
```

## License

MIT
