<?php
require_once 'includes/auth.php';
// if isLoggedIn() redirect... (Optional, but user might want to reset password while logged in? Usually not for forgot password)
if (isLoggedIn()) {
   // header('Location: customer_dashboard.php'); // Optional
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="auth-container">
    <!-- Left Sidebar (Branding) -->
    <div class="auth-sidebar" style="background-image: linear-gradient(rgba(15, 23, 42, 0.8), rgba(15, 23, 42, 0.9)), url('https://images.unsplash.com/photo-1621905251189-08b45d6a269e'); color: white;">
        <div>
            <h1 style="color: white;" class="text-3xl font-bold mb-4">Secure & Reliable</h1>
            <p class="text-lg opacity-90" style="color: white;">Recover access to your account quickly and securely.</p>
        </div>
    </div>

    <!-- Right Content (Form) -->
    <div class="auth-content">
        <div class="auth-box">
             <div class="flex items-center gap-2 mb-6 text-primary">
                 <i class="fa-solid fa-car-side"></i> <span class="font-bold">AutoCare Connect</span>
            </div>

            <h2 class="text-2xl font-bold mb-2">Forgot Password?</h2>
            <p class="text-muted mb-6">Enter your email address and we'll send you a link to reset your password.</p>

            <div id="statusMessage" class="hidden" style="padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px;">
                 <span id="msgContent"></span>
            </div>

            <form id="forgotPasswordForm">
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="search-bar">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" id="email" class="form-control" placeholder="name@example.com" required>
                    </div>
                </div>

                <button type="submit" id="submitBtn" class="btn btn-primary w-full">Send Reset Link</button>
            </form>

            <div class="text-center mt-6">
                <a href="login.php" class="text-sm font-bold text-primary hover:underline">
                    <i class="fa-solid fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Firebase SDK -->
<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
    import { getAuth, sendPasswordResetEmail, fetchSignInMethodsForEmail } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

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

    const form = document.getElementById('forgotPasswordForm');
    const emailInput = document.getElementById('email');
    const submitBtn = document.getElementById('submitBtn');
    const statusMessage = document.getElementById('statusMessage');
    const msgContent = document.getElementById('msgContent');

    // Auto-fill email from URL if present
    const urlParams = new URLSearchParams(window.location.search);
    const emailParam = urlParams.get('email');
    if (emailParam) {
        emailInput.value = emailParam;
    }

    function showMsg(text, type) {
        msgContent.innerHTML = text;
        statusMessage.classList.remove('hidden');
        if (type === 'error') {
            statusMessage.style.background = '#FEE2E2';
            statusMessage.style.border = '1px solid #EF4444';
            statusMessage.style.color = '#991B1B';
        } else {
            statusMessage.style.background = '#D1FAE5';
            statusMessage.style.border = '1px solid #10B981';
            statusMessage.style.color = '#065F46';
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = emailInput.value;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
        statusMessage.classList.add('hidden');

        try {
            // Optional: Check if user exists or uses Google
            // Note: fetchSignInMethodsForEmail might be protected in some Firebase projects to prevent email enumeration.
            // If it fails, we catch the error and try to send functionality anyway or show generic message.
            try {
                const methods = await fetchSignInMethodsForEmail(auth, email);
                if (methods.length > 0 && !methods.includes('password')) {
                    throw new Error("This email is associated with a specific provider (" + methods[0] + "). Please sign in with that provider.");
                }
            } catch (innerError) {
                // If this fails (e.g. permission denied due to security settings), just ignore and proceed to try sending email.
                // Unless it's the specific error we threw above.
                if (innerError.message.includes('provider')) {
                    throw innerError;
                }
                console.warn("Skipping method check due to error:", innerError);
            }

            // Define Action Code Settings
            const actionCodeSettings = {
                // Redirect user to login page after password reset
                url: window.location.origin + window.location.pathname.replace('forgot_password_firebase.php', 'login.php'),
                handleCodeInApp: false
            };

            await sendPasswordResetEmail(auth, email, actionCodeSettings);
            
            showMsg('<i class="fa-solid fa-check-circle"></i> Password reset email sent! Check your inbox.', 'success');
            submitBtn.innerHTML = 'Email Sent';
            
        } catch (error) {
            console.error(error);
            let msg = error.message;
            if (error.code === 'auth/user-not-found') msg = "No user found with this email.";
            if (error.code === 'auth/invalid-email') msg = "Invalid email format.";
            showMsg('<i class="fa-solid fa-circle-exclamation"></i> ' + msg, 'error');
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Send Reset Link';
        }
    });
</script>

</body>
</html>

