# Google OAuth Debugging Guide

## Quick Debug Steps

### Step 1: Test the Database Setup

First, make sure the database is initialized:

1. Go to: `http://localhost/autocare-connect/setup.html`
2. Click "Initialize Database"
3. Wait for success message

### Step 2: Use the Test Page

I've created a special test page with detailed logging:

**Go to:** `http://localhost/autocare-connect/test_google.html`

This page will show you:
- ✅ If Google Sign-In initializes properly
- ✅ What data Google sends back
- ✅ What the backend API returns
- ✅ Any errors that occur

**Steps to test:**
1. Open the test page
2. Click the Google button
3. Select your Google account
4. Watch the debug console on the page
5. It will show you exactly where the error occurs

### Step 3: Check Browser Console

Open browser developer tools:
- Press `F12` or `Ctrl + Shift + I`
- Go to the "Console" tab
- Look for any red errors

Common errors and solutions:

**"Failed to fetch"**
- Make sure XAMPP Apache is running
- Check that `api/google_login.php` file exists

**"Unauthorized"**
- Database might not be set up
- Run `setup.html` first

**"Invalid credential"**
- Google Client ID might be wrong
- Check if you're using the correct Client ID

**"Email not found"**
- Google account doesn't have an email (rare)
- Try a different Google account

### Step 4: Check PHP Errors

1. Open: `http://localhost/autocare-connect/api/google_login.php` directly
2. You should see: `{"success":false,"message":"Method not allowed"}`
3. If you see a PHP error instead, that's the problem

### Step 5: Verify Database Tables

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Select `autocare_connect` database
3. Check these tables exist:
   - `users`
   - `vehicles`
   - `bookings`

If tables don't exist, run `setup.html` again.

## Common Issues & Solutions

### Issue: "An error occurred during Google sign-up"

**Cause:** Backend API is returning an error or timing out

**Fix:**
1. Check XAMPP Apache is running
2. Verify database is initialized (`setup.html`)
3. Use `test_google.html` to see exact error
4. Check browser console (F12)

### Issue: Google button doesn't appear

**Cause:** Google Sign-In script not loading

**Fix:**
1. Check internet connection (Google script loads from CDN)
2. Clear browser cache
3. Try incognito mode

### Issue: "Invalid origin" error

**Cause:** Domain not authorized in Google Cloud Console

**Fix:**
For localhost testing, this shouldn't happen with the current Client ID. If it does, you need to set up your own Google OAuth credentials.

### Issue: User created but can't login

**Cause:** Session not being created properly

**Fix:**
1. Check that `includes/auth.php` exists
2. Verify `config/db.php` database connection works
3. Make sure cookies are enabled in browser

## Expected Behavior

When Google Sign-In works correctly:

1. Click "Continue with Google" button
2. Google account chooser appears
3. Select your account
4. Brief loading message
5. Automatically redirected to dashboard
6. Dashboard shows your name from Google account

## Test Account Alternative

If Google Sign-In continues to have issues, you can always use the email/password login:

**Email:** `john@example.com`  
**Password:** `password123`

This will let you test the rest of the application while we debug Google OAuth.

## Need More Help?

If you're still getting errors:

1. Use `test_google.html` and share what errors you see in the debug console
2. Check browser Console (F12) for JavaScript errors
3. Check phpMyAdmin to see if `users` table exists
4. Try creating an account with regular registration first to verify database works
