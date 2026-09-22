# Inventory Management API

A full-stack inventory management backend built with Laravel 12 and PostgreSQL, designed around secure authentication, multi-business data isolation, role-based access control, inventory management, and production deployment.

The API powers a separate Next.js inventory management dashboard.

> **Commercial project:** This repository is primarily intended to demonstrate the engineering work and architecture behind the application. Commercial deployment, customization, licensing, and redistribution are separate from this public project documentation.

---

## Overview

The Inventory Management API provides the backend infrastructure for a business inventory management platform.

It allows businesses to manage:

* Products
* Categories
* Stock levels
* Stock movements
* Users
* User roles
* Inventory analytics

The application uses a **multi-business architecture**, meaning users and inventory records are associated with a specific business.

This provides the foundation for a multi-tenant SaaS-style architecture.

---

## Core Features

### Authentication

The API supports:

* Email/password registration
* Email verification
* Verification email resend
* Secure login
* Logout
* Password reset
* Password recovery
* Google OAuth
* Laravel Sanctum authentication
* Authentication throttling
* Protected API routes

### Multi-Business Data Isolation

Every user belongs to a business.

Inventory resources are associated with the relevant business through `business_id`.

Conceptually:

```text
Business A
├── Users
├── Categories
├── Products
└── Stock Movements

Business B
├── Users
├── Categories
├── Products
└── Stock Movements
```

This ensures the application can support multiple independent businesses without mixing their inventory data.

---

## User Roles

The application currently supports:

* **Admin**
* **Manager**

Administrative operations are protected using role-based middleware.

Examples include:

* Product deletion
* Category deletion
* User management
* User role changes

---

## Product Management

The API supports:

* Product creation
* Product listing
* Product details
* Product updates
* Product deletion
* Product categories
* SKU management
* Unique slugs
* Product pricing
* Stock quantities
* Low-stock thresholds
* Active/inactive products
* Soft deletion
* Low-stock queries

---

## Category Management

Businesses can manage their inventory categories through the API.

Supported operations include:

* Create category
* List categories
* View category
* Update category
* Delete category

Categories are business-scoped.

---

## Stock Management

Products can have stock movements recorded against them.

The system supports:

* Stock additions
* Stock reductions
* Movement quantities
* Movement history
* Movement types
* User attribution

This creates an auditable inventory history rather than simply changing a product's quantity without context.

---

## Dashboard Analytics

The dashboard API provides inventory-level business metrics including:

* Total products
* Total categories
* Total stock units
* Low-stock count
* Out-of-stock count
* Inventory value

Example endpoint:

```text
GET /api/dashboard/summary
```

---

# Authentication Architecture

## Email Authentication

```text
Registration
     │
     ▼
Create Business
     │
     ▼
Create User
     │
     ▼
Send Verification Email
     │
     ▼
Verify Email
     │
     ▼
Login
     │
     ▼
Sanctum Token
     │
     ▼
Authenticated Requests
```

## Google OAuth

Google authentication uses Laravel Socialite.

The flow is separated into two stages:

```text
Next.js
   │
   ▼
Laravel Google OAuth
   │
   ▼
Google
   │
   ▼
OAuth Callback
   │
   ▼
Find/Create User
   │
   ▼
Short-lived OAuth Code
   │
   ▼
Next.js Callback
   │
   ▼
Code Exchange
   │
   ▼
Sanctum Token
   │
   ▼
HTTP-only Cookie
   │
   ▼
Dashboard
```

OAuth handoff codes are:

* Randomly generated
* Stored as SHA-256 hashes
* Short-lived
* Single-use
* Protected against concurrent reuse

Google-authenticated users are also marked as having a verified email because Google has already verified the identity's email address.

---

# Transactional Email

Brevo is used for transactional email delivery.

The API sends:

* Email verification emails
* Password reset emails

The application uses the Brevo HTTP API rather than depending on SMTP connectivity.

---

# Technology Stack

## Backend

* Laravel 12
* PHP 8.2
* Laravel Sanctum
* Laravel Socialite
* PostgreSQL
* Eloquent ORM
* REST API
* PHPUnit

## Frontend

The backend API is consumed by a separate:

* Next.js application
* TypeScript
* Tailwind CSS

## External Services

* Google OAuth
* Brevo

## Infrastructure

* GitHub
* Render
* PostgreSQL
* Vercel

---

# System Architecture

