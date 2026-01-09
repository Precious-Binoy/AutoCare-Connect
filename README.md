<<<<<<< HEAD
# AutoCare-Connect
=======
# AutoCare Connect - Setup & Documentation

## Project Overview
AutoCare Connect is a complete car care management platform built with PHP, HTML5, CSS3, and vanilla JavaScript.

## System Requirements
- XAMPP with Apache & PHP 7.4+
- Windows/Mac/Linux
- Modern web browser (Chrome, Firefox, Safari, Edge)

## Installation Instructions

### 1. File Structure
```
c:\xampp\htdocs\autocare-connect\
├── index.php (Landing page)
├── login.php (Login page)
├── register.php (Registration page)
├── customer_dashboard.php (Main dashboard)
├── book_service.php (Service booking)
├── track_service.php (Service tracking)
├── pickup_delivery.php (Pickup & delivery management)
│
├── api/
│   ├── get_recent_activity.php (Returns recent activity JSON)
│   ├── get_service_status.php (Returns service status & timeline JSON)
│   ├── create_user.php (Registration endpoint)
│   ├── create_booking.php (Booking creation endpoint)
│   └── update_pickup_delivery.php (Pickup/delivery update endpoint)
│
├── includes/
│   ├── header.php (Page header with user info)
│   ├── sidebar.php (Navigation sidebar)
│   ├── navbar_public.php (Public navbar for landing/login)
│   └── footer_public.php (Public footer)
│
├── assets/
│   ├── css/
│   │   └── style.css (Main stylesheet)
│   ├── js/ (if any)
│   └── images/ (if any)
│
└── README.md (This file)
```

### 2. XAMPP Configuration
1. Ensure XAMPP is installed (download from https://www.apachefriends.org)
2. Place the entire `autocare-connect` folder in `c:\xampp\htdocs\`
3. Start Apache and MySQL services from XAMPP Control Panel
4. Access the application at `http://localhost/autocare-connect/`

### 3. Page Descriptions

#### index.php (Landing Page)
- Public landing page showcasing AutoCare Connect features
- Hero section with call-to-action buttons
- Features overview section
- No PHP includes required

#### login.php (Login)
- Customer login form
- Google Sign-In modal simulation
- Forgot Password modal simulation
- HTML-only, no backend authentication yet

#### register.php (Registration)
- Customer registration form
- Full form validation (client-side)
- Password visibility toggle
- AJAX submit to `api/create_user.php`
- Success/error notifications

#### customer_dashboard.php (Dashboard)
- Protected dashboard (requires sidebar/header includes)
- Recent activity section (fetched via AJAX)
- Welcome message with user info
- AJAX endpoint: `api/get_recent_activity.php`
- Responsive layout with sidebar navigation

#### book_service.php (Service Booking)
- Service booking form
- Vehicle selection, service type, date/time picker
- Form validation before submit
- AJAX submit to `api/create_booking.php`
- Success/error handling

#### track_service.php (Service Tracking)
- Real-time service status tracking
- Timeline visualization
- Vehicle & mechanic information
- AJAX endpoint: `api/get_service_status.php`
- Responsive timeline layout

#### pickup_delivery.php (Pickup & Delivery)
- Pickup/delivery request form
- Address input and date/time picker
- AJAX submit to `api/update_pickup_delivery.php`
- Success/error notifications

### 4. API Endpoints (Mock)

All endpoints return JSON responses and are located in `/api/` folder.

#### GET api/get_recent_activity.php
Returns array of recent activity records.
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "booking",
      "title": "Service Booking Confirmed",
      "date": "2024-01-15 10:30 AM"
    }
  ]
}
```

#### GET api/get_service_status.php
Returns complete service status with timeline.
Query param: `booking_id` (optional)
```json
{
  "success": true,
  "booking_ref": "BK-2024-889",
  "status": "In Progress",
  "vehicle": {...},
  "mechanic": {...},
  "timeline": [...],
  "details": [...]
}
```

#### POST api/create_user.php
Accepts JSON payload with name, email, password.
Returns user creation response.

#### POST api/create_booking.php
Accepts JSON payload with vehicle, service_type, preferred_at.
Returns booking_id or error.

#### POST api/update_pickup_delivery.php
Accepts JSON payload with pickup_datetime, pickup_address.
Returns success or error.

### 5. PHP Variables Used

#### $page_title
Set at the top of each page. Used in:
- HTML `<title>` tag
- `includes/header.php` for page heading

#### $current_page
Set using `basename(__FILE__)`. Used in:
- `includes/sidebar.php` for active nav link highlighting

All pages that use sidebar/header includes must set both variables:
```php
<?php
$page_title = 'Page Title';
$current_page = basename(__FILE__);
?>
```

### 6. Styling & Responsiveness

**CSS Framework**: Custom CSS + Tailwind CDN
**Responsive Breakpoints**:
- 1024px and below: Hide auth sidebar (login page)
- 768px and below: Hide main sidebar, adjust layout

**Mobile-First Design**: 
- All layouts use `max-width` constraints
- Flexbox and CSS Grid for responsive layouts
- Font sizes use `rem` units for scalability

### 7. JavaScript Features

- **Vanilla JavaScript** (no jQuery)
- **Fetch API** for AJAX calls (modern alternative to XMLHttpRequest)
- **Form Validation**: Client-side validation before submit
- **Error Handling**: Try-catch blocks with user-friendly error messages
- **Loading States**: Button disabling during requests

### 8. Font Awesome Icons
All pages include Font Awesome 6.4.0 CDN for icons:
```html
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
```

### 9. Testing Checklist

- [x] All pages load without PHP errors
- [x] Include paths are correct (sidebar, header)
- [x] AJAX calls use relative paths (api/endpoint.php)
- [x] All API endpoints return valid JSON
- [x] Form validation works correctly
- [x] Responsive design works on mobile/tablet/desktop
- [x] No console errors in browser DevTools

### 10. Troubleshooting

**Issue**: Pages not loading / 404 errors
- Check XAMPP Apache is running
- Verify file paths match directory structure
- Check `c:\xampp\htdocs\autocare-connect\` exists

**Issue**: Sidebar not showing / include errors
- Verify `includes/sidebar.php` and `includes/header.php` exist
- Check all PHP pages set `$page_title` and `$current_page` variables
- Check include statement: `<?php include 'includes/sidebar.php'; ?>`

**Issue**: AJAX calls failing
- Open browser DevTools (F12) → Network tab
- Check API endpoint URLs are correct: `api/endpoint.php`
- Verify API files exist in `/api/` folder
- Check API files set `header('Content-Type: application/json');`

**Issue**: Forms not submitting
- Check form `id` attribute matches JavaScript selector
- Verify `name` attributes on form inputs
- Check browser console for JavaScript errors

### 11. Future Enhancements

- [ ] Database integration (MySQL)
- [ ] User authentication system
- [ ] Session management
- [ ] Email notifications
- [ ] Mobile app
- [ ] Admin panel

### 12. Support & Contact

For issues or questions, contact the development team.

---

**Last Updated**: 2024
**Version**: 1.0.0
>>>>>>> 975e875 (first commit)
