<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin - RTTC 2026' ?></title>
    <link rel="icon" href="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg" type="image/jpeg">
    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body class="admin-body">

<!-- Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <img src="<?= BASE_URL ?>/assets/img/RTTC_logo.jpeg" alt="RTTC" height="40" class="me-2">
        <div>
            <div class="sidebar-brand-text">RTTC Admin</div>
            <div class="sidebar-brand-sub">2026 Panel</div>
        </div>
    </div>

    <div class="sidebar-divider"></div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Main</div>
        <a href="<?= route('admin.dashboard') ?>" class="sidebar-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i><span>Dashboard</span>
        </a>

        <div class="nav-section-label">Applications</div>
        <a href="<?= route('admin.students') ?>" class="sidebar-link <?= ($activePage ?? '') === 'students' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i><span>All Students</span>
        </a>
        <a href="<?= route('admin.applications') ?>" class="sidebar-link <?= ($activePage ?? '') === 'applications' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-person-fill"></i><span>Applications</span>
        </a>
        <a href="<?= route('admin.payments') ?>" class="sidebar-link <?= ($activePage ?? '') === 'payments' ? 'active' : '' ?>">
            <i class="bi bi-credit-card-fill"></i><span>Payments</span>
        </a>

        <div class="nav-section-label">Support</div>
        <a href="<?= route('admin.queries') ?>" class="sidebar-link <?= ($activePage ?? '') === 'queries' ? 'active' : '' ?>">
            <i class="bi bi-chat-left-dots-fill"></i><span>Student Queries</span>
        </a>

        <div class="nav-section-label">Tools</div>
        <a href="<?= route('admin.notice-documents') ?>" class="sidebar-link <?= ($activePage ?? '') === 'notice-documents' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-pdf-fill"></i><span>Notice Documents</span>
        </a>
        <a href="<?= route('admin.marquee') ?>" class="sidebar-link <?= ($activePage ?? '') === 'marquee' ? 'active' : '' ?>">
            <i class="bi bi-megaphone-fill"></i><span>Home Marquee</span>
        </a>
        <a href="<?= route('admin.export') ?>" class="sidebar-link <?= ($activePage ?? '') === 'export' ? 'active' : '' ?>">
            <i class="bi bi-download"></i><span>Export Data</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= route('admin.settings') ?>" class="sidebar-link <?= ($activePage ?? '') === 'settings' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i><span>Settings</span>
        </a>
        <a href="<?= route('admin.logout') ?>" class="sidebar-link text-danger">
            <i class="bi bi-box-arrow-left"></i><span>Logout</span>
        </a>
    </div>
</div>

<!-- Main content wrapper -->
<div class="admin-main" id="adminMain">
    <!-- Top navbar -->
    <nav class="admin-topbar">
        <button class="btn btn-link text-dark p-0 me-3" id="sidebarToggle">
            <i class="bi bi-list fs-4"></i>
        </button>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <a href="<?= route('home') ?>" class="btn btn-sm btn-outline-primary me-2">
                <i class="bi bi-house-door me-1"></i>Home
            </a>
            <span class="text-muted small d-none d-md-block">
                <?= date('l, d M Y') ?>
            </span>
            <div class="dropdown">
                <button class="btn btn-link text-dark p-0 dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-5 me-1"></i>
                    <?= htmlspecialchars(SessionHelper::get('admin_username') ?? 'Admin') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= route('admin.settings') ?>">
                        <i class="bi bi-gear me-2"></i>Settings
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= route('admin.logout') ?>">
                        <i class="bi bi-box-arrow-left me-2"></i>Logout
                    </a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Page content -->
    <div class="admin-content">
        <?php if (isset($breadcrumb)): ?>
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?= route('admin.dashboard') ?>">Home</a></li>
                <?php foreach ($breadcrumb as $bc): ?>
                    <?php if (isset($bc['url'])): ?>
                        <li class="breadcrumb-item"><a href="<?= $bc['url'] ?>"><?= $bc['label'] ?></a></li>
                    <?php else: ?>
                        <li class="breadcrumb-item active"><?= $bc['label'] ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php endif; ?>

        <?php include BASE_PATH . '/views/partials/flash.php'; ?>

        <?= $content ?? '' ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    document.getElementById('adminSidebar').classList.toggle('collapsed');
    document.getElementById('adminMain').classList.toggle('expanded');
});
</script>
<?= $extraFoot ?? '' ?>
</body>
</html>
