// Small progressive enhancements. Everything works without this file except the phone menu button.
(function () {
    document.documentElement.classList.add('js');

    // Phone menu.
    var button = document.querySelector('.menu-button');
    var links = document.getElementById('site-nav');
    if (button && links) {
        button.addEventListener('click', function () {
            var open = links.classList.toggle('open');
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    // Slow forms (photo uploads): lock the buttons after the first submit so a second tap cannot post twice.
    document.querySelectorAll('form[data-busy]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.sending === '1') { event.preventDefault(); return; }
            form.dataset.sending = '1';
            var note = form.querySelector('.busy-note');
            if (note) { note.hidden = false; }
            form.querySelectorAll('button:not([type=button])').forEach(function (b) {
                // Disabling in the same tick would drop the clicked button's value; defer it.
                setTimeout(function () { b.disabled = true; }, 0);
            });
        });
    });

    // Bulk selection on a brand page: an action bar that shows how many are ticked.
    var bar = document.getElementById('bulk-bar');
    if (bar) {
        var boxes = function () { return document.querySelectorAll('input[name="polish_ids[]"]'); };
        var update = function () {
            var n = 0;
            boxes().forEach(function (c) { if (c.checked) { n++; } });
            bar.hidden = n === 0 && !bar.dataset.keep;
            var count = bar.querySelector('[data-count]');
            if (count) { count.textContent = n + (n === 1 ? ' selected' : ' selected'); }
        };
        document.addEventListener('change', function (e) {
            if (e.target && e.target.name === 'polish_ids[]') { update(); }
        });
        var all = document.getElementById('select-all');
        if (all) {
            all.addEventListener('click', function () {
                boxes().forEach(function (c) { c.checked = true; });
                update();
            });
        }
        update();
    }
})();