```text
                     ┌───────────────────────────┐
                     │      Next.js Frontend     │
                     │                           │
                     │ Dashboard                 │
                     │ Products                  │
                     │ Categories                │
                     │ Stock                     │
                     │ Users                     │
                     └─────────────┬─────────────┘
                                   │
                                   │ HTTPS / JSON
                                   ▼
                     ┌───────────────────────────┐
                     │       Laravel API         │
                     │                           │
                     │ Controllers               │
                     │ Requests                  │
                     │ Resources                 │
                     │ Middleware                │
                     │ Authentication            │
                     └─────────────┬─────────────┘
                                   │
               ┌───────────────────┼───────────────────┐
               │                   │                   │
               ▼                   ▼                   ▼
        ┌────────────┐       ┌────────────┐      ┌────────────┐
        │ PostgreSQL │       │   Brevo    │      │   Google   │
        │            │       │            │      │   OAuth    │
        │ Application│       │ Transaction│      │            │
        │    Data    │       │   Email    │      │ Identity   │
        └────────────┘       └────────────┘      └────────────┘
```

---

# API Structure

The API is organized around several major areas.

```text
Authentication
├── Register
├── Login
├── Logout
├── Email verification
├── Verification resend
├── Forgot password
├── Reset password
└── Google OAuth

Inventory
├── Products
├── Categories
└── Stock movements

Dashboard
└── Inventory summary

Users
├── List users
└── Update user roles

System
└── Health check
```

---

# Security

Security considerations implemented in the application include:

* Laravel Sanctum authentication
* Verified-email middleware
* Authentication throttling
* Registration throttling
* Password reset throttling
* Verification throttling
* Role-based authorization
* Business-level data isolation
* Hashed passwords
* Hashed OAuth handoff codes
* Short-lived OAuth codes
* Single-use OAuth codes
* HTTP-only authentication cookies
* Signed verification URLs
* Environment-based secrets
* Production debug mode disabled

Secrets such as API keys, OAuth client secrets, database credentials, and application keys are stored in environment variables and are not committed to source control.

---

# Testing

The backend currently has a fully passing automated test suite.

```text
84 / 84 tests passed
372 assertions
```

Authentication and application behavior were tested after implementing:

* Email registration
* Email verification
* Password reset
* Google OAuth
* Sanctum authentication
* Protected API routes
* Role-based access
* Business-scoped data

The final authentication implementation was verified against the production application after deployment.

---

# Health Check

The API provides a health endpoint:

```text
GET /api/health
```

Successful response:

```json
{
    "status": "ok",
    "service": "inventory-api"
}
```

This endpoint is also used to verify that the deployed API is responding correctly.

---

# Production Architecture

The application is deployed using separate frontend and backend services.

```text
GitHub
   │
   ├──────────────► Vercel
   │                  │
   │                  │ Next.js
   │                  ▼
   │            Inventory Dashboard
   │
   └──────────────► Render
                      │
                      │ Laravel API
                      ▼
                  PostgreSQL
```

The frontend communicates with the Laravel backend through HTTPS API requests.

---

# Environment Configuration

The application uses environment variables for deployment-specific configuration.

Examples include:

```env
APP_URL=
FRONTEND_URL=

DB_CONNECTION=
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

BREVO_API_KEY=
BREVO_SENDER_EMAIL=
BREVO_SENDER_NAME=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
```

Actual production credentials are intentionally excluded from the repository.

---

# Local Development

Install PHP dependencies:

```bash
composer install
```

Create the environment configuration:

```bash
cp .env.example .env
```

Generate the application key:

```bash
php artisan key:generate
```

Configure the database and required environment variables.

Run migrations:

```bash
php artisan migrate
```

Start the Laravel development server:

```bash
php artisan serve
```

Run the test suite:

```bash
php artisan test
```

---

# Engineering Highlights

This project demonstrates practical experience with:

* Laravel API development
* RESTful API design
* PostgreSQL
* Eloquent ORM
* Database migrations
* Authentication architecture
* OAuth integration
* Laravel Sanctum
* Role-based authorization
* Multi-business data isolation
* Transactional email
* API security
* Automated testing
* Production deployment
* Frontend/backend separation
* SaaS-oriented architecture

---

# Project Status

The core inventory management platform is implemented and deployed.

### Completed

* Production Laravel API
* Production Next.js dashboard
* PostgreSQL database
* Email/password authentication
* Email verification
* Password reset
* Google OAuth
* Sanctum authentication
* Multi-business architecture
* Admin/manager roles
* Product management
* Category management
* Stock management
* Dashboard analytics
* Production health check
* Automated backend testing

### Current verification

**84/84 backend tests passing.**

---

# Commercial Use

This project is part of a professional development and portfolio project.

The public repository documentation is intended to demonstrate the architecture, engineering decisions, and capabilities of the system.

Commercial versions may include:

* Private source code
* Business-specific customization
* Branding
* Deployment
* Additional modules
* Custom integrations
* Data migration
* Maintenance and support

Commercial licensing and usage rights should be agreed upon separately with the developer.

---

# License

No open-source license is granted by this repository unless a separate license file explicitly states otherwise.

All rights not expressly granted are reserved by the project owner.
