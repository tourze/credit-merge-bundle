# Contributing to Credit Merge Bundle

Thank you for your interest in contributing to the Credit Merge Bundle! This document provides guidelines for contributing to this project.

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Git

### Development Environment Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/your-username/php-monorepo.git
   cd php-monorepo
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Run tests to ensure everything is working:
   ```bash
   ./vendor/bin/phpunit packages/credit-merge-bundle/tests
   ```

## Development Guidelines

### Code Style

- Follow PSR-12 coding standards
- Use PHP 8.1+ features where appropriate
- Add type hints for all parameters and return values
- Use meaningful variable and method names

### Testing

- Write unit tests for all new functionality
- Ensure all tests pass before submitting a pull request
- Maintain or improve code coverage
- Use PHPUnit for testing

### Documentation

- Update README.md if you add new features
- Add inline documentation for complex methods
- Include examples for new functionality

## Pull Request Process

1. Create a feature branch from `main`:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes and commit them:
   ```bash
   git add .
   git commit -m "Add feature: description of your changes"
   ```

3. Push to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```

4. Create a pull request with:
   - Clear description of changes
   - Reference to any related issues
   - Screenshots if applicable

### Code Review

All submissions require review before being merged. Please:

- Be responsive to feedback
- Make requested changes promptly
- Keep discussions respectful and constructive

## Reporting Issues

When reporting issues, please include:

- PHP version
- Bundle version
- Steps to reproduce
- Expected vs actual behavior
- Any relevant error messages

## License

By contributing to this project, you agree that your contributions will be licensed under the MIT License.