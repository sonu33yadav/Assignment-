Laravel User Management API
Overview
This Laravel-based API provides user management functionalities with role-based access control, authentication, user listing, bulk import, updates, and soft deletion. It is designed to support multiple user roles with fine-grained permissions and optimized performance through caching.

Functionalities
User Registration: Allows creating a new user with a specified role, validating input data, and securely hashing passwords.

User Authentication: Supports user login by verifying credentials and issuing API tokens for authenticated access.

Role-Based Access Control: Applies middleware to restrict access to certain routes based on the authenticated user’s role (e.g., SuperAdmin, Admin, User).

User Listing with Caching: Retrieves user lists tailored by role, with results cached to improve performance and reduce database load.

Bulk User Import: Accepts CSV file uploads to import multiple users asynchronously via a queued job for efficient processing.

User Update: Enables updating user information with permission checks to ensure only authorized roles can update specific users.

Soft Delete Users: Implements soft deletion of users, marking them as deleted without removing from the database, restricted to SuperAdmin role.

Logging: Records key actions and events such as user creation, login attempts, updates, deletions, and bulk imports for auditing and troubleshooting.

API Routes and Middleware
Public routes for user registration and login.

Protected routes guarded by authentication and role management middleware, including user listing, bulk import, updates, and deletion.

Environment Configuration
Ensure proper setup of database, cache (Redis or database), and authentication drivers in the .env file, including role definitions.

