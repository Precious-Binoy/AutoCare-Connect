<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoCare Connect - Complete Car Care</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CRITICAL: BRANDS SECTION STYLES -->
    <style>
        .brands-grid-logos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 1.5rem;
            margin-top: 2.5rem;
            justify-content: center;
        }
        
        .brand-logo-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            height: 100%;
        }
        
        .brand-logo-card:hover {
            transform: translateY(-4px);
            border-color: #2563EB;
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.1), 0 4px 6px -2px rgba(37, 99, 235, 0.05);
        }
        
        .brand-logo-img {
            height: 60px;
            width: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .brand-logo-img img {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain;
            transition: transform 0.2s ease;
        }
        
        .brand-logo-card:hover .brand-logo-img img {
            transform: scale(1.1);
        }
        
        .brand-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: #475569;
            margin: 0;
            text-align: center;
        }
        
        .brand-logo-card:hover .brand-label {
            color: #2563EB;
            font-weight: 600;
        }

        /* Desktop: 6 columns */
        @media (min-width: 1024px) {
            .brands-grid-logos {
                grid-template-columns: repeat(6, 1fr);
            }
        }
        
        /* Tablet: 4 columns */
        @media (max-width: 1023px) {
            .brands-grid-logos {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        /* Mobile: 3 columns */
        @media (max-width: 640px) {
            .brands-grid-logos {
                grid-template-columns: repeat(3, 1fr);
                gap: 0.75rem;
            }
            .brand-logo-card {
                padding: 0.75rem 0.25rem;
                border-radius: 8px;
            }
            .brand-logo-img {
                height: 40px;
                width: 50px;
            }
            .brand-label {
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/navbar_public.php'; ?>

    <!-- HERO SECTION -->
    <section class="hero-section" id="home">
        <div class="container hero-grid">
            <div class="hero-content">
                <h1 class="hero-title">Complete Care for Your Car, Complete Control for Your Workshop.</h1>
                <p class="hero-subtitle">From seamless booking to pickup & delivery tracking. The all-in-one platform for drivers and mechanics that modernizes auto repair.</p>
                <div class="flex gap-4 flex-wrap">
                    <!-- Customer Button -->
                    <div class="flex flex-col gap-1">
                        <a href="login.php" class="btn btn-primary">Book a Service</a>
                        <span class="text-xs text-muted" style="padding-left: 0.5rem;">For Customers</span>
                    </div>
                    
                    <!-- Career Button -->
                    <div class="flex flex-col gap-1">
                        <a href="careers.php" class="btn btn-secondary btn-icon" style="background: #E2E8F0; color: #1E293B;">
                            <i class="fa-solid fa-briefcase"></i> Join Our Team
                        </a>
                        <span class="text-xs text-muted" style="padding-left: 0.5rem;">Mechanics & Drivers Apply Here</span>
                    </div>
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

    <!-- CAR BRANDS SECTION -->
    <section class="brands-section" id="brands">
        <div class="container">
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold mb-2">Trusted by All Major Brands</h2>
                <p class="text-muted">We service vehicles from leading manufacturers worldwide</p>
            </div>

            <div class="brands-grid-logos">
                <!-- Mercedes -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/mercedes-benz-logo.png" alt="Mercedes-Benz">
                    </div>
                    <p class="brand-label">Mercedes-Benz</p>
                </div>

                <!-- BMW -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/bmw-logo.png" alt="BMW">
                    </div>
                    <p class="brand-label">BMW</p>
                </div>

                <!-- Audi -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/audi-logo.png" alt="Audi">
                    </div>
                    <p class="brand-label">Audi</p>
                </div>

                <!-- Toyota -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/toyota-logo.png" alt="Toyota">
                    </div>
                    <p class="brand-label">Toyota</p>
                </div>

                <!-- Honda -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/honda-logo.png" alt="Honda">
                    </div>
                    <p class="brand-label">Honda</p>
                </div>

                <!-- Ford -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/ford-logo.png" alt="Ford">
                    </div>
                    <p class="brand-label">Ford</p>
                </div>

                <!-- Tesla -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/tesla-logo.png" alt="Tesla">
                    </div>
                    <p class="brand-label">Tesla</p>
                </div>

                <!-- Porsche -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/porsche-logo.png" alt="Porsche">
                    </div>
                    <p class="brand-label">Porsche</p>
                </div>

                <!-- Volkswagen -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/volkswagen-logo.png" alt="Volkswagen">
                    </div>
                    <p class="brand-label">Volkswagen</p>
                </div>

                <!-- Nissan -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/nissan-logo.png" alt="Nissan">
                    </div>
                    <p class="brand-label">Nissan</p>
                </div>

                <!-- Hyundai -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/hyundai-logo.png" alt="Hyundai">
                    </div>
                    <p class="brand-label">Hyundai</p>
                </div>

                <!-- Chevrolet -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/chevrolet-logo.png" alt="Chevrolet">
                    </div>
                    <p class="brand-label">Chevrolet</p>
                </div>

                <!-- Kia -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; fill: black;"><title>Kia</title><path d="M13.923 14.175c0 .046.015.072.041.072a.123.123 0 0 0 .058-.024l7.48-4.854a.72.72 0 0 1 .432-.13h1.644c.252 0 .422.168.422.42v3.139c0 .38-.084.6-.42.801l-1.994 1.2a.137.137 0 0 1-.067.024c-.024 0-.048-.019-.048-.088v-3.663c0-.043-.012-.071-.041-.071a.113.113 0 0 0-.058.024l-5.466 3.551a.733.733 0 0 1-.42.127h-3.624c-.254 0-.422-.168-.422-.422V9.757c0-.033-.015-.064-.044-.064a.118.118 0 0 0-.057.024L7.732 11.88c-.036.024-.046.041-.046.058 0 .014.008.029.032.055l2.577 2.575c.034.034.058.06.058.089 0 .024-.039.043-.084.043H7.94c-.183 0-.324-.026-.423-.125l-1.562-1.56a.067.067 0 0 0-.048-.024.103.103 0 0 0-.048.015l-2.61 1.57a.72.72 0 0 1-.423.122H.425C.168 14.7 0 14.53 0 14.279v-3.08c0-.38.084-.6.422-.8L2.43 9.192a.103.103 0 0 1 .052-.016c.032 0 .048.03.048.1V13.4c0 .043.01.063.041.063a.144.144 0 0 0 .06-.024L9.407 9.36a.733.733 0 0 1 .446-.124h3.648c.252 0 .422.168.422.42l-.002 4.518z"/></svg>
                    </div>
                    <p class="brand-label">Kia</p>
                </div>

                <!-- Tata Motors -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/8/8e/Tata_logo.svg" alt="Tata Motors">
                    </div>
                    <p class="brand-label">Tata Motors</p>
                </div>

                <!-- Maruti Suzuki -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                         <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; fill: #E31937;"><title>Suzuki</title><path d="M17.369 19.995C13.51 22.39 12 24 12 24L.105 15.705s5.003-3.715 9.186-.87l5.61 3.882.683-.453L.106 7.321s2.226-.65 6.524-3.315C10.49 1.609 12 0 12 0l11.895 8.296s-5.003 3.715-9.187.87L9.1 5.281l-.683.454L23.893 16.68s-2.224.649-6.524 3.315Z"/></svg>
                    </div>
                    <p class="brand-label">Maruti Suzuki</p>
                </div>

                <!-- Mahindra -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; fill: black;"><title>Mahindra</title><path d="M5.145 11.311H6.78a.67.67 0 0 1 .674.66v1.509H5.009a.408.408 0 0 1-.41-.404v-.524a.38.38 0 0 1 .383-.375h1.354l-.144.306h-.998c-.043 0-.092.034-.092.081v.412c0 .047.049.082.092.082h1.73v-.99c0-.191-.169-.338-.357-.338H4.945l.2-.419zm13.427-.787v2.959h-2.383a.408.408 0 0 1-.41-.403v-1.11a.67.67 0 0 1 .675-.659h1.357l-.2.422h-.948c-.188 0-.357.147-.357.337v.91c0 .046.049.08.092.08h1.644v-2.536h.53zM10.2 13.483h.527v-1.51a.67.67 0 0 0-.674-.659H8.932l-.2.422h1.111c.188 0 .357.147.357.337v1.41zm-2.195-2.96v2.96h.527v-2.96h-.527zm-4.4 2.96h.527v-1.51a.67.67 0 0 0-.674-.659H0v2.169h.526v-1.669c0-.047.05-.081.093-.081h1.09c.043 0 .092.034.092.081v1.669h.527v-1.669c0-.047.049-.081.092-.081h.828c.188 0 .357.147.357.337v1.413zm17.72-2.172H20a.67.67 0 0 0-.674.66v1.509h.527v-1.41c0-.19.169-.337.357-.337h.914l.2-.422zm-6.753 0a.67.67 0 0 1 .675.66v1.509h-.527v-1.41c0-.19-.17-.337-.357-.337h-1.268v1.75h-.527v-2.169c.665 0 1.333-.003 2.004-.003zm-3.19.137.527-.306v2.338h-.526v-2.032zm.53-.609v-.322h-.526v.625l.526-.303zm9.782.472h1.632a.67.67 0 0 1 .674.66v1.509h-2.445a.408.408 0 0 1-.41-.404v-.524a.38.38 0 0 1 .383-.375h1.354l-.144.306h-.998c-.043 0-.092.034-.092.081v.412c0 .047.049.082.092.082h1.73v-.99c0-.191-.169-.338-.357-.338h-1.622l.203-.419z"/></svg>
                    </div>
                    <p class="brand-label">Mahindra</p>
                </div>

                <!-- Renault -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%;">
                            <path d="M50 10L80 50L50 90L20 50L50 10Z" stroke="black" stroke-width="8" fill="none"/>
                            <path d="M50 25L65 50L50 75L35 50L50 25Z" stroke="black" stroke-width="4" fill="none"/>
                        </svg>
                    </div>
                    <p class="brand-label">Renault</p>
                </div>

                <!-- Skoda -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; fill: #4BA82E;"><title>&#352;KODA</title><path d="M12 0C5.3726 0 0 5.3726 0 12s5.3726 12 12 12 12-5.3726 12-12S18.6274 0 12 0Zm0 22.9636C5.945 22.9636 1.0364 18.055 1.0364 12 1.0364 5.945 5.945 1.0364 12 1.0364S22.9636 5.945 22.9636 12 18.055 22.9636 12 22.9636Zm5.189-7.2325-.269.7263h-.984c.263-.7089 3.5783-8.6177-2.9362-13.9819a9.5254 9.5254 0 0 0-4.0531.4483c.2172.175 2.474 2.0276 3.5373 4.315l-.312.084c-.5861-.6387-2.7156-2.9833-4.7448-3.7379a9.6184 9.6184 0 0 0-2.8448 2.3597c.953.4875 3.4432 1.9748 4.3896 3.1302-.0542.0244-.267.139-.267.139-1.736-1.3195-4.8199-2.0043-4.9775-2.0383a9.5126 9.5126 0 0 0-1.2267 3.6098c4.7759.9613 6.0618 3.1715 6.2818 5.6721H7.878l-1.5545-.6776a.8563.8563 0 0 0-.2524-.0531H3.1767a9.587 9.587 0 0 0 1.9267 2.9155h1.2334c.1063 0 .1993-.0133.2923-.0664l1.2489-.6378h9.042l.269.7264a4.8386 4.8386 0 0 0 2.9466-1.4667 4.839 4.839 0 0 0-2.9467-1.4666zm-4.14-.5786a1.1863 1.1863 0 0 1-.5038-1.2162 1.1862 1.1862 0 0 1 .931-.9309 1.1863 1.1863 0 0 1 1.2161.5038c.3098.4636.2563 1.0924-.1473 1.496-.4032.4032-1.0318.4574-1.496.1473z"/></svg>
                    </div>
                    <p class="brand-label">Skoda</p>
                </div>

                <!-- MG -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                         <svg role="img" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; fill: #DA291C;"><title>MG</title><path d="M23.835 8.428c-.015-.184-.034-.368-.053-.552-.016-.138-.034-.274-.052-.411a.592.592 0 0 0-.104-.243c-.087-.11-.175-.217-.266-.323l-.365-.429a42.198 42.198 0 0 0-2.509-2.638A42.774 42.774 0 0 0 17.3 1.064c-.11-.088-.222-.174-.336-.257a.664.664 0 0 0-.252-.1 21.852 21.852 0 0 0-1-.102 45.346 45.346 0 0 0-3.71-.158 45.291 45.291 0 0 0-4.286.211c-.142.015-.284.03-.426.048a.664.664 0 0 0-.253.1c-.113.085-.225.17-.337.258a42.775 42.775 0 0 0-3.185 2.768A42.467 42.467 0 0 0 .641 6.898c-.09.107-.18.215-.267.324a.609.609 0 0 0-.105.243c-.019.137-.035.273-.05.41-.02.185-.038.37-.056.553A41.387 41.387 0 0 0 0 12.001a41.35 41.35 0 0 0 .163 3.574l.057.552c.014.138.03.274.05.41.015.087.052.17.104.244a24.04 24.04 0 0 0 .633.753 42.577 42.577 0 0 0 2.507 2.636A42.394 42.394 0 0 0 6.7 22.938c.112.087.224.172.337.255a.663.663 0 0 0 .253.102l.426.048c.19.018.383.037.574.053 1.234.103 2.473.157 3.712.157 1.237 0 2.476-.054 3.71-.157.193-.016.384-.035.573-.053.144-.015.287-.031.427-.048a.66.66 0 0 0 .252-.102c.115-.083.227-.168.336-.255a42.392 42.392 0 0 0 3.187-2.767 42.424 42.424 0 0 0 2.509-2.637l.365-.43c.09-.106.18-.215.266-.323a.596.596 0 0 0 .104-.243c.018-.137.036-.273.052-.411A39.963 39.963 0 0 0 24 12c0-1.191-.058-2.384-.165-3.573m-1.805 6.601c-.013.156-.029.313-.044.469l-.044.348a.499.499 0 0 1-.089.205c-.073.092-.148.185-.225.276l-.31.363a35.829 35.829 0 0 1-2.126 2.234c-.86.827-1.762 1.61-2.7 2.346a7.787 7.787 0 0 1-.285.216.551.551 0 0 1-.214.087l-.362.04a38.171 38.171 0 0 1-3.63.178c-1.05 0-2.1-.045-3.146-.132l-.486-.045-.362-.041a.547.547 0 0 1-.214-.087 9.555 9.555 0 0 1-.285-.216c-.127-.099-.251-.2-.376-.3a35.855 35.855 0 0 1-2.324-2.046 36.03 36.03 0 0 1-2.125-2.234c-.182-.21-.361-.423-.536-.639a.515.515 0 0 1-.089-.205 33.972 33.972 0 0 1-.09-.817 34.723 34.723 0 0 1-.138-3.028c.003-1.01.047-2.02.138-3.029.015-.155.03-.311.048-.467.012-.118.026-.232.042-.348a.506.506 0 0 1 .089-.206 21.379 21.379 0 0 1 .536-.638 36.255 36.255 0 0 1 2.125-2.236 36.3 36.3 0 0 1 2.7-2.346c.094-.073.189-.146.285-.218a.553.553 0 0 1 .214-.084c.282-.035.565-.063.848-.086a38.642 38.642 0 0 1 3.146-.135 38.792 38.792 0 0 1 3.63.18c.122.012.243.026.362.04a.56.56 0 0 1 .214.085 26.752 26.752 0 0 1 .662.517 36.24 36.24 0 0 1 2.323 2.047c.74.715 1.45 1.46 2.126 2.236l.31.364c.077.09.152.181.225.274a.5.5 0 0 1 .089.205l.044.349c.015.155.031.312.044.467.091 1.009.14 2.019.14 3.029 0 1.01-.048 2.021-.14 3.028m-1.225-3c-.098-.01-.981-.012-1.456-.017-.622-.005-1.042 0-1.246-.001-.06 0-.068-.003-.135 0-.003.047-.003.071-.005.13-.002.043-.01.19-.018.384-.012.326-.026.787-.018 1.116l.001.114c.036.002.616.002 1.007.005.053 0 .057.001.11.003-.001.027 0 .052.001.097 0 .048-.055.74-.088.94-.1.149-.163.23-.367.456-.217.24-.256.3-.934.984-.704.712-2.035 1.867-2.513 2.263a9.84 9.84 0 0 0-.303.257s.007-.243-.002-.361c.018-4.565.013-7.807-.004-12.84.008-.114-.005-.209 0-.347.15.117.156.123.259.208.7.594 1.438 1.203 2.024 1.79.81.815 1.156 1.174 1.74 1.863.058.073.069.088.108.15.01.064.01.076.021.157.023.193.062.588.068.696.002.062.009.091.007.151.06.006.1 0 .16.004.352.006.77.008 1.167.006.133-.001.265-.003.39-.006.068 0 .072.002.128 0a1.427 1.427 0 0 0 0-.17 12.32 12.32 0 0 0-.097-1.292 2.536 2.536 0 0 0-.032-.267c-.05-.068-.081-.1-.128-.155A28.182 28.182 0 0 0 18.5 6.02c-1.795-1.721-2.75-2.375-2.75-2.375s-.077-.057-.134-.095c-.075-.014-.058-.01-.13-.02a31.483 31.483 0 0 0-2.608-.168c-.124-.004-.16-.007-.293-.001.006.15.002.153-.002.267.014 6.216-.02 10.641-.009 16.813v.188s.088.008.203.004c.734 0 2.167-.08 2.534-.14.142-.022.219-.027.319-.056.075-.043.115-.074.176-.126a36.5 36.5 0 0 0 2.616-2.267c.983-.941 1.876-1.96 2.09-2.2.09-.099.15-.176.256-.315.045-.166.034-.215.054-.347.093-1.076.167-1.752.167-2.977-.004-.064-.002-.095-.007-.169-.089-.005-.128-.004-.177-.008m-9.539-8.672c-.152.006-.43-.003-.942.026-.537.031-.85.064-.977.075-.073.007-.117.007-.17.013-.022.048-.019.042-.042.103-.779 1.95-1.788 4.655-2.627 6.666-.042.085-.128.3-.128.3s-.039-.064-.139-.267A85.298 85.298 0 0 0 4.67 7.276c-.046-.077-.128-.246-.128-.246s-.123.132-.204.204c-.173.155-.805.878-.93 1.046-.064.083-.085.107-.157.21-.03.117-.036.187-.058.316-.045.257-.153 1.364-.18 2.852.004 1.21.076 2.292.186 3.498l.031.322s.137.186.166.219c.605.71 1.046 1.217 1.463 1.643l.358.365s-.018-.257-.025-.39l-.024-.413c-.082-1.297-.244-3.484-.29-4.621-.008-.144.018-.824.018-.824l1.742 3.508s.13-.315.188-.447c.7-1.754 1.366-3.327 2.05-5.081.047-.11.294-.77.294-.77s.007.712 0 .866c-.034 4.924-.019 7.741-.012 10.444l.001.249c0 .138-.003.156-.003.247.181.03.163.03.261.042.317.04.313.051.686.075.385.024.806.035 1.142.043.086-.004.133-.004.175-.006.003-.08.003-.118.003-.193-.029-6.302.044-16.917.044-16.917s.003-.057 0-.162a2.544 2.544 0 0 0-.2.001"/></svg>
                    </div>
                    <p class="brand-label">MG Motor</p>
                </div>

                <!-- Jeep -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/jeep-logo.png" alt="Jeep">
                    </div>
                    <p class="brand-label">Jeep</p>
                </div>

                <!-- Land Rover -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/land-rover-logo.png" alt="Land Rover">
                    </div>
                    <p class="brand-label">Land Rover</p>
                </div>

                <!-- Volvo -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/volvo-logo.png" alt="Volvo">
                    </div>
                    <p class="brand-label">Volvo</p>
                </div>

                <!-- Jaguar -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/jaguar-logo.png" alt="Jaguar">
                    </div>
                    <p class="brand-label">Jaguar</p>
                </div>

                <!-- Volkswagen -->
                <div class="brand-logo-card">
                    <div class="brand-logo-img">
                        <img src="https://www.carlogos.org/car-logos/volkswagen-logo.png" alt="Volkswagen">
                    </div>
                    <p class="brand-label">Volkswagen</p>
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
