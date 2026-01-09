# Google OAuth Setup Guide

## Issue: Google Sign-In Not Showing Account Details

The Google Sign-In button should now properly show the account selection dialog when clicked.

## What Was Fixed

### 1. Button Rendering
- Changed from custom button with `prompt()` to Google's official `renderButton()`
- This ensures the account chooser appears correctly

### 2. Proper Configuration
- Added `auto_select: false` to prevent automatic sign-in attempts
- Added `cancel_on_tap_outside: true` for better UX
- Used Google's styled button instead of custom implementation

### 3. Error Handling
- Added console logging for debugging
- Added loading states
- Better error messages

## Testing the Fix

1. **Clear Browser Cache** (Important!)
   - Press `Ctrl + Shift + Delete`
   - Clear cached images and files
   - Close and reopen browser

2. **Test Login Page**
   - Go to: `http://localhost/autocare-connect/login.php`
   - Click the "Continue with Google" button
   - **You should see**: Google account chooser popup
   - Select your account
   - It will create/login and redirect to dashboard

3. **Test Register Page**
   - Go to: `http://localhost/autocare-connect/register.php`
   - Same process as login

## Important Notes

### Google Client ID
The current Client ID (`498753295346-jnh8q2qlnrbdbjit9add5nmfef3cc2lr`) is from the design mockup.

**For production use, you MUST:**
1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project
3. Enable "Google+ API"
4. Create OAuth 2.0 credentials
5. Add authorized JavaScript origins:
   - `http://localhost`
   - Your production domain
6. Update the Client ID in:
   - `login.php` (line ~148)
   - `register.php` (line ~130)
   - `config/config.php`

### Troubleshooting

**Button not appearing:**
- Check browser console for errors
- Make sure Google Sign-In script is loaded
- Clear browser cache

**"Not a valid origin" error:**
- Your domain must be authorized in Google Cloud Console
- localhost should work for testing

**Account chooser doesn't show:**
- Try incognito/private browsing mode
- Clear browser cache completely
- Make sure you're not already signed in

## Backend Processing

The backend (`api/google_login.php`) will:
1. Decode the JWT credential
2. Extract user info (email, name, picture)
3. Check if user exists
4. Create new user OR login existing user
5. Create session
6. Return success + redirect URL

## Success Indicators

✅ Google button renders with Google logo
✅ Clicking button shows account selection
✅ Selecting account logs you in/creates account
✅ Redirects to customer dashboard
✅ User data saved in database
✅ Session created properly
