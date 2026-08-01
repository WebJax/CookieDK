# CookieDK tests

## Dansk

### Kør tests
1. Installer dependencies:
   - `composer install`
2. Kør PHPUnit:
   - `vendor/bin/phpunit -c tests/phpunit.xml.dist`
3. Kør coverage-rapport:
   - `XDEBUG_MODE=coverage vendor/bin/phpunit -c tests/phpunit.xml.dist --coverage-text --coverage-html build/coverage`

### Krav
- PHP 7.4+
- Composer
- Xdebug eller PCOV til coverage

## English

### Run tests
1. Install dependencies:
   - `composer install`
2. Run PHPUnit:
   - `vendor/bin/phpunit -c tests/phpunit.xml.dist`
3. Generate coverage:
   - `XDEBUG_MODE=coverage vendor/bin/phpunit -c tests/phpunit.xml.dist --coverage-text --coverage-html build/coverage`

### Notes
- Target is at least 80% coverage for critical plugin logic.
- Unit tests cover cookie detection, storage CRUD, GDPR formatting/sanitization, frontend rendering helpers, and admin sanitization.
