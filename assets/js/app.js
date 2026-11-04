// Progressive enhancement: selects with data-nav navigate on change.
document.querySelectorAll('select[data-nav]').forEach(function (select) {
    var button = select.form && select.form.querySelector('[data-nav-submit]');
    if (button) {
        button.hidden = true;
    }
    select.addEventListener('change', function () {
        if (select.value) {
            window.location.href = select.value;
        }
    });
});
