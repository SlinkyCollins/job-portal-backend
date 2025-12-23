# JobNet API: High-Performance PHP & MySQL Backend

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white) ![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white) ![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED?logo=docker&logoColor=white)

The server-side infrastructure for **JobNet**. This is a **Dockerized**, pure PHP API designed for speed and portability. It implements a custom routing engine and a **Unified Identity Layer** that bridges Firebase Auth with a relational MySQL database.

### 🧠 Core Architecture

#### 1. The Dual-Pipeline Authentication System
JobNet does not rely on a single auth provider. Instead, it uses a **Hybrid Strategy**:
* **Pipeline A (Native):** Standard Email/Password registration stored directly in MySQL using `password_verify` (Bcrypt).
* **Pipeline B (Social):** Uses **Firebase** strictly as a gateway for Google/Facebook login.
* **The Bridge:** A custom synchronization engine intercepts Firebase tokens, verifies them, and maps them to the local `users_table`. This allows social accounts to be "linked" to existing native accounts within the same JWT session.

#### 2. Exchange Rate Caching Mechanism
To support global job listings, the `all_jobs` endpoint implements a caching strategy for currency conversion:
* Checks local `exchange_rates` table for data newer than 72 hours.
* If stale/missing, fetches live rates from external APIs.
* Dynamically normalizes salary sorting via SQL `CASE` statements.

#### 3. Security & Middleware
* **Unified JWT Middleware:** `validateJWT()` intercepts requests from *both* auth pipelines. It enforces strict Role-Based Access Control (RBAC) ensuring an Employer cannot access Seeker endpoints.
* **Cloudinary Integration:** Direct secure signing for CV and Logo uploads/deletions.

---

### 📂 Directory Structure

```text
api/
├── auth/           # Login (Native), Signup, & Social Auth Bridge (Firebase Sync)
├── dashboard/      # Role-specific protected endpoints
│   ├── admin/      # System stats & user management
│   ├── employer/   # Job posting & application tracking
│   └── seeker/     # Resume management & saved jobs
├── jobs/           # Public job listings & search logic
└── config/         # Database, Headers, & Middleware
```

### Module Descriptions

### `auth/`
The Identity Engine. Handles the complexity of merging two authentication worlds. It contains logic for standard login.php (MySQL check) and social_login.php (Firebase Token Verification + User Sync).

### `dashboard/`
Contains role-specific protected endpoints. The API strictly segregates logic here:

### `admin/`
System statistics, user moderation, and category management.

### `employer/`
CRUD operations for Job Posts and Applicant Tracking Systems (ATS).

### `seeker/`
Profile management, Resume uploads, and "Saved Jobs" functionality.

### `jobs/`
Manages public job listings. Contains the Search & Filter Engine, which handles multi-currency salary filtering and tag-based searches.

### `config/`
Global configuration. Includes database.php (PDO Singleton), CORS headers, and the JWT decoding logic.

---

### 🐳 Deployment (Docker)

This project is containerized for easy deployment.

1.  **Clone & Configure**
    ```bash
    cp .env.example .env
    # Fill in your DB_HOST, FIREBASE_CREDENTIALS, and CLOUDINARY_KEYS
    ```

2.  **Build & Run**
    ```bash
    docker build -t jobnet-backend .
    docker run -p 8080:8080 jobnet-backend
    ```

### 🔧 Manual Setup (XAMPP/Apache)
1.  Place files in `htdocs/JobPortal`.
2.  Import `database/schema.sql` (if provided) into your MySQL instance.
3.  Ensure `mod_rewrite` is enabled in Apache.
4.  Point your frontend `apiUrl` to `http://localhost/JobPortal/api`.

### 🧪 CLI Tools
The backend includes CLI utilities for system management:
```bash
# Create a new super-admin
php api/auth/create_admin_cli.php <email> <password> <firstname> <lastname>
