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
                    if (menu) menu.classList.add('hidden');
                }
            });

            // Keyboard shortcut: "/" to focus search, Escape to blur
            document.addEventListener('keydown', function(e) {
                var searchInput = document.getElementById('globalSearch');
                if (!searchInput) return;
                if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA' && document.activeElement.tagName !== 'SELECT') {
                    e.preventDefault();
                    searchInput.focus();
                }
                if (e.key === 'Escape' && document.activeElement === searchInput) {
                    searchInput.blur();
                }
            });
        });

        // -- Sidebar Toggle --
        function toggleSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var isMobile = window.innerWidth < 768;

            if (isMobile) {
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('translate-x-0');
                if (overlay) overlay.classList.toggle('hidden');
            } else {
                sidebar.classList.toggle('sidebar-collapsed');
                var mainArea = document.querySelector('.main-area');
                if (sidebar.classList.contains('sidebar-collapsed')) {
                    mainArea.style.marginLeft = '72px';
                } else {
                    mainArea.style.marginLeft = '';
                }
            }
        }

        function closeSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.add('-translate-x-full');
            sidebar.classList.remove('translate-x-0');
            if (overlay) overlay.classList.add('hidden');
        }

        function closeSidebarMobile() {
            if (window.innerWidth < 768) {
                closeSidebar();
            }
        }

        // -- User Dropdown --
        function toggleUserDropdown() {
            var menu = document.getElementById('userDropdownMenu');
            if (menu) menu.classList.toggle('hidden');
        }

        // -- Confirm Delete --
        function confirmDelete(message) {
            return confirm(message || 'Apakah Anda yakin ingin menghapus data ini?');
        }

        // -- Auto-hide flash alerts --
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert-flash');
            alerts.forEach(function(el) {
                el.style.opacity = '0';
                setTimeout(function() { el.remove(); }, 300);
            });
        }, 4000);

        // -- Generic delete button handler --
        document.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-delete-url]');
            if (btn) {
                e.preventDefault();
                var msg = btn.getAttribute('data-delete-confirm') || 'Apakah Anda yakin ingin menghapus data ini?';
                if (confirm(msg)) {
                    window.location.href = btn.getAttribute('data-delete-url');
                }
            }
        });
    </script>

    <?php if (isset($extra_js)) echo $extra_js; ?>

    <script src="<?php echo BASE_URL; ?>assets/js/main.js"></script>
</body>
</html>
