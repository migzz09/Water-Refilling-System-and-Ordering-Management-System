// Forgot Password Modal Logic
// Handles OTP request and password reset via modal on login page

document.addEventListener('DOMContentLoaded', function() {
    // Modal elements
    const forgotModal = document.getElementById('forgotPasswordModal');
    const forgotForm = document.getElementById('forgotPasswordForm');
    const forgotEmail = document.getElementById('forgotEmail');
    const forgotOtpSection = document.getElementById('forgotOtpSection');
    const forgotOtp = document.getElementById('forgotOtp');
    const newPassword = document.getElementById('newPassword');
    const confirmNewPassword = document.getElementById('confirmNewPassword');
    const resetPasswordBtn = document.getElementById('resetPasswordBtn');
    const forgotMessage = document.getElementById('forgotPasswordMessage');

    let tempEmail = '';
    let otpSent = false;

    // Step 1: Send OTP
    forgotForm.onsubmit = async function(e) {
        e.preventDefault();
        forgotMessage.innerHTML = '';
        const email = forgotEmail.value.trim();
        if (!email) {
            forgotMessage.innerHTML = '<span style="color:red;">Please enter your email address.</span>';
            return;
        }
        // Call API to send OTP
        try {
            const res = await fetch('/api/password/forgot-request.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email })
            });
            const result = await res.json();
            if (result.success) {
                tempEmail = email;
                otpSent = true;
                forgotForm.style.display = 'none';
                forgotOtpSection.style.display = 'block';
                forgotMessage.innerHTML = '<span style="color:green;">OTP sent to your email.</span>';
            } else {
                forgotMessage.innerHTML = '<span style="color:red;">' + (result.message || 'Failed to send OTP.') + '</span>';
            }
        } catch (err) {
            forgotMessage.innerHTML = '<span style="color:red;">Server error. Please try again.</span>';
        }
    };

    // Step 2: Reset Password
    resetPasswordBtn.onclick = async function(e) {
        e.preventDefault();
        forgotMessage.innerHTML = '';
        const otp = forgotOtp.value.trim();
        const pass = newPassword.value;
        const confirmPass = confirmNewPassword.value;
        if (!otp || otp.length !== 6) {
            forgotMessage.innerHTML = '<span style="color:red;">Enter the 6-digit OTP code.</span>';
            return;
        }
        if (!pass || pass.length < 8) {
            forgotMessage.innerHTML = '<span style="color:red;">Password must be at least 8 characters.</span>';
            return;
        }
        if (pass !== confirmPass) {
            forgotMessage.innerHTML = '<span style="color:red;">Passwords do not match.</span>';
            return;
        }
        // Call API to reset password
        try {
            const res = await fetch('/api/password/forgot-reset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: tempEmail, otp, new_password: pass })
            });
            const result = await res.json();
            if (result.success) {
                forgotMessage.innerHTML = '<span style="color:green;">Password reset successful! You can now log in.</span>';
                setTimeout(() => {
                    forgotModal.style.display = 'none';
                    forgotForm.reset();
                    forgotForm.style.display = 'block';
                    forgotOtpSection.style.display = 'none';
                    forgotMessage.innerHTML = '';
                }, 2000);
            } else {
                forgotMessage.innerHTML = '<span style="color:red;">' + (result.message || 'Failed to reset password.') + '</span>';
            }
        } catch (err) {
            forgotMessage.innerHTML = '<span style="color:red;">Server error. Please try again.</span>';
        }
    };
});
