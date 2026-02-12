// Message Admin Modal
document.addEventListener('DOMContentLoaded', function () {
    const messageAdminBtn = document.getElementById('messageAdminBtn');
    if (!messageAdminBtn) return;

    messageAdminBtn.addEventListener('click', function (e) {
        e.preventDefault();
        showMessageAdminModal();
    });
});

function showMessageAdminModal() {
    const modal = document.createElement('div');
    modal.id = 'messageAdminModal';
    modal.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;';

    const modalContent = `
        <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%;">
            <h3 style="font-size: 1.5rem; font-weight: bold; margin-bottom: 1rem; color: #1f2937;">
                <i class="fa-solid fa-paper-plane" style="color: #2563eb;"></i> Message Admin
            </h3>
            <p style="color: #6b7280; margin-bottom: 1.5rem; font-size: 0.875rem;">Send a message to the administrator. They will receive it as a notification.</p>
            
            <textarea id="adminMessageText" rows="6" style="width: 100%; padding: 0.75rem; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 0.875rem; resize: vertical;" placeholder="Type your message here..."></textarea>
            
            <div id="messageError" style="color: #ef4444; font-size: 0.875rem; margin-top: 0.5rem; display: none;"></div>
            
            <div style="display: flex; gap: 0.75rem; margin-top: 1.5rem; justify-content: flex-end;">
                <button onclick="closeMessageAdminModal()" style="padding: 0.75rem 1.5rem; border: 1px solid #d1d5db; border-radius: 8px; background: white; cursor: pointer; font-weight: 600;">
                    Cancel
                </button>
                <button onclick="sendAdminMessage()" style="padding: 0.75rem 1.5rem; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fa-solid fa-paper-plane"></i> Send Message
                </button>
            </div>
        </div>
    `;

    modal.innerHTML = modalContent;
    document.body.appendChild(modal);
}

function closeMessageAdminModal() {
    const modal = document.getElementById('messageAdminModal');
    if (modal) modal.remove();
}

function sendAdminMessage() {
    const messageText = document.getElementById('adminMessageText').value.trim();
    const errorDiv = document.getElementById('messageError');

    if (!messageText) {
        errorDiv.textContent = 'Please enter a message';
        errorDiv.style.display = 'block';
        return;
    }

    if (messageText.length < 10) {
        errorDiv.textContent = 'Message must be at least 10 characters';
        errorDiv.style.display = 'block';
        return;
    }

    // Send via AJAX
    fetch('ajax/send_admin_message.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'admin_message=' + encodeURIComponent(messageText)
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeMessageAdminModal();
                alert('Message sent to admin successfully!');
            } else {
                errorDiv.textContent = data.error || 'Failed to send message';
                errorDiv.style.display = 'block';
            }
        })
        .catch(error => {
            errorDiv.textContent = 'Network error. Please try again.';
            errorDiv.style.display = 'block';
        });
}
