<header class="page-header">
    <div class="navbar">
        <div class="logo">
            <img src="images/rainbow-logo.png" alt="Resort Logo">
            <div class="logo-text">
                <h1>Rainbow Forest Paradise</h1>
                <h2>Resort and Campsite</h2>
            </div>
            <!-- Mobile version - next to logo text -->
            <div class="icon mobile-user">
                <?php if($current_user): ?>
                    <div class="user-info">
                        <span class="user-name">Hello, <?= htmlspecialchars($current_user["first_name"]) ?></span>
                        <div class="user-actions">
                            <a href="account.php" class="profile-btn">My Profile</a>
                            <form action="logout.php" method="post">
                                <button type="submit" class="logout-btn">Logout</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="user-icon">
                        <img src="images/logo.png" alt="User Icon">
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <ul class="nav-links">
            <li><a href="index.php">HOME</a></li>
            <li><a href="aboutus.php">ABOUT</a></li>
            <li><a href="accommodation.php">ACCOMMODATIONS</a></li>
            <li><a href="contact.php">CONTACT US</a></li>
            <li><a href="booking_form.php">BOOK NOW</a></li>
        </ul>

        <!-- Desktop version - original position -->
        <div class="icon desktop-user">
            <?php if($current_user): ?>
                <div class="user-info">
                    <span class="user-name">Hello, <?= htmlspecialchars($current_user["first_name"]) ?></span>
                    <div class="user-actions">
                        <a href="account.php" class="profile-btn">My Profile</a>
                        <form action="logout.php" method="post">
                            <button type="submit" class="logout-btn">Logout</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <a href="login.php" class="user-icon">
                    <img src="images/logo.png" alt="User Icon">
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>