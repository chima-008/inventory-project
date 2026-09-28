# Inventory Management API

A full-stack-ready inventory management REST API built with **Laravel 12, PHP, PostgreSQL, and Laravel Sanctum**.

The API provides authentication, user management, business profiles, products, categories, stock movements, invitations, dashboard statistics, email verification, password recovery, and Google authentication.

The backend is designed to work with a separate Next.js frontend.

---

# Live Deployment

**Production API:** inventory-api-utcf.onrender.com

**Production Frontend:** inventory-dashboard-sand-alpha.vercel.app

**Frontend Repository:** chima-008/inventory-dashboard

**Backend Repository:** chima-008/inventory-project

---

# Overview

The Inventory Management API provides the backend services for a business inventory management application.

It is responsible for:

* Authentication
* Authorization
* User accounts
* Business profiles
* User invitations
* Product management
* Category management
* Stock movements
* Dashboard statistics
* Email verification
* Password recovery
* Google authentication
* Database persistence
* API validation

The frontend communicates with the API through HTTP requests.

---

# Tech Stack

## Backend

* Laravel 12
* PHP 8.2+
* PostgreSQL
* Eloquent ORM
* Laravel Sanctum
* Laravel Mail
* Laravel Storage

## Authentication

* Laravel Sanctum
* Email verification
* Password reset
* Google OAuth

## Deployment

* Backend: Render
* Database: PostgreSQL on Render
* Frontend: Vercel
* Source control: GitHub

---

# Architecture

```text
┌───────────────────────────────────┐
│          Next.js Frontend         │
│             Vercel                │
│                                   │
│  Dashboard                        │
│  Products                         │
│  Categories                       │
│  Stock                            │
│  Users                            │
│  Authentication                   │
└─────────────────┬─────────────────┘
                  │
                  │ HTTPS / REST API
                  ▼
┌───────────────────────────────────┐
│           Laravel API             │
│             Render                │
│                                   │
│  Authentication                   │
│  Products                         │
│  Categories                       │
│  Stock Movements                  │
│  Users / Roles                    │
│  Invitations                      │
│  Dashboard Summary                │
│  Business / Profile               │
└─────────────────┬─────────────────┘
                  │
                  │ Eloquent
                  ▼
┌───────────────────────────────────┐
│       PostgreSQL Database         │
│             Render                │
└───────────────────────────────────┘
```

---

# Features

## Authentication

The API supports:

* User registration
* Login
* Authenticated user retrieval
* Email verification
* Verification email resend
* Password reset
* Google authentication

---

## User Management

The API supports:

* Listing users
* Updating user roles
* Updating user profiles
* Updating business information

Authorization is enforced by the backend.

---

## Invitations

The API provides a multi-user invitation workflow.

Supported operations include:

* Create invitation
* View invitation
* Accept invitation
* Complete invitation

---

## Product Management

The API supports:

* Product creation
* Product listing
* Product retrieval
* Product updates
* Product deletion
* Category relationships
* Product pricing
* Stock quantities
* Low-stock thresholds
* Active/inactive product status

---

## Category Management

The API supports:

* Category creation
* Category listing
* Category retrieval
* Category updates
* Category deletion

---

## Stock Management

Products can have stock movement records.

The API supports:

* Listing stock movements
* Creating stock movements
* Associating movements with products
* Maintaining inventory history

---

## Dashboard Summary

The API provides a dashboard summary endpoint containing inventory statistics.

The summary includes:

```text
total_products
total_categories
total_stock_units
low_stock_count
out_of_stock_count
inventory_value
```

These values are consumed by the frontend dashboard.

---

# Production Environment

The application is deployed on Render.

Production API:

```text
https://inventory-api-utcf.onrender.com
```

Production API base path:

```text
https://inventory-api-utcf.onrender.com/api
```

The deployed frontend is:

```text
https://inventory-dashboard-sand-alpha.vercel.app
```

The production frontend URL is supplied to the backend through:

```env
FRONTEND_URL=https://inventory-dashboard-sand-alpha.vercel.app
```

---

# Environment Variables

Production environment variables must be configured through Render's environment settings.

Never commit production credentials to GitHub.

A safe production configuration has the following structure:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=your_application_key
APP_URL=https://inventory-api-utcf.onrender.com

DB_CONNECTION=pgsql
DB_URL=your_postgresql_connection_string

FRONTEND_URL=https://inventory-dashboard-sand-alpha.vercel.app

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_FROM_ADDRESS=your_sender_email
MAIL_FROM_NAME="Inventory Dashboard"

GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=https://inventory-api-utcf.onrender.com/api/auth/google/callback
```

### Important

The following values must remain private:

* `APP_KEY`
* `DB_URL`
* Database passwords
* SMTP passwords
* Google client secrets
* API keys

They belong in Render's environment configuration, not in the repository.

---

# Local Development

Local development uses a separate configuration from production.

Typical local architecture:

```text
Next.js
localhost:3000
      │
      ▼
Laravel
127.0.0.1:8000
      │
      ▼
Local PostgreSQL
127.0.0.1:5432
```

---

# Requirements

Install:

* PHP 8.2+
* Composer
* PostgreSQL
* Git
* Node.js/npm if running the frontend

Verify PHP:

```bash
php -v
```

Verify Composer:

```bash
composer -V
```

---

# Installation

Clone the repository:

```bash
git clone https://github.com/chima-008/inventory-project.git
```

Move into the project:

```bash
cd inventory-project
```

Install dependencies:

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
```

Generate the Laravel application key:

```bash
php artisan key:generate
```

---

# Local Environment Configuration

A local PostgreSQL setup can use:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=inventory_project
DB_USERNAME=postgres
DB_PASSWORD=your_local_database_password

FRONTEND_URL=http://localhost:3000
```

Configure mail and Google OAuth credentials separately when those features are required locally.

Never copy production secrets into a public repository.

---

# Database Setup

Create a PostgreSQL database named:

```text
inventory_project
```

Then run migrations:

```bash
php artisan migrate
```

If seed data is configured:

```bash
php artisan db:seed
```

Or:

```bash
php artisan migrate --seed
```

To rebuild the database during development:

```bash
php artisan migrate:fresh --seed
```

Use `migrate:fresh` carefully because it deletes existing database tables and data.

---

# Storage

Create Laravel's storage link when required:

```bash
php artisan storage:link
```

---

# Running the API

Start the Laravel development server:

```bash
php artisan serve
```

The local API will normally be available at:

```text
http://127.0.0.1:8000
```

API base URL:

```text
http://127.0.0.1:8000/api
```

---

# API Reference

## Health

```http
GET /api/health
```

Checks whether the API is responding.

---

# Authentication

## Register

```http
POST /api/register
```

## Login

```http
POST /api/login
```

## Current User

```http
GET /api/user
```

## Resend Email Verification

```http
POST /api/email/verification-notification
```

## Verify Email

```http
GET /api/email/verify/{id}/{hash}
```

## Forgot Password

```http
POST /api/forgot-password
```

## Reset Password

```http
POST /api/reset-password
```

---

# Google Authentication

## Redirect to Google

```http
GET /api/auth/google
```

## Google Callback

```http
GET /api/auth/google/callback
```

## Exchange Authentication Code

```http
POST /api/auth/google/exchange
```

Production callback:

```text
https://inventory-api-utcf.onrender.com/api/auth/google/callback
```

The production callback must also be registered with the corresponding Google OAuth application.

---

# Products

## List Products

```http
GET /api/products
```

## Create Product

```http
POST /api/products
```

## View Product

```http
GET /api/products/{product}
```

## Update Product

```http
PUT /api/products/{product}
```

or:

```http
PATCH /api/products/{product}
```

## Delete Product

```http
DELETE /api/products/{product}
```

---

# Categories

## List Categories

```http
GET /api/categories
```

## Create Category

```http
POST /api/categories
```

## View Category

```http
GET /api/categories/{category}
```

## Update Category

```http
PUT /api/categories/{category}
```

or:

```http
PATCH /api/categories/{category}
```

## Delete Category

```http
DELETE /api/categories/{category}
```

---

# Stock Movements

## List Product Stock Movements

```http
GET /api/products/{product}/stock-movements
```

## Create Stock Movement

```http
POST /api/products/{product}/stock-movements
```

---

# Dashboard

## Inventory Summary

```http
GET /api/dashboard/summary
```

Returns dashboard statistics including:

```text
total_products
total_categories
total_stock_units
low_stock_count
out_of_stock_count
inventory_value
```

---

# Users

## List Users

```http
GET /api/users
```

## Update User Role

```http
PATCH /api/users/{user}/role
```

---

# Profile and Business

## Update User Profile

```http
PATCH /api/user/profile
```

## Update Business

```http
PATCH /api/business
```

---

# Invitations

## Create Invitation

```http
POST /api/invitations
```

## View Invitation

```http
GET /api/invitations/{token}
```

## Accept Invitation

```http
POST /api/invitations/accept
```

## Complete Invitation

```http
POST /api/invitations/complete
```

---

# Current Route Summary

The backend currently exposes the following major API groups:

```text
Authentication
├── POST   /api/register
├── POST   /api/login
├── GET    /api/user
├── POST   /api/email/verification-notification
├── GET    /api/email/verify/{id}/{hash}
├── POST   /api/forgot-password
└── POST   /api/reset-password

