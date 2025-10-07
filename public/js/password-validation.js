function validatePassword(password) {
    const minLength = 8;
    const hasUpperCase = /[A-Z]/.test(password);
    const hasLowerCase = /[a-z]/.test(password);
    const hasNumbers = /\d/.test(password);
    const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
    
    return {
        isValid: password.length >= minLength && 
                hasUpperCase && 
                hasLowerCase && 
                hasNumbers && 
                hasSpecial,
        requirements: {
            minLength: password.length >= minLength,
            hasUpperCase,
            hasLowerCase,
            hasNumbers,
            hasSpecial
        }
    };
}

// Add to your form submission
document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    const validation = validatePassword(password);
    
    if (!validation.isValid) {
        e.preventDefault();
        // Show error messages to user
        const errors = [];
        if (!validation.requirements.minLength) errors.push('Minimal 8 karakter');
        if (!validation.requirements.hasUpperCase) errors.push('Harus mengandung huruf besar');
        if (!validation.requirements.hasLowerCase) errors.push('Harus mengandung huruf kecil');
        if (!validation.requirements.hasNumbers) errors.push('Harus mengandung angka');
        if (!validation.requirements.hasSpecial) errors.push('Harus mengandung karakter khusus');
        
        alert('Password tidak memenuhi syarat:\n' + errors.join('\n'));
    }
});