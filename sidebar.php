<div class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center mb-4">
            <i class="bi bi-person-circle display-4"></i>
            <h6 class="mt-2"><?php echo $_SESSION['username']; ?></h6>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="dashboard.php">
                    <i class="bi bi-house-door me-2"></i>
                    Ana Sayfa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="bi bi-people me-2"></i>
                    Kullanıcılar
                </a>
            </li>
            <!-- Sınavlar Menüsü -->
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#examsSubmenu">
                    <i class="bi bi-journal-text me-2"></i>
                    Sınavlar
                    <i class="bi bi-chevron-down float-end"></i>
                </a>
                <div class="collapse" id="examsSubmenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link" href="exams.php">
                                <i class="bi bi-list-check me-2"></i>
                                Sınav Listesi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_exam.php">
                                <i class="bi bi-plus-circle me-2"></i>
                                Yeni Sınav
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="question_bank.php">
                                <i class="bi bi-collection me-2"></i>
                                Soru Bankası
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_questions.php">
                                <i class="bi bi-plus-square me-2"></i>
                                Soru Ekle
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <!-- Kategoriler Menüsü -->
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#categoriesSubmenu">
                    <i class="bi bi-diagram-3 me-2"></i>
                    Kategoriler
                    <i class="bi bi-chevron-down float-end"></i>
                </a>
                <div class="collapse" id="categoriesSubmenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="bi bi-list me-2"></i>
                                Kategori Listesi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create_category.php">
                                <i class="bi bi-plus-circle me-2"></i>
                                Yeni Kategori
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <!-- Seviyeler -->
            <li class="nav-item">
                <a class="nav-link" href="levels.php">
                    <i class="bi bi-bar-chart-steps me-2"></i>
                    Seviyeler
                </a>
            </li>
            <!-- Yeni Eklenen Menüler -->
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#studyPlansSubmenu">
                    <i class="bi bi-calendar-check me-2"></i>
                    Çalışma Planları
                    <i class="bi bi-chevron-down float-end"></i>
                </a>
                <div class="collapse" id="studyPlansSubmenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link" href="study-plans.php">
                                <i class="bi bi-list me-2"></i>
                                Plan Listesi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create-study-plan.php">
                                <i class="bi bi-plus-circle me-2"></i>
                                Yeni Plan
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#achievementsSubmenu">
                    <i class="bi bi-trophy me-2"></i>
                    Başarı Rozetleri
                    <i class="bi bi-chevron-down float-end"></i>
                </a>
                <div class="collapse" id="achievementsSubmenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link" href="achievements.php">
                                <i class="bi bi-list me-2"></i>
                                Rozet Listesi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="create-achievement.php">
                                <i class="bi bi-plus-circle me-2"></i>
                                Yeni Rozet
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="collapse" href="#analyticsSubmenu">
                    <i class="bi bi-graph-up me-2"></i>
                    Başarı Analizleri
                    <i class="bi bi-chevron-down float-end"></i>
                </a>
                <div class="collapse" id="analyticsSubmenu">
                    <ul class="nav flex-column ms-3">
                        <li class="nav-item">
                            <a class="nav-link" href="analytics.php">
                                <i class="bi bi-graph-up me-2"></i>
                                Genel Analiz
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="student-analytics.php">
                                <i class="bi bi-person-lines-fill me-2"></i>
                                Öğrenci Analizi
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="topic-analytics.php">
                                <i class="bi bi-book me-2"></i>
                                Konu Analizi
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
            <!-- Raporlar -->
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="bi bi-file-earmark-text me-2"></i>
                    Raporlar
                </a>
            </li>
            <!-- Çıkış -->
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i>
                    Çıkış Yap
                </a>
            </li>
        </ul>
    </div>
</div>
