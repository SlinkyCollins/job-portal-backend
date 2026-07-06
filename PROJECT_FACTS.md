# JobNet — Project Facts

## Overview

JobNet is a full-stack recruitment platform built over roughly one year with a separate frontend and backend repository structure.

## Project Type

- Full-stack recruitment platform

## Development Duration

- Approximately 1 year

## Architecture

- Separate frontend and backend repositories

## Stack

### Frontend

- Angular
- TypeScript
- Bootstrap
- RxJS

### Backend

- PHP
- MySQL
- Firebase Authentication
- JWT
- Docker

### Cloud

- Render
- Vercel
- Cloudinary
- Aiven MySQL

## Scale

| Metric | Count |
|---|---:|
| REST API endpoints | 61 |
| Angular pages/routes | 31 |
| Database tables | 11 |
| Role dashboards | 3 |
| Authentication methods | 3 |
| Supported currencies | 4 |
| Upload features | 3 |
| Core features | 13 |
| Git commits | 513 |
| Development duration | ~1 year |

## Major Features

- Hybrid authentication
- Email/password login
- Google login
- Facebook login
- Account linking
- Employer dashboard
- Admin dashboard
- Job seeker dashboard
- Job posting
- Job applications
- Wishlist
- Resume upload
- Company profiles

## Interesting Engineering Challenges

### Hybrid Authentication

Merged Firebase identities with relational MySQL users while supporting:

- Email/password authentication
- Google OAuth
- Facebook OAuth
- Account linking
- Automatic account reconciliation

### Salary Normalization

Implemented multi-currency salary support with automatic exchange-rate conversion for consistent job comparisons.

### Role-Based Authorization

Built protected dashboards and APIs for:

- Job seekers
- Employers
- Administrators

### API Documentation

Documented all REST endpoints using Swagger/OpenAPI.

## Deployment

### Frontend

- Vercel

### Backend

- Render using Docker

### Database

- Aiven MySQL

## Testing

Manually validated:

- Email/password signup
- Email/password login
- Google login
- Facebook login
- Social account linking
- Employer workflows
- Admin workflows
- Job seeker workflows

## Lessons Learned

- Hybrid authentication introduces significant state synchronization challenges.
- Designing API-first simplified frontend development.
- Functionality-first development accelerated MVP completion before UI polishing.
- Clean project structure and modular APIs improved maintainability as the project grew.

## Future Improvements

- Migrate backend to Laravel
- Add automated testing
- Introduce Redis caching
- Add real-time notifications
- Add a CI/CD pipeline
- Improve advanced filtering and search