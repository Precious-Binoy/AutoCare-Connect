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
    <title>Login - AutoCare Connect</title>
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
    <div class="auth-sidebar" style="background-image: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.9)), url('https://images.unsplash.com/photo-1621905251189-08b45d6a269e'); color: white;">
        <div>
            <h1 style="color: white;" class="text-3xl font-bold mb-4">Streamline Your Workshop Operations</h1>
            <p class="text-lg opacity-90" style="color: white;">Connect mechanics, drivers, and admins in one seamless platform tailored for modern auto care.</p>
        </div>
    </div>

    <!-- Right Content (Form) -->
    <div class="auth-content">
        <div class="auth-box">
            <div class="flex items-center gap-2 mb-6">
                <div style="width: 32px; height: 32px; background: var(--primary); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white;">
                    <i class="fa-solid fa-car-side"></i>
                </div>
                <span class="font-bold text-xl text-primary">AutoCare Connect</span>
            </div>

            <h2 class="text-2xl font-bold mb-2">Welcome Back</h2>
            <p class="text-muted mb-6">Manage your workshop efficiently.</p>

            <div id="errorDisplay" class="hidden" style="display: none; background: #FEE2E2; border: 1px solid #EF4444; color: #991B1B; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
                <i class="fa-solid fa-circle-exclamation"></i> <span id="errorMsg"></span>
            </div>

            <!-- Google Sign In -->
            <button id="googleBtn" class="google-btn">
                <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" width="20" height="20" alt="Google">
                Sign in with Google
            </button>

            <div class="divider"><span>OR CONTINUE WITH EMAIL</span></div>

            <form id="loginForm">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" id="email" class="form-control" placeholder="name@autocare.com" required>
                </div>

                <div class="form-group">
                    <div class="flex justify-between">
                            <label class="form-label">Password</label>
                            <a href="forgot_password_firebase.php" id="forgotPasswordLink" class="forgot-link text-sm text-primary">Forgot password?</a>
                    </div>
                    <div class="search-bar">
                        <input type="password" id="passwordField" class="form-control" placeholder="Enter your password" style="padding-left: 1rem; padding-right: 2.5rem;" required>
                         <i class="fa-regular fa-eye" id="togglePassword" style="left: auto; right: 1rem; cursor: pointer;"></i>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-primary w-full">Log In</button>
            </form>

            <p class="text-center text-sm mt-6 text-muted">
                Don't have an account? <a href="register.php" class="text-primary font-bold">Sign Up <i class="fa-solid fa-arrow-right"></i></a>
            </p>
        </div>
    </div>
</div>

<!-- Firebase SDK -->
<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
    import { getAuth, signInWithPopup, GoogleAuthProvider, signInWithEmailAndPassword } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

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

    const errorDisplay = document.getElementById('errorDisplay');
    const errorMsg = document.getElementById('errorMsg');
    const submitBtn = document.getElementById('submitBtn');

    function showError(message) {
        errorMsg.textContent = message;
        errorDisplay.style.display = 'block';
        errorDisplay.classList.remove('hidden');
    }

    async function handleLoginSuccess(user, additionalData = {}) {
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';
        submitBtn.disabled = true;

        try {
            const token = await user.getIdToken(true); // Refresh token 
            
            // Send token to backend
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
                window.location.href = data.redirect;
            } else {
                throw new Error(data.message || 'Server verification failed');
            }

        } catch (error) {
            console.error(error);
            showError(error.message);
            submitBtn.innerHTML = 'Log In';
            submitBtn.disabled = false;
        }
    }

    // Google Login
    document.getElementById('googleBtn').addEventListener('click', async () => {
        try {
            errorDisplay.classList.add('hidden');
            const result = await signInWithPopup(auth, googleProvider);
            handleLoginSuccess(result.user);
        } catch (error) {
            console.error(error);
            showError(error.message);
        }
    });

    // Email Login
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('email').value;
        const password = document.getElementById('passwordField').value;

        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Signing In...';
        submitBtn.disabled = true;
        errorDisplay.classList.add('hidden');

        try {
            const userCredential = await signInWithEmailAndPassword(auth, email, password);
            handleLoginSuccess(userCredential.user);
        } catch (error) {
            console.error(error);
            let msg = error.message;
            if (error.code === 'auth/wrong-password' || error.code === 'auth/user-not-found' || error.code === 'auth/invalid-credential') {
                msg = "Invalid email or password."; 
            }
            showError(msg);
            submitBtn.innerHTML = 'Log In';
            submitBtn.disabled = false;
        }
    });
</script>

<script>
// Password toggle functionality
const togglePassword = document.getElementById('togglePassword');
const passwordField = document.getElementById('passwordField');

if (togglePassword && passwordField) {
    togglePassword.addEventListener('click', function() {
        const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordField.setAttribute('type', type);
        this.classList.toggle('fa-eye');
        this.classList.toggle('fa-eye-slash');
    });
}
    // --- Dynamic Forgot Password Link ---
    const emailInput = document.getElementById('email');
    const forgotLink = document.getElementById('forgotPasswordLink');

    if (emailInput && forgotLink) {
        emailInput.addEventListener('input', () => {
             const email = emailInput.value.trim();
             if (email) {
                 forgotLink.href = 'forgot_password_firebase.php?email=' + encodeURIComponent(email);
             } else {
                 forgotLink.href = 'forgot_password_firebase.php';
             }
        });
    }

</script>

</body>
</html>
