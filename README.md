# Personal Income & Expense Management System

A full-stack personal finance tracker built to master **Laravel REST API core concepts** alongside a lightweight **AlpineJS** frontend. This project focuses on real, production-style API authentication (Sanctum bearer tokens) rather than session-based shortcuts — the goal is to genuinely understand how token-based auth, validation, and per-user data isolation work under the hood.

## Why this project exists

After getting used to "vibe coding," it's easy to lose the habit of writing code confidently and understanding *why* it works. Many newcomers don't even know the basic concepts but rely entirely on AI agents to generate projects — a gap that could become a real drawback down the line. This project is a deliberate exercise in rebuilding that core knowledge: authentication flows, validation, HTTP responses, and clean API design, built by hand and understood step by step.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend Framework | Laravel 13 |
| API Authentication | Laravel Sanctum (Bearer Tokens) |
| Frontend | AlpineJS |
| HTTP Communication | Native `fetch()` |
| Database | MySQL / SQLite (configurable) |

## Core Features

### Authentication
- API-based user registration
- Email verification required before login
- Login blocked until the user's email is verified
- Sanctum bearer token issued on successful login
- Logout (token revocation)

### Finance Management
- Full CRUD for **Income** and **Expense** entries
- **Categories** for organizing transactions
- **Dashboard** with income/expense totals and summary stats
- Strict **per-user data isolation** — users can only ever see and manage their own records

### API Design Principles
- Proper HTTP status codes and consistent JSON response structure
- Request validation on every endpoint (with meaningful error messages)
- No financial data is accessible without a valid, verified, authenticated token
- Guests are fully locked out of all finance-related endpoints

## Authentication Flow

This project intentionally uses **Sanctum bearer tokens** instead of cookie/session auth, to practice genuine stateless API authentication as used in real SPA/mobile-backed systems.

```
1. POST /api/register        → creates user, sends verification email
2. User clicks verification link (verified_at is set)
3. POST /api/login           → rejected if email not verified
                              → returns Sanctum bearer token if verified
4. Authorization: Bearer <token>  → required on all protected routes
5. POST /api/logout          → revokes the current token
```

The AlpineJS frontend stores the token client-side and attaches it to every `fetch()` request via the `Authorization` header — no server-side sessions involved.

## API Endpoints (overview)

| Method | Endpoint | Description | Auth Required |
|---|---|---|---|
| POST | `/api/register` | Register a new user | No |
| GET | `/api/email/verify/{id}/{hash}` | Verify email address | Signed URL |
| POST | `/api/login` | Login (verified users only) | No |
| POST | `/api/logout` | Revoke current token | Yes |
| GET | `/api/dashboard` | Get income/expense totals | Yes |
| GET/POST | `/api/incomes` | List / create income entries | Yes |
| PUT/DELETE | `/api/incomes/{id}` | Update / delete an income entry | Yes |
| GET/POST | `/api/expenses` | List / create expense entries | Yes |
| PUT/DELETE | `/api/expenses/{id}` | Update / delete an expense entry | Yes |
| GET/POST | `/api/categories` | List / create categories | Yes |
| PUT/DELETE | `/api/categories/{id}` | Update / delete a category | Yes |

> Full request/response examples will be documented as each module is completed.

## Data Isolation & Security

- Every finance-related query is scoped to `auth()->id()` — no user can ever read or modify another user's data.
- All protected routes are wrapped in `auth:sanctum` middleware.
- Email verification is enforced via Laravel's `verified` middleware before login is permitted.
- Input is validated using Form Request classes with explicit rules and custom error messages.
- API responses follow consistent JSON structures with appropriate HTTP status codes (`200`, `201`, `401`, `403`, `404`, `422`, etc.).

## Project Structure (planned)

```
app/
 ├── Http/
 │   ├── Controllers/Api/     # Auth, Income, Expense, Category, Dashboard controllers
 │   ├── Requests/            # Form Request validation classes
 │   └── Resources/           # API Resource classes for consistent JSON output
 ├── Models/                  # User, Income, Expense, Category
resources/
 └── views/                   # AlpineJS-powered frontend (Blade + Alpine components)
routes/
 └── api.php                  # All API routes
```

## Getting Started

```bash
git clone <repo-url>
cd personal-finance
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Configure your mail driver in `.env` for email verification to work (e.g. Mailtrap for local development).

## Roadmap

- [ ] User registration + email verification
- [ ] Sanctum login/logout flow
- [ ] Category CRUD
- [ ] Income CRUD
- [ ] Expense CRUD
- [ ] Dashboard totals endpoint
- [ ] AlpineJS frontend wiring
- [ ] API documentation (request/response examples)
- [ ] Tests (feature tests for auth + CRUD)

## License

This is a personal learning project. Feel free to fork and learn from it.