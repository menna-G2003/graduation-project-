# Real Estate Listings API Documentation

This document provides details of all the available endpoints in the Real Estate Listings API.

## Base URL

All API requests should be made to: `http://localhost:8000/api/`

## Authentication

Most endpoints require authentication using a Bearer token. To authenticate, include the token in the Authorization header:

```
Authorization: Bearer {your_token}
```

You can obtain a token by registering or logging in.

## Error Handling

The API returns appropriate HTTP status codes:

- `200 OK` - The request was successful
- `201 Created` - A resource was successfully created
- `400 Bad Request` - The request was malformed
- `401 Unauthorized` - Authentication failed or token expired
- `403 Forbidden` - The authenticated user doesn't have permission
- `404 Not Found` - The requested resource doesn't exist
- `422 Unprocessable Entity` - Validation errors

Error responses include a message and sometimes validation errors:

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email has already been taken."]
    }
}
```

## Authentication Endpoints

### Register

Creates a new user account.

- **URL**: `/register`
- **Method**: `POST`
- **Auth required**: No
- **Permissions**: None

**Request Body**:

```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password",
    "password_confirmation": "password",
    "role_id": 3,
    "phone": "1234567890"
}
```

**Response**: `201 Created`

```json
{
    "message": "User registered successfully",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role_id": 3,
        "phone": "1234567890",
        "created_at": "2023-01-01T00:00:00.000000Z"
    },
    "token": "your_auth_token"
}
```

### Login

Authenticates a user and provides a token.

- **URL**: `/login`
- **Method**: `POST`
- **Auth required**: No
- **Permissions**: None

**Request Body**:

```json
{
    "email": "john@example.com",
    "password": "password"
}
```

**Response**: `200 OK`

```json
{
    "message": "Login successful",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role_id": 3
    },
    "token": "your_auth_token"
}
```

### Logout

Invalidates the current token.

- **URL**: `/logout`
- **Method**: `POST`
- **Auth required**: Yes
- **Permissions**: None

**Response**: `200 OK`

```json
{
    "message": "Successfully logged out"
}
```

## User Endpoints

### Get User Profile

Retrieves the authenticated user's profile.

- **URL**: `/user`
- **Method**: `GET`
- **Auth required**: Yes
- **Permissions**: None

**Response**: `200 OK`

```json
{
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role_id": 3,
        "phone": "1234567890",
        "profile_image": null,
        "role": {
            "id": 3,
            "name": "user",
            "description": "Can browse and search for properties"
        }
    }
}
```

### Update User Profile

Updates the authenticated user's profile information.

- **URL**: `/user`
- **Method**: `PUT`
- **Auth required**: Yes
- **Permissions**: None

**Request Body**:

```json
{
    "name": "John Smith",
    "phone": "9876543210"
}
```

**Response**: `200 OK`

```json
{
    "message": "Profile updated successfully",
    "data": {
        "id": 1,
        "name": "John Smith",
        "email": "john@example.com",
        "role_id": 3,
        "phone": "9876543210"
    }
}
```

## Listings Endpoints

### Get Listings

Retrieves a list of active property listings with pagination.

- **URL**: `/listings`
- **Method**: `GET`
- **Auth required**: No
- **Permissions**: None

**Query Parameters**:

- `city` - Filter by city
- `property_type` - Filter by property type (apartment, house, villa, land, commercial, other)
- `listing_type` - Filter by listing type (rent, sale)
- `min_price` - Minimum price
- `max_price` - Maximum price
- `bedrooms` - Number of bedrooms
- `bathrooms` - Number of bathrooms
- `sort_by` - Field to sort by (price, created_at, bedrooms, bathrooms, area)
- `sort_order` - Sort direction (asc, desc)
- `per_page` - Results per page (default: 10)

**Response**: `200 OK`

```json
{
    "data": [
        {
            "id": 1,
            "title": "Luxury Apartment",
            "description": "A beautiful apartment in the city center",
            "price": "250000.00",
            "address": "123 Main St",
            "city": "New York",
            "property_type": "apartment",
            "listing_type": "sale",
            "bedrooms": 2,
            "bathrooms": 1,
            "area": "120.00",
            "is_featured": true,
            "images": [
                {
                    "id": 1,
                    "image_path": "listings/1/image1.jpg",
                    "is_primary": true
                }
            ],
            "user": {
                "id": 2,
                "name": "Property Owner"
            },
            "ad_type": {
                "id": 3,
                "name": "Featured"
            }
        }
    ],
    "meta": {
        "total": 50,
        "per_page": 10,
        "current_page": 1,
        "last_page": 5
    }
}
```

### Get Featured Listings

Retrieves a list of featured property listings.

- **URL**: `/listings/featured`
- **Method**: `GET`
- **Auth required**: No
- **Permissions**: None

**Response**: `200 OK`

```json
{
    "data": [
        {
            "id": 1,
            "title": "Luxury Apartment",
            "description": "A beautiful apartment in the city center",
            "price": "250000.00",
            "is_featured": true
        }
    ]
}
```

## Admin Endpoints

### Get All Users (Admin)

Retrieves a list of all users.

- **URL**: `/admin/users`
- **Method**: `GET`
- **Auth required**: Yes
- **Permissions**: Admin only

**Response**: `200 OK`

```json
{
    "data": [
        {
            "id": 1,
            "name": "Admin User",
            "email": "admin@example.com",
            "role_id": 1,
            "is_active": true
        }
    ],
    "meta": {
        "total": 50,
        "per_page": 15,
        "current_page": 1,
        "last_page": 4
    }
}
```

## Comment Endpoints

### Get Listing Comments
**GET** `/listings/{listing_id}/comments`

Response:
```json
{
    "data": [
        {
            "id": 1,
            "user_id": 3,
            "listing_id": 1,
            "content": "This property looks amazing! Is it still available?",
            "created_at": "2023-06-15T14:30:00.000000Z",
            "user": {
                "id": 3,
                "name": "Jane Smith"
            }
        }
    ],
    "meta": {
        "total": 5,
        "per_page": 15,
        "current_page": 1,
        "last_page": 1
    }
}
```

### Get Single Comment
**GET** `/listings/{listing_id}/comments/{comment_id}`

Response:
```json
{
    "data": {
        "id": 1,
        "user_id": 3,
        "listing_id": 1,
        "content": "This property looks amazing! Is it still available?",
        "created_at": "2023-06-15T14:30:00.000000Z",
        "user": {
            "id": 3,
            "name": "Jane Smith"
        }
    }
}
```

### Add Comment (Authenticated)
**POST** `/listings/{listing_id}/comments`

Request Body:
```json
{
    "content": "I'm interested in this property. Please contact me."
}
```

Response:
```json
{
    "message": "Comment added successfully",
    "data": {
        "id": 2,
        "user_id": 3,
        "listing_id": 1,
        "content": "I'm interested in this property. Please contact me.",
        "created_at": "2023-06-16T10:45:00.000000Z",
        "user": {
            "id": 3,
            "name": "Jane Smith"
        }
    }
}
```

### Update Comment (Owner of comment)
**PUT** `/listings/{listing_id}/comments/{comment_id}`

Request Body:
```json
{
    "content": "Updated comment text."
}
```

Response:
```json
{
    "message": "Comment updated successfully",
    "data": {
        "id": 2,
        "content": "Updated comment text.",
        "created_at": "2023-06-16T10:45:00.000000Z",
        "updated_at": "2023-06-16T11:00:00.000000Z",
        "user": {
            "id": 3,
            "name": "Jane Smith"
        }
    }
}
```

### Delete Comment (Owner of comment)
**DELETE** `/listings/{listing_id}/comments/{comment_id}`

Response:
```json
{
    "message": "Comment deleted successfully"
}
```

### Get Comments on My Listings (Property owner)
**GET** `/my-listings-comments`

Response:
```json
{
    "data": [
        {
            "id": 1,
            "content": "This property looks amazing! Is it still available?",
            "created_at": "2023-06-15T14:30:00.000000Z",
            "user": {
                "id": 3,
                "name": "Jane Smith"
            },
            "listing": {
                "id": 1,
                "title": "Luxury Apartment"
            }
        }
    ],
    "meta": {
        "total": 8,
        "per_page": 15,
        "current_page": 1,
        "last_page": 1
    }
}
```

## Payment Methods

### Get Available Payment Methods
**GET** `/payment-methods`

Response:
```json
{
    "data": [
        {
            "id": "stripe",
            "name": "Credit/Debit Card",
            "description": "Pay securely with your credit or debit card",
            "auto_approved": true
        },
        {
            "id": "vodafone_cash",
            "name": "Vodafone Cash",
            "description": "Pay using Vodafone Cash mobile wallet",
            "auto_approved": false
        },
        {
            "id": "bank_transfer",
            "name": "Bank Transfer",
            "description": "Pay via bank transfer",
            "auto_approved": false
        },
        {
            "id": "paypal",
            "name": "PayPal",
            "description": "Pay securely with PayPal",
            "auto_approved": true
        }
    ]
}
```

### Create Payment Intent
**POST** `/payments/create-intent`

Request Body:
```json
{
    "listing_id": 1,
    "payment_method": "vodafone_cash"
}
```

Response:
```json
{
    "payment_intent": {
        "id": "vc_123456789",
        "amount": 150.00,
        "currency": "egp",
        "instructions": "Send payment to Vodafone Cash wallet: 01XXXXXXXXX with reference: 1",
        "note": "After sending payment, please upload the receipt/screenshot as proof"
    },
    "listing": {
        "id": 1,
        "title": "Luxury Apartment",
        "ad_type": {
            "id": 2,
            "name": "Premium",
            "price": "150.00"
        }
    }
}
```

### Confirm Payment
**POST** `/payments/confirm`

Request Body:
```json
{
    "payment_id": "vc_123456789",
    "listing_id": 1,
    "payment_method": "vodafone_cash",
    "receipt_image": "[image file]"
}
```

Response:
```json
{
    "message": "Payment received and awaiting verification",
    "payment": {
        "id": 1,
        "payment_id": "vc_123456789",
        "payment_method": "vodafone_cash",
        "amount": "150.00",
        "status": "pending",
        "notes": "Awaiting manual verification"
    },
    "listing": {
        "id": 1,
        "title": "Luxury Apartment",
        "is_paid": false
    }
}
```

## Booking Endpoints

### Get User Bookings

Retrieves all bookings made by the authenticated user.

- **URL**: `/bookings`
- **Method**: `GET`
- **Auth required**: Yes
- **Permissions**: None

**Query Parameters**:
- `status` - Filter by status (pending, confirmed, cancelled, completed)
- `per_page` - Results per page (default: 10)

**Response**: `200 OK`

```json
{
    "data": [
        {
            "id": 1,
            "booking_date": "2023-06-15T14:30:00.000000Z",
            "check_in": "2023-07-01T12:00:00.000000Z",
            "check_out": "2023-07-05T10:00:00.000000Z",
            "total_price": "500.00",
            "status": "confirmed",
            "is_paid": true,
            "notes": "Special requests: early check-in if possible",
            "listing": {
                "id": 3,
                "title": "Beach Front Villa",
                "address": "123 Ocean Drive",
                "city": "Miami",
                "images": [
                    {
                        "id": 1,
                        "image_path": "listings/3/image1.jpg",
                        "is_primary": true
                    }
                ]
            }
        }
    ],
    "meta": {
        "total": 5,
        "per_page": 10,
        "current_page": 1,
        "last_page": 1
    }
}
```

### Get Single Booking

Retrieves details of a specific booking.

- **URL**: `/bookings/{booking_id}`
- **Method**: `GET`
- **Auth required**: Yes
- **Permissions**: Owner of booking or property owner

**Response**: `200 OK`

```json
{
    "data": {
        "id": 1,
        "booking_date": "2023-06-15T14:30:00.000000Z",
        "check_in": "2023-07-01T12:00:00.000000Z",
        "check_out": "2023-07-05T10:00:00.000000Z",
        "total_price": "500.00",
        "status": "confirmed",
        "is_paid": true,
        "notes": "Special requests: early check-in if possible",
        "listing": {
            "id": 3,
            "title": "Beach Front Villa",
            "address": "123 Ocean Drive",
            "city": "Miami",
            "property_type": "villa",
            "listing_type": "rent",
            "bedrooms": 3,
            "bathrooms": 2,
            "area": "200.00",
            "images": [
                {
                    "id": 1,
                    "image_path": "listings/3/image1.jpg",
                    "is_primary": true
                }
            ]
        },
        "user": {
            "id": 5,
            "name": "Jane Smith",
            "email": "jane@example.com",
            "phone": "9876543210"
        }
    }
}
```

### Create Booking

Creates a new booking for a property.

- **URL**: `/listings/{listing_id}/bookings`
- **Method**: `POST`
- **Auth required**: Yes
- **Permissions**: None

**Request Body**:

```json
{
    "check_in": "2023-08-10T14:00:00",
    "check_out": "2023-08-15T10:00:00",
    "notes": "We will arrive late in the evening"
}
```

**Response**: `201 Created`

```json
{
    "message": "Booking created successfully",
    "data": {
        "id": 2,
        "booking_date": "2023-06-20T15:45:00.000000Z",
        "check_in": "2023-08-10T14:00:00.000000Z",
        "check_out": "2023-08-15T10:00:00.000000Z",
        "total_price": "750.00",
        "status": "pending",
        "is_paid": false,
        "notes": "We will arrive late in the evening",
        "listing": {
            "id": 5,
            "title": "Mountain Cabin"
        }
    }
}
```

### Update Booking Status

Updates the status of a booking (for property owners or admins).

- **URL**: `/bookings/{booking_id}/status`
- **Method**: `PUT`
- **Auth required**: Yes
- **Permissions**: Property owner or admin

**Request Body**:

```json
{
    "status": "confirmed"
}
```

**Response**: `200 OK`

```json
{
    "message": "Booking status updated successfully",
    "data": {
        "id": 2,
        "status": "confirmed",
        "updated_at": "2023-06-21T09:30:00.000000Z"
    }
}
```

### Cancel Booking

Allows a user to cancel their booking.

- **URL**: `/bookings/{booking_id}/cancel`
- **Method**: `PUT`
- **Auth required**: Yes
- **Permissions**: Owner of booking

**Response**: `200 OK`

```json
{
    "message": "Booking cancelled successfully",
    "data": {
        "id": 2,
        "status": "cancelled",
        "updated_at": "2023-06-21T10:15:00.000000Z"
    }
}
```

### Get Property Bookings (Property Owner)

Retrieves all bookings for properties owned by the authenticated user.

- **URL**: `/my-property-bookings`
- **Method**: `GET`
- **Auth required**: Yes
- **Permissions**: Property owner

**Query Parameters**:
- `status` - Filter by status (pending, confirmed, cancelled, completed)
- `property_id` - Filter by specific property ID
- `per_page` - Results per page (default: 10)

**Response**: `200 OK`

```json
{
    "data": [
        {
            "id": 3,
            "booking_date": "2023-06-18T11:20:00.000000Z",
            "check_in": "2023-09-05T15:00:00.000000Z",
            "check_out": "2023-09-10T11:00:00.000000Z",
            "total_price": "600.00",
            "status": "pending",
            "is_paid": false,
            "listing": {
                "id": 8,
                "title": "Downtown Apartment"
            },
            "user": {
                "id": 7,
                "name": "Robert Johnson",
                "email": "robert@example.com",
                "phone": "5551234567"
            }
        }
    ],
    "meta": {
        "total": 12,
        "per_page": 10,
        "current_page": 1,
        "last_page": 2
    }
}
```

## Getting Started

1. Clone the repository
2. Install dependencies with `composer install`
3. Configure your `.env` file
4. Run migrations and seeders: `php artisan migrate --seed`
5. Start the server: `php artisan serve`
6. Test the API endpoints using Postman or any API client

For detailed environment setup, check the `env-instructions.txt` file in the project root.

## Postman Collection

A Postman collection is included in the project root (`real-estate-api.postman_collection.json`). Import this file into Postman to quickly test all API endpoints. 