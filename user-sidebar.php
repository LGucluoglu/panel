<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <i class="bi bi-person-circle display-4"></i>
            <h6 class="mt-2"><?php echo htmlspecialchars($_SESSION['username']); ?></h6>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'user-dashboard.php' ? 'active' : ''; ?>" href="user-dashboard.php">
                    <i class="bi bi-house-door me-2"></i>
                    Ana Sayfa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-exams.php' ? 'active' : ''; ?>" href="my-exams.php">
                    <i class="bi bi-journal-text me-2"></i>
                    Sınavlarım
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-results.php' ? 'active' : ''; ?>" href="my-results.php">
                    <i class="bi bi-graph-up me-2"></i>
                    Sonuçlarım
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                    <i class="bi bi-person me-2"></i>
                    Profilim
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Çıkış Yap
                </a>
            </li>
        </ul>
    </div>
</div>