Google Authentication
├── GET    /api/auth/google
├── GET    /api/auth/google/callback
└── POST   /api/auth/google/exchange

Business / Profile
├── PATCH  /api/business
└── PATCH  /api/user/profile

Products
├── GET    /api/products
├── POST   /api/products
├── GET    /api/products/{product}
├── PUT    /api/products/{product}
├── PATCH  /api/products/{product}
└── DELETE /api/products/{product}

Categories
├── GET    /api/categories
├── POST   /api/categories
├── GET    /api/categories/{category}
├── PUT    /api/categories/{category}
├── PATCH  /api/categories/{category}
└── DELETE /api/categories/{category}

Stock Movements
├── GET    /api/products/{product}/stock-movements
└── POST   /api/products/{product}/stock-movements

Dashboard
└── GET    /api/dashboard/summary

Users
├── GET    /api/users
└── PATCH  /api/users/{user}/role

Invitations
├── POST   /api/invitations
├── GET    /api/invitations/{token}
├── POST   /api/invitations/accept
└── POST   /api/invitations/complete

System
└── GET    /api/health
```

Use the following command whenever you need to verify the authoritative route list for the current version:

```bash
php artisan route:list
```

---

# Database

The application uses PostgreSQL.

## Local

Local development can use:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=inventory_project
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

## Production

The Render deployment uses a PostgreSQL connection configured through an environment variable.

For security, the actual production connection string is intentionally not documented.

Example:

```env
DB_CONNECTION=pgsql
DB_URL=your_render_postgresql_connection_string
```

---

# Data Model

The inventory system contains several major entities.

```text
User
 │
 ├── Profile
 ├── Business
 └── Roles / Access
       │
       ├── Products
       │      │
       │      ├── Category
       │      └── Stock Movements
       │
       └── Invitations
```

Products are associated with categories and can have stock movement records.

---

# Product Data

Products contain inventory-related information such as:

```text
id
category_id
name
slug
sku
description
price
stock_quantity
low_stock_threshold
is_active
created_at
updated_at
deleted_at
```

The product model supports soft deletion.

Product identifiers such as SKU and slug are designed to be unique.

---

# Inventory Calculations

The backend calculates inventory value using product price and stock quantity.

Conceptually:

```text
Inventory Value =
Σ (Product Stock Quantity × Product Price)
```

The dashboard summary endpoint returns the resulting value to the frontend.

---

# Validation

Validation is performed by the backend before data is persisted.

Validation can cover:

* Required fields
* Data types
* Numeric values
* Unique fields
* Database relationships
* Authentication requirements
* Authorization requirements

Frontend validation should be considered a user-experience layer, not the primary security boundary.

---

# Authorization

The backend is the authoritative source for authorization.

Frontend UI restrictions are not sufficient protection.

Protected operations must be enforced server-side so that users cannot bypass permissions by manually sending API requests.

---

# Email

The application supports email functionality for:

* Email verification
* Password recovery
* Invitations

The production deployment uses an SMTP mail configuration.

Example:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_FROM_ADDRESS=your_sender_email
MAIL_FROM_NAME="Inventory Dashboard"
```

Credentials must be configured through Render environment variables.

---

# Project Structure

```text
inventory-project/
│
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   └── Resources/
│   │
│   ├── Models/
│   └── ...
│
├── bootstrap/
├── config/
│
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
│
├── public/
├── resources/
├── routes/
│   ├── api.php
│   └── web.php
│
├── storage/
├── tests/
│
├── .env.example
├── artisan
├── composer.json
└── README.md
```

---

# Development Commands

## Start server

```bash
php artisan serve
```

## Run migrations

```bash
php artisan migrate
```

## Seed database

```bash
php artisan db:seed
```

## Migrate and seed

```bash
php artisan migrate --seed
```

## Rebuild development database

```bash
php artisan migrate:fresh --seed
```

## Clear cached configuration

```bash
php artisan optimize:clear
```

