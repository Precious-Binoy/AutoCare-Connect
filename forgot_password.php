<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - AutoCare Connect</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .hidden { display: none; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="auth-container" style="max-width: 450px; margin: 2rem auto; width: 100%;">
        <div class="card p-8">
            <div class="text-center mb-6">
                <!-- Branding Icon -->
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-primary text-white mb-3" style="background-color: var(--primary);">
                    <i class="fa-solid fa-car-side text-xl"></i>
                </div>
                <!-- Updated Title -->
                <h1 class="text-2xl font-bold">Password Reset</h1>
                <p class="text-muted mt-2">Enter your email to receive a password reset link.</p>
            </div>

            <form id="forgotPasswordForm">
                <div class="form-group mb-4">
                    <label class="form-label">Email Address</label>
                    <div class="search-bar">
                        <i class="fa-solid fa-envelope text-muted"></i>
                        <input type="email" id="email" class="form-control" placeholder="name@example.com" required>
                    </div>
                </div>

                <!-- Message Display Area -->
                <div id="message" class="mb-4 text-sm font-medium hidden" style="padding: 10px; border-radius: 6px;"></div>

                <button type="submit" class="btn btn-primary w-full" id="submitBtn">
                    Send Reset Link
                </button>
            </form>

            <div class="text-center mt-6">
                <a href="login.php" class="text-sm text-muted hover:text-primary transition-colors">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const emailField = document.getElementById("email");
            const email = emailField.value.trim();
            const msgDiv = document.getElementById("message");
            const btn = document.getElementById("submitBtn");

            // Reset UI
            msgDiv.classList.add('hidden');
            msgDiv.className = 'mb-4 text-sm font-medium hidden'; // reset classes
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';

            if (!email) {
                showMessage("Please enter your email!", 'error');
                resetBtn();
                return;
            }

            try {
                // Call Local API
                const response = await fetch('api/password_reset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });

                const data = await response.json();

                if (data.success) {
                    showMessage(data.message, 'success');
                    btn.innerHTML = '<i class="fa-solid fa-check"></i> Sent';
                    
                    // DEBUG: If localhost/dev and link is returned, show it
                    if (data.debug_info && data.debug_info.reset_link) {
                         const debugMsg = document.createElement('div');
                         debugMsg.style.marginTop = '10px';
                         debugMsg.style.padding = '10px';
                         debugMsg.style.background = '#f0f9ff';
                         debugMsg.style.border = '1px dashed #0284c7';
                         debugMsg.style.color = '#0284c7';
                         debugMsg.style.fontSize = '0.85rem';
                         debugMsg.innerHTML = '<strong>Debug Mode:</strong> <a href="' + data.debug_info.reset_link + '" style="text-decoration:underline;">Click here to Reset Password</a>';
                         msgDiv.parentNode.insertBefore(debugMsg, msgDiv.nextSibling);
                    }
                    
                } else {
                    showMessage(data.message || 'An error occurred.', 'error');
                    resetBtn();
                }

            } catch (error) {
                console.error(error);
                showMessage('Network error. Please try again.', 'error');
                resetBtn();
            }

            function showMessage(text, type) {
                msgDiv.textContent = text;
                msgDiv.classList.remove('hidden');
                msgDiv.style.display = 'block';
                
                if (type === 'error') {
                    msgDiv.style.backgroundColor = '#fee2e2';
                    msgDiv.style.color = '#991b1b';
                    msgDiv.style.border = '1px solid #fca5a5';
                } else {
                    msgDiv.style.backgroundColor = '#dcfce7';
                    msgDiv.style.color = '#166534';
                    msgDiv.style.border = '1px solid #86efac';
                }
            }

            function resetBtn() {
                btn.disabled = false;
                btn.innerHTML = 'Send Reset Link';
            }
        });
    </script>
</body>
</html>
