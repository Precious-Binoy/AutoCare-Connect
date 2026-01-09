<?php
// includes/footer_public.php
?>
<footer style="background: var(--accent); color: var(--text-light); padding: 5rem 0 2rem;">
    <div class="container">
        <div class="grid" style="grid-template-columns: 2fr 1fr 1fr 1fr; gap: 4rem; margin-bottom: 4rem;">
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div style="width: 32px; height: 32px; background: var(--primary); border-radius: 6px; display: flex; align-items: center; justify-content: center; color: white;">
                        <i class="fa-solid fa-car-side"></i>
                    </div>
                    <span class="font-bold text-xl text-white">AutoCare Connect</span>
                </div>
                <p style="margin-bottom: 1.5rem; max-width: 300px;">The modern standard for auto repair management. We connect drivers with top-tier workshops for a seamless service experience.</p>
            </div>
            <div>
                <h4 class="text-white mb-4">Company</h4>
                <ul class="flex flex-col gap-2">
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Press</a></li>
                    <li><a href="#">Blog</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white mb-4">Services</h4>
                <ul class="flex flex-col gap-2">
                    <li><a href="#">Diagnostics</a></li>
                    <li><a href="#">Fleet Management</a></li>
                    <li><a href="#">Oil Change</a></li>
                    <li><a href="#">Pickup & Delivery</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white mb-4">Contact</h4>
                <ul class="flex flex-col gap-2">
                    <li><i class="fa-solid fa-location-dot"></i> 123 Mechanic Ave</li>
                    <li><i class="fa-solid fa-phone"></i> (555) 123-4567</li>
                    <li><i class="fa-solid fa-envelope"></i> help@autocare.com</li>
                </ul>
            </div>
        </div>
        <div style="border-top: 1px solid #334155; padding-top: 2rem; display: flex; justify-content: space-between; font-size: 0.9rem;">
            <p>&copy; <?php echo date('Y'); ?> AutoCare Connect. All rights reserved.</p>
            <div class="flex gap-4">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
            </div>
        </div>
    </div>
</footer>
