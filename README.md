# JobNet API: High-Performance PHP & MySQL Backend

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white) ![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white) ![Docker](https://img.shields.io/badge/Docker-Enabled-2496ED?logo=docker&logoColor=white)

The server-side infrastructure for **JobNet**. This is a **Dockerized**, pure PHP API designed for speed and portability. It implements a custom routing engine and a JWT-based security layer that bridges Firebase Auth with a relational MySQL database.

### 🧠 Core Architecture

#### 1. The Authentication/Authorization Split
* **Authentication (Who you are):** Handled via **Firebase**. The frontend sends a Firebase ID Token.
* **Authorization (What you can do):** Handled via **MySQL**. The backend verifies the token, extracts the UID, and checks the local `users_table` to determine if the user is an `admin`, `employer`, or `job_seeker`.

#### 2. Exchange Rate Caching Mechanism
To support global job listings, the `all_jobs` endpoint implements a caching strategy for currency conversion:
* Checks local `exchange_rates` table for data newer than 72 hours.
* If stale/missing, fetches live rates from external APIs.
* Dynamically normalizes salary sorting via SQL `CASE` statements.

#### 3. Security & Middleware
* **Custom JWT Middleware:** `validateJWT()` intercepts requests, verifies headers, and strictly enforces role-based access (RBAC) before any controller logic executes.
* **Cloudinary Integration:** Direct secure signing for CV and Logo uploads/deletions.

### 📂 Directory Structure

api/

├── auth/ 

├── dashboard/ 

│ ├── admin/ 

│ ├── employer/ 

│ └── seeker/ 

├── jobs/ 

└── config/ 


## Module Descriptions

### `auth/`
Handles authentication processes (login, signup, and social auth bridging).
This module is dedicated to user authentication and authorization. It includes logic for standard login/signup procedures as well as bridges for social authentication services.

### `dashboard/`
Contains role-specific protected endpoints for various user types.
This directory houses protected endpoints that require user authentication. It is further subdivided by user roles to manage specific functionalities:
*   **`admin/`**: Endpoints for system statistics and user management functions.
Administrative tasks, system monitoring, and comprehensive user management tools.
*   **`employer/`**: Endpoints for job posting and tracking job applications.
Tools for employers to post new jobs and monitor applicant status.
*   **`seeker/`**: Endpoints for managing resumes and saved jobs lists.
User-specific endpoints for job seekers to manage their profiles, resumes, and job interests.

### `jobs/`
Manages public job listings and core job search logic.
This module handles all public-facing job-related logic, including listing available jobs and implementing the core job search functionality.

### `config/`
Contains configuration files for the database, headers, and middleware.
This directory is for global settings and utility files, encompassing database connections, HTTP header management, and custom middleware definitions.

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
