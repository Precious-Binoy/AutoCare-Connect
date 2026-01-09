<?php
require_once 'includes/auth.php';

// Redirect if logged in
if (isLoggedIn()) {
    // Optional: redirect to dashboard if desired, but user asked to be able to change it
    // header('Location: customer_dashboard.php');
    // exit;
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
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="auth-container" style="max-width: 450px; margin: 2rem auto; width: 100%;">
        <div class="card p-8">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-primary text-white mb-3">
                    <i class="fa-solid fa-key text-xl"></i>
                </div>
                <h1 class="text-2xl font-bold">Forgot Password?</h1>
                <p class="text-muted mt-2">Enter your email address and we'll send you a link to reset your password.</p>
            </div>

            <!-- SIMULATION MODE ALERT -->
            <div id="simulationAlert" class="hidden mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <h4 class="font-bold text-green-800 mb-2"><i class="fa-solid fa-envelope-open-text"></i> Email Sent (Simulated)</h4>
                <p class="text-sm text-green-700 mb-2">Since this is localhost, click the link below to reset your password:</p>
                <a id="resetLinkHref" href="#" class="block w-full text-center bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition-colors">
                    Reset Password Now
                </a>
            </div>

            <form id="forgotPasswordForm">
                <div class="form-group mb-4">
                    <label class="form-label">Email Address</label>
                    <div class="search-bar">
                        <i class="fa-solid fa-envelope text-muted"></i>
                        <input type="email" name="email" id="emailInput" class="form-control" placeholder="name@example.com">
                    </div>
                </div>

                <div id="message" class="mb-4 text-sm font-medium"></div>

                <button type="submit" class="btn btn-primary w-full" id="submitBtn">
                    Reset Password
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
        function showMessage(text, type) {
            const msgDiv = document.getElementById('message');
            msgDiv.textContent = text;
            if (type === 'error') {
                msgDiv.style.color = 'red';
                msgDiv.className = 'mb-4 text-sm font-medium text-red-600';
            } else {
                msgDiv.style.color = 'green';
                msgDiv.className = 'mb-4 text-sm font-medium text-green-600';
            }
        }

        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('submitBtn');
            const email = document.getElementById('emailInput').value.trim();
            const simulationAlert = document.getElementById('simulationAlert');
            
            // Client-side Validation logic
            if (!email) {
                showMessage('Please enter your email!', 'error');
                return;
            }
            
            // Reset UI
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
            document.getElementById('message').textContent = '';
            simulationAlert.classList.add('hidden');
            
            fetch('api/password_reset.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email: email })
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Server response was not JSON: ' + text.substring(0, 100) + '...');
                }
            })
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = 'Reset Password';
                
                if (data.success) {
                    // Success Message
                    showMessage("SIMULATION MODE: Link generated below! (No real email sent on Localhost)", 'success');
                    
                    // SIMULATION: Show the link directly
                    if (data.debug_info && data.debug_info.reset_link) {
                        const link = data.debug_info.reset_link;
                        document.getElementById('resetLinkHref').href = link;
                        simulationAlert.classList.remove('hidden');
                    }
                } else {
                    // Error Message from Server
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                btn.disabled = false;
                btn.innerHTML = 'Reset Password';
                showMessage('Error: ' + error.message, 'error');
                console.error('Error:', error);
            });
        });
    </script>
</body>
</html>
