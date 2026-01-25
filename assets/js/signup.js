// Student Sign-up Handler
const signupForm = document.getElementById('signupForm');
const errorMessage = document.getElementById('error-message');
const successMessage = document.getElementById('success-message');

signupForm.addEventListener('submit', async function(e) {
    e.preventDefault();

    errorMessage.style.display = 'none';
    successMessage.style.display = 'none';

    const payload = {
        full_name: document.getElementById('full_name').value.trim(),
        student_id: document.getElementById('student_id').value.trim(),
        email: document.getElementById('email').value.trim(),
        username: document.getElementById('username').value.trim(),
        password: document.getElementById('password').value
    };

    if (payload.password.length < 6) {
        errorMessage.textContent = 'Password must be at least 6 characters.';
        errorMessage.style.display = 'block';
        return;
    }

    try {
        const response = await fetch('api/student/register.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const text = await response.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (err) {
            console.error('Invalid JSON from server:', text);
            errorMessage.textContent = 'Server returned invalid response. Please try again.';
            errorMessage.style.display = 'block';
            return;
        }

        if (data.success) {
            successMessage.textContent = data.message || 'Account created. Redirecting to login...';
            successMessage.style.display = 'block';
            signupForm.reset();
            setTimeout(() => {
                window.location.href = 'index.html';
            }, 1200);
        } else {
            errorMessage.textContent = data.message || 'Registration failed. Please try again.';
            errorMessage.style.display = 'block';
        }
    } catch (error) {
        console.error('Signup error:', error);
        errorMessage.textContent = 'An error occurred. Please try again.';
        errorMessage.style.display = 'block';
    }
});
