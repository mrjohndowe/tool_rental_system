</main>
<footer> <?= e(APP_NAME) ?> v<?= e(APP_VERSION)  ?> - &copy; <?= auto_copyright(e(DEVELOPED_YEAR)) ?> <?= e(DEVELEPER_NAME) ?></footer>
<script>
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
        if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
});
</script>
</body>
</html>
