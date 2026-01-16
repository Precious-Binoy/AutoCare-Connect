<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare Connect - Complete Car Care</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <?php include 'includes/navbar_public.php'; ?>

    <!-- HERO SECTION -->
    <section class="hero-section" id="home">
        <div class="container hero-grid">
            <div class="hero-content">
                <h1 class="hero-title">Complete Care for Your Car, Complete Control for Your Workshop.</h1>
                <p class="hero-subtitle">From seamless booking to pickup & delivery tracking. The all-in-one platform for drivers and mechanics that modernizes auto repair.</p>
                <div class="flex gap-2">
                    <a href="login.php" class="btn btn-primary">Book a Service</a>
                    <a href="careers.php" class="btn btn-secondary" style="background: #E2E8F0; color: #1E293B;">Partner with Us</a>
                </div>
                <div class="flex items-center gap-2 mt-4 text-sm text-primary font-medium">
                    <i class="fa-solid fa-circle-check"></i> Trusted by 500+ Local Workshops
                </div>
            </div>
            <div class="hero-image">
                <img src="https://images.unsplash.com/photo-1605559424843-9e4c228bf1c2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Mechanic with tablet" style="border-radius: 1rem; box-shadow: var(--shadow-lg);">
            </div>
        </div>
    </section>

    <!-- SERVICES SECTION -->
    <section class="services-section" id="services">
        <div class="container">
            <div class="text-center mb-6">
                <span class="text-primary font-bold text-xs uppercase tracking-wider">Our Expertise</span>
                <h2 class="text-3xl mt-2">Our Premium Services</h2>
                <p class="text-muted mt-2">Professional care for every aspect of your vehicle.</p>
            </div>

            <div class="services-grid">
                <!-- Service 1 -->
                <div class="service-card">
                    <div class="icon-box"><i class="fa-solid fa-stethoscope"></i></div>
                    <h3 class="text-xl font-bold mb-2">Detailed Diagnostics</h3>
                    <p class="text-muted text-sm">Advanced computer diagnostics to identify engine, transmission, and electrical issues instantly.</p>
                </div>
                <!-- Service 2 -->
                <div class="service-card">
                    <div class="icon-box"><i class="fa-solid fa-wrench"></i></div>
                    <h3 class="text-xl font-bold mb-2">Regular Maintenance</h3>
                    <p class="text-muted text-sm">Scheduled oil changes, tire rotations, fluid checks, and brake inspections to keep you safe.</p>
                </div>
                <!-- Service 3 -->
                <div class="service-card">
                    <div class="icon-box"><i class="fa-solid fa-truck-pickup"></i></div>
                    <h3 class="text-xl font-bold mb-2">Pickup & Delivery</h3>
                    <p class="text-muted text-sm">Don't leave your couch. We pick up your car, service it, and bring it back fixed and cleaned.</p>
                </div>
                 <!-- Service 4 -->
                 <div class="service-card">
                    <div class="icon-box"><i class="fa-solid fa-clock-rotate-left"></i></div>
                    <h3 class="text-xl font-bold mb-2">Digital Service History</h3>
                    <p class="text-muted text-sm">Access your complete repair logs and invoices anytime through our secure customer portal.</p>
                </div>
                 <!-- Service 5 -->
                 <div class="service-card">
                    <div class="icon-box"><i class="fa-solid fa-bell"></i></div>
                    <h3 class="text-xl font-bold mb-2">Real-Time Updates</h3>
                    <p class="text-muted text-sm">Get SMS or app notifications at every stage of the repair process. No more guessing.</p>
                </div>
                 <!-- Service 6 -->
                 <div class="service-card">
                    <div class="icon-box"><i class="fa-solid fa-shield-halved"></i></div>
                    <h3 class="text-xl font-bold mb-2">Warranty Guarantee</h3>
                    <p class="text-muted text-sm">All repairs are backed by our 12-month / 12,000-mile warranty for your peace of mind.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES / WHY CHOOSE US -->
    <section class="hero-section" id="about">
        <div class="container hero-grid">
            <div class="hero-content">
                <h2 class="text-3xl font-bold mb-4">Why Choose AutoCare Connect?</h2>
                <p class="text-muted mb-6">We bridge the gap between car owners and modern workshops. Transparency, efficiency, and trust are at our core.</p>
                
                <ul class="flex flex-col gap-3">
                    <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-primary"></i> Upfront Pricing - No Hidden Fees</li>
                    <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-primary"></i> Certified ASE Mechanics</li>
                    <li class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-primary"></i> Track Your Repair Status Live</li>
                </ul>

                <a href="register.php" class="btn btn-primary mt-4">Get Started Now</a>
            </div>
            <div class="hero-image">
                <img src="https://images.unsplash.com/photo-1486262715619-67b85e0b08d3?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Car Maintenance" style="border-radius: 1rem; box-shadow: var(--shadow-lg);">
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section style="background: var(--primary); padding: 5rem 0; text-align: center; color: white;" id="contact">
        <div class="container">
            <h2 class="text-white text-3xl font-bold mb-2">Ready to fix your ride?</h2>
            <p class="mb-6 opacity-90">Join thousands of satisfied drivers who trust AutoCare Connect for their vehicle maintenance.</p>
            <div class="flex justify-center gap-3">
                <a href="login.php" class="btn btn-white">Book Appointment</a>
                <a href="#" class="btn btn-outline" style="color: white; border-color: white;">Contact Support</a>
            </div>
        </div>
    </section>

    <?php include 'includes/footer_public.php'; ?>

</body>
</html>
