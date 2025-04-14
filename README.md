# Real Estate Listings Backend (Laravel)

A backend-only application for a real estate platform, similar to Dubizzle, built using Laravel. The platform allows users to browse and publish property listings with different advertisement tiers (free, premium, featured). It also supports online payments, user authentication, and role-based access.

## Features

- **Authentication & User Management**
  - Register, login, logout
  - Login with Google & Facebook (Laravel Socialite)
  - Email verification
  - Forgot/reset password via email
  - Role-based access (user, owner, admin)
  - Admin approval/blocking of users

- **Listings Management**
  - CRUD operations for listings (owners only)
  - Multiple image uploads per listing
  - Listing types: free, premium, featured
  - Admin approval of listings
  - Search, filter, and sort listings
  - Favorite listings

- **Payments**
  - Online payment integration
  - Store transaction details
  - Mark listings as "paid" after successful payment
  - Different pricing for each ad type
  - Admin view of payment history

- **Admin Panel APIs**
  - Manage users (view, block, delete)
  - Manage listings (view, approve, delete)
  - View payment transactions
  - Generate statistics: total users, ads, revenue, etc.

## Setup Instructions

1. Clone the repository
2. Run `composer install`
3. Create `.env` file and configure DB, mail, and payment credentials
4. Run `php artisan key:generate`
5. Run migrations: `php artisan migrate`
6. Setup storage link: `php artisan storage:link`
7. Seed roles and ad types: `php artisan db:seed`
8. Setup Laravel Socialite for Google/Facebook login in `.env`:
   ```
   GOOGLE_CLIENT_ID=your-google-client-id
   GOOGLE_CLIENT_SECRET=your-google-client-secret
   GOOGLE_REDIRECT_URI=http://your-domain.com/api/login/google/callback

   FACEBOOK_CLIENT_ID=your-facebook-client-id
   FACEBOOK_CLIENT_SECRET=your-facebook-client-secret
   FACEBOOK_REDIRECT_URI=http://your-domain.com/api/login/facebook/callback
   ```
9. Setup mail configuration in `.env` for email verification and password reset
10. (Optional) Setup Stripe payment integration in `.env`:
    ```
    STRIPE_KEY=your-stripe-publishable-key
    STRIPE_SECRET=your-stripe-secret-key
    ```

## API Routes

The API routes are organized into the following groups:

- **Public Routes**
  - Authentication: register, login, forgot password, reset password, email verification
  - Social login: Google, Facebook
  - Public listing endpoints: browse listings, view listing details, search listings

- **Protected Routes (requires authentication)**
  - User profile management
  - Favorites management
  - Owner-only routes: listing management, payment processing
  - Admin-only routes: user management, listing approval, payment monitoring, statistics

## User Roles

- **Admin**: Full control over the system, can manage users, listings, and monitor payments.
- **Owner**: Can create and manage property listings (paid or free).
- **User**: Can browse and search for properties, save favorites, and contact owners.

## Database Schema

The application uses the following main database tables:

- `users` - User accounts with role-based permissions
- `roles` - User roles (admin, owner, user)
- `listings` - Property listings with details
- `listing_images` - Images associated with listings
- `ad_types` - Types of advertisements (free, premium, featured)
- `payments` - Payment transactions
- `favorites` - User's favorite listings

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
