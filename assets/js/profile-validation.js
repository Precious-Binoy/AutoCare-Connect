<script>
// Real-time validation for profile form
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[method="POST"]');
    if (!form) return;
    
    // Phone validation
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            const value = this.value.trim();
            const errorDiv = this.nextElementSibling;
            
            if (errorDiv && errorDiv.classList.contains('error-message')) {
                errorDiv.remove();
            }
            
            if (value === '') {
                showError(this, 'Phone number is required');
            } else if (!/^\d{10}$/.test(value)) {
                showError(this, 'Phone number must be 10 digits');
            } else {
                clearError(this);
            }
        });
    }
    
    // Date of Birth validation
    const dobInput = document.querySelector('input[name="dob"]');
    if (dobInput) {
        dobInput.addEventListener('change', function() {
            const value = this.value;
            const errorDiv = this.nextElementSibling;
            
            if (errorDiv && errorDiv.classList.contains('error-message')) {
                errorDiv.remove();
            }
            
            if (value) {
                const dob = new Date(value);
                const today = new Date();
                const age = today.getFullYear() - dob.getFullYear();
                
                if (dob > today) {
                    showError(this, 'Date of birth cannot be in the future');
                } else if (age < 18) {
                    showError(this, 'Must be at least 18 years old');
                } else if (age > 100) {
                    showError(this, 'Please enter a valid date of birth');
                } else {
                    clearError(this);
                }
            }
        });
    }
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        
        // Validate phone
        if (phoneInput) {
            const phoneValue = phoneInput.value.trim();
            if (phoneValue === '' || !/^\d{10}$/.test(phoneValue)) {
                showError(phoneInput, 'Valid 10-digit phone number is required');
                isValid = false;
            }
        }
        
        if (!isValid) {
            e.preventDefault();
        }
    });
});

function showError(input, message) {
    clearError(input);
    input.style.borderColor = '#ef4444';
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message text-red-600 text-xs mt-1';
    errorDiv.textContent = message;
    input.parentNode.insertBefore(errorDiv, input.nextSibling);
}

function clearError(input) {
    input.style.borderColor = '';
    const errorDiv = input.nextElementSibling;
    if (errorDiv && errorDiv.classList.contains('error-message')) {
        errorDiv.remove();
    }
}
</script>
<style>
.leave-table-row {
    margin-bottom: 0.25rem;
}
</style>
