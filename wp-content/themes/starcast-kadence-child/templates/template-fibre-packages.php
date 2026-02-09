<?php
/**
 * Template Name: Starcast Pro - Fibre Packages
 * Description: Advanced fibre packages listing with filtering and sorting
 *
 * @package Starcast_Pro
 */

get_header();
?>

<!-- Page Hero -->
<div class="starcast-hero" style="min-height: 400px;">
    <div class="starcast-hero-content">
        <h1>
            Ultra-Fast Fibre Internet
            <span class="starcast-hero-accent">Connect at Lightning Speed</span>
        </h1>
        <p>Choose from 127+ fibre packages nationwide. Uncapped, reliable, and blazing fast.</p>
    </div>
</div>

<!-- Main Content -->
<div style="max-width: 1366px; margin: 0 auto; padding: var(--starcast-spacing-xl) 1.5rem;">

    <!-- Coverage Search -->
    <div class="starcast-coverage-card" style="margin-bottom: var(--starcast-spacing-xl);">
        <h3 style="margin-bottom: 1rem; color: var(--starcast-primary);">Find Packages in Your Area</h3>
        <div class="starcast-search-box">
            <input type="text"
                   id="starcast-address-input"
                   class="starcast-search-input"
                   placeholder="Enter your address to see available packages...">
            <button class="starcast-btn starcast-btn-primary" onclick="starcastCheckCoverage()">
                Search
            </button>
        </div>
        <div id="starcast-coverage-results" style="margin-top: 1.5rem;"></div>
    </div>

    <!-- Promo Banner -->
    <div class="starcast-promo-banner starcast-animate-fade-in-up">
        🔥 <strong>HOT DEAL:</strong> Get up to 25% OFF on selected fibre packages! Limited time offer.
    </div>

    <!-- Advanced Filters -->
    <div class="starcast-filters starcast-animate-fade-in-up">
        <div class="starcast-filter-row">
            <div class="starcast-filter-group">
                <label for="starcast-filter-provider">
                    <strong>Filter by Provider</strong>
                </label>
                <select id="starcast-filter-provider" class="starcast-filter-select">
                    <option value="">All Providers</option>
                    <?php
                    $providers = get_terms(array(
                        'taxonomy' => 'fibre_provider',
                        'hide_empty' => true,
                    ));
                    if (!is_wp_error($providers) && !empty($providers)) {
                        foreach ($providers as $provider) {
                            echo '<option value="' . esc_attr(strtolower($provider->name)) . '">' . esc_html($provider->name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>

            <div class="starcast-filter-group">
                <label for="starcast-filter-speed">
                    <strong>Filter by Speed</strong>
                </label>
                <select id="starcast-filter-speed" class="starcast-filter-select">
                    <option value="">All Speeds</option>
                    <option value="0-50">Up to 50 Mbps</option>
                    <option value="50-100">50 - 100 Mbps</option>
                    <option value="100-200">100 - 200 Mbps</option>
                    <option value="200-500">200 - 500 Mbps</option>
                    <option value="500-999">500 - 1000 Mbps</option>
                    <option value="1000+">1000+ Mbps (1 Gbps+)</option>
                </select>
            </div>

            <div class="starcast-filter-group">
                <label for="starcast-filter-price">
                    <strong>Filter by Price</strong>
                </label>
                <select id="starcast-filter-price" class="starcast-filter-select">
                    <option value="">All Prices</option>
                    <option value="0-500">Under R500</option>
                    <option value="500-1000">R500 - R1,000</option>
                    <option value="1000-1500">R1,000 - R1,500</option>
                    <option value="1500-2000">R1,500 - R2,000</option>
                    <option value="2000+">Over R2,000</option>
                </select>
            </div>

            <div class="starcast-filter-group">
                <label for="starcast-filter-sort">
                    <strong>Sort by</strong>
                </label>
                <select id="starcast-filter-sort" class="starcast-filter-select">
                    <option value="price-asc">Price: Low to High</option>
                    <option value="price-desc">Price: High to Low</option>
                    <option value="speed-desc">Speed: High to Low</option>
                    <option value="speed-asc">Speed: Low to High</option>
                    <option value="popular">Most Popular</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div class="starcast-results-count" style="padding: 1rem; text-align: center; color: var(--starcast-gray-600); font-weight: 600;"></div>

    <!-- Packages Grid -->
    <div class="starcast-packages-grid" id="starcast-packages-container">
        <?php
        $args = array(
            'post_type' => 'fibre_packages',
            'posts_per_page' => -1,
            'orderby' => 'meta_value_num',
            'meta_key' => 'price',
            'order' => 'ASC',
        );

        $packages = new WP_Query($args);

        if ($packages->have_posts()) :
            $count = 0;
            while ($packages->have_posts()) : $packages->the_post();
                $count++;

                // Get package meta
                $speed = get_post_meta(get_the_ID(), 'download_speed', true);
                $upload_speed = get_post_meta(get_the_ID(), 'upload_speed', true);
                $price = get_post_meta(get_the_ID(), 'price', true);
                $is_uncapped = get_post_meta(get_the_ID(), 'uncapped', true);
                $provider_terms = get_the_terms(get_the_ID(), 'fibre_provider');
                $provider = $provider_terms && !is_wp_error($provider_terms) ? $provider_terms[0]->name : 'Starcast';

                // Determine badge based on conditions
                $badge = '';
                $badge_class = '';

                // Check for special deals based on various criteria
                if ($count % 5 == 0) { // Every 5th package
                    $badge = 'HOT DEAL';
                    $badge_class = 'starcast-badge-hot';
                } elseif ($count % 3 == 0) { // Every 3rd package
                    $badge = 'RECOMMENDED';
                    $badge_class = 'starcast-badge-recommended';
                } elseif ($speed >= 1000) { // Ultra-fast packages
                    $badge = 'FASTEST';
                    $badge_class = 'starcast-badge-deal';
                } elseif ($price < 500) { // Budget packages
                    $badge = 'BEST VALUE';
                    $badge_class = 'starcast-badge-deal';
                }
                ?>

                <div class="starcast-package-card starcast-animate-fade-in-up"
                     data-speed="<?php echo esc_attr($speed); ?>"
                     data-price="<?php echo esc_attr($price); ?>"
                     data-provider="<?php echo esc_attr(strtolower($provider)); ?>">

                    <?php if ($badge) : ?>
                        <div class="starcast-package-badge <?php echo esc_attr($badge_class); ?>">
                            <?php echo esc_html($badge); ?>
                        </div>
                    <?php endif; ?>

                    <div class="starcast-package-header">
                        <div class="starcast-package-speed">
                            <?php echo esc_html($speed); ?><?php echo $upload_speed ? '/' . esc_html($upload_speed) : ''; ?> Mbps
                        </div>
                        <div class="starcast-package-name">
                            <?php echo esc_html(get_the_title()); ?>
                        </div>
                        <div class="starcast-package-provider" style="font-size: 0.875rem; opacity: 0.8; margin-top: 0.5rem;">
                            via <?php echo esc_html($provider); ?>
                        </div>
                        <div class="starcast-package-price">
                            R<?php echo number_format($price, 2); ?> <small>/month</small>
                        </div>
                    </div>

                    <div class="starcast-package-body">
                        <ul class="starcast-package-features">
                            <?php if ($is_uncapped) : ?>
                                <li>Unlimited Uncapped Data</li>
                            <?php endif; ?>
                            <li>No Fair Usage Policy</li>
                            <li>24/7 Customer Support</li>
                            <li>Fast Installation (7 Days)</li>
                            <li>Month-to-Month Contract</li>
                            <li>Local Support Team</li>
                        </ul>

                        <a href="<?php echo home_url('/signup?package_id=' . get_the_ID()); ?>"
                           class="starcast-btn starcast-btn-primary"
                           style="width: 100%; text-align: center;">
                            Get This Package →
                        </a>
                    </div>
                </div>

            <?php
            endwhile;
            wp_reset_postdata();
        else :
            ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 2rem;">
                <h3 style="color: var(--starcast-gray-600); margin-bottom: 1rem;">No Packages Found</h3>
                <p style="color: var(--starcast-gray-500);">Please adjust your filters or check back later.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Trust Indicators -->
    <div class="starcast-trust-bar" style="margin-top: var(--starcast-spacing-2xl); border-radius: var(--starcast-radius-lg);">
        <div class="starcast-trust-items">
            <div class="starcast-trust-item">
                <span class="starcast-trust-icon">✓</span>
                <span>7 Day Installation Guarantee</span>
            </div>
            <div class="starcast-trust-item">
                <span class="starcast-trust-icon">✓</span>
                <span>24/7 Technical Support</span>
            </div>
            <div class="starcast-trust-item">
                <span class="starcast-trust-icon">✓</span>
                <span>100% Local Service</span>
            </div>
            <div class="starcast-trust-item">
                <span class="starcast-trust-icon">✓</span>
                <span>No Long-Term Contracts</span>
            </div>
        </div>
    </div>

</div>

<!-- Enhanced JavaScript for Price Filtering -->
<script>
jQuery(document).ready(function($) {
    // Add price filter functionality
    $('#starcast-filter-price').on('change', function() {
        const priceRange = $(this).val();

        $('.starcast-package-card').each(function() {
            const $card = $(this);
            const price = parseFloat($card.data('price'));
            let show = true;

            if (priceRange !== '') {
                const [min, max] = priceRange.split('-').map(v => v === '+' ? Infinity : parseInt(v));

                if (max) {
                    if (price < min || price > max) {
                        show = false;
                    }
                } else {
                    if (price < min) {
                        show = false;
                    }
                }
            }

            // Check other active filters
            const provider = $('#starcast-filter-provider').val().toLowerCase();
            if (provider !== '' && $card.data('provider') !== provider) {
                show = false;
            }

            const speedRange = $('#starcast-filter-speed').val();
            if (speedRange !== '') {
                const speed = parseInt($card.data('speed'));
                const [speedMin, speedMax] = speedRange.split('-').map(v => v === '+' ? Infinity : parseInt(v));

                if (speedMax) {
                    if (speed < speedMin || speed > speedMax) {
                        show = false;
                    }
                } else {
                    if (speed < speedMin) {
                        show = false;
                    }
                }
            }

            if (show) {
                $card.fadeIn(300);
            } else {
                $card.fadeOut(300);
            }
        });

        // Update results count
        const visibleCount = $('.starcast-package-card:visible').length;
        const totalCount = $('.starcast-package-card').length;
        $('.starcast-results-count').text(`Showing ${visibleCount} of ${totalCount} packages`);
    });

    // Trigger initial count
    const totalCount = $('.starcast-package-card').length;
    $('.starcast-results-count').text(`Showing ${totalCount} of ${totalCount} packages`);
});
</script>

<?php get_footer(); ?>
