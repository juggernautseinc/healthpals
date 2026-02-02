# AGENTS.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

OpenEMR is a Free and Open Source electronic health records (EHR) and medical practice management application. This is version 7.0.3 built with PHP 8.1+, using a mix of modern and legacy architecture patterns.

## Development Setup

### Docker Development Environment (Recommended)

The primary development environment uses Docker. Navigate to `docker/development-easy/`:

```bash
cd docker/development-easy
docker-compose up
```

Access points after startup:
- OpenEMR: `http://localhost:8300/` or `https://localhost:9300/`
- Default credentials: username `admin`, password `pass`
- phpMyAdmin: `http://localhost:8310/`
- MySQL direct connection: `localhost:8320` (user: `openemr`, password: `openemr`)

### Build System

Node.js 20.x is required for frontend asset compilation:

```bash
# Install dependencies
composer install --no-dev
npm install

# Build themes and assets
npm run build           # Production build
npm run dev            # Development build (unminified)
npm run gulp-watch     # Watch mode for development
```

**IMPORTANT**: Never run `composer dump-autoload` in the project root as it will break the application. Each module has its own composer autoload.

### Theme Development

Themes are built using Gulp + SASS:

```bash
# Inside Docker container
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools build-themes'
```

Themes are located in `interface/themes/` and compiled to `public/themes/`.

## Testing

### Running Tests

All tests use PHPUnit 10. Test suites are defined in `phpunit.xml`:

```bash
# Inside Docker container
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools unit-test'
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools api-test'
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools e2e-test'
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools services-test'
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools controllers-test'
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools common-test'

# Run all automated tests
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools clean-sweep-tests'

# Full dev tool suite (includes PSR-12 fixes, linting, and all tests)
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools clean-sweep'
```

### Code Quality

```bash
# Check PSR-12 compliance
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools psr12-report'

# Fix PSR-12 issues
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools psr12-fix'

# Check PHP parse errors
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools php-parserror'

# Lint themes
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools lint-themes-report'
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools lint-themes-fix'
```

## Architecture

### Directory Structure

```
/var/www/html/healthpals/
├── src/                      # Modern PHP classes (PSR-4 autoloaded under OpenEMR\)
│   ├── Common/              # Core shared components (Database, Auth, Forms, etc.)
│   ├── Services/            # Business logic layer
│   ├── RestControllers/     # REST API controllers
│   ├── Validators/          # Input validation
│   ├── Events/              # Event system
│   ├── FHIR/               # FHIR R4 implementation
│   └── Reports/            # Report generation logic
├── interface/               # Legacy UI and main application interface
│   ├── themes/             # SASS theme source files
│   └── modules/            # Module system (managed by Laminas)
├── library/                 # Legacy library code
├── templates/              # Twig templates (new views)
├── public/                 # Public web assets
│   ├── assets/            # Compiled JS/CSS dependencies
│   └── themes/            # Compiled theme files
├── tests/                  # Test suites
└── _rest_routes.inc.php   # REST API route definitions
```

### Key Architectural Patterns

**API Architecture**: OpenEMR provides both Standard REST API and FHIR R4 API:
- Standard API routes defined in `_rest_routes.inc.php`
- Request flow: `Route → RestController → Service → Database`
- Response flow: `Database → Service → RestController → RestControllerHelper → JSON`
- All APIs use OAuth2 with OIDC for authentication

**Service Layer**: Business logic resides in `src/Services/`:
- Services handle data validation and business rules
- Use `src/Common/Database/QueryUtils` for database access (NOT legacy `sqlQuery`/`sqlStatement`)
- Example: `src/Services/PatientService.php`

**Database Access**:
- Use `QueryUtils::querySingleRow()` instead of deprecated `sqlQuery()`
- Use `QueryUtils::fetchRecords()` instead of `sqlStatement()` + `sqlFetchArray()` loops
- Import with: `use OpenEMR\Common\Database\QueryUtils;`
- Legacy functions are deprecated and cause PHPStan errors

