# Contributing to Laravel Multi-Wallet

Thank you for considering contributing to Laravel Multi-Wallet! This document outlines the process for contributing to this project. We welcome contributions from developers of all skill levels.

## 🤝 Code of Conduct

This project adheres to the [Contributor Covenant Code of Conduct](https://www.contributor-covenant.org/version/2/0/code_of_conduct/). By participating, you are expected to uphold this code. Please report unacceptable behavior to mohamedhabibwork@gmail.com.

## 🎯 How Can I Contribute?

### Reporting Bugs
- 🐛 **Bug reports** - Help us identify and fix issues
- 📝 **Documentation improvements** - Make our docs clearer
- 🧪 **Test improvements** - Help maintain code quality

### Suggesting Enhancements
- 💡 **Feature requests** - Propose new functionality
- 🔧 **Performance improvements** - Optimize existing code
- 🎨 **UI/UX improvements** - Enhance user experience

### Code Contributions
- 🔨 **Bug fixes** - Fix reported issues
- ✨ **New features** - Implement requested functionality
- 🧹 **Code refactoring** - Improve code structure
- 📚 **Documentation** - Write or improve documentation

## 🐛 Bug Reports

Before creating bug reports, please check the existing issues to avoid duplicates.

### Before Submitting a Bug Report

1. **Check existing issues** - Search for similar problems
2. **Test with latest version** - Ensure the bug exists in the current release
3. **Check documentation** - Verify it's not a configuration issue
4. **Reproduce the issue** - Create a minimal test case

### Bug Report Template

```markdown
## Bug Report

### Describe the bug
A clear and concise description of what the bug is.

### To Reproduce
Steps to reproduce the behavior:
1. Create a wallet with '...'
2. Perform transaction '...'
3. See error

### Expected behavior
A clear and concise description of what you expected to happen.

### Environment
- **PHP Version:** [e.g. 8.2]
- **Laravel Version:** [e.g. 10.x]
- **Package Version:** [e.g. 1.0.0]
- **Database:** [e.g. MySQL 8.0]
- **Operating System:** [e.g. Ubuntu 22.04]

### Code Sample
```php
// Minimal code to reproduce the issue
$wallet = $user->createWallet('USD', 'Test Wallet');
// ... rest of your code
```

### Stack Trace
```
// Full stack trace if applicable
```

### Additional context
- Does this happen consistently or intermittently?
- Are you using any other packages that might interfere?
- Have you made any customizations to the package configuration?
- Any other context about the problem here.
```

## 💡 Feature Requests

We welcome feature requests! Before submitting, please:

1. **Check existing issues** - Your idea might already be discussed
2. **Think about the use case** - How would this benefit users?
3. **Consider implementation** - Is it feasible and maintainable?

### Feature Request Template

```markdown
## Feature Request

### Is your feature request related to a problem?
A clear and concise description of what the problem is.

### Describe the solution you'd like
A clear and concise description of what you want to happen.

### Describe alternatives you've considered
A clear and concise description of any alternative solutions.

### Use Case
- What type of application would benefit from this?
- How would this feature be used in practice?
- What business problem does this solve?

### Proposed API (if applicable)
```php
// Example of how you envision the feature working
$wallet->newFeature($parameter);
```

### Backward Compatibility
- [ ] This feature is backward compatible
- [ ] This feature introduces breaking changes

**Breaking changes description:**
[If applicable, describe what existing functionality would be affected]

### Additional context
Add any other context, screenshots, or examples about the feature request.
```

## 🔧 Development Setup

### Prerequisites

- **PHP 8.1+** (latest stable recommended)
- **Composer 2.0+**
- **Laravel 10.0+**
- **Git**
- **Database** (MySQL 8.0+, PostgreSQL 12+, SQLite 3.35+)

### Local Development Setup

1. **Fork and clone the repository**
   ```bash
   git clone https://github.com/mohamedhabibwork/laravel-multi-wallet.git
   cd laravel-multi-wallet
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Set up testing environment**
   ```bash
   cp phpunit.xml.dist phpunit.xml
   ```

4. **Run tests to ensure everything works**
   ```bash
   composer test
   ```

5. **Set up pre-commit hooks (optional)**
   ```bash
   # Install pre-commit hooks for code quality
   composer run-script post-install-cmd
   ```

### IDE Setup

#### PHPStorm/IntelliJ IDEA
- Install **PHP Annotations** plugin
- Configure **PHPStan** integration
- Set up **Laravel Pint** as external tool

#### VS Code
- Install **PHP Intelephense** extension
- Install **Laravel Pint** extension
- Configure **PHPStan** integration

### Database Setup

The package uses SQLite for testing by default. For local development:

```bash
# Create test database
touch database/database.sqlite

# Run migrations
php artisan migrate --env=testing
```

## 🧪 Testing

We maintain comprehensive test coverage. All contributions must include appropriate tests.

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test file
vendor/bin/pest tests/Feature/WalletTest.php

# Run tests with specific filter
vendor/bin/pest --filter="can create wallet"

# Run tests in parallel (faster)
vendor/bin/pest --parallel

# Run tests with verbose output
vendor/bin/pest --verbose
```

### Test Categories

- **Unit Tests** (`tests/Unit/`) - Test individual methods and classes
- **Feature Tests** (`tests/Feature/`) - Test complete workflows
- **Integration Tests** - Test database operations and external services
- **Architecture Tests** (`tests/ArchTest.php`) - Test code structure

### Writing Tests

#### Test Naming Convention
```php
// Good test names
test('it can create wallet with valid parameters')
test('it throws exception when debiting insufficient funds')
test('it can transfer funds between wallets with fee')

// Avoid generic names
test('test wallet creation') // Too generic
test('works') // Not descriptive
```

#### Test Structure (AAA Pattern)
```php
test('it can transfer funds between wallets with fee', function () {
    // Arrange - Set up test data
    $sender = User::factory()->create();
    $recipient = User::factory()->create();
    $senderWallet = $sender->createWallet('USD', 'Main');
    $recipientWallet = $recipient->createWallet('USD', 'Main');
    $senderWallet->credit(100.00, 'available');
    
    // Act - Perform the action being tested
    $transfer = $sender->transferTo($recipient, 50.00, 'USD', ['fee' => 2.50]);
    
    // Assert - Verify the results
    expect($transfer->getNetAmount())->toBe(52.50);
    expect($senderWallet->fresh()->getBalance('available'))->toBe(47.50);
    expect($recipientWallet->fresh()->getBalance('available'))->toBe(50.00);
});
```

#### Test Best Practices

- **Use factories** for test data generation
- **Test edge cases** and error conditions
- **Keep tests independent** - no shared state
- **Use descriptive assertions** - explain what you're testing
- **Mock external dependencies** when appropriate
- **Test both positive and negative scenarios**

### Test Data

```php
// Use factories for consistent test data
$user = User::factory()->create();
$wallet = Wallet::factory()->for($user)->create();

// Create specific test scenarios
$wallet = Wallet::factory()
    ->for($user)
    ->state(['currency' => 'USD', 'balance' => 1000.00])
    ->create();
```

## 📝 Code Style

We use **Laravel Pint** for code formatting and **PHPStan** for static analysis.

### Code Standards

- **PSR-12** coding standards
- **Type declarations** for all parameters and return types
- **PHPDoc blocks** for all public methods
- **Meaningful variable names** - avoid abbreviations
- **Small, focused methods** - single responsibility
- **SOLID principles** - clean architecture

### Running Code Quality Tools

```bash
# Fix code style issues automatically
composer format

# Check code style without fixing
vendor/bin/pint --test

# Run static analysis
composer analyse

# Run all quality checks
composer check-quality
```

### Code Style Examples

#### Good Code Style
```php
<?php

declare(strict_types=1);

namespace HWallet\LaravelMultiWallet\Services;

use HWallet\LaravelMultiWallet\Models\Wallet;
use HWallet\LaravelMultiWallet\Models\Transfer;

class WalletService
{
    /**
     * Transfer funds between wallets.
     *
     * @param Wallet $fromWallet The source wallet
     * @param Wallet $toWallet The destination wallet
     * @param float $amount The amount to transfer
     * @param array<string, mixed> $options Additional transfer options
     *
     * @throws InsufficientFundsException When source wallet has insufficient funds
     * @throws InvalidAmountException When amount is invalid
     */
    public function transfer(
        Wallet $fromWallet,
        Wallet $toWallet,
        float $amount,
        array $options = []
    ): Transfer {
        $this->validateTransfer($fromWallet, $toWallet, $amount);
        
        return $this->executeTransfer($fromWallet, $toWallet, $amount, $options);
    }
}
```

#### Code Style Checklist

- [ ] **Strict types** declared at file top
- [ ] **Proper namespacing** and imports
- [ ] **Type hints** for all parameters and return types
- [ ] **PHPDoc blocks** for public methods
- [ ] **Meaningful variable names** (no `$a`, `$b`, etc.)
- [ ] **Consistent indentation** (4 spaces)
- [ ] **No trailing whitespace**
- [ ] **Proper line endings** (LF, not CRLF)

## 🔄 Pull Request Process

### Before Submitting

1. **Create a feature branch** from `main`
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Write tests** for your changes
3. **Ensure all tests pass**
4. **Run code quality tools**
5. **Update documentation** if needed
6. **Self-review** your changes

### Commit Message Guidelines

Use conventional commit format:

```bash
# Format: type(scope): description
feat(wallet): add support for wallet freezing
fix(transfer): resolve fee calculation bug
docs(readme): update installation instructions
test(wallet): add tests for balance validation
refactor(service): improve wallet manager performance
```

#### Commit Types
- `feat` - New feature
- `fix` - Bug fix
- `docs` - Documentation changes
- `style` - Code style changes (formatting, etc.)
- `refactor` - Code refactoring
- `test` - Adding or updating tests
- `chore` - Maintenance tasks

### Pull Request Guidelines

1. **Clear, descriptive title** - What does this PR do?
2. **Detailed description** - Why is this change needed?
3. **Link to related issues** - Fixes #123, Closes #456
4. **Screenshots** for UI changes
5. **Breaking changes** clearly documented
6. **Testing instructions** - How to test the changes

### Pull Request Template

```markdown
## Description
Brief description of changes made.

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update
- [ ] Performance improvement
- [ ] Code refactoring
- [ ] Test improvements

## Related Issues
Fixes #(issue number)
Closes #(issue number)
Resolves #(issue number)

## Changes Made
- [ ] Added new feature X
- [ ] Fixed bug in Y
- [ ] Updated documentation for Z
- [ ] Improved performance of W

## Testing
- [ ] Tests pass locally (`composer test`)
- [ ] New tests added for changes
- [ ] Test coverage maintained or improved
- [ ] Manual testing performed

## Code Quality
- [ ] Code follows project style guidelines (`composer format`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] Self-review completed
- [ ] Code is properly documented (PHPDoc blocks)

## Documentation
- [ ] README.md updated (if needed)
- [ ] CHANGELOG.md updated
- [ ] Configuration examples updated (if needed)
- [ ] New features documented with examples

## Breaking Changes
- [ ] No breaking changes
- [ ] Breaking changes documented below

**Breaking changes description:**
[If applicable, describe what existing functionality would be affected and how users should migrate]

## Screenshots (if applicable)
[Add screenshots here if the changes include visual elements]

## Additional Notes
[Any additional information that reviewers should know]

## Checklist
- [ ] I have read the [CONTRIBUTING.md](CONTRIBUTING.md) guidelines
- [ ] My code follows the project's coding standards
- [ ] I have performed a self-review of my own code
- [ ] I have commented my code, particularly in hard-to-understand areas
- [ ] I have made corresponding changes to the documentation
- [ ] My changes generate no new warnings or errors
- [ ] I have added tests that prove my fix is effective or that my feature works
- [ ] New and existing unit tests pass locally with my changes
```

## 📚 Documentation

### Documentation Standards

- **Clear, concise language** - Avoid jargon
- **Working code examples** - Test all examples
- **Expected outputs** - Show what users should expect
- **Use cases** - Explain when and why to use features
- **Configuration examples** - Provide real-world configs

### Updating Documentation

- **README.md** - Update for new features
- **PHPDoc blocks** - Document all public methods
- **Configuration files** - Update examples
- **CHANGELOG.md** - Document all changes
- **Migration guides** - For breaking changes

### Documentation Style Guide

```markdown
## Feature Name

Brief description of the feature.

### Basic Usage

```php
// Simple example
$wallet = $user->createWallet('USD', 'Main Wallet');
```

### Advanced Usage

```php
// More complex example with options
$wallet = $user->createWallet('EUR', 'European Wallet', [
    'description' => 'For European transactions',
    'meta' => ['region' => 'EU']
]);
```

### Configuration

```php
// config/multi-wallet.php
return [
    'feature_setting' => true,
    'advanced_option' => 'value',
];
```

### Events

The following events are fired:
- `WalletCreated` - When a wallet is created
- `TransactionCompleted` - When a transaction is completed
```

## 🏗️ Architecture Guidelines

### Design Principles

- **Single Responsibility** - Each class has one reason to change
- **Open/Closed** - Open for extension, closed for modification
- **Liskov Substitution** - Subtypes must be substitutable for base types
- **Interface Segregation** - Many specific interfaces are better than one general
- **Dependency Inversion** - Depend on abstractions, not concretions

### Patterns Used

- **Repository Pattern** - For data access abstraction
- **Service Pattern** - For business logic encapsulation
- **Factory Pattern** - For object creation
- **Observer Pattern** - For event handling
- **Strategy Pattern** - For configurable behaviors
- **Builder Pattern** - For complex object construction

### Code Organization

```
src/
├── Contracts/          # Interfaces and contracts
├── Enums/             # Enumeration classes
├── Events/            # Event classes
├── Exceptions/        # Custom exceptions
├── Facades/           # Laravel facades
├── Listeners/         # Event listeners
├── Models/            # Eloquent models
├── Observers/         # Model observers
├── Providers/         # Service providers
├── Repositories/      # Data access layer
├── Services/          # Business logic
└── Traits/            # Reusable traits
```

## 🔒 Security Considerations

When contributing, please consider:

- **Input validation** - Validate all user inputs
- **SQL injection** - Use Eloquent ORM properly
- **Mass assignment** - Define fillable attributes
- **Authorization** - Check permissions where needed
- **Audit trails** - Log important operations
- **Data encryption** - Protect sensitive data
- **Rate limiting** - Prevent abuse

### Security Checklist

- [ ] All inputs are validated
- [ ] Database queries use parameterized statements
- [ ] Sensitive data is properly handled
- [ ] Authorization checks are in place
- [ ] Audit logging is implemented
- [ ] No sensitive data in logs or error messages

## 📋 Release Process

### Version Bumping

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** - Breaking changes
- **MINOR** - New features (backward compatible)
- **PATCH** - Bug fixes (backward compatible)

### Release Checklist

1. **Update version** in `composer.json`
2. **Update CHANGELOG.md** with release notes
3. **Create git tag** for the version
4. **Push to GitHub** and create release
5. **Update Packagist** (automatic via webhook)
6. **Announce release** to community

### Release Commands

```bash
# Create release tag
git tag -a v1.1.0 -m "Release version 1.1.0"
git push origin v1.1.0

# Create GitHub release
gh release create v1.1.0 --title "Version 1.1.0" --notes "Release notes here"
```

## 🆘 Getting Help

### Communication Channels

- **GitHub Issues** - For bugs and feature requests
- **GitHub Discussions** - For questions and general discussion
- **Email** - mohamedhabibwork@gmail.com for security issues
- **Discord/Slack** - Community channels (if available)

### Before Asking for Help

1. **Check documentation** - Your question might be answered
2. **Search existing issues** - Similar problems might exist
3. **Try to reproduce** - Create a minimal test case
4. **Provide context** - Include environment details

### Asking Good Questions

```markdown
## Question Title

### What I'm trying to do
Describe what you're trying to accomplish.

### What I've tried
List the approaches you've already attempted.

### Environment
- PHP Version: 8.2
- Laravel Version: 10.x
- Package Version: 1.0.0

### Code Example
```php
// Your code here
```

### Expected vs Actual Behavior
What you expected vs what actually happened.

### Additional Context
Any other relevant information.
```

## 📄 License

By contributing, you agree that your contributions will be licensed under the MIT License.

## 🙏 Recognition

### Contributor Recognition

Contributors will be recognized in:

- **README.md** contributors section
- **CHANGELOG.md** for significant contributions
- **GitHub releases** acknowledgments
- **Community shoutouts** for major contributions

### Contributor Levels

- **First-time contributor** - Welcome to the community!
- **Regular contributor** - Consistent quality contributions
- **Core contributor** - Significant impact on the project
- **Maintainer** - Trusted with repository access

### Contribution Badges

Earn badges for different types of contributions:

- 🐛 **Bug Hunter** - Fixed bugs
- ✨ **Feature Creator** - Added new features
- 📚 **Documentation Hero** - Improved docs
- 🧪 **Test Champion** - Enhanced test coverage
- 🔧 **Code Quality Guardian** - Improved code quality

## 🎯 Getting Started for New Contributors

### Good First Issues

Look for issues labeled:
- `good first issue` - Perfect for newcomers
- `help wanted` - Need community help
- `documentation` - Documentation improvements
- `tests` - Test coverage improvements

### Mentorship

- **Ask questions** - Don't hesitate to ask for clarification
- **Start small** - Begin with documentation or tests
- **Get feedback** - Request reviews on your PRs
- **Learn from others** - Review existing code and PRs

### Resources

- [Laravel Documentation](https://laravel.com/docs)
- [PHP Documentation](https://www.php.net/docs.php)
- [GitHub Flow](https://guides.github.com/introduction/flow/)
- [Conventional Commits](https://www.conventionalcommits.org/)

---

Thank you for helping make Laravel Multi-Wallet better! 🎉

Your contributions help the entire Laravel community build better financial applications. 