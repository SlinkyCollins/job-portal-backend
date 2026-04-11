# Hybrid Authentication Flow

JobNet uses a hybrid authentication model where the MySQL database is the source of truth and Firebase is used strictly as a secure identity provider.

## Architecture Principle

| Component | Responsibility |
| --- | --- |
| MySQL database | System of record for users, linked providers, and account authority |
| Firebase | Identity provider for secure sign-in and credential linking |
| Backend JWT | Session token issued by the application backend after authentication succeeds |

This separation keeps account state deterministic, avoids Firebase lock-in, and makes login behavior easier to debug.

## Core Flow Patterns

The application supports three major authentication patterns:

| Scenario | Description |
| --- | --- |
| Scenario A | Email/password user later links a social account |
| Scenario B | Social user later links another provider |
| Scenario C | User signs in with a social provider after forgetting the original signup method |

## Scenario A: Email/Password User Links a Social Account Later

This flow applies when a user begins with traditional email/password authentication and later adds Google login.

### 1. Registration

- The user signs up with email and password.
- The backend in `signup.php` creates a row in `users_table`.

#### Database State

| Field | Value |
| --- | --- |
| email | `john@mail.com` |
| password | hashed value |
| google_id | `NULL` |

### 2. Login

- The user logs in with email and password.
- The backend in `login.php` verifies the credentials and issues a JWT.
- No Firebase session exists yet, so `auth.currentUser === null`.

### 3. Link Social Account

- The user opens Settings and chooses Link Google.
- The frontend checks `auth.currentUser` and finds it is `null`.

#### Action

- The frontend calls `signInWithPopup`, not `linkWithPopup`.

#### Result

- Google returns a UID such as `goog_123`.
- The frontend sends `goog_123` to `link_social.php`.
- The backend updates the user record and sets `google_id = goog_123`.

### 4. Future Login

- The user clicks Login with Google.
- The backend in `social_login.php` finds the user by `google_id`.
- The backend issues a JWT for the same account.

## Scenario B: Social User Links Another Provider Later

This flow applies when a user starts with a social login and later adds another provider, such as Facebook.

### 1. Registration

- The user clicks Sign up with Google.
- The frontend receives a Google UID such as `goog_123`.
- The backend in `save_role.php` creates the user record.

#### Database State

| Field | Value |
| --- | --- |
| email | `jane@gmail.com` |
| password | `NULL` |
| google_id | `goog_123` |

### 2. Login

- The user logs in with Google.
- The backend in `social_login.php` finds `google_id = goog_123`.
- The backend issues a JWT.
- A Firebase session exists, so `auth.currentUser !== null`.

### 3. Link Another Provider

- The user opens Settings and chooses Link Facebook.
- The frontend checks `auth.currentUser` and confirms a session exists.

#### Action

- The frontend calls `linkWithPopup`, allowing Firebase to merge credentials.

#### Result

- Firebase links Facebook to the same user.
- Facebook returns a UID such as `fb_456`.
- The frontend sends `fb_456` to the backend.
- The backend updates the user record and sets `facebook_id = fb_456`.

### 4. Future Login

- The user clicks Login with Facebook.
- The backend finds `facebook_id = fb_456`.
- The backend logs the user into the same account.

## Scenario C: Auto-Linking When the Signup Method Was Forgotten

This flow applies when a user forgets how they originally signed up and later attempts social login using the same email address.

### 1. Registration

- The user signs up with email and password using `mark@yahoo.com`.

#### Database State

| Field | Value |
| --- | --- |
| email | `mark@yahoo.com` |
| google_id | `NULL` |

### 2. Social Login Attempt

- The user clicks Login with Google using the same email.
- The frontend receives a Google UID such as `goog_789`.

### 3. Backend Resolution in `social_login.php`

1. Check for a user with `google_id = goog_789` and do not find one.
2. Check for a user with `email = mark@yahoo.com` and find a match.
3. Auto-link the account by updating `google_id = goog_789`.

### 4. Result

- The user is logged in successfully.
- Future logins use the linked provider directly.

## Why This Architecture Works

This is a strong authentication design because it separates identity from authority.

### Practical Advantages

- The database remains the source of truth, so account state is fully under application control.
- Firebase handles security-sensitive identity operations, which reduces attack surface.
- JWTs are issued by the backend, which keeps session handling scalable and predictable.
- Auto-linking prevents unnecessary user friction.
- Provider linking is explicit, traceable, and safe.

### Common Mistakes This Avoids

- Using Firebase as the database.
- Creating duplicate user accounts.
- Breaking login flows when users switch providers.

## Optional Production Rule

When auto-linking by email, it is good practice to:

- Log the event.
- Optionally notify the user later, for example: “We linked your Google account.”

This is a useful production polish step, but it is not required for an MVP.

## Final Verdict

This auth flow is:

- Correct
- Scalable
- Debuggable
- Recruiter-impressive
- Worth documenting in a README or project explanation
- A strong foundation for future features such as password reset and 2FA