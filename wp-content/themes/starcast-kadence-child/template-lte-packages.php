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
            Home Internet
            <span class="starcast-hero-accent">LTE & 5G Solutions</span>
        </h1>
        <p>Flexible home internet powered by MTN, Vodacom, and Telkom networks. No fibre required!</p>
    </div>
</div>

<!-- Main Content -->
<div style="max-width: 1366px; margin: 0 auto; padding: var(--starcast-spacing-xl) 1.5rem;">

    <!-- LTE Info Banner -->
    <div style="background: linear-gradient(135deg, #3B82F6 0%, #8B5CF6 100%); color: white; padding: 2rem; border-radius: var(--starcast-radius-xl); margin-bottom: var(--starcast-spacing-xl); text-align: center;">
        <h3 style="margin-bottom: 1rem;">📡 Wireless Home Internet</h3>
        <p style="opacity: 0.95; max-width: 700px; margin: 0 auto;">
            Stay connected without fibre infrastructure. All uncapped packages include an After Usage Policy (AUP) — your connection continues at a reduced speed after your monthly threshold is reached.
        </p>
    </div>

    <!-- Filters -->
    <div class="starcast-filters starcast-animate-fade-in-up">
        <div class="starcast-filter-row">
            <div class="starcast-filter-group">
                <label for="starcast-filter-provider">
                    <strong>Provider</strong>
                </label>
                <select id="starcast-filter-provider" class="starcast-filter-select">
                    <option value="">All Providers</option>
                    <option value="mtn">MTN</option>
                    <option value="vodacom">Vodacom</option>
                    <option value="telkom">Telkom</option>
                </select>
            </div>

            <div class="starcast-filter-group">
                <label for="starcast-filter-price-lte">
                    <strong>Price Range</strong>
                </label>
                <select id="starcast-filter-price-lte" class="starcast-filter-select">
                    <option value="">All Prices</option>
                    <option value="0-400">Under R400</option>
                    <option value="400-600">R400 - R600</option>
                    <option value="600-800">R600 - R800</option>
                    <option value="800+">Over R800</option>
                </select>
            </div>

            <div class="starcast-filter-group">
                <label for="starcast-filter-sort-lte">
                    <strong>Sort by</strong>
                </label>
                <select id="starcast-filter-sort-lte" class="starcast-filter-select">
                    <option value="price-asc">Price: Low to High</option>
                    <option value="price-desc">Price: High to Low</option>
                    <option value="provider">By Provider</option>
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
            'post_type'      => 'lte_packages',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => 'price',
            'order'          => 'ASC',
        );

        $packages = new WP_Query($args);

        if ($packages->have_posts()) :
            while ($packages->have_posts()) : $packages->the_post();

                // Get package meta
                $data_gb         = get_post_meta(get_the_ID(), 'data_gb', true);
                $price           = get_post_meta(get_the_ID(), 'price', true);
                $is_uncapped     = get_post_meta(get_the_ID(), 'uncapped', true);
                $speed           = get_post_meta(get_the_ID(), 'speed', true);
                $contract_months = get_post_meta(get_the_ID(), 'contract_months', true);
                $aup_info        = get_post_meta(get_the_ID(), 'aup_info', true);

                // Get provider taxonomy term
                $provider_terms = get_the_terms(get_the_ID(), 'lte_provider');
                $provider_slug  = (!is_wp_error($provider_terms) && !empty($provider_terms)) ? $provider_terms[0]->slug : '';
                $provider_name  = (!is_wp_error($provider_terms) && !empty($provider_terms)) ? $provider_terms[0]->name : '';

                // Provider-specific header gradient
                $provider_gradients = array(
                    'mtn'     => 'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)',
                    'vodacom' => 'linear-gradient(135deg, #EF4444 0%, #DC2626 100%)',
                    'telkom'  => 'linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%)',
                );
                $header_gradient = isset($provider_gradients[$provider_slug])
                    ? $provider_gradients[$provider_slug]
                    : 'linear-gradient(135deg, #8B5CF6 0%, #6366F1 100%)';

                // Main stat shown prominently in card header
                if ($is_uncapped && $speed) {
                    $main_stat = $speed . ' Mbps';
                } elseif ($is_uncapped) {
                    $main_stat = 'Uncapped';
                } else {
                    // Capped — show data amount (2000 GB = 2 TB)
                    $main_stat = ($data_gb >= 1000) ? ($data_gb / 1000) . ' TB' : $data_gb . ' GB';
                }

                // Badge based on package tier
                $title_lower = strtolower(get_the_title());
                $badge       = '';
                $badge_class = '';
                if (strpos($title_lower, 'starter') !== false) {
                    $badge       = 'BEST VALUE';
                    $badge_class = 'starcast-badge-hot';
                } elseif (strpos($title_lower, 'plus') !== false) {
                    $badge       = 'POPULAR';
                    $badge_class = 'starcast-badge-recommended';
                } elseif (strpos($title_lower, '30 mbps') !== false) {
                    $badge       = 'NEW';
                    $badge_class = 'starcast-badge-deal';
                }
                ?>

                <div class="starcast-package-card starcast-animate-fade-in-up"
                     data-provider="<?php echo esc_attr($provider_slug); ?>"
                     data-price="<?php echo esc_attr($price); ?>">

                    <?php if ($badge) : ?>
                        <div class="starcast-package-badge <?php echo esc_attr($badge_class); ?>">
                            <?php echo esc_html($badge); ?>
                        </div>
                    <?php endif; ?>

                    <div class="starcast-package-header" style="background: <?php echo $header_gradient; ?>;">
                        <div class="starcast-package-speed">
                            <?php echo esc_html($main_stat); ?>
                        </div>
                        <div class="starcast-package-name">
                            <?php echo esc_html(get_the_title()); ?>
                        </div>
                        <?php if ($provider_name) : ?>
                            <div class="starcast-package-provider" style="font-size: 0.875rem; opacity: 0.85; margin-top: 0.5rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;">
                                <?php echo esc_html($provider_name); ?> Network
                            </div>
                        <?php endif; ?>
                        <div class="starcast-package-price">
                            R<?php echo number_format($price, 2); ?> <small>/month</small>
                        </div>
                    </div>

                    <div class="starcast-package-body">
                        <ul class="starcast-package-features">
                            <?php if ($is_uncapped) : ?>
                                <li>Uncapped Data</li>
                            <?php else : ?>
                                <li><?php echo esc_html($main_stat); ?> Monthly Data</li>
                            <?php endif; ?>
                            <?php if ($aup_info) : ?>
                                <li style="font-size: 0.875em; color: var(--starcast-gray-600);">
                                    AUP: <?php echo esc_html($aup_info); ?>
                                </li>
                            <?php endif; ?>
                            <?php if ($speed && !$is_uncapped) : ?>
                                <li>Up to <?php echo esc_html($speed); ?> Mbps</li>
                            <?php elseif ($speed && $is_uncapped) : ?>
                                <li>Fixed <?php echo esc_html($speed); ?> Mbps Line Speed</li>
                            <?php endif; ?>
                            <?php if ($contract_months == 0 || !$contract_months || $contract_months == 1) : ?>
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
        <h3 style="text-align: center; color: var(--starcast-primary); margin-bottom: 2rem;">Why Choose Home Internet?</h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem;">
            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🚀</div>
                <h4 style="margin-bottom: 0.5rem;">Quick Setup</h4>
                <p style="color: var(--starcast-gray-600);">Get connected in minutes. Plug in your router and you're online immediately.</p>
            </div>

            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">🌍</div>
                <h4 style="margin-bottom: 0.5rem;">Nationwide Coverage</h4>
                <p style="color: var(--starcast-gray-600);">Available anywhere with MTN, Vodacom, or Telkom signal. Perfect for areas without fibre.</p>
            </div>

            <div style="text-align: center;">
                <div style="font-size: 2.5rem; margin-bottom: 1rem;">📶</div>
                <h4 style="margin-bottom: 0.5rem;">Always On</h4>
                <p style="color: var(--starcast-gray-600);">All uncapped plans stay connected after your AUP threshold — just at a reduced speed.</p>
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
    const totalCount = $('.starcast-package-card').length;
    $('.starcast-results-count-lte').text('Showing ' + totalCount + ' of ' + totalCount + ' packages');

    function applyLTEFilters() {
        const provider   = $('#starcast-filter-provider').val();
        const priceRange = $('#starcast-filter-price-lte').val();

        $('.starcast-package-card').each(function() {
            const $card = $(this);
            let show = true;

            // Provider filter
            if (provider !== '' && $card.data('provider') !== provider) {
                show = false;
            }

            // Price filter
            if (priceRange !== '') {
                const price = parseFloat($card.data('price'));
                const parts = priceRange.split('-');
                const pMin  = parseInt(parts[0]);
                const pMax  = (parts[1] && parts[1] !== '+') ? parseInt(parts[1]) : Infinity;

                if (price < pMin || price > pMax) {
                    show = false;
                }
            }

            if (show) {
                $card.fadeIn(300);
            } else {
                $card.fadeOut(300);
            }
        });

        const visibleCount = $('.starcast-package-card:visible').length;
        $('.starcast-results-count-lte').text('Showing ' + visibleCount + ' of ' + totalCount + ' packages');
    }

    $('#starcast-filter-provider, #starcast-filter-price-lte').on('change', applyLTEFilters);

    // Sort functionality
    $('#starcast-filter-sort-lte').on('change', function() {
        const sortBy     = $(this).val();
        const $cards     = $('.starcast-package-card');
        const $container = $('#starcast-lte-packages-container');

        const sorted = $cards.sort(function(a, b) {
            const $a = $(a);
            const $b = $(b);

            switch (sortBy) {
                case 'price-asc':
                    return parseFloat($a.data('price')) - parseFloat($b.data('price'));
                case 'price-desc':
                    return parseFloat($b.data('price')) - parseFloat($a.data('price'));
                case 'provider':
                    const pa = $a.data('provider') || '';
                    const pb = $b.data('provider') || '';
                    if (pa < pb) return -1;
                    if (pa > pb) return 1;
                    return parseFloat($a.data('price')) - parseFloat($b.data('price'));
                default:
                    return 0;
            }
        });

        $container.html(sorted);
    });
});
</script>

<?php get_footer(); ?>
