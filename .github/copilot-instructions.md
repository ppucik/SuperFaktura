# Copilot Instructions for SuperFaktura PHP Client

These instructions define how GitHub Copilot should generate, modify, and reason about code in this repository.

---

## 📌 Project Overview
SuperFaktura is a PHP client library for interacting with the SuperFaktura API.  
The project aims to provide a clean, modern, testable, and maintainable wrapper around the API with support for:

- Authentication (API key, email, password)
- CRUD operations for invoices, clients, stock items, and other entities
- Request/response normalization
- Error handling
- Modern PHP coding standards
- Automated CI (PHPStan, PHPUnit, Coding Standards)

---

## 🧱 Architecture & Code Style Rules

### PHP Version
- Target PHP **8.2+**
- Use strict types:
  ```php
  declare(strict_types=1);
