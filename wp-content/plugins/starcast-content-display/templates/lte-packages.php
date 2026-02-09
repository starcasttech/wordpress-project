<?php
/**
 * Template for displaying LTE packages
 * Variables available: $package_data, $atts
 */
if (!defined('ABSPATH')) exit;
?>

<div class="starcast-lte-packages" data-columns="<?php echo esc_attr($atts['columns']); ?>">

    <?php if ($atts['show_filters'] === 'yes'): ?>
    <div class="package-filters">
        <div class="filter-group">
            <label>Sort by:</label>
            <select id="lte-sort-filter" class="package-filter">
                <option value="price-asc">Price: Low to High</option>
                <option value="price-desc">Price: High to Low</option>
                <option value="data-asc">Data: Low to High</option>
                <option value="data-desc">Data: High to Low</option>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <div class="packages-grid" style="grid-template-columns: repeat(<?php echo esc_attr($atts['columns']); ?>, 1fr);">
        <?php foreach ($package_data as $package): ?>
            <div class="package-card lte-package"
                 data-price="<?php echo esc_attr($package['price']); ?>"
                 data-data="<?php echo esc_attr(intval(preg_replace('/[^0-9]/', '', $package['data_limit']))); ?>">

                <div class="package-header">
                    <h3 class="package-title"><?php echo esc_html($package['title']); ?></h3>
                </div>

                <div class="package-details">
                    <div class="detail-item">
                        <span class="detail-label">Data Limit</span>
                        <span class="detail-value"><?php echo esc_html($package['data_limit']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Speed</span>
                        <span class="detail-value"><?php echo esc_html($package['speed']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Validity</span>
                        <span class="detail-value"><?php echo esc_html($package['validity']); ?></span>
                    </div>
                </div>

                <div class="package-price">
                    <span class="currency">R</span>
                    <span class="amount"><?php echo esc_html(number_format($package['price'], 0)); ?></span>
                </div>

                <div class="package-actions">
                    <a href="<?php echo esc_url(home_url('/lte-signup/?package_id=' . $package['id'])); ?>"
                       class="btn btn-primary">
                        Select Package
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if (empty($package_data)): ?>
        <div class="no-packages">
            <p>No LTE packages available at this time.</p>
        </div>
    <?php endif; ?>
</div>