**View Layer**:
- Modern code uses Twig templates in `templates/`
- Twig extensions in `src/Common/Twig/TwigExtension.php`
- Header system managed by `src/Core/Header.php`
- Use `{{ setupHeader() }}` in Twig templates
- Always escape/translate: `{{ 'Text' | xlt }}`

**Module System**:
- Modules managed by Laminas framework in `interface/modules/`
- Each module has its own `composer.json` and autoloading
- Module structure includes: `src/`, `templates/`, `public/`, `sql/` folders

**Event System**:
- Symfony event dispatcher in `src/Events/`
- Use for extensibility and decoupling

**Session Management**:
- Use `$_SESSION['site_id']` for multisite support
- NEVER use fallback values like `'default'` for site_id
- Access globals via `OEGlobalsBag::getInstance()->get('key')`

### Database

**Local Database Access**:
- Primary database: `localhost/openemr703` at `/var/www/html/openemr703`
- Connection: `mysql -u local_openemr -p 5qy3xkMjP4A2US1u7Qv`
- Schema documentation: `Documentation/EHI_Export/docs/tables/*.html`
- Core billing tables: `ar_session`, `ar_activity`, `billing`, `payments`

### Multisite Support

- Use `$GLOBALS['OE_SITES_DIR']` for site directory paths
- API endpoints support multisite: `/apis/{site}/api/...`
- Default site is `default`

## Development Workflow

### Making Changes

1. Create feature branch from `master`
2. Make code changes following PSR-12 standards
3. Run code quality checks (PSR-12, linting)
4. Write/update tests for changes
5. Verify tests pass
6. Build themes if modified (`/root/devtools build-themes`)
7. Submit PR with co-author line: `Co-Authored-By: Warp <agent@warp.dev>`

### API Development

- Standard API Swagger UI: `https://localhost:9300/swagger/`
- Update API docs: `docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools build-api-docs'`
- Register OAuth2 client: `docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools register-oauth2-client'`

### Database Changes

When adding database schema changes:
1. Create upgrade SQL file in `sql/` directory (e.g., `sql/7_0_3-to-7_0_4_upgrade.sql`)
2. For modules, add to module's `sql/` directory with version naming
3. Increment `$v_database` in `version.php`

### Debugging

```bash
# Check PHP error logs
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools php-log'

# Xdebug configuration
# - Xdebug port: 9003
# - Client: host.docker.internal
# - See CONTRIBUTING.md for IDE setup
```

### Reset/Demo Data

```bash
# Reset OpenEMR (reinstall via setup.php)
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools dev-reset'

# Reset and reinstall with demo data
docker exec -i $(docker ps | grep _openemr | cut -f 1 -d " ") sh -c '/root/devtools dev-reset-install-demodata'
```

## Important Coding Standards

### PHP Standards

- Follow PSR-12 coding standard
- Use PHP 8.1+ features (enums, first-class callables, etc.)
- Use QueryUtils for database access, not legacy functions
- Never access `$GLOBALS` directly; use `OEGlobalsBag::getInstance()`
- Encrypt sensitive data (API keys, passwords) in database
- Use custom exception types per module, not generic `Exception`

### Frontend Standards

- Use modern ES6+ JavaScript syntax (`const`/`let`, arrow functions)
- Avoid `var` unless global scope required
- Use `includes()` instead of `indexOf() !== -1`

### Security

- Never store secrets in plaintext (encrypt in database)
- Always use HTTPS for API development
- Use proper OAuth2 scopes for API access
- Never include PHI in external API calls (like ChatGPT)
- Sanitize and validate all user inputs

### Twig Templates

- Cannot use PHP in Twig templates
- Must extend `base.twig` for consistency
- Use theme-aware header system
- Datepicker class ordering: `datepicker` before `form-control`
- Always check syntax: all `{% %}` tags must close

## Additional Resources

- API Documentation: See `API_README.md` and `FHIR_README.md`
- Contributing Guide: See `CONTRIBUTING.md`
- Docker Setup: See `DOCKER_README.md`
- Security: See `.github/SECURITY.md`
- Test Documentation: See `tests/README.md`
