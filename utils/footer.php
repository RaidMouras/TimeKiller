</div>
    <?php
    $js_path = (strpos($_SERVER['PHP_SELF'], '/views/') !== false) ? '../js/' : 'js/';
    ?>
    <script src="<?php echo $js_path; ?>main.js"></script>
    <script src="<?php echo $js_path; ?>dropdown.js"></script>
    <?php echo $page_scripts ?? ''; ?>
</body>
<style>
    body {
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        margin: 0;
    }
    .site-footer {
        margin-top: auto;
        padding: 20px 0;
        width: 100%;
    }
</style>
<footer class="site-footer">
    <div class="container">
        <div class="footer-content" style="text-align: center;">
            
            <div class="footer-copyright">
                <p class="blockquote-footer">Any issues? Please contact admin@timekiller.com</p>
                <p>&copy; 2025 Time Killer.</p>
            </div>
        </div>
    </div>
</footer>
</html> 