<div class="sidebar">
    <div class="user-section">
        <i class="bi bi-person-circle"></i>
        <h6><?php echo htmlspecialchars($_SESSION['name']); ?></h6>
    </div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'user-dashboard.php' ? 'active' : ''; ?>" 
               href="user-dashboard.php">
                <i class="bi bi-speedometer2"></i>
                Ana Sayfa
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'my-goals.php' ? 'active' : ''; ?>" 
               href="my-goals.php">
                <i class="bi bi-bullseye"></i>
                Hedeflerim
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'my-study-plan.php' ? 'active' : ''; ?>" 
               href="my-study-plan.php">
                <i class="bi bi-calendar-check"></i>
                Çalışma Planım
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'my-exams.php' ? 'active' : ''; ?>" 
               href="my-exams.php">
                <i class="bi bi-journal-text"></i>
                Sınavlarım
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'my-progress.php' ? 'active' : ''; ?>" 
               href="my-progress.php">
                <i class="bi bi-graph-up"></i>
                İlerleme Durumum
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'my-achievements.php' ? 'active' : ''; ?>" 
               href="my-achievements.php">
                <i class="bi bi-trophy"></i>
                Rozetlerim
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link <?php echo $current_page === 'my-results.php' ? 'active' : ''; ?>" 
               href="my-results.php">
                <i class="bi bi-clipboard-data"></i>
                Sonuçlarım
            </a>
        </li>

        <li class="nav-item mt-3">
            <a class="nav-link" href="logout.php">
                <i class="bi bi-box-arrow-right"></i>
                Çıkış Yap
            </a>
        </li>
    </ul>
</div>
