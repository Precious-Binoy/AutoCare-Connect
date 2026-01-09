<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login (Firebase) - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hidden { display: none; }
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
        }
        .google-btn:hover { background: #f9fafb; border-color: #d1d5db; }
        .divider { display: flex; align-items: center; margin: 1.5rem 0; color: #9ca3af; font-size: 0.875rem; font-weight: 500; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e5e7eb; }
        .divider span { padding: 0 1rem; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="auth-container" style="max-width: 450px; margin: 2rem auto; width: 100%;">
        <div class="card p-8">
            <div class="text-center mb-6">
                 <div class="flex items-center justify-center gap-2 mb-2">
                    <div style="width: 32px; height: 32px; background: var(--primary); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fa-solid fa-car-side"></i>
                    </div>
                    <span class="font-bold text-xl text-primary">AutoCare Connect</span>
                </div>
                <h1 class="text-2xl font-bold">Welcome Back</h1>
            </div>

            <!-- Google Sign In -->
            <button id="googleBtn" class="google-btn mb-4">
                <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg" width="20" height="20" alt="Google">
                Sign in with Google
            </button>
            <div id="googleError" class="text-red-500 text-sm text-center mb-2 hidden"></div>

            <div class="divider"><span>OR CONTINUE WITH EMAIL</span></div>

            <form id="emailForm">
                <div class="form-group mb-4">
                    <label class="form-label">Email</label>
                    <input type="email" id="loginEmail" class="form-control" required>
                </div>
                <div class="form-group mb-4">
                    <div class="flex justify-between">
                        <label class="form-label">Password</label>
                        <a href="forgot_password_firebase.php" class="text-sm text-primary hover:underline">Forgot password?</a>
                    </div>
                    <input type="password" id="loginPassword" class="form-control" required>
                </div>
                
                <div id="emailError" class="text-red-500 text-sm mb-4 hidden"></div>

                <button type="submit" id="emailBtn" class="btn btn-primary w-full">Sign In</button>
            </form>
            
            <p class="text-center text-sm mt-6 text-muted">
                Don't have an account? <a href="#" id="toggleMode" class="text-primary font-bold">Sign Up</a>
            </p>
        </div>
    </div>

    <!-- Firebase SDK -->
    <script type="module">
        import { initializeApp } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js";
        import { getAuth, signInWithPopup, GoogleAuthProvider, signInWithEmailAndPassword, createUserWithEmailAndPassword } from "https://www.gstatic.com/firebasejs/10.7.1/firebase-auth.js";

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

        let isLoginMode = true;

        // Toggle Login/Signup
        document.body.addEventListener('click', (e) => {
            if (e.target && e.target.id === 'toggleMode') {
                e.preventDefault();
                isLoginMode = !isLoginMode;
                document.querySelector('h1').textContent = isLoginMode ? 'Welcome Back' : 'Create Account';
                document.getElementById('emailBtn').textContent = isLoginMode ? 'Sign In' : 'Create Account';
                
                const p = document.querySelector('p.text-muted');
                if (isLoginMode) {
                    p.innerHTML = 'Don\'t have an account? <a href="#" id="toggleMode" class="text-primary font-bold">Sign Up</a>';
                } else {
                    p.innerHTML = 'Already have an account? <a href="#" id="toggleMode" class="text-primary font-bold">Log In</a>';
                }
            }
        });

        // Google Sign In
        document.getElementById('googleBtn').addEventListener('click', async () => {
             try {
                 document.getElementById('googleError').classList.add('hidden');
                 const result = await signInWithPopup(auth, googleProvider);
                 alert("Google Sign-In Successful! User: " + result.user.email);
                 // Redirect to dashboard (Note: dashboard.php is PHP-based and won't know this user unless synched)
                 // window.location.href = 'customer_dashboard.php'; 
             } catch (error) {
                 console.error(error);
                 document.getElementById('googleError').textContent = error.message;
                 document.getElementById('googleError').classList.remove('hidden');
             }
        });

        // Email Sign In / Sign Up
        document.getElementById('emailForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('loginEmail').value;
            const password = document.getElementById('loginPassword').value;
            const errorDiv = document.getElementById('emailError');
            const btn = document.getElementById('emailBtn');

            errorDiv.classList.add('hidden');
            btn.disabled = true;

            try {
                if (isLoginMode) {
                    await signInWithEmailAndPassword(auth, email, password);
                    alert("Login Successful!");
                } else {
                    await createUserWithEmailAndPassword(auth, email, password);
                    alert("Account Created Successfully!");
                }
            } catch (error) {
                console.error(error);
                let msg = error.message;
                if (error.code === 'auth/wrong-password') msg = "Incorrect password.";
                if (error.code === 'auth/user-not-found') msg = "No user found with this email.";
                if (error.code === 'auth/email-already-in-use') msg = "Email already in use.";
                errorDiv.textContent = msg;
                errorDiv.classList.remove('hidden');
            } finally {
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
