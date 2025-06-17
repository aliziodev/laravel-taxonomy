# Contributing to Laravel Taxonomy

Thank you for your interest in contributing to Laravel Taxonomy! This document explains the contribution process and standards we use.

## 📋 Table of Contents

- [Conventional Commits](#conventional-commits)
- [Automatic Changelog](#automatic-changelog)
- [Development Workflow](#development-workflow)
- [Testing](#testing)
- [Code Style](#code-style)

## 🔄 Conventional Commits

We use [Conventional Commits](https://www.conventionalcommits.org/) for standardizing commit messages. This format enables automatic changelog generation and semantic versioning.

### Commit Message Format

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

### Supported Types

- **feat**: New feature
- **fix**: Bug fix
- **docs**: Documentation changes
- **style**: Formatting changes, missing semi colons, etc.
- **refactor**: Code refactoring without changing functionality
- **perf**: Performance improvements
- **test**: Adding or fixing tests
- **chore**: Maintenance tasks, dependency updates
- **ci**: CI configuration changes
- **build**: Build system or external dependency changes
- **revert**: Revert previous commit

### Commit Message Examples

```bash
# New feature
feat: add moveToParent method with optional rebuildNestedSet parameter

# Bug fix
fix: resolve nested set corruption on concurrent operations

# Breaking change
feat!: change taxonomy structure to support multi-tenancy

BREAKING CHANGE: The taxonomy table now requires tenant_id column

# With scope
feat(manager): add bulk operations for taxonomy management
fix(model): correct parent validation in moveToParent method
docs(readme): update installation instructions
```

## 📝 Automatic Changelog

### How It Works

We use [semantic-release](https://github.com/semantic-release/semantic-release) for:

1. **Commit Analysis**: Analyzing commit messages to determine release type
2. **Automatic Versioning**: Determining new version based on Semantic Versioning
3. **Changelog Generation**: Creating automatic changelog from commit messages
4. **GitHub Release**: Creating GitHub release with release notes

### Versioning Rules

| Commit Type | Release Type | Example |
|-------------|--------------|--------|
| `fix:` | Patch (1.0.1) | Bug fixes |
| `feat:` | Minor (1.1.0) | New features |
| `feat!:` or `BREAKING CHANGE:` | Major (2.0.0) | Breaking changes |
| `docs:`, `style:`, `test:`, `chore:` | No Release | Documentation, formatting, tests |

### Automated Workflows

#### 1. Auto Changelog Workflow (`.github/workflows/auto-changelog.yml`)

Triggered on:
- Push to `main` or `master` branch
- Manual trigger via `workflow_dispatch`

Process:
1. Run tests (Pest, PHPStan, Laravel Pint)
2. Analyze commit messages
3. Generate changelog and release notes
4. Create GitHub release
5. Update `CHANGELOG.md`

#### 2. Commitlint Workflow (`.github/workflows/commitlint.yml`)

Triggered on:
- Pull requests
- Push to main branches

Validation:
- Ensures commit messages follow conventional commits format
- Provides feedback if format is incorrect

### Configuration Files

#### `.releaserc.yml`
Semantic-release configuration:
- Plugins used
- Release rules
- Changelog format
- Commit message parsing

#### `.commitlintrc.yml`
Commitlint configuration:
- Rules for commit message validation
- Allowed types
- Format requirements

## 🔄 Development Workflow

### 1. Fork and Clone Repository

```bash
git clone https://github.com/your-username/laravel-taxonomy.git
cd laravel-taxonomy
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Create Feature Branch

```bash
git checkout -b feat/your-feature-name
```

### 4. Make Changes

- Follow existing coding standards
- Add tests for new features
- Update documentation if needed

### 5. Commit Changes

```bash
# Use conventional commits format
git add .
git commit -m "feat: add new taxonomy validation method"
```

### 6. Push and Create Pull Request

```bash
git push origin feat/your-feature-name
```

## 🧪 Testing

### Running Tests

```bash
# Semua tests
vendor/bin/pest

# Specific test file
vendor/bin/pest tests/Feature/TaxonomyFeatureTest.php

# Dengan coverage
vendor/bin/pest --coverage
```

### Test Requirements

- All new features must have tests
- Tests must cover success and failure scenarios
- Use PestPHP functional style
- Include database assertions

## 🎨 Code Style

### Laravel Pint

```bash
# Check code style
vendor/bin/pint --test

# Fix code style
vendor/bin/pint
```

### PHPStan

```bash
# Static analysis
vendor/bin/phpstan analyse
```

### Standards

- Follow PSR-12 coding standard
- Use type hints and return types
- Write descriptive method and variable names
- Add PHPDoc comments for public methods

## 📚 Tips for Contributors

### Commit Message Best Practices

1. **Use imperative mood**: "add feature" not "added feature"
2. **Clear and descriptive**: Explain what is done, not how
3. **Reference issues**: Use `Fixes #123` or `Closes #123`
4. **Breaking changes**: Always document with `BREAKING CHANGE:`

### Complete Workflow Example

```bash
# 1. Create feature branch
git checkout -b feat/bulk-taxonomy-operations

# 2. Make changes
# ... edit files ...

# 3. Add tests
# ... create tests ...

# 4. Run tests
vendor/bin/pest
vendor/bin/phpstan analyse
vendor/bin/pint --test

# 5. Commit with conventional format
git add .
git commit -m "feat: add bulk operations for taxonomy management

Implement bulkCreate, bulkUpdate, and bulkDelete methods
to improve performance for large-scale operations.

Closes #45"

# 6. Push and create PR
git push origin feat/bulk-taxonomy-operations
```

## 🚀 Release Process

### Automatic Releases

After PR is merged to main branch:

1. **Auto Changelog workflow** will run
2. **Semantic-release** analyzes commits
3. **New version** is determined based on commit types
4. **Changelog** is updated automatically
5. **GitHub release** is created with release notes
6. **Git tag** is created for new version

### Manual Release (Emergency)

If manual release is needed:

```bash
# Trigger manual release
gh workflow run auto-changelog.yml
```

## ❓ FAQ

### Q: What if my commit message has wrong format?
**A**: Commitlint workflow will give an error. You can amend commit or rebase to fix it.

### Q: Do all commits need to be conventional?
**A**: Yes, to ensure automatic changelog works properly.

### Q: How to create a breaking change?
**A**: Use `!` after type (`feat!:`) or add `BREAKING CHANGE:` in footer.

### Q: When is changelog updated?
**A**: Automatically every time there's a push to main branch containing commits that trigger a release.

---

**Thank you for contributing! 🎉**

If you have questions, please create an issue or discussion in the GitHub repository.