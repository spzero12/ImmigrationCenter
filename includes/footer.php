    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/main.js"></script>
    <?php foreach ($extraScripts ?? [] as $src): ?>
    <script src="<?php echo htmlspecialchars($src); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
