// Login Form Handler
document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value;
    const password = document.getElementById('password').value;
    const role = document.getElementById('role').value;
    const errorMessage = document.getElementById('error-message');
    
    errorMessage.style.display = 'none';
    
    try {
        const response = await fetch('api/login.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username, password, role })
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (role && data.user.role !== role) {
                errorMessage.textContent = 'Selected role does not match your account role.';
                errorMessage.style.display = 'block';
                return;
            }
            // Redirect based on role
            switch(data.user.role) {
                case 'student':
                    window.location.href = 'student/dashboard.html';
                    break;
                case 'faculty':
                    window.location.href = 'faculty/dashboard.html';
                    break;
                case 'admin':
                    window.location.href = 'admin/dashboard.html';
                    break;
                default:
                    errorMessage.textContent = 'Unknown user role';
                    errorMessage.style.display = 'block';
            }
        } else {
            errorMessage.textContent = data.message;
            errorMessage.style.display = 'block';
        }
    } catch (error) {
        errorMessage.textContent = 'An error occurred. Please try again.';
        errorMessage.style.display = 'block';
        console.error('Login error:', error);
    }
});

