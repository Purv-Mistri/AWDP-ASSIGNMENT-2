// Form Password Confirmation Check
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', e => {
        const password = form.querySelector('[name=password]');
        const confirm = form.querySelector('[name=confirm]');
        if (password && confirm && password.value !== confirm.value) {
            e.preventDefault();
            alert('Passwords do not match! Please check and try again.');
            confirm.focus();
        }
    });
});

// Auto-hide alert toasts after 5 seconds
setTimeout(() => {
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        const bsAlert = bootstrap.Alert.getInstance(alert) || new bootstrap.Alert(alert);
        if (bsAlert) bsAlert.close();
    });
}, 5000);