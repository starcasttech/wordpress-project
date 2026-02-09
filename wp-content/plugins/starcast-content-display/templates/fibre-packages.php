<?php
/**
 * Template for displaying fibre packages
 * Variables available: $provider_data, $atts
 */
if (!defined('ABSPATH')) exit;
?>

<div class="starcast-fibre-packages" data-columns="<?php echo esc_attr($atts['columns']); ?>">

    <?php if ($atts['show_filters'] === 'yes'): ?>
    <div class="package-filters">
        <div class="filter-group">
            <label>Filter by Provider:</label>
            <select id="provider-filter" class="package-filter">
                <option value="">All Providers</option>
                <?php foreach ($provider_data as $provider): ?>
                    <option value="<?php echo esc_attr($provider['slug']); ?>"><?php echo esc_html($provider['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label>Filter by Speed:</label>
            <select id="speed-filter" class="package-filter">
                <option value="">All Speeds</option>
                <option value="0-50">Up to 50 Mbps</option>
                <option value="50-100">50-100 Mbps</option>
                <option value="100-200">100-200 Mbps</option>
                <option value="200-500">200-500 Mbps</option>
                <option value="500-999999">500 Mbps+</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Sort by:</label>
            <select id="sort-filter" class="package-filter">
                <option value="price-asc">Price: Low to High</option>
                <option value="price-desc">Price: High to Low</option>
                <option value="speed-asc">Speed: Low to High</option>
                <option value="speed-desc">Speed: High to Low</option>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <div class="packages-container">
        <?php foreach ($provider_data as $provider): ?>
            <div class="provider-section" data-provider="<?php echo esc_attr($provider['slug']); ?>">
                <div class="provider-header">
                    <?php if ($provider['logo']): ?>
                        <img src="<?php echo esc_url($provider['logo']); ?>" alt="<?php echo esc_attr($provider['name']); ?>" class="provider-logo">
                    <?php else: ?>
                        <h2 class="provider-name"><?php echo esc_html($provider['name']); ?></h2>
                    <?php endif; ?>
                </div>

                <div class="packages-grid" style="grid-template-columns: repeat(<?php echo esc_attr($atts['columns']); ?>, 1fr);">
                    <?php foreach ($provider['packages'] as $package): ?>
                        <div class="package-card"
                             data-provider="<?php echo esc_attr($provider['slug']); ?>"
                             data-speed="<?php echo esc_attr($package['download_speed']); ?>"
                             data-price="<?php echo esc_attr($package['price']); ?>">

                            <div class="package-header">
                                <h3 class="package-title"><?php echo esc_html($package['title']); ?></h3>
                            </div>

                            <div class="package-speed">
                                <div class="speed-item">
                                    <span class="speed-label">Download</span>
                                    <span class="speed-value"><?php echo esc_html($package['download']); ?></span>
                                </div>
                                <div class="speed-item">
                                    <span class="speed-label">Upload</span>
                                    <span class="speed-value"><?php echo esc_html($package['upload']); ?></span>
                                </div>
                            </div>

                            <div class="package-price">
                                <span class="currency">R</span>
                                <span class="amount"><?php echo esc_html(number_format($package['price'], 0)); ?></span>
                                <span class="period">/month</span>
                            </div>

                            <div class="package-actions">
                                <a href="<?php echo esc_url(home_url('/signup/?package_id=' . $package['id'])); ?>"
                                   class="btn btn-primary">
                                    Select Package
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($provider_data)): ?>
        <div class="no-packages">
            <p>No fibre packages available at this time.</p>
        </div>
    <?php endif; ?>
</div>
