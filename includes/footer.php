            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                var dropdown = document.getElementById('userDropdown');
                var menu = document.getElementById('userDropdownMenu');
                if (dropdown && !dropdown.contains(e.target)) {
                    menu.classList.remove('show');
                }
            });

            // Keyboard shortcut: "/" to focus search
            document.addEventListener('keydown', function(e) {
                var searchInput = document.getElementById('globalSearch');
                if (!searchInput) return;
                if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    searchInput.focus();
                }
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.blur();
                }
            });
        });

        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            var mainContent = document.querySelector('.main-content');
            var overlay = document.getElementById('sidebarOverlay');
            var isMobile = window.innerWidth <= 768;

            if (isMobile) {
                sidebar.classList.toggle('show');
                if (overlay) overlay.classList.toggle('show');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        }

        function closeSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.remove('show');
            if (overlay) overlay.classList.remove('show');
        }

        function toggleUserDropdown() {
            var menu = document.getElementById('userDropdownMenu');
            menu.classList.toggle('show');
        }

        function confirmDelete(message) {
            return confirm(message || 'Apakah Anda yakin ingin menghapus data ini?');
        }

        // Auto hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);
    </script>

    <?php if (isset($extra_js)) echo $extra_js; ?>

    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
</body>
</html>
