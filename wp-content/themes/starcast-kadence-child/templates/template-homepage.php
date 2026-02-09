<?php
/**
 * Template Name: Starcast Pro - Homepage
 * Description: Modern ISP homepage with hero, coverage checker, and featured packages
 *
 * @package Starcast_Pro
 */

get_header();
?>

<!-- Hero Section -->
<div class="starcast-hero">
    <div class="starcast-hero-content">
        <h1>
            Fast, Reliable Internet
            <span class="starcast-hero-accent">Made Simple</span>
        </h1>
        <p>Premium fibre and LTE solutions with transparent pricing, quick installation, and local support you can count on</p>
        <div class="starcast-hero-cta">
            <a href="<?php echo home_url('/fibre'); ?>" class="starcast-btn starcast-btn-primary">
                🚀 View Fibre Packages
            </a>
            <a href="<?php echo home_url('/lte-5g'); ?>" class="starcast-btn starcast-btn-secondary">
                📡 View LTE Options
            </a>
        </div>
    </div>
</div>

<!-- Trust Bar -->
<div class="starcast-trust-bar">
    <div class="starcast-trust-items">
        <div class="starcast-trust-item">
            <span class="starcast-trust-icon">✓</span>
            <span>7 Day Installation</span>
        </div>
        <div class="starcast-trust-item">
            <span class="starcast-trust-icon">✓</span>
            <span>24/7 Expert Support</span>
        </div>
        <div class="starcast-trust-item">
            <span class="starcast-trust-icon">✓</span>
            <span>100% Local Service</span>
        </div>
        <div class="starcast-trust-item">
            <span class="starcast-trust-icon">✓</span>
            <span>No Contract Required</span>
        </div>
    </div>
</div>

<!-- Coverage Checker -->
<div class="starcast-coverage-checker">
    <div class="starcast-coverage-card">
        <h2>Check Coverage in Your Area</h2>
        <p style="text-align: center; color: var(--starcast-gray-600); font-size: 1.125rem;">
            Enter your address to see available fibre and LTE packages
        </p>

        <div class="starcast-search-box">
            <input type="text"
                   id="starcast-address-input"
                   class="starcast-search-input"
                   placeholder="Enter your street address or suburb...">
            <button class="starcast-btn starcast-btn-primary" onclick="starcastCheckCoverage()">
                Check Availability
            </button>
        </div>

        <div id="starcast-coverage-results" style="margin-top: 2rem;"></div>
    </div>
</div>

<!-- Promotional Banner -->
<div style="max-width: 1366px; margin: 0 auto; padding: 0 1.5rem;">
    <div class="starcast-promo-banner starcast-animate-fade-in-up">
        ✨ <strong>NEW:</strong> Check out our latest packages with competitive pricing and flexible options
    </div>
</div>

<!-- Featured Packages Section -->
<section style="background: var(--starcast-gray-50); padding: var(--starcast-spacing-3xl) 0;">
    <div style="max-width: 1366px; margin: 0 auto; padding: 0 1.5rem;">
        <div style="text-align: center; margin-bottom: var(--starcast-spacing-xl);">
            <h2 style="font-size: 2.5rem; color: var(--starcast-primary); margin-bottom: 1rem;">
                Popular Fibre Packages
            </h2>
            <p style="font-size: 1.125rem; color: var(--starcast-gray-600); max-width: 600px; margin: 0 auto;">
                High-speed fibre packages to fit your needs
            </p>
        </div>

        <!-- Featured packages grid -->
        <div class="starcast-packages-grid">
            <?php
            // Get featured fibre packages
            $args = array(
                'post_type' => 'fibre-packages',
                'posts_per_page' => 6,
                'meta_query' => array(
                    array(
                        'key' => 'is_featured',
                        'value' => '1',
                        'compare' => '='
                    )
                ),
                'orderby' => 'meta_value_num',
                'meta_key' => 'price',
                'order' => 'ASC',
            );

            $featured_packages = new WP_Query($args);

            if ($featured_packages->have_posts()) :
                while ($featured_packages->have_posts()) : $featured_packages->the_post();
                    starcast_render_package_card(get_the_ID());
                endwhile;
                wp_reset_postdata();
            else :
                // Fallback - show any packages
                $args['posts_per_page'] = 6;
                unset($args['meta_query']);
                $packages = new WP_Query($args);

                if ($packages->have_posts()) :
                    while ($packages->have_posts()) : $packages->the_post();
                        starcast_render_package_card(get_the_ID());
                    endwhile;
                    wp_reset_postdata();
                endif;
            endif;
            ?>
        </div>

        <div style="text-align: center; margin-top: var(--starcast-spacing-xl);">
            <a href="<?php echo home_url('/fibre'); ?>" class="starcast-btn starcast-btn-outline">
                View All Fibre Packages →
            </a>
        </div>
    </div>
