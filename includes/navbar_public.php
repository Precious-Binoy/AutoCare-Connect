<?php
// includes/navbar_public.php
?>
<nav class="navbar-public">
    <div class="container">
        <a href="index.php" class="flex items-center gap-2">
            <!-- Icon Placeholder -->
            <div style="width: 32px; height: 32px; background: var(--primary); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fa-solid fa-car-side"></i>
            </div>
            <span class="font-bold text-xl text-primary">AutoCare Connect</span>
        </a>

        <div class="nav-links-public">
            <a href="index.php#home">Home</a>
            <a href="index.php#services">Services</a>
            <a href="index.php#about">About</a>
            <a href="careers.php">Careers</a>
            <a href="index.php#contact">Contact</a>
        </div>

        <div class="flex gap-2">
            <a href="login.php" class="btn btn-outline btn-sm">Login</a>
            <a href="register.php" class="btn btn-primary btn-sm">Sign Up</a>
        </div>
    </div>
</nav>
