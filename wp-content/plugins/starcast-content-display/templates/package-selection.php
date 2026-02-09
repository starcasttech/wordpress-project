<?php
/**
 * Template for package selection (used in LTE-5G page)
 * Variables available: $atts
 */
if (!defined('ABSPATH')) exit;

$type = $atts['type'] ?? 'lte';
?>

<div class="starcast-package-selection" data-type="<?php echo esc_attr($type); ?>">
    <div class="selection-header">
        <h2>Choose Your <?php echo esc_html(ucfirst($type)); ?> Package</h2>
        <p>Select the package that best fits your needs and get connected today.</p>
    </div>

    <?php
    // Display packages based on type
    if ($type === 'lte') {
        echo do_shortcode('[starcast_lte_packages]');
    } else {
        echo do_shortcode('[starcast_fibre_packages]');
    }
    ?>
</div>

<style>
.starcast-package-selection {
    padding: 40px 0;
}

.selection-header {
    text-align: center;
    margin-bottom: 48px;
}

.selection-header h2 {
    font-size: 36px;
    font-weight: 800;
    color: #2d2823;
    margin-bottom: 16px;
}

.selection-header p {
    font-size: 18px;
    color: #6b6355;
    max-width: 600px;
    margin: 0 auto;
}

@media (max-width: 768px) {
    .selection-header h2 {
        font-size: 28px;
    }

    .selection-header p {
        font-size: 16px;
    }
}
</style>
