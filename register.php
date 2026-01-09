<?php
require_once 'includes/auth.php';

// Redirect if already logged in
// if (isLoggedIn()) {
//     header('Location: customer_dashboard.php');
//     exit;
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: white;
            color: #374151;
            border: 1px solid #e5e7eb;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 1rem;
        }
        .google-btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .divider { display: flex; align-items: center; margin: 1.5rem 0; color: #9ca3af; font-size: 0.875rem; font-weight: 500; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }
        .divider span { padding: 0 1rem; }
    </style>
</head>
<body>

<div class="auth-container">
    <!-- Left Sidebar (Branding) -->
    <div class="auth-sidebar" style="background-image: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.9)), url('https://images.unsplash.com/photo-1625047509248-ecb8a1c56f11'); color: white;">
        <div>
            <div style="width: 48px; height: 48px; background: rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white; margin-bottom: 2rem;">
                <i class="fa-solid fa-car-side text-2xl"></i>
            </div>
            <h1 style="color: white;" class="text-3xl font-bold mb-4">Professional Auto Care, Simplified.</h1>
            <p class="text-lg opacity-90" style="color: white;">Join thousands of car owners who trust AutoCare Connect for their vehicle maintenance and repair scheduling.</p>
            
            <div class="mt-8 flex gap-4 text-sm opacity-75" style="color: white;">
                <span>&copy; 2024 AutoCare Connect</span>
                <span>Privacy Policy</span>
                <span>Terms of Service</span>
            </div>
        </div>
    </div>

    <!-- Right Content (Form) -->
    <div class="auth-content">
        <div class="auth-box">
             <div class="flex items-center gap-2 mb-6 text-primary">
                 <i class="fa-solid fa-car-side"></i> <span class="font-bold">AutoCare Connect</span>
            </div>

            <h2 class="text-2xl font-bold mb-2">Join AutoCare Connect</h2>
            <p class="text-muted mb-6">Manage your vehicle service history and appointments in one place.</p>

            <!-- Error Display -->
            <div id="errorDisplay" class="hidden" style="display: none; background: #FEE2E2; border: 1px solid #EF4444; color: #991B1B; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="errorMsg"></span>
            </div>

             <!-- Google Sign Up -->
            <button id="googleBtn" class="google-btn">
                <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" width="20" height="20" alt="Google">
                Sign up with Google
            </button>

            <div class="divider"><span>OR REGISTER WITH EMAIL</span></div>

            <form id="registerForm">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <div class="search-bar">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" id="name" class="form-control" placeholder="John Doe" required>
                    </div>
                    <p id="nameError" class="text-xs text-red-600 mt-1 hidden"></p>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="search-bar">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" id="email" class="form-control" placeholder="name@example.com" required>
                    </div>
                    <p id="emailError" class="text-xs text-red-600 mt-1 hidden"></p>
                </div>

                <div class="form-group">
                     <label class="form-label">Phone Number (Optional)</label>
                    <div class="search-bar">
                        <i class="fa-solid fa-phone"></i>
                        <input type="tel" id="phone" class="form-control" placeholder="(555) 123-4567">
                    </div>
                    <p id="phoneError" class="text-xs text-red-600 mt-1 hidden"></p>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="search-bar">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="passwordField" class="form-control" placeholder="Create a password" required>
                         <i class="fa-regular fa-eye" id="togglePassword" style="left: auto; right: 1rem; cursor: pointer;"></i>
                    </div>
                    <p id="passwordError" class="text-xs text-red-600 mt-1 hidden"></p>
                </div>
                
                <div class="form-group">
                     <label class="form-label">Confirm Password</label>
                     <div class="search-bar">
                        <i class="fa-solid fa-rotate-right"></i>
                        <input type="password" id="confirmPasswordField" class="form-control" placeholder="Confirm your password" required>
                        <i class="fa-regular fa-eye" id="toggleConfirmPassword" style="left: auto; right: 1rem; cursor: pointer;"></i>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-primary w-full">Create Account</button>
            </form>

            <p class="text-xs text-muted mt-4 text-center">By clicking "Create Account", you agree to our <a href="#" class="text-primary underline">Terms</a> and <a href="#" class="text-primary underline">Privacy Policy</a>.</p>

            <p class="text-center text-sm mt-6 text-muted">
                Already have an account? <a href="login.php" class="text-primary font-bold">Log in</a>
            </p>
        </div>
    </div>
</div>

<!-- Firebase SDK -->
<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
    import { getAuth, createUserWithEmailAndPassword, updateProfile, signInWithPopup, GoogleAuthProvider } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

    const firebaseConfig = {
        apiKey: "AIzaSyDGsEVjwWTkHrTu70gRY0rlQEPkvb2dfs0",
        authDomain: "autocare-connect-16c27.firebaseapp.com",
        projectId: "autocare-connect-16c27",
        storageBucket: "autocare-connect-16c27.firebasestorage.app",
        messagingSenderId: "112810720056",
        appId: "1:112810720056:web:ce5ad1bb69ff9ef6cd5fcd"
    };

    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);
    const googleProvider = new GoogleAuthProvider();
    googleProvider.setCustomParameters({ prompt: 'select_account' });

    const form = document.getElementById('registerForm');
    const submitBtn = document.getElementById('submitBtn');
    const errorDisplay = document.getElementById('errorDisplay');
    const errorMsg = document.getElementById('errorMsg');

    function showError(msg) {
        // Global error (fallback)
        errorMsg.textContent = msg;
        errorDisplay.style.display = 'block';
        errorDisplay.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function handleAuthSuccess(user, additionalData = {}) {
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Finalizing Setup...';
        submitBtn.disabled = true;

        try {
            const token = await user.getIdToken(true); // Force refresh to get latest profile info
            
            // Send token and additional data to backend
            const response = await fetch('api/firebase_login_handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    token: token,
                    name: additionalData.name || null,
                    phone: additionalData.phone || null
                })
            });

            const data = await response.json();

            if (data.success) {
                // Redirect to dashboard (Auto Login)
                window.location.href = data.redirect;
            } else {
                throw new Error(data.message || 'Server setup failed');
            }

        } catch (error) {
            console.error(error);
            showError(error.message);
            submitBtn.innerHTML = 'Create Account';
            submitBtn.disabled = false;
        }
    }

    function showFieldError(fieldId, msg) {
        const errorEl = document.getElementById(fieldId + 'Error');
        const inputEl = document.getElementById(fieldId);
        
        if (errorEl) {
            errorEl.textContent = msg;
            errorEl.classList.remove('hidden');
            errorEl.style.display = 'block'; // Failsafe
        }
        
        if (inputEl) {
            inputEl.classList.add('border-red-500'); // Assuming Tailwind/Custom CSS for red border exists or will be added
            inputEl.focus();
            inputEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function clearErrors() {
        errorDisplay.classList.add('hidden');
        document.querySelectorAll('.text-red-600').forEach(el => el.classList.add('hidden'));
        document.querySelectorAll('.form-control').forEach(el => el.classList.remove('border-red-500'));
    }

    // Google Sign Up
    document.getElementById('googleBtn').addEventListener('click', async () => {
        try {
            clearErrors();
            const result = await signInWithPopup(auth, googleProvider);
            handleAuthSuccess(result.user);
        } catch (error) {
            console.error(error);
            showError(error.message);
        }
    });

    // Email Sign Up
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();
        
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('passwordField');
        const confirmInput = document.getElementById('confirmPasswordField');
        const phoneInput = document.getElementById('phone');

        const name = nameInput.value;
        const email = emailInput.value;
        const password = passwordInput.value;
        const confirm = confirmInput.value;
        const phone = phoneInput.value; 

        // Validate Full Name (Letters and spaces only)
        const nameRegex = /^[A-Za-z\s]+$/;
        if (!nameRegex.test(name)) {
            showFieldError('name', 'Full Name should contain characters only.');
            return;
        }

        // Validate Phone Number (Optional but if entered, must be 10 digits)
        const phoneVal = phoneInput.value.trim();
        if (phoneVal) {
            const cleanPhone = phoneVal.replace(/\D/g, '');
            if (cleanPhone.length !== 10) {
                showFieldError('phone', 'Phone number should be exactly 10 digits.');
                return;
            }
        }

        // Basic validation
        if (password !== confirm) {
            showFieldError('passwordField', 'Passwords do not match'); // Or highlight both?
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating Account...';

        try {
            // Create user in Firebase
            const userCredential = await createUserWithEmailAndPassword(auth, email, password);
            const user = userCredential.user;
            
            // Update Profile with Name
            await updateProfile(user, {
                displayName: name
            });

            // Proceed to backend sync
            handleAuthSuccess(user, { name: name, phone: phone });

        } catch (error) {
            console.error("Firebase Error:", error);
            let msg = error.message;
            let targetField = '';

            if (error.code === 'auth/email-already-in-use') {
                msg = 'Email is already registered. Please log in.';
                targetField = 'email';
            } else if (error.code === 'auth/weak-password') {
                msg = 'Password should be at least 6 characters.';
                targetField = 'passwordField';
            } else if (error.code === 'auth/invalid-email') {
                 msg = 'Invalid email address.';
                 targetField = 'email';
            }

            if (targetField) {
                showFieldError(targetField, msg);
            } else {
                showError(msg);
            }
            
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Create Account';
        }
    });
</script>

<script>
// Password toggle functionality
const togglePassword = document.getElementById('togglePassword');
const passwordField = document.getElementById('passwordField');
const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
const confirmPasswordField = document.getElementById('confirmPasswordField');

if (togglePassword && passwordField) {
    togglePassword.addEventListener('click', function() {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
}

if (toggleConfirmPassword && confirmPasswordField) {
    toggleConfirmPassword.addEventListener('click', function() {
        const type = confirmPasswordField.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmPasswordField.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
}
</script>

</body>
</html>
