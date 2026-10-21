            </div>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.4.0/dist/js/tabler.min.js"></script>
    <script src="js/main.js?v=<?php echo filemtime(__DIR__ . '/../js/main.js'); ?>"></script>
    <?php foreach ($extraScripts ?? [] as $src): ?>
    <script src="<?php echo htmlspecialchars($src); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../' . $src); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
