# Password Reset & Email Verification Implementation

This document outlines the implementation of password reset and email verification features for the MyHessa application.

## Features Implemented

### 1. Forgot Password
- Users can request a password reset by providing their email address
- A reset token is generated and sent to the user's email
- Token expires after 24 hours
- Users can reset their password using the token

### 2. Email Verification
- Users can request an email verification link after registration
- A verification token is generated and sent to the user's email
- Token expires after 24 hours
- Users can verify their email and mark it as verified

## API Endpoints

### Password Reset Endpoints

#### 1. Forgot Password
- **POST** `/api/auth/forgot-password`
- **Description**: Send password reset link to user's email
- **Body**:
  ```json
  {
    "email": "user@example.com"
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "message": "Password reset link has been sent to your email.",
    "data": []
  }
  ```

#### 2. Reset Password
- **POST** `/api/auth/reset-password`
- **Description**: Reset password using token
- **Body**:
  ```json
  {
    "email": "user@example.com",
    "token": "reset-token-here",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "message": "Your password has been reset successfully.",
    "data": []
  }
  ```

### Email Verification Endpoints

#### 1. Send Verification Email
- **POST** `/api/auth/send-verification-email`
- **Description**: Send verification email to authenticated user
- **Headers**: `Authorization: Bearer <jwt-token>`
- **Body**: (empty)
- **Response**:
  ```json
  {
    "success": true,
    "message": "Verification email has been sent. Please check your inbox.",
    "data": []
  }
  ```

#### 2. Verify Email
- **POST** `/api/auth/verify-email`
- **Description**: Verify email using token
- **Body**:
  ```json
  {
    "token": "verification-token-here"
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "message": "Your email has been verified successfully.",
    "data": []
  }
  ```

#### 3. Resend Verification Email
- **POST** `/api/auth/resend-verification-email`
- **Description**: Resend verification email (no authentication required)
- **Body**:
  ```json
  {
    "email": "user@example.com"
  }
  ```
- **Response**:
  ```json
  {
    "success": true,
    "message": "Verification email has been sent. Please check your inbox.",
    "data": []
  }
  ```

## Database Changes

### New Table: email_verification_tokens
```sql
CREATE TABLE email_verification_tokens (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT NOT NULL,
  token VARCHAR(255) UNIQUE NOT NULL,
  created_at TIMESTAMP,
  expires_at TIMESTAMP,
  verified_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Existing Table: password_reset_tokens
- Already exists in migrations
- Used for storing password reset tokens

## Models

### EmailVerificationToken Model
Location: `app/Models/EmailVerificationToken.php`
- Relationship: `belongs to User`
- Methods: Standard Eloquent methods

### User Model Updates
Location: `app/Models/User.php`
- Added relationship: `emailVerificationTokens()` (one-to-many)

## Controllers

### PasswordResetController
Location: `app/Http/Controllers/Auth/PasswordResetController.php`
- Methods:
  - `forgotPassword()`: Generate and send reset token
  - `resetPassword()`: Validate token and update password

### EmailVerificationController
Location: `app/Http/Controllers/Auth/EmailVerificationController.php`
- Methods:
  - `sendVerificationEmail()`: Generate and send verification token
  - `verifyEmail()`: Validate token and mark email as verified
  - `resendVerificationEmail()`: Resend verification token

## Mail Templates

### Password Reset Email
Location: `resources/views/emails/password-reset.blade.php`
- Template for password reset email
- Includes reset link and expiry information

### Email Verification Email
Location: `resources/views/emails/email-verification.blade.php`
- Template for email verification
- Includes verification link and expiry information

## Configuration

### Environment Variables
Add to `.env`:
```
FRONTEND_URL=http://localhost:3000
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@myhessa.com
MAIL_FROM_NAME="MyHessa"
```

### App Configuration
Updated `config/app.php`:
- Added `frontend_url` configuration for frontend URL construction

## Translation Files

### English
- `resources/lang/en/messages.php`: Added password reset and email verification messages
- `resources/lang/en/mail.php`: Created with email template messages

### Arabic
- `resources/lang/ar/messages.php`: Added password reset and email verification messages (Arabic)
- `resources/lang/ar/mail.php`: Created with email template messages (Arabic)

## Running Migrations

To apply the new migration:
```bash
php artisan migrate
```

Or use the setup command:
```bash
composer run setup
```

## Testing

### Manual Testing
1. Register a new user
2. Test forgot password flow:
   - POST to `/api/auth/forgot-password` with valid email
   - Check email for reset link
   - Click link and reset password
3. Test email verification flow:
   - Login with user account
   - POST to `/api/auth/send-verification-email`
   - Check email for verification link
   - Click link to verify

### API Testing with Postman/Thunder Client
- Import the endpoints above
- Test with valid and invalid data
- Verify error handling

## Security Considerations

1. **Token Expiry**: All tokens expire after 24 hours
2. **Token Validation**: Tokens are validated before processing
3. **Single Use**: Reset tokens are deleted after successful password reset
4. **Email Verification**: Only verified emails can be used for sensitive operations
5. **Rate Limiting**: Consider implementing rate limiting on token generation endpoints

## Frontend Integration

The frontend should:
1. Provide forgot password form with email input
2. Redirect users to reset password page with token: `/reset-password?token=TOKEN&email=EMAIL`
3. Provide email verification page with token: `/verify-email?token=TOKEN`
4. Handle success/error responses from API
5. Store reset links with appropriate expiry handling

## Troubleshooting

### Emails not sending
- Check MAIL_MAILER configuration
- Verify SMTP credentials
- Check mail logs in `storage/logs/`

### Token errors
- Ensure token format is correct
- Check token expiry time
- Verify token exists in database

### Database errors
- Run migrations: `php artisan migrate`
- Check database connection in `.env`

## Future Enhancements

1. Add email verification requirement on registration
2. Implement rate limiting on reset/verification endpoints
3. Add multi-factor authentication (MFA)
4. Add remember-me token expiry in reset flow
5. Implement email change verification
6. Add activity logging for security events
