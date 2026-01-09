# AutoCare Connect - Quick Start Guide

## 🚀 Getting Started

### Step 1: Ensure XAMPP is Running
1. Open XAMPP Control Panel
2. Click **Start** next to "Apache"
3. Click **Start** next to "MySQL" (optional)
4. You should see "Running" status

### Step 2: Access the Application
Open your browser and go to:
```
http://localhost/autocare-connect/
```

---

## 📍 Navigation Map

### Public Pages (No Login Required)
```
Landing Page (index.php)
├── Home section
├── Features overview
├── Pricing (if applicable)
└── Links to Login/Register
```

### Authentication Pages
```
Login (login.php)
├── Email/Password form
├── "Sign in with Google" modal
└── "Forgot Password" modal

Register (register.php)
├── Full name input
├── Email input
├── Password input
├── Password visibility toggle
└── Submit button
```

### Protected Pages (With Sidebar)
```
Dashboard (customer_dashboard.php)
├── Sidebar navigation
├── Welcome message
└── Recent activity section

Book Service (book_service.php)
├── Vehicle selection
├── Service type selection
├── Date/time picker
└── Submit button

Track Service (track_service.php)
├── Service timeline
├── Vehicle details
├── Mechanic information
└── Status updates

Pickup & Delivery (pickup_delivery.php)
├── Pickup address input
├── Date/time picker
└── Submit button
```

---

## 🧪 Testing Each Feature

### Test Landing Page
1. Go to `http://localhost/autocare-connect/`
2. Verify all sections load correctly
3. Click "Login" button → Should go to login.php
4. Click "Sign Up" button → Should go to register.php

### Test Login Page
1. Go to `http://localhost/autocare-connect/login.php`
2. Try entering credentials and clicking "Sign In"
3. Click "Sign in with Google" → Modal appears
4. Click "Forgot Password?" → Modal appears

### Test Registration
1. Go to `http://localhost/autocare-connect/register.php`
2. Fill in all fields:
   - Full Name: "John Doe"
   - Email: "john@example.com"
   - Password: "Test@1234"
3. Click the eye icon to toggle password visibility
4. Click "Register" button
5. Watch for success message in browser console (F12)

### Test Dashboard
1. Go to `http://localhost/autocare-connect/customer_dashboard.php`
2. Verify sidebar appears with navigation menu
3. Verify header appears with page title "Dashboard"
4. Recent activity section should load via AJAX
5. Click sidebar links to navigate to other pages

### Test Service Booking
1. Go to `http://localhost/autocare-connect/book_service.php`
2. Select a vehicle from dropdown
3. Select a service type
4. Pick a date and time
5. Click "Book Service"
6. Check browser console (F12) for API response

### Test Service Tracking
1. Go to `http://localhost/autocare-connect/track_service.php`
2. Verify timeline loads with 5 steps
3. Verify vehicle and mechanic info displays
4. Check responsive layout on mobile (F12 → Toggle device toolbar)

### Test Pickup & Delivery
1. Go to `http://localhost/autocare-connect/pickup_delivery.php`
2. Enter pickup address
3. Select date and time
4. Click "Request Pickup"
5. Watch for response

---

## 🔍 Browser Developer Tools Testing

### Check for Errors
1. Press **F12** to open Developer Tools
2. Go to **Console** tab
3. Verify NO red error messages appear
4. Reload page (Ctrl+R) and check again

### Check Network Activity
1. Open Developer Tools (F12)
2. Go to **Network** tab
3. Reload page (Ctrl+R)
4. Verify all requests return 200 status
5. Check API responses in Network tab:
   - Click on `api/get_recent_activity.php`
   - View the Response tab for JSON data

### Check Responsive Design
1. Open Developer Tools (F12)
2. Press **Ctrl+Shift+M** (or click device toolbar icon)
3. Test at different screen sizes:
   - iPhone SE (375x667)
   - iPad (768x1024)
   - Desktop (1920x1080)
4. Verify:
   - Text is readable
   - Buttons are clickable (>44px)
   - No horizontal scrolling
   - Layout adapts to screen size

---

## 📊 API Endpoints Testing

### Test Recent Activity API
Open this in your browser:
```
http://localhost/autocare-connect/api/get_recent_activity.php
```
Should return JSON array of activities.

### Test Service Status API
Open this in your browser:
```
http://localhost/autocare-connect/api/get_service_status.php
```
Should return JSON with booking status and timeline.

### Test with Postman (Optional)
1. Download Postman: https://www.postman.com/downloads/
2. Create new POST request:
   - URL: `http://localhost/autocare-connect/api/create_user.php`
   - Body (JSON):
   ```json
   {
     "name": "Test User",
     "email": "test@example.com",
     "password": "Test@1234"
   }
   ```
   - Click Send → Should get success response

---

## ⚙️ Troubleshooting

### Issue: Page shows "404 Not Found"
**Solution**:
1. Check XAMPP Apache is running
2. Verify URL is correct: `http://localhost/autocare-connect/`
3. Check file exists in correct location
4. Restart Apache

### Issue: Sidebar doesn't appear on dashboard
**Solution**:
1. Open browser console (F12)
2. Check for include errors
3. Verify `includes/sidebar.php` exists
4. Check file permissions

### Issue: AJAX calls fail with 404
**Solution**:
1. Check Network tab (F12) for exact URL
2. Verify API file exists: `api/endpoint.php`
3. Check API files have correct headers
4. Verify PHP has permission to read files

### Issue: Form submissions don't work
**Solution**:
1. Check console for JavaScript errors
2. Verify form has correct `id` attribute
3. Check Network tab for API response
4. Verify API endpoint returns valid JSON

### Issue: Styles don't load (page looks unstyled)
**Solution**:
1. Check Network tab (F12) for CSS status
2. Verify `assets/css/style.css` exists
3. Check CDN links are working (Font Awesome, Tailwind)
4. Clear browser cache (Ctrl+Shift+Delete)

---

## 📱 Mobile Testing Checklist

- [ ] Page loads on mobile (tested with device toolbar)
- [ ] Text is readable (no zooming needed)
- [ ] Buttons are clickable (>44px height/width)
- [ ] Forms are usable on touch devices
- [ ] No horizontal scrolling
- [ ] Images scale properly
- [ ] Navigation works on mobile

---

## 🔒 Security Notes

⚠️ **IMPORTANT**: This is a mock application for demonstration. Before production deployment:

1. **Add Authentication**: Implement real login system
2. **Add Database**: Move from mock JSON to real database
3. **Validate Input**: Add server-side validation for all forms
4. **Use HTTPS**: Enable SSL certificate
5. **Protect Passwords**: Use password hashing (bcrypt)
6. **Add CSRF Protection**: Implement CSRF tokens

---

## 📧 Support

For issues or questions:
1. Check console logs (F12)
2. Review error messages
3. Check file permissions
4. Verify XAMPP is running
5. Restart Apache/PHP

---

## 📋 Files Reference

| Page | File Path | Purpose |
|------|-----------|---------|
| Landing | `/index.php` | Homepage |
| Login | `/login.php` | Login page |
| Register | `/register.php` | Registration |
| Dashboard | `/customer_dashboard.php` | Main dashboard |
| Book Service | `/book_service.php` | Booking form |
| Track Service | `/track_service.php` | Service tracking |
| Pickup & Delivery | `/pickup_delivery.php` | Delivery management |

---

**Ready to test! Happy coding! 🎉**
