# Testing Guide

## Quick Start

```bash
# Run all tests
./vendor/bin/pest

# Run with coverage
./vendor/bin/pest --coverage --min=60

# Run specific suite
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Feature

# Run specific file
./vendor/bin/pest tests/Unit/Auth/AuthTest.php


# Validation Scripts

## Full Validation
```bash
scripts\test.bat
```

Runs:

- Composer validation  
- Code style check (Pint)  
- Static analysis (PHPStan)  
- Security audit  
- Test suite  
- Coverage check  

---

## Quick Test
```bash
scripts\quick-test.bat
```

---

## Auto-fix Issues
```bash
scripts\fix.bat
```

---

## Pre-deployment Check
```bash
scripts\deploy-check.bat
```

---

# Writing Tests

## Unit Test Example
```php
<?php

use App\Services\MyService;

it('performs calculation correctly', function () {
    $service = new MyService();
    $result = $service->calculate(5, 10);
    
    expect($result)->toBe(15);
});
```

---

## Feature Test Example
```php
<?php

beforeEach(function () {
    $this->user = createUser();
    $_SESSION['user_id'] = $this->user['id'];
});

it('creates a resource', function () {
    $model = app(ResourceModel::class);
    $id = $model->create(['name' => 'Test']);
    
    expect($id)->toBeGreaterThan(0);
});
```

---

# Coverage Goals

- **Unit Tests:** 80%+  
- **Feature Tests:** 60%+  
- **Overall:** 70%+  

**Current Coverage:** 4.5%

---

# Well-Tested

- Auth (58.6%)  
- Container (91.1%)  
- Router (71.8%)  

---

# Needs Tests

- Controllers (0%)  
- Middleware (0%)  
- Services (0%)  
- Most Models (0%)  

---

# Continuous Integration

Tests run automatically on:

- Every commit to main/develop  
- Every pull request  
- Scheduled weekly security audits  
---

# Troubleshooting

## Tests fail with database errors
```bash
# Reset test database
mysql -u root -p blog_test < database/schema.sql
```

## Coverage not generating
```bash
# Verify Xdebug
php -v | findstr Xdebug

# Should show: "with Xdebug v3.x.x"
```

## Pint format errors
```bash
# Auto-fix
./vendor/bin/pint
```


***

## **✅ What We've Accomplished**

1. ✅ **PHPStan** - Static analysis configured
2. ✅ **Laravel Pint** - Code style enforcement
3. ✅ **Pest Tests** - 36 passing tests (Unit + Feature)
4. ✅ **Validation Scripts** - Automated local testing
5. ✅ **Security Audit** - Composer vulnerability scanning
6. ✅ **Coverage Reporting** - Xdebug integration
7. ✅ **CI/CD Workflows** - GitHub Actions templates

***

**Ready to add more tests or move to another improvement area?**

Options:
1. **Add more model tests** (increase coverage to 20-30%)
2. **Security hardening** (CSRF, input validation, headers)
3. **Performance optimization** (caching, queries, assets)
4. **Documentation** (API docs, architecture diagrams)


# Run unit tests (fast, mocked)
composer test:unit

# Run feature tests (slow, real DB)
composer test:feature

# Run with coverage to see what's tested
composer test:coverage

# Run mutation testing to verify test quality
composer infection
