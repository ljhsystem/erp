//경로: PROJECT_ROOT/assets/js/pages/home/contact.js

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[data-contact-form], form[action*="/api/contact/send"]');
    if (!form) return;

    const get = (id) => document.getElementById(id);
    const requiredIds = ['FullName', 'EmailId', 'MobileNo', 'Subject', 'Message'];

    form.addEventListener('submit', (e) => {
        const invalid = requiredIds.some((id) => {
            const el = get(id);
            return !el || !el.value || el.value.trim() === '';
        });

        if (invalid) {
            e.preventDefault();
            alert('필수 항목을 모두 입력해 주세요.');
        }
    });
});
