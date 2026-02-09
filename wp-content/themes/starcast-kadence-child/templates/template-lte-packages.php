<?php
/**
 * Template Name: Starcast Pro - LTE Packages
 * Description: LTE/5G packages listing with filtering
 *
 * @package Starcast_Pro
 */

get_header();
?>

<!-- Page Hero -->
<div class="starcast-hero" style="min-height: 400px; background: linear-gradient(135deg, #8B5CF6 0%, #6366F1 50%, #3B82F6 100%);">
    <div class="starcast-hero-content">
        <h1>
            LTE & 5G Internet Solutions
            <span class="starcast-hero-accent">Connect Anywhere, Anytime</span>
        </h1>
        <p>Wireless internet solutions perfect for homes and businesses. No fibre required!</p>
    </div>
</div>

<!-- Main Content -->
<div style="max-width: 1366px; margin: 0 auto; padding: var(--starcast-spacing-xl) 1.5rem;">

    <!-- LTE Info Banner -->
    <div style="background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%); color: white; padding: 2rem; border-radius: var(--starcast-radius-xl); margin-bottom: var(--starcast-spacing-xl); text-align: center;">
        <h3 style="margin-bottom: 1rem;">📡 Wireless Freedom</h3>
        <p style="opacity: 0.95; max-width: 700px; margin: 0 auto;">
            Get connected in areas without fibre coverage or use as a reliable backup. Quick activation, portable routers, and flexible data options.
        </p>
    </div>

    <!-- Filters -->
    <div class="starcast-filters starcast-animate-fade-in-up">
        <div class="starcast-filter-row">
            <div class="starcast-filter-group">
                <label for="starcast-filter-data">
                    <strong>Data Amount</strong>
                </label>
                <select id="starcast-filter-data" class="starcast-filter-select">
                    <option value="">All Packages</option>
                    <option value="0-50">Up to 50 GB</option>
                    <option value="50-100">50 - 100 GB</option>
                    <option value="100-200">100 - 200 GB</option>
                    <option value="200-500">200 - 500 GB</option>
                    <option value="500+">500+ GB / Uncapped</option>
                </select>
            </div>

            <div class="starcast-filter-group">
                <label for="starcast-filter-price-lte">
                    <strong>Price Range</strong>
                </label>
                <select id="starcast-filter-price-lte" class="starcast-filter-select">
                    <option value="">All Prices</option>
                    <option value="0-300">Under R300</option>
                    <option value="300-600">R300 - R600</option>
                    <option value="600-1000">R600 - R1,000</option>
                    <option value="1000-1500">R1,000 - R1,500</option>
                    <option value="1500+">Over R1,500</option>
                </select>
            </div>

            <div class="starcast-filter-group">
                <label for="starcast-filter-type">
                    <strong>Connection Type</strong>
                </label>
                <select id="starcast-filter-type" class="starcast-filter-select">
                    <option value="">All Types</option>
                    <option value="4g">4G LTE</option>
                    <option value="5g">5G</option>
                    <option value="fixed">Fixed LTE</option>
                </select>
            </div>

            <div class="starcast-filter-group">
                <label for="starcast-filter-sort-lte">
                    <strong>Sort by</strong>
                </label>
                <select id="starcast-filter-sort-lte" class="starcast-filter-select">
                    <option value="price-asc">Price: Low to High</option>
                    <option value="price-desc">Price: High to Low</option>
                    <option value="data-desc">Data: High to Low</option>
                    <option value="popular">Most Popular</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Results Count -->
    <div class="starcast-results-count-lte" style="padding: 1rem; text-align: center; color: var(--starcast-gray-600); font-weight: 600;"></div>

    <!-- Packages Grid -->
    <div class="starcast-packages-grid" id="starcast-lte-packages-container">
        <?php
        $args = array(
            'post_type' => 'lte_packages',
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
                $data_gb = get_post_meta(get_the_ID(), 'data_gb', true);
                $price = get_post_meta(get_the_ID(), 'price', true);
                $is_uncapped = get_post_meta(get_the_ID(), 'uncapped', true);
                $speed = get_post_meta(get_the_ID(), 'speed', true);
                $contract_months = get_post_meta(get_the_ID(), 'contract_months', true);

                // Determine badge
                $badge = '';
                $badge_class = '';

                if ($count % 4 == 0) {
                    $badge = 'POPULAR';
                    $badge_class = 'starcast-badge-recommended';
                } elseif ($data_gb >= 500 || $is_uncapped) {
                    $badge = 'UNLIMITED';
                    $badge_class = 'starcast-badge-deal';
                } elseif ($price < 400) {
                    $badge = 'BEST VALUE';
                    $badge_class = 'starcast-badge-hot';
                }
                ?>

                <div class="starcast-package-card starcast-animate-fade-in-up"
                     data-data="<?php echo esc_attr($data_gb); ?>"
                     data-price="<?php echo esc_attr($price); ?>"
                     data-type="4g">

                    <?php if ($badge) : ?>
                        <div class="starcast-package-badge <?php echo esc_attr($badge_class); ?>">
                            <?php echo esc_html($badge); ?>
                        </div>
                    <?php endif; ?>

                    <div class="starcast-package-header" style="background: linear-gradient(135deg, #8B5CF6 0%, #6366F1 100%);">
                        <div class="starcast-package-speed">
                            <?php if ($is_uncapped) : ?>
                                Uncapped
                            <?php else : ?>
                                <?php echo esc_html($data_gb); ?> GB
                            <?php endif; ?>
                        </div>
                        <div class="starcast-package-name">
                            <?php echo esc_html(get_the_title()); ?>
                        </div>
                        <?php if ($speed) : ?>
                            <div class="starcast-package-provider" style="font-size: 0.875rem; opacity: 0.8; margin-top: 0.5rem;">
                                Up to <?php echo esc_html($speed); ?> Mbps
                            </div>
                        <?php endif; ?>
                        <div class="starcast-package-price">
                            R<?php echo number_format($price, 2); ?> <small>/month</small>
                        </div>
                    </div>

                    <div class="starcast-package-body">
                        <ul class="starcast-package-features">
                            <?php if ($is_uncapped) : ?>
                                <li>Unlimited Data</li>
                            <?php else : ?>
                                <li><?php echo esc_html($data_gb); ?>GB Monthly Data</li>
                            <?php endif; ?>
                            <li>4G/LTE Connection</li>
                            <li>Quick Activation</li>
                            <li>Portable Router Included</li>
                            <?php if ($contract_months == 0 || !$contract_months) : ?>
                                <li>Month-to-Month (No Contract)</li>
                            <?php else : ?>
                                <li><?php echo esc_html($contract_months); ?>-Month Contract</li>
                            <?php endif; ?>
                            <li>24/7 Support</li>
                        </ul>

                        <a href="<?php echo home_url('/lte-signup?package_id=' . get_the_ID()); ?>"
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
                <h3 style="color: var(--starcast-gray-600); margin-bottom: 1rem;">No LTE Packages Found</h3>
                <p style="color: var(--starcast-gray-500);">Please check back later or contact us for custom solutions.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- LTE Benefits -->
    <section style="margin-top: var(--starcast-spacing-3xl); background: var(--starcast-gray-50); padding: var(--starcast-spacing-xl); border-radius: var(--starcast-radius-xl);">
        <h3 style="text-align: center; color: var(--starcast-primary); margin-bottom: 2rem;">Why Choose LTE?</h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🚀</div>
                <h4 style="margin-bottom: 0.5rem;">Quick Setup</h4>
                <p style="color: var(--starcast-gray-600);">Get connected in minutes, not days. Plug and play simplicity.</p>
            </div>

            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🌍</div>
                <h4 style="margin-bottom: 0.5rem;">Nationwide Coverage</h4>
                <p style="color: var(--starcast-gray-600);">Available anywhere with cellular coverage. Perfect for remote areas.</p>
            </div>

            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">💼</div>
                <h4 style="margin-bottom: 0.5rem;">Portable</h4>
                <p style="color: var(--starcast-gray-600);">Take your internet anywhere. Perfect for mobile offices.</p>
            </div>

            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🔄</div>
                <h4 style="margin-bottom: 0.5rem;">Backup Solution</h4>
                <p style="color: var(--starcast-gray-600);">Ideal as a backup for your fibre connection for uninterrupted service.</p>
            </div>
        </div>
    </section>

</div>

<!-- Custom JavaScript for LTE Filtering -->
<script>
jQuery(document).ready(function($) {
    // Initial count
    const totalCount = $('.starcast-package-card').length;
    $('.starcast-results-count-lte').text(`Showing ${totalCount} of ${totalCount} packages`);

    // Combined filter function
    function applyLTEFilters() {
        const dataRange = $('#starcast-filter-data').val();
        const priceRange = $('#starcast-filter-price-lte').val();
        const type = $('#starcast-filter-type').val();

        $('.starcast-package-card').each(function() {
            const $card = $(this);
            let show = true;

            // Data filter
            if (dataRange !== '') {
                const data = parseFloat($card.data('data'));
                const [min, max] = dataRange.split('-').map(v => v === '+' ? Infinity : parseInt(v));

                if (max) {
                    if (data < min || data > max) {
                        show = false;
                    }
                } else {
                    if (data < min) {
                        show = false;
                    }
                }
            }

            // Price filter
            if (priceRange !== '') {
                const price = parseFloat($card.data('price'));
                const [pMin, pMax] = priceRange.split('-').map(v => v === '+' ? Infinity : parseInt(v));

                if (pMax) {
                    if (price < pMin || price > pMax) {
                        show = false;
                    }
                } else {
                    if (price < pMin) {
                        show = false;
                    }
                }
            }

            // Type filter (future implementation)
            if (type !== '' && $card.data('type') !== type) {
                // show = false;
            }

            if (show) {
                $card.fadeIn(300);
            } else {
                $card.fadeOut(300);
            }
        });

        // Update count
        const visibleCount = $('.starcast-package-card:visible').length;
        $('.starcast-results-count-lte').text(`Showing ${visibleCount} of ${totalCount} packages`);
    }

    // Attach filter events
    $('#starcast-filter-data, #starcast-filter-price-lte, #starcast-filter-type').on('change', applyLTEFilters);

    // Sort functionality
    $('#starcast-filter-sort-lte').on('change', function() {
        const sortBy = $(this).val();
        const $cards = $('.starcast-package-card');
        const $container = $('#starcast-lte-packages-container');

        const sorted = $cards.sort((a, b) => {
            const $a = $(a);
            const $b = $(b);

            switch(sortBy) {
                case 'price-asc':
                    return parseFloat($a.data('price')) - parseFloat($b.data('price'));
                case 'price-desc':
                    return parseFloat($b.data('price')) - parseFloat($a.data('price'));
                case 'data-desc':
                    return parseFloat($b.data('data')) - parseFloat($a.data('data'));
                default:
                    return 0;
            }
        });

        $container.html(sorted);
    });
});
</script>

<?php get_footer(); ?>