## Create storage link

```bash
php artisan storage:link
```

## Run queue worker

```bash
php artisan queue:work
```

## List routes

```bash
php artisan route:list
```

## Run tests

```bash
php artisan test
```

---

# Deployment

The backend is deployed to Render.

Production URL:

```text
https://inventory-api-utcf.onrender.com
```

The production environment should have:

```text
APP_ENV=production
APP_DEBUG=false
```

Production environment variables should be configured through Render.

Do not commit production secrets to GitHub.

---

# Production Deployment Checklist

Before deploying:

* [ ] `APP_ENV=production`
* [ ] `APP_DEBUG=false`
* [ ] Production `APP_KEY` configured
* [ ] PostgreSQL connection configured
* [ ] Frontend URL configured
* [ ] Mail configuration configured
* [ ] Google OAuth credentials configured
* [ ] Google callback URL registered
* [ ] HTTPS enabled
* [ ] Database migrations completed
* [ ] Storage configured where required
* [ ] Authorization tested
* [ ] Authentication tested
* [ ] Critical API endpoints tested

---

# Security

Never commit:

* `.env`
* Database passwords
* PostgreSQL connection strings
* SMTP passwords
* Google client secrets
* API keys
* Laravel application keys

Production secrets belong in Render's environment configuration.

The repository should contain only safe configuration examples.

---

# Testing Checklist

## Authentication

* [ ] Registration works
* [ ] Login works
* [ ] Invalid credentials are rejected
* [ ] Email verification works
* [ ] Password reset works
* [ ] Google authentication works
* [ ] Protected endpoints reject unauthenticated requests

## Products

* [ ] Product listing works
* [ ] Product creation works
* [ ] Product retrieval works
* [ ] Product updates work
* [ ] Product deletion works
* [ ] Product validation works

## Categories

* [ ] Category listing works
* [ ] Category creation works
* [ ] Category retrieval works
* [ ] Category updates work
* [ ] Category deletion works

## Stock

* [ ] Stock movement listing works
* [ ] Stock movement creation works
* [ ] Inventory quantities update correctly

## Users

* [ ] User listing works
* [ ] Role updates are protected
* [ ] Profile updates work
* [ ] Business updates work

## Invitations

* [ ] Invitations can be created
* [ ] Invitation information can be retrieved
* [ ] Invitations can be accepted
* [ ] Invitation completion works

## Dashboard

* [ ] Summary endpoint responds
* [ ] Product totals are correct
* [ ] Category totals are correct
* [ ] Stock totals are correct
* [ ] Low-stock count is correct
* [ ] Out-of-stock count is correct
* [ ] Inventory value is correct

---

# Known Limitations

The current API focuses on inventory management and account functionality.

Potential future features include:

* Suppliers
* Purchase orders
* Sales orders
* Barcode management
* Advanced reporting
* Audit logs
* Automated notifications
* Scheduled low-stock alerts
* Advanced analytics
* More granular permissions
* Multi-business tenancy
* Accounting integrations

---

# Future Improvements

Potential technical improvements include:

* API versioning
* More extensive automated testing
* API documentation generation
* Pagination for larger datasets
* Advanced search and filtering
* Rate limiting
* Audit logging
* Background processing
* More granular permission management
* Expanded inventory analytics

---

# Frontend Application

The corresponding frontend is a Next.js application deployed on Vercel.

Production frontend:

```text
https://inventory-dashboard-sand-alpha.vercel.app
```

Frontend repository:

```text
https://github.com/chima-008/inventory-dashboard.git
```

Backend repository:

```text
https://github.com/chima-008/inventory-project.git
```

---

# Project Purpose

This project demonstrates practical full-stack application development using Laravel, PostgreSQL, REST APIs, authentication, and a separate Next.js frontend.

It demonstrates experience with:

* Laravel
* PHP
* PostgreSQL
* Eloquent ORM
* REST API development
* Laravel Sanctum
* Authentication
* Authorization
* Email verification
* Password recovery
* Google OAuth
* CRUD operations
* Database relationships
* Validation
* User roles
* Invitations
* Inventory management
* Stock movement tracking
* Business dashboards
* Production deployment

---

# Author

**Ojeh Chimamanda**

Full-stack developer focused on building practical business applications, dashboards, APIs, and modern web systems.

GitHub: **chima-008**

---

# License

This project is intended for portfolio, demonstration, and commercial development purposes.

If distributed as a reusable template or commercial product, provide the applicable license and usage terms.
