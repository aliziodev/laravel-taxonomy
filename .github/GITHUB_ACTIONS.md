# GitHub Actions Setup

This package includes comprehensive GitHub Actions workflows for continuous integration and deployment.

## Workflows

### 1. Tests (`tests.yml`)
- **Trigger**: Push/PR to `main` or `develop` branches
- **Matrix Testing**: 
  - PHP versions: 8.2, 8.3
  - Laravel versions: 11.*, 12.*
  - Dependency versions: prefer-lowest, prefer-stable
- **Features**:
  - Automated testing with Pest
  - PHPStan static analysis
  - Code coverage reporting
  - Codecov integration

### 2. Code Quality (`code-quality.yml`)
- **Trigger**: Push/PR to `main` or `develop` branches
- **Checks**:
  - PHPStan static analysis
  - Laravel Pint code formatting
  - Security audit with `composer audit`

### 3. Release (`release.yml`)
- **Trigger**: Git tags starting with `v*`
- **Features**:
  - Automated testing before release
  - Extract release notes from CHANGELOG.md
  - Create GitHub releases automatically

## Configuration Files

### PHPStan (`phpstan.neon`)
- Level 8 analysis (strictest)
- Laravel-specific rules via Larastan
- Pest testing framework support
- Octane compatibility checks

### Laravel Pint (`pint.json`)
- Laravel preset with custom rules
- Consistent code formatting
- Import organization
- PSR-12 compliance

## Badges for README

Add these badges to your main README.md:

```markdown
[![Tests](https://github.com/aliziodev/laravel-taxonomy/workflows/Tests/badge.svg)](https://github.com/aliziodev/laravel-taxonomy/actions)
[![Code Quality](https://github.com/aliziodev/laravel-taxonomy/workflows/Code%20Quality/badge.svg)](https://github.com/aliziodev/laravel-taxonomy/actions)
[![Latest Stable Version](https://poser.pugx.org/aliziodev/laravel-taxonomy/v/stable)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![Total Downloads](https://poser.pugx.org/aliziodev/laravel-taxonomy/downloads)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![License](https://poser.pugx.org/aliziodev/laravel-taxonomy/license)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![codecov](https://codecov.io/gh/aliziodev/laravel-taxonomy/branch/main/graph/badge.svg)](https://codecov.io/gh/aliziodev/laravel-taxonomy)
```

## Local Development

Run the same checks locally:

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run static analysis
composer analyse

# Format code
composer format

# Run all checks
composer check
```

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+
- Composer 2.0+

## Security

The workflows include:
- Dependency vulnerability scanning
- Static analysis for potential security issues
- Automated security audits

## Contributing

All pull requests must pass:
- ✅ All tests
- ✅ PHPStan level 8 analysis
- ✅ Laravel Pint formatting
- ✅ Security audit
- ✅ Minimum 80% code coverage