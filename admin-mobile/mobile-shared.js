const MobileUI = (() => {
    const state = {
        user: null
    };

    function toggleMenu() {
        document.body.classList.toggle('drawer-open');
    }

    function closeMenu() {
        document.body.classList.remove('drawer-open');
    }

    function goMobile(path) {
        window.location.href = path;
    }

    function logout() {
        fetch('../api/logout.php')
            .then(response => response.json())
            .then(data => {
                window.location.href = data.success ? '../admin/login.html' : '../admin/login.html';
            })
            .catch(() => {
                window.location.href = '../admin/login.html';
            });
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatDate(value) {
        if (!value) return '-';
        const date = new Date(value.replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return value;
        return date.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' });
    }

    function initAdmin({ requireSuper = false, onReady } = {}) {
        fetch('../api/check_login.php?t=' + Date.now(), { cache: 'no-store' })
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.logged_in) {
                    window.location.href = '../admin/login.html';
                    return;
                }
                if (data.user.login_type !== 'admin' || ![1, 2, 3].includes(data.user.role)) {
                    window.location.href = '../admin/login.html';
                    return;
                }
                if (requireSuper && data.user.role !== 1) {
                    const noAccess = document.getElementById('noAccessCard');
                    const content = document.getElementById('adminCreateCard');
                    if (noAccess) noAccess.style.display = 'block';
                    if (content) content.style.display = 'none';
                }
                const userLabel = document.getElementById('mobileUser');
                if (userLabel) {
                    userLabel.textContent = (data.user.name || '관리자') + ' 님';
                }
                if (data.user.role !== 1) {
                    const adminCreateBtn = document.getElementById('adminCreateBtn');
                    if (adminCreateBtn) {
                        adminCreateBtn.style.display = 'none';
                    }
                }
                state.user = data.user;
                if (typeof onReady === 'function') {
                    onReady(data.user);
                }
            })
            .catch(() => {
                window.location.href = '../admin/login.html';
            });
    }

    return {
        toggleMenu,
        closeMenu,
        goMobile,
        logout,
        escapeHtml,
        formatDate,
        initAdmin
    };
})();

