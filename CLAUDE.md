# CLAUDE.md

This file provides guidance for Claude instances working on this codebase.

## Project Overview

Domain Checker is a PHP CLI tool that checks domain availability via WHOIS/RDAP lookups. It supports 24 TLDs with both synchronous and asynchronous query capabilities.

## Quick Commands

```bash
# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit

# Run the tool
php bin/console                      # Default: reads domains.csv
php bin/console -f custom.csv        # Custom CSV file
php bin/console -o json              # Output as JSON (also: table, csv)
php bin/console -c 10                # 10 concurrent queries
php bin/console -w                   # Watch mode (re-check on file changes)

# With Docker
./check.sh
./check.sh -o json -c 10
```

## Project Structure

```
src/
├── Command/
│   └── CheckDomainsCommand.php      # CLI command with output formatting
└── Service/
    ├── WhoisClientInterface.php     # Interface for WHOIS queries
    ├── WhoisClient.php              # Sync implementation
    ├── AsyncWhoisClient.php         # Async implementation (Amp)
    ├── DomainCheckerService.php     # Core checking logic & patterns
    ├── DomainCheckResult.php        # Result DTO
    ├── DomainStatus.php             # Enum: Available, Registered, Error
    └── CsvReaderService.php         # CSV file reader

tests/Service/
└── DomainCheckerServiceTest.php     # Unit tests with data providers
```

## Adding Support for a New TLD

To add a new TLD (e.g., `.xx`), update these 5 locations:

1. **`src/Service/WhoisClient.php`** - Add to `WHOIS_SERVERS` constant:
   ```php
   'xx' => 'whois.nic.xx',
   ```

2. **`src/Service/AsyncWhoisClient.php`** - Add identical entry to `WHOIS_SERVERS`

3. **`src/Service/DomainCheckerService.php`** - Add to `AVAILABLE_PATTERNS`:
   ```php
   'xx' => '/pattern for available domain/i',
   ```
   If TLD uses RDAP instead of WHOIS, add to `RDAP_SERVERS` in both clients and use `'/^HTTP_STATUS:404/m'` pattern.

4. **`tests/Service/DomainCheckerServiceTest.php`**:
   - Add TLD to `SUPPORTED_TLDS` constant
   - Add test case to `availableDomainProvider()` with sample WHOIS response
   - Add test case to `registeredDomainProvider()` with sample response

5. **`README.md`** - Add to supported TLDs list

## Currently Supported TLDs (24)

WHOIS: cz, com, net, eu, org, ai, io, info, de, at, es, us, sk, ua, lt, fi, se, nl, bg, pt, it, hu, pl

RDAP: dev (uses Google's RDAP API)

## Coding Conventions

- `declare(strict_types=1);` at top of every file
- All classes are `final`
- Use `readonly` properties with constructor promotion
- Full type hints on all parameters and return types
- `private const` for class constants (UPPERCASE_SNAKE_CASE)
- PSR-4 autoloading: `App\` namespace maps to `src/`
- Minimal comments - code should be self-documenting

## Key Patterns

**WHOIS Query Flow:**
1. `DomainCheckerService::check()` extracts TLD from domain
2. Delegates to `WhoisClientInterface::query()` for WHOIS lookup
3. `parseResponse()` matches response against `AVAILABLE_PATTERNS` regex
4. Returns `DomainCheckResult` with status enum

**Rate Limiting Strategy:**
- Domains grouped by TLD
- One query per TLD per batch round
- 200ms delay between rounds
- Prevents WHOIS server rate limiting

**Special Query Formats:**
- `.de` domains require `-T dn,ace` prefix (see `buildQuery()` method)

## Testing

Tests use PHPUnit 11 with `#[Test]` and `#[DataProvider]` attributes. Each TLD has:
- An "available" test case with expected WHOIS response
- A "registered" test case with expected response

Mock `WhoisClientInterface` to test `DomainCheckerService` logic.

## Dependencies

- PHP 8.2+
- symfony/console ^7.0 - CLI framework
- amphp/socket ^2.3 - Async socket operations
- amphp/http-client ^5.3 - Async HTTP for RDAP
- phpunit/phpunit ^11.0 (dev)
