// Common utility functions

// Check if user is logged in
async function checkAuth() {
    try {
        const response = await fetch('../api/session_status.php');
        const data = await response.json();
        
        if (!data.logged_in) {
            window.location.href = '../index.html';
            return null;
        }
        
        return data.user;
    } catch (error) {
        console.error('Auth check error:', error);
        window.location.href = '../index.html';
        return null;
    }
}

// Logout function
async function logout() {
    try {
        await fetch('../api/logout.php');
        window.location.href = '../index.html';
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = '../index.html';
    }
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
}

// Format time
function formatTime(dateString) {
    const date = new Date(dateString);
    return date.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Format datetime
function formatDateTime(dateString) {
    return `${formatDate(dateString)} ${formatTime(dateString)}`;
}

// Show success message
function showSuccess(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'success-message';
    messageDiv.textContent = message;
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.zIndex = '9999';
    messageDiv.style.maxWidth = '400px';
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 3000);
}

// Show error message
function showError(message) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'error-message';
    messageDiv.textContent = message;
    messageDiv.style.position = 'fixed';
    messageDiv.style.top = '20px';
    messageDiv.style.right = '20px';
    messageDiv.style.zIndex = '9999';
    messageDiv.style.maxWidth = '400px';
    
    document.body.appendChild(messageDiv);
    
    setTimeout(() => {
        messageDiv.remove();
    }, 3000);
}

// Get status badge class
function getStatusBadgeClass(status) {
    switch(status) {
        case 'present':
            return 'badge-success';
        case 'absent':
            return 'badge-danger';
        case 'active':
            return 'badge-info';
        case 'completed':
            return 'badge-success';
        case 'not_started':
            return 'badge-warning';
        case 'auto_absent':
            return 'badge-warning';
        case 'qr_scan':
            return 'badge-success';
        case 'manual':
            return 'badge-info';
        default:
            return 'badge-info';
    }
}

// Create status badge
function createStatusBadge(status) {
    const displayText = status === 'auto_absent' ? 'Auto Absent' : status.replace('_', ' ').toUpperCase().replace(/^./, c => c.toUpperCase());
    return `<span class="badge ${getStatusBadgeClass(status)}">${displayText}</span>`;
}