</section>

<!-- Services Section -->
<section style="padding: var(--starcast-spacing-3xl) 0;">
    <div style="max-width: 1366px; margin: 0 auto; padding: 0 1.5rem;">
        <div style="text-align: center; margin-bottom: var(--starcast-spacing-xl);">
            <h2 style="font-size: 2.5rem; color: var(--starcast-primary); margin-bottom: 1rem;">
                Our Services
            </h2>
            <p style="font-size: 1.125rem; color: var(--starcast-gray-600);">
                Comprehensive internet and technical services for home and business
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
            <!-- Fibre Internet -->
            <div style="background: var(--starcast-white); padding: 2rem; border-radius: var(--starcast-radius-xl); box-shadow: var(--starcast-shadow-md); text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🚀</div>
                <h3 style="color: var(--starcast-primary); margin-bottom: 1rem;">Fibre Internet</h3>
                <p style="color: var(--starcast-gray-600); margin-bottom: 1.5rem;">
                    Lightning-fast, reliable connectivity with uncapped packages available nationwide through our trusted partner network.
                </p>
                <a href="<?php echo home_url('/fibre'); ?>" class="starcast-btn starcast-btn-primary">Explore Packages</a>
            </div>

            <!-- LTE Solutions -->
            <div style="background: var(--starcast-white); padding: 2rem; border-radius: var(--starcast-radius-xl); box-shadow: var(--starcast-shadow-md); text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">📡</div>
                <h3 style="color: var(--starcast-primary); margin-bottom: 1rem;">LTE Solutions</h3>
                <p style="color: var(--starcast-gray-600); margin-bottom: 1.5rem;">
                    Stay connected anywhere with our flexible LTE packages. Perfect for areas without fibre or as a backup solution.
                </p>
                <a href="<?php echo home_url('/lte-5g'); ?>" class="starcast-btn starcast-btn-primary">View LTE Options</a>
            </div>

            <!-- Technical Services -->
            <div style="background: var(--starcast-white); padding: 2rem; border-radius: var(--starcast-radius-xl); box-shadow: var(--starcast-shadow-md); text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem;">🔧</div>
                <h3 style="color: var(--starcast-primary); margin-bottom: 1rem;">Technical Services</h3>
                <p style="color: var(--starcast-gray-600); margin-bottom: 1.5rem;">
                    Professional installation and support for WiFi, CCTV, routers, and access points. Expert technicians ready to help.
                </p>
                <a href="<?php echo home_url('/booking'); ?>" class="starcast-btn starcast-btn-primary">Book a Technician</a>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Starcast -->
<section style="background: var(--starcast-primary); color: var(--starcast-white); padding: var(--starcast-spacing-3xl) 0;">
    <div style="max-width: 1366px; margin: 0 auto; padding: 0 1.5rem;">
        <div style="text-align: center; margin-bottom: var(--starcast-spacing-xl);">
            <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">
                Why Choose Starcast?
            </h2>
            <p style="font-size: 1.125rem; opacity: 0.9;">
                Honest service with real value
            </p>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; text-align: center;">
            <div>
                <div style="font-size: 3rem; font-weight: 800; color: var(--starcast-accent); margin-bottom: 0.5rem;">7</div>
                <div style="font-size: 1.125rem; opacity: 0.9;">Days Installation</div>
            </div>
            <div>
                <div style="font-size: 3rem; font-weight: 800; color: var(--starcast-accent); margin-bottom: 0.5rem;">24/7</div>
                <div style="font-size: 1.125rem; opacity: 0.9;">Expert Support</div>
            </div>
            <div>
                <div style="font-size: 3rem; font-weight: 800; color: var(--starcast-accent); margin-bottom: 0.5rem;">100%</div>
                <div style="font-size: 1.125rem; opacity: 0.9;">Local Service</div>
            </div>
            <div>
                <div style="font-size: 3rem; font-weight: 800; color: var(--starcast-accent); margin-bottom: 0.5rem;">0</div>
                <div style="font-size: 1.125rem; opacity: 0.9;">Contracts Required</div>
            </div>
        </div>

        <div style="text-align: center; margin-top: var(--starcast-spacing-xl);">
            <a href="<?php echo home_url('/about'); ?>" class="starcast-btn starcast-btn-secondary">
                Learn More About Us
            </a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
