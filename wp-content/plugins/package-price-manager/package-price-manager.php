<?php
/**
 * Plugin Name: Package Price Manager
 * Description: Manage prices for LTE/5G and Fibre packages
 * Version: 2.1
 * Author: Starcast
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'ppm_add_admin_menu');
function ppm_add_admin_menu() {
    add_menu_page(
        'Package Price Manager',
        'Price Manager',
        'manage_options',
        'package-price-manager',
        'ppm_admin_page',
        'dashicons-tag',
        30
    );
}

// Admin page HTML
function ppm_admin_page() {
    ?>
    <div class="wrap ppm-admin-wrap">
        <h1 class="ppm-main-title">
            <span class="dashicons dashicons-tag"></span>
            Package Price Manager
        </h1>
        
        <div class="ppm-tabs">
            <button class="ppm-tab active" data-tab="lte">LTE/5G Packages</button>
            <button class="ppm-tab" data-tab="fibre">Fibre Packages</button>
            <button class="ppm-tab" data-tab="promo">Promo Manager</button>
            <button class="ppm-tab" data-tab="bulk">Bulk Upload</button>
        </div>
        
        <div class="ppm-content">
            <!-- LTE/5G Tab -->
            <div id="lte-tab" class="ppm-tab-content active">
                <div class="ppm-header">
                    <h2>LTE/5G Package Prices</h2>
                    <button class="ppm-btn ppm-btn-primary" id="save-lte-prices">
                        <span class="dashicons dashicons-saved"></span> Save All Changes
                    </button>
                </div>
                
                <div class="ppm-filters">
                    <select id="lte-provider-filter" class="ppm-filter-select">
                        <option value="all">All Providers</option>
                        <option value="mtn">MTN</option>
                        <option value="vodacom">Vodacom</option>
                        <option value="telkom">Telkom</option>
                        <option value="topup">TopUp</option>
                    </select>
                    
                    <input type="text" id="lte-search" class="ppm-search" placeholder="Search packages...">
                </div>
                
                <div id="lte-packages-container" class="ppm-packages-grid">
                    <!-- LTE packages will be loaded here -->
                </div>
            </div>
            
            <!-- Fibre Tab -->
            <div id="fibre-tab" class="ppm-tab-content">
                <div class="ppm-header">
                    <h2>Fibre Package Prices</h2>
                    <button class="ppm-btn ppm-btn-primary" id="save-fibre-prices">
                        <span class="dashicons dashicons-saved"></span> Save All Changes
                    </button>
                </div>
                
                <div class="ppm-filters">
                    <select id="fibre-provider-filter" class="ppm-filter-select">
                        <option value="all">All Providers</option>
                        <?php
                        $providers = get_terms(['taxonomy' => 'fibre_provider', 'hide_empty' => false]);
                        if (!is_wp_error($providers)) {
                            foreach ($providers as $provider) {
                                echo '<option value="' . esc_attr($provider->slug) . '">' . esc_html($provider->name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    
                    <input type="text" id="fibre-search" class="ppm-search" placeholder="Search packages...">
                </div>
                
                <div id="fibre-packages-container" class="ppm-packages-grid">
                    <!-- Fibre packages will be loaded here -->
                </div>
            </div>
            
            <!-- Promo Tab -->
            <div id="promo-tab" class="ppm-tab-content">
                <div class="ppm-header">
                    <h2>Promotional Pricing Manager</h2>
                    <button class="ppm-btn ppm-btn-primary" id="save-promo-settings">
                        <span class="dashicons dashicons-saved"></span> Save All Settings
                    </button>
                </div>
                
                <!-- Global Promo Settings -->
                <div class="ppm-promo-section">
                    <h3 class="ppm-section-title">Global Promo Settings</h3>
                    <div class="ppm-promo-global">
                        <div class="ppm-promo-row">
                            <div class="ppm-promo-field">
                                <label for="promo-master-toggle">Master Promo Toggle</label>
                                <select id="promo-master-toggle" class="ppm-select">
                                    <option value="enabled">Enable All Promos</option>
                                    <option value="disabled">Disable All Promos</option>
                                </select>
                            </div>
                            <div class="ppm-promo-field">
                                <label for="promo-campaign-name">Campaign Name</label>
                                <input type="text" id="promo-campaign-name" class="ppm-input" placeholder="e.g., Summer Special 2024">
                            </div>
                        </div>
                        <div class="ppm-promo-row">
                            <div class="ppm-promo-field">
                                <label for="promo-start-date">Campaign Start Date</label>
                                <input type="date" id="promo-start-date" class="ppm-input">
                            </div>
                            <div class="ppm-promo-field">
                                <label for="promo-end-date">Campaign End Date</label>
                                <input type="date" id="promo-end-date" class="ppm-input">
                            </div>
                        </div>
                        <div class="ppm-promo-row">
                            <div class="ppm-promo-field">
                                <label for="promo-default-duration">Default Promo Duration (months)</label>
                                <select id="promo-default-duration" class="ppm-select">
                                    <option value="1">1 Month</option>
                                    <option value="2" selected>2 Months</option>
                                    <option value="3">3 Months</option>
                                    <option value="6">6 Months</option>
                                </select>
                            </div>
                            <div class="ppm-promo-field">
                                <label for="promo-default-badge-text">Default Badge Text</label>
                                <input type="text" id="promo-default-badge-text" class="ppm-input" placeholder="e.g., PROMO, SPECIAL, SALE" maxlength="15">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Package-Specific Promo Settings -->
                <div class="ppm-promo-section">
                    <h3 class="ppm-section-title">Package-Specific Promo Settings</h3>
                    <div class="ppm-promo-filters">
                        <select id="promo-package-type" class="ppm-filter-select">
                            <option value="all">All Package Types</option>
                            <option value="lte">LTE/5G Packages</option>
                            <option value="fibre">Fibre Packages</option>
                        </select>
                        <select id="promo-provider-filter" class="ppm-filter-select">
                            <option value="all">All Providers</option>
                            <!-- Providers will be populated dynamically -->
                        </select>
                        <input type="text" id="promo-package-search" class="ppm-search" placeholder="Search packages...">
                    </div>
                    <div id="promo-packages-container" class="ppm-promo-packages">
                        <!-- Promo packages will be loaded here -->
                    </div>
                </div>
            </div>
            
            <!-- Bulk Upload Tab -->
            <div id="bulk-tab" class="ppm-tab-content">
                <div class="ppm-header">
                    <h2>Bulk Price Updates</h2>
                    <div class="ppm-bulk-actions">
                        <button class="ppm-btn ppm-btn-secondary" id="export-csv-template">
                            <span class="dashicons dashicons-download"></span> Export Template
                        </button>
                        <button class="ppm-btn ppm-btn-primary" id="apply-bulk-changes" style="display: none;">
                            <span class="dashicons dashicons-saved"></span> Apply Changes
                        </button>
                    </div>
                </div>
                
                <!-- Upload Section -->
                <div class="ppm-bulk-section">
                    <h3 class="ppm-section-title">Upload CSV File</h3>
                    <div class="ppm-upload-area" id="csv-upload-area">
                        <div class="ppm-upload-content">
                            <div class="ppm-upload-icon">
                                <span class="dashicons dashicons-upload"></span>
                            </div>
                            <h4>Drag & Drop CSV File Here</h4>
                            <p>Or click to select a file</p>
                            <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                            <button class="ppm-btn ppm-btn-secondary" id="select-csv-file">Select File</button>
                        </div>
                    </div>
                    <div class="ppm-upload-info">
                        <p><strong>Supported Format:</strong> CSV files with the template structure</p>
                        <p><strong>Max File Size:</strong> 5MB</p>
                        <p><strong>Tip:</strong> Export the template first to get the correct format with your current packages</p>
                    </div>
                </div>
                
                <!-- Preview Section -->
                <div class="ppm-bulk-section" id="preview-section" style="display: none;">
                    <h3 class="ppm-section-title">Preview Changes</h3>
                    <div class="ppm-preview-stats" id="preview-stats"></div>
                    <div class="ppm-preview-table-container">
                        <table class="ppm-preview-table" id="preview-table">
                            <thead>
                                <tr>
                                    <th>Package</th>
                                    <th>Provider</th>
                                    <th>Type</th>
                                    <th>Current Price</th>
                                    <th>New Price</th>
                                    <th>Change</th>
                                    <th>Action</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="preview-tbody">
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Progress Section -->
                <div class="ppm-bulk-section" id="progress-section" style="display: none;">
                    <h3 class="ppm-section-title">Update Progress</h3>
                    <div class="ppm-progress-bar">
                        <div class="ppm-progress-fill" id="progress-fill"></div>
                    </div>
                    <div class="ppm-progress-text" id="progress-text">Ready to start...</div>
                    <div class="ppm-progress-log" id="progress-log"></div>
                </div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <div id="ppm-message" class="ppm-message" style="display: none;"></div>
    </div>
    
    <style>
    /* Import Roboto Font */
    @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap');
    
    .ppm-admin-wrap {
        background: #f0f0f1;
        margin: -20px -20px 0 -2px;
        padding: 20px;
        min-height: calc(100vh - 32px);
        font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    
    .ppm-main-title {
        background: white;
        padding: 20px 30px;
        margin: 0 0 20px 0;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 24px;
        color: #1d2327;
    }
    
    .ppm-main-title .dashicons {
        color: #2271b1;
        font-size: 28px;
        width: 28px;
        height: 28px;
    }
    
    .ppm-tabs {
        display: flex;
        gap: 0;
        margin-bottom: 20px;
        background: white;
        border-radius: 8px;
        padding: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    
    .ppm-tab {
        flex: 1;
        padding: 12px 24px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-size: 15px;
        font-weight: 500;
        color: #50575e;
        border-radius: 6px;
        transition: all 0.3s ease;
        font-family: 'Roboto', sans-serif;
    }
    
    .ppm-tab:hover {
        background: #f0f0f1;
    }
    
    .ppm-tab.active {
        background: #2271b1;
        color: white;
    }
    
    .ppm-content {
        background: white;
        border-radius: 8px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        padding: 30px;
    }
    
    .ppm-tab-content {
        display: none;
    }
    
    .ppm-tab-content.active {
        display: block;
    }
    
    .ppm-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #e0e0e0;
    }
    
    .ppm-header h2 {
        margin: 0;
        color: #1d2327;
        font-size: 20px;
    }
    
    .ppm-container {
        font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }
    
    .ppm-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 12px;
        margin-bottom: 30px;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    
    .ppm-header h1 {
        margin: 0;
        font-size: 28px;
        font-weight: 600;
        letter-spacing: -0.5px;
    }
    
    .ppm-header p {
        margin: 10px 0 0 0;
        font-size: 16px;
        opacity: 0.9;
        font-weight: 300;
    }
    
    .ppm-nav {
        display: flex;
        gap: 0;
        margin-bottom: 30px;
        background: white;
        border-radius: 8px;
        padding: 4px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .ppm-nav-item {
        flex: 1;
        padding: 12px 20px;
        background: transparent;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        color: #646970;
        border-radius: 6px;
        transition: all 0.2s ease;
        font-family: 'Roboto', sans-serif;
    }
    
    .ppm-nav-item:hover {
        background: #f8f9fa;
        color: #1d2327;
    }
    
    .ppm-nav-item.active {
        background: #2271b1;
        color: white;
        box-shadow: 0 2px 8px rgba(34, 113, 177, 0.3);
    }
    
    .ppm-tab-content {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        border: 1px solid #e0e0e0;
    }
    
    .ppm-tab-content.hidden {
        display: none;
    }
    
    .ppm-filters {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .ppm-filter-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .ppm-filter-group label {
        font-size: 14px;
        font-weight: 500;
        color: #1d2327;
        margin: 0;
    }
    
    .ppm-filter-select,
    .ppm-filter-input,
    .ppm-search {
        padding: 8px 12px;
        border: 1px solid #dcdcde;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        color: #1d2327;
        font-family: 'Roboto', sans-serif;
    }
    
    .ppm-filter-select:focus,
    .ppm-filter-input:focus,
    .ppm-search:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
        outline: none;
    }
    
    .ppm-search {
        flex: 1;
        min-width: 200px;
    }
    
    .ppm-packages-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    
    .ppm-package-card {
        border: 1px solid #e0e0e0;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    
    .ppm-package-card:hover {
        box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        transform: translateY(-2px);
    }
    
    .ppm-package-header {
        padding: 16px 20px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-bottom: 1px solid #e0e0e0;
        position: relative;
    }
    
    .ppm-package-title {
        margin: 0 0 4px 0;
        font-size: 16px;
        font-weight: 600;
        color: #1d2327;
        line-height: 1.3;
        letter-spacing: -0.2px;
    }
    
    .ppm-package-meta {
        font-size: 13px;
        color: #646970;
        font-weight: 400;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .ppm-package-body {
        padding: 20px;
    }
    
    .ppm-price-group {
        margin-bottom: 16px;
    }
    
    .ppm-price-group label {
        display: block;
        margin-bottom: 8px;
        font-size: 12px;
        font-weight: 600;
        color: #646970;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    
    .ppm-price-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        background-color: white;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        overflow: hidden;
        transition: all 0.2s ease;
    }
    
    .ppm-price-input-wrapper:focus-within {
        border-color: #2271b1;
        box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
    }
    
    .ppm-currency-symbol {
        flex-shrink: 0;
        padding: 0 12px;
        color: #2271b1;
        font-weight: 600;
        font-size: 16px;
        background-color: #f8f9fa;
        display: flex;
        align-items: center;
        height: 100%;
        border-right: 1px solid #e0e0e0;
    }
    
    .ppm-price-input {
        flex: 1;
        padding: 12px 15px;
        border: none;
        font-size: 16px;
        font-weight: 600;
        color: #1d2327;
        background-color: transparent;
        outline: none;
        -moz-appearance: textfield;
        min-width: 0;
        font-family: 'Roboto', sans-serif;
    }
    
    .ppm-price-input::-webkit-outer-spin-button,
    .ppm-price-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    .ppm-price-input.changed {
        background-color: #fff3cd;
    }
    
    .ppm-price-input-wrapper.changed {
        border-color: #ffc107;
        box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
    }
    
    .ppm-package-details {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #f0f0f0;
        font-size: 13px;
        color: #646970;
    }
    
    .ppm-package-details div {
        margin-bottom: 6px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .ppm-package-details strong {
        font-weight: 500;
        color: #495057;
        min-width: 70px;
    }
    
    .ppm-message {
        position: fixed;
        top: 50px;
        right: 20px;
        padding: 12px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        z-index: 10000;
        animation: slideIn 0.3s ease;
        font-family: 'Roboto', sans-serif;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    }
    
    .ppm-message.success {
        background: #00a32a;
        color: white;
    }
    
    .ppm-message.error {
        background: #d63638;
        color: white;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    /* Clean Provider Styling - No Color Banners */
    .ppm-provider-mtn,
    .ppm-provider-vodacom,
    .ppm-provider-telkom,
    .ppm-provider-topup,
    .ppm-provider-vuma,
    .ppm-provider-openserve,
    .ppm-provider-frogfoot,
    .ppm-provider-metrofibre,
    .ppm-provider-octotel,
    .ppm-provider-linklayer,
    .ppm-provider-mitchells,
    .ppm-provider-unknown {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        color: #1d2327;
        border-left: 4px solid #2271b1;
    }
    
    .ppm-provider-mtn .ppm-package-title,
    .ppm-provider-vodacom .ppm-package-title,
    .ppm-provider-telkom .ppm-package-title,
    .ppm-provider-topup .ppm-package-title,
    .ppm-provider-vuma .ppm-package-title,
    .ppm-provider-openserve .ppm-package-title,
    .ppm-provider-frogfoot .ppm-package-title,
    .ppm-provider-metrofibre .ppm-package-title,
    .ppm-provider-octotel .ppm-package-title,
    .ppm-provider-linklayer .ppm-package-title,
    .ppm-provider-mitchells .ppm-package-title,
    .ppm-provider-unknown .ppm-package-title {
        color: #1d2327;
    }
    
    .ppm-provider-mtn .ppm-package-meta,
    .ppm-provider-vodacom .ppm-package-meta,
    .ppm-provider-telkom .ppm-package-meta,
    .ppm-provider-topup .ppm-package-meta,
    .ppm-provider-vuma .ppm-package-meta,
    .ppm-provider-openserve .ppm-package-meta,
    .ppm-provider-frogfoot .ppm-package-meta,
    .ppm-provider-metrofibre .ppm-package-meta,
    .ppm-provider-octotel .ppm-package-meta,
    .ppm-provider-linklayer .ppm-package-meta,
    .ppm-provider-mitchells .ppm-package-meta,
    .ppm-provider-unknown .ppm-package-meta {
        color: #495057;
    }
    
    /* Provider-specific accent colors for border only */
    .ppm-provider-mtn {
        border-left-color: #ffcc00;
    }
    
    .ppm-provider-vodacom {
        border-left-color: #e60000;
    }
    
    .ppm-provider-telkom {
        border-left-color: #0066cc;
    }
    
    .ppm-provider-topup {
        border-left-color: #ff9800;
    }
    
    .ppm-provider-vuma {
        border-left-color: #28a745;
    }
    
    .ppm-provider-openserve {
        border-left-color: #6610f2;
    }
    
    .ppm-provider-frogfoot {
        border-left-color: #20c997;
    }
    
    .ppm-provider-metrofibre {
        border-left-color: #fd7e14;
    }
    
    .ppm-provider-octotel {
        border-left-color: #e83e8c;
    }
    
    .ppm-provider-linklayer {
        border-left-color: #6f42c1;
    }
    
    .ppm-provider-mitchells {
        border-left-color: #dc3545;
    }
    
    .ppm-provider-unknown {
        border-left-color: #6c757d;
    }
    
    .ppm-loading {
        text-align: center;
        padding: 40px;
        color: #646970;
        font-family: 'Roboto', sans-serif;
    }
    
    .ppm-loading .spinner {
        display: inline-block;
        width: 40px;
        height: 40px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #2271b1;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 15px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Action Buttons */
    .ppm-actions {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    
    .ppm-btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        font-family: 'Roboto', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    
    .ppm-btn-primary {
        background: linear-gradient(135deg, #2271b1 0%, #1e5a8a 100%);
        color: white;
        box-shadow: 0 2px 8px rgba(34, 113, 177, 0.3);
    }
    
    .ppm-btn-primary:hover {
        background: linear-gradient(135deg, #1e5a8a 0%, #1a4d73 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(34, 113, 177, 0.4);
    }
    
    .ppm-btn-secondary {
        background: white;
        color: #646970;
        border: 1px solid #dcdcde;
    }
    
    .ppm-btn-secondary:hover {
        background: #f8f9fa;
        border-color: #c3c4c7;
    }
    
    .ppm-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        transform: none !important;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .ppm-packages-grid {
            grid-template-columns: 1fr;
        }
        
        .ppm-filters {
            flex-direction: column;
            align-items: stretch;
        }
        
        .ppm-filter-group {
            flex-direction: column;
            align-items: stretch;
        }
        
        .ppm-nav {
            flex-direction: column;
        }
        
        .ppm-actions {
            flex-direction: column;
        }
    }
    
    /* Promo Tab Styles */
    .ppm-promo-section {
        margin-bottom: 40px;
        background: #f8f9fa;
        padding: 25px;
        border-radius: 12px;
        border: 1px solid #e0e0e0;
    }
    
    .ppm-section-title {
        margin: 0 0 20px 0;
        color: #1d2327;
        font-size: 18px;
        font-weight: 600;
        padding-bottom: 10px;
        border-bottom: 2px solid #2271b1;
    }
    
    .ppm-promo-global {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .ppm-promo-row {
        display: flex;
        gap: 20px;
        align-items: end;
    }
    
    .ppm-promo-field {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .ppm-promo-field label {
        margin-bottom: 8px;
        font-weight: 600;
        color: #1d2327;
        font-size: 14px;
    }
    
    .ppm-input,
    .ppm-select {
        padding: 10px 12px;
        border: 1px solid #dcdcde;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        color: #1d2327;
        font-family: 'Roboto', sans-serif;
    }
    
    .ppm-input:focus,
    .ppm-select:focus {
        border-color: #2271b1;
        box-shadow: 0 0 0 3px rgba(34, 113, 177, 0.1);
        outline: none;
    }
    
    .ppm-promo-filters {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .ppm-promo-packages {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 20px;
    }
    
    /* Bulk Upload Styles */
    .ppm-bulk-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .ppm-bulk-section {
        margin-bottom: 30px;
        background: #f8f9fa;
        padding: 25px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    .ppm-upload-area {
        border: 2px dashed #dcdcde;
        border-radius: 8px;
        padding: 40px 20px;
        text-align: center;
        background: white;
        transition: all 0.3s ease;
        cursor: pointer;
    }
    
    .ppm-upload-area:hover {
        border-color: #2271b1;
        background: #f0f6fc;
    }
    
    .ppm-upload-area.dragover {
        border-color: #2271b1;
        background: #e6f3ff;
    }
    
    .ppm-upload-icon {
        font-size: 48px;
        color: #2271b1;
        margin-bottom: 15px;
    }
    
    .ppm-upload-content h4 {
        margin: 0 0 10px 0;
        color: #1d2327;
        font-size: 18px;
    }
    
    .ppm-upload-content p {
        margin: 0 0 20px 0;
        color: #646970;
    }
    
    .ppm-upload-info {
        margin-top: 20px;
        padding: 15px;
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 4px;
    }
    
    .ppm-upload-info p {
        margin: 5px 0;
        font-size: 14px;
        color: #856404;
    }
    
    .ppm-preview-stats {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .ppm-stat-card {
        background: white;
        padding: 15px 20px;
        border-radius: 6px;
        border: 1px solid #dcdcde;
        min-width: 120px;
        text-align: center;
    }
    
    .ppm-stat-number {
        font-size: 24px;
        font-weight: 600;
        color: #2271b1;
        display: block;
    }
    
    .ppm-stat-label {
        font-size: 12px;
        color: #646970;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .ppm-preview-table-container {
        background: white;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid #dcdcde;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .ppm-preview-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .ppm-preview-table th {
        background: #f8f9fa;
        padding: 12px 15px;
        text-align: left;
        font-weight: 600;
        color: #1d2327;
        border-bottom: 1px solid #dcdcde;
        position: sticky;
        top: 0;
        z-index: 10;
    }
    
    .ppm-preview-table td {
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f1;
        color: #1d2327;
    }
    
    .ppm-preview-table tr:hover {
        background: #f8f9fa;
    }
    
    .ppm-progress-bar {
        width: 100%;
        height: 20px;
        background: #f0f0f1;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 15px;
    }
    
    .ppm-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #2271b1, #135e96);
        width: 0%;
        transition: width 0.3s ease;
    }
    
    .ppm-progress-text {
        text-align: center;
        font-weight: 600;
        color: #1d2327;
        margin-bottom: 15px;
    }
    
    .ppm-progress-log {
        background: white;
        border: 1px solid #dcdcde;
        border-radius: 4px;
        padding: 15px;
        max-height: 200px;
        overflow-y: auto;
        font-family: monospace;
        font-size: 13px;
        line-height: 1.4;
    }
    
    .ppm-filters {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        align-items: center;
        flex-wrap: wrap;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Tab switching
        $('.ppm-tab').on('click', function() {
            const tab = $(this).data('tab');
            
            $('.ppm-tab').removeClass('active');
            $(this).addClass('active');
            
            $('.ppm-tab-content').removeClass('active');
            $('#' + tab + '-tab').addClass('active');
            
            if (tab === 'lte') {
                loadLTEPackages();
            } else if (tab === 'fibre') {
                loadFibrePackages();
            } else if (tab === 'promo') {
                loadPromoSettings();
            } else if (tab === 'bulk') {
                initBulkUpload();
            }
        });
        
        // Load LTE packages on page load
        loadLTEPackages();
        
        // Track changed inputs
        $(document).on('input', '.ppm-price-input', function() {
            $(this).addClass('changed');
            $(this).closest('.ppm-price-input-wrapper').addClass('changed');
        });
        
        // Filter and search functionality
        $('#lte-provider-filter, #lte-search').on('change keyup', function() {
            filterPackages('lte');
        });
        
        $('#fibre-provider-filter, #fibre-search').on('change keyup', function() {
            filterPackages('fibre');
        });
        
        // Save LTE prices
        $('#save-lte-prices').on('click', function() {
            saveLTEPrices();
        });
        
        // Save Fibre prices
        $('#save-fibre-prices').on('click', function() {
            saveFibrePrices();
        });
        
        // Save Promo settings
        $('#save-promo-settings').on('click', function() {
            savePromoSettings();
        });
        
        // Promo filters
        $('#promo-package-type, #promo-provider-filter, #promo-package-search').on('change keyup', function() {
            filterPromoPackages();
        });
        
        // Promo toggle changes
        $(document).on('change', '.ppm-promo-toggle input', function() {
            const $card = $(this).closest('.ppm-promo-card');
            const $settings = $card.find('.ppm-promo-settings');
            
            if ($(this).is(':checked')) {
                $card.addClass('promo-active');
                $settings.removeClass('disabled');
            } else {
                $card.removeClass('promo-active');
                $settings.addClass('disabled');
            }
        });
        
        // Badge style preview
        // Badge text preview
        $(document).on('input', '.promo-badge-text-input', function() {
            const badgeText = $(this).val().toUpperCase();
            const $preview = $(this).siblings('.ppm-promo-badge-preview');
            $preview.text(badgeText || 'PROMO');
        });
        
        function loadLTEPackages() {
            $('#lte-packages-container').html('<div class="ppm-loading"><div class="spinner"></div><p>Loading packages...</p></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ppm_load_lte_packages',
                    nonce: '<?php echo wp_create_nonce('ppm_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        displayLTEPackages(response.data);
                    } else {
                        showMessage('Error loading packages: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    showMessage('Failed to load packages', 'error');
                }
            });
        }
        
        function displayLTEPackages(data) {
            let html = '';
            
            // Handle new response format
            const packages = data.packages || data;
            
            if (!packages || packages.length === 0) {
                $('#lte-packages-container').html('<div class="ppm-loading">No packages found</div>');
                return;
            }
            
            packages.forEach(function(pkg) {
                const providerClass = 'ppm-provider-' + pkg.provider.toLowerCase();
                const price = parseInt(pkg.price) || 0;
                
                html += `
                    <div class="ppm-package-card" data-provider="${pkg.provider.toLowerCase()}" data-name="${pkg.name.toLowerCase()}">
                        <div class="ppm-package-header ${providerClass}">
                            <h3 class="ppm-package-title">${pkg.name}</h3>
                            <div class="ppm-package-meta">${pkg.provider} - ${pkg.type || 'Fixed LTE'}</div>
                        </div>
                        <div class="ppm-package-body">
                            <div class="ppm-price-group">
                                <label>Monthly Price</label>
                                <div class="ppm-price-input-wrapper">
                                    <span class="ppm-currency-symbol">R</span>
                                    <input type="text" 
                                           class="ppm-price-input" 
                                           data-package-id="${pkg.id}" 
                                           data-original-price="${price}"
                                           value="${price}" 
                                           min="0">
                                </div>
                            </div>
                            <div class="ppm-package-details">
                                ${pkg.speed ? '<div><strong>Speed:</strong> ' + pkg.speed + ' Mbps</div>' : ''}
                                ${pkg.data ? '<div><strong>Data:</strong> ' + pkg.data + '</div>' : ''}
                                ${pkg.aup ? '<div><strong>FUP:</strong> ' + pkg.aup + ' GB</div>' : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#lte-packages-container').html(html || '<div class="ppm-loading">No packages found</div>');
        }
        
        function loadFibrePackages() {
            $('#fibre-packages-container').html('<div class="ppm-loading"><div class="spinner"></div><p>Loading packages...</p></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ppm_load_fibre_packages',
                    nonce: '<?php echo wp_create_nonce('ppm_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        displayFibrePackages(response.data);
                    } else {
                        showMessage('Error loading packages: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    showMessage('Failed to load packages', 'error');
                }
            });
        }
        
        function displayFibrePackages(data) {
            let html = '';
            
            // Handle new response format
            const packages = data.packages || data;
            
            if (!packages || packages.length === 0) {
                $('#fibre-packages-container').html('<div class="ppm-loading">No packages found</div>');
                return;
            }
            
            packages.forEach(function(pkg) {
                const price = parseInt(pkg.price) || 0;
                const providerClass = 'ppm-provider-' + pkg.provider_slug.toLowerCase();
                
                html += `
                    <div class="ppm-package-card" data-provider="${pkg.provider_slug}" data-name="${pkg.title.toLowerCase()}">
                        <div class="ppm-package-header ${providerClass}">
                            <h3 class="ppm-package-title">${pkg.title}</h3>
                            <div class="ppm-package-meta">${pkg.provider} - Fibre</div>
                        </div>
                        <div class="ppm-package-body">
                            <div class="ppm-price-group">
                                <label>Monthly Price</label>
                                <div class="ppm-price-input-wrapper">
                                    <span class="ppm-currency-symbol">R</span>
                                    <input type="text" 
                                           class="ppm-price-input" 
                                           data-package-id="${pkg.id}" 
                                           data-original-price="${price}"
                                           value="${price}" 
                                           min="0">
                                </div>
                            </div>
                            <div class="ppm-package-details">
                                <div><strong>Download:</strong> ${pkg.download}</div>
                                <div><strong>Upload:</strong> ${pkg.upload}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#fibre-packages-container').html(html || '<div class="ppm-loading">No packages found</div>');
        }
        
        function filterPackages(type) {
            const providerFilter = $('#' + type + '-provider-filter').val().toLowerCase();
            const searchTerm = $('#' + type + '-search').val().toLowerCase();
            
            $('#' + type + '-packages-container .ppm-package-card').each(function() {
                const $card = $(this);
                const provider = $card.data('provider');
                const name = $card.data('name');
                
                let show = true;
                
                if (providerFilter !== 'all' && provider !== providerFilter) {
                    show = false;
                }
                
                if (searchTerm && !name.includes(searchTerm)) {
                    show = false;
                }
                
                $card.toggle(show);
            });
        }
        
        function saveLTEPrices() {
            const prices = [];
            
            $('#lte-packages-container .ppm-price-input.changed').each(function() {
                const value = $(this).val();
                const numericValue = parseInt(value) || 0;
                
                prices.push({
                    id: $(this).data('package-id'),
                    price: numericValue
                });
            });
            
            if (prices.length === 0) {
                showMessage('No changes to save', 'error');
                return;
            }
            
            $('#save-lte-prices').prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ppm_save_lte_prices',
                    nonce: '<?php echo wp_create_nonce('ppm_nonce'); ?>',
                    prices: prices
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('Prices updated successfully!', 'success');
                        $('.ppm-price-input.changed').removeClass('changed');
                        $('.ppm-price-input-wrapper.changed').removeClass('changed');
                        loadLTEPackages();
                    } else {
                        showMessage('Error saving prices: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showMessage('Server error while saving prices', 'error');
                },
                complete: function() {
                    $('#save-lte-prices').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save All Changes');
                }
            });
        }
        
        function saveFibrePrices() {
            const prices = [];
            
            $('#fibre-packages-container .ppm-price-input.changed').each(function() {
                const value = $(this).val();
                const numericValue = parseInt(value) || 0;
                
                prices.push({
                    id: $(this).data('package-id'),
                    price: numericValue
                });
            });
            
            if (prices.length === 0) {
                showMessage('No changes to save', 'error');
                return;
            }
            
            $('#save-fibre-prices').prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ppm_save_fibre_prices',
                    nonce: '<?php echo wp_create_nonce('ppm_nonce'); ?>',
                    prices: prices
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('Prices updated successfully!', 'success');
                        $('.ppm-price-input.changed').removeClass('changed');
                        $('.ppm-price-input-wrapper.changed').removeClass('changed');
                        loadFibrePackages();
                    } else {
                        showMessage('Error saving prices: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showMessage('Server error while saving prices', 'error');
                },
                complete: function() {
                    $('#save-fibre-prices').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save All Changes');
                }
            });
        }
        
        function loadPromoSettings() {
            // Load global promo settings
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ppm_load_promo_settings',
                    nonce: '<?php echo wp_create_nonce('ppm_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        populatePromoSettings(response.data);
                    }
                }
            });
            
            // Load promo packages
            loadPromoPackages();
        }
        
        function populatePromoSettings(settings) {
            $('#promo-master-toggle').val(settings.master_toggle || 'enabled');
            $('#promo-campaign-name').val(settings.campaign_name || '');
            $('#promo-start-date').val(settings.start_date || '');
            $('#promo-end-date').val(settings.end_date || '');
            $('#promo-default-duration').val(settings.default_duration || '2');
            $('#promo-default-badge-text').val(settings.default_badge_text || 'PROMO');
        }
        
        function loadPromoPackages() {
            $('#promo-packages-container').html('<div class="ppm-loading"><div class="spinner"></div><p>Loading packages...</p></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ppm_load_promo_packages',
                    nonce: '<?php echo wp_create_nonce('ppm_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        displayPromoPackages(response.data);
                    } else {
                        showMessage('Error loading packages: ' + (response.data || 'Unknown error'), 'error');
                    }
                },
                error: function() {
                    showMessage('Failed to load packages', 'error');
                }
            });
        }
        
        function populatePromoProviderDropdown(packages) {
            const providers = new Set();
            
            // Collect all unique providers from packages
            packages.forEach(function(pkg) {
                if (pkg.provider) {
                    providers.add(pkg.provider);
                }
            });
            
            // Sort providers alphabetically
            const sortedProviders = Array.from(providers).sort();
            
            // Build dropdown options
            let options = '<option value="all">All Providers</option>';
            sortedProviders.forEach(function(provider) {
                const providerSlug = provider.toLowerCase().replace(/\s+/g, '-');
                options += `<option value="${providerSlug}">${provider}</option>`;
            });
            
            // Update the dropdown
            $('#promo-provider-filter').html(options);
        }
        
        function displayPromoPackages(packages) {
            let html = '';
            
            // Populate provider dropdown dynamically
            populatePromoProviderDropdown(packages);
            
            packages.forEach(function(pkg) {
                const isActive = pkg.promo_active === 'yes' || pkg.promo_active === true;
                const promoPrice = pkg.promo_price || pkg.price;
                const promoDuration = pkg.promo_duration || 2;
                const promoType = pkg.promo_type || 'general';
                const promoBadgeText = pkg.promo_badge_text || 'PROMO';
                const promoText = pkg.promo_text || '';
                
                html += `
                    <div class="ppm-promo-card ${isActive ? 'promo-active' : ''}" data-package-id="${pkg.id}" data-provider="${pkg.provider.toLowerCase().replace(/\s+/g, '-')}" data-package-type="${pkg.type}">
                        <div class="ppm-promo-card-header">
                            <h4 class="ppm-promo-card-title">${pkg.name} (${pkg.type.toUpperCase()})</h4>
                            <label class="ppm-promo-toggle">
                                <input type="checkbox" ${isActive ? 'checked' : ''}>
                                <span class="ppm-promo-slider"></span>
                            </label>
                        </div>
                        <div class="ppm-promo-card-body">
                            <div class="ppm-promo-settings ${isActive ? '' : 'disabled'}">
                                <div class="ppm-promo-field">
                                    <label>Regular Price</label>
                                    <input type="text" class="ppm-input" value="R${pkg.price}" readonly>
                                </div>
                                <div class="ppm-promo-field">
                                    <label>Promo Price</label>
                                    <input type="number" class="ppm-input promo-price-input" value="${promoPrice}" min="0">
                                </div>
                                <div class="ppm-promo-field">
                                    <label>Duration (months)</label>
                                    <select class="ppm-select promo-duration-select">
                                        <option value="1" ${promoDuration == 1 ? 'selected' : ''}>1 Month</option>
                                        <option value="2" ${promoDuration == 2 ? 'selected' : ''}>2 Months</option>
                                        <option value="3" ${promoDuration == 3 ? 'selected' : ''}>3 Months</option>
                                        <option value="6" ${promoDuration == 6 ? 'selected' : ''}>6 Months</option>
                                    </select>
                                </div>
                                <div class="ppm-promo-field">
                                    <label>Promo Type</label>
                                    <select class="ppm-select promo-type-select">
                                        <option value="general" ${promoType === 'general' ? 'selected' : ''}>General Promo</option>
                                        <option value="new_customers_only" ${promoType === 'new_customers_only' ? 'selected' : ''}>New Customers Only</option>
                                        <option value="upgrade_special" ${promoType === 'upgrade_special' ? 'selected' : ''}>Upgrade Special</option>
                                    </select>
                                </div>
                                <div class="ppm-promo-field">
                                    <label>Badge Text</label>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <input type="text" class="ppm-input promo-badge-text-input" value="${promoBadgeText}" placeholder="PROMO" maxlength="15" style="width: 120px;">
                                        <span class="ppm-promo-badge-preview ppm-promo-badge-promo">${promoBadgeText || 'PROMO'}</span>
                                    </div>
                                </div>
                                <div class="ppm-promo-field ppm-promo-full-width">
                                    <label>Custom Promo Text (optional)</label>
                                    <input type="text" class="ppm-input promo-text-input" value="${promoText}" placeholder="e.g., Save R100 for 2 months!">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            $('#promo-packages-container').html(html || '<div class="ppm-loading">No packages found</div>');
        }
        
        function filterPromoPackages() {
            const packageType = $('#promo-package-type').val();
            const provider = $('#promo-provider-filter').val();
            const searchTerm = $('#promo-package-search').val().toLowerCase();
            
            $('.ppm-promo-card').each(function() {
                const $card = $(this);
                const cardProvider = $card.data('provider');
                const cardPackageType = $card.data('package-type');
                const cardName = $card.find('.ppm-promo-card-title').text().toLowerCase();
                
                let show = true;
                
                // Filter by package type
                if (packageType !== 'all' && cardPackageType !== packageType) {
                    show = false;
                }
                
                // Filter by provider
                if (provider !== 'all' && cardProvider !== provider) {
                    show = false;
                }
                
                // Filter by search term
                if (searchTerm && !cardName.includes(searchTerm)) {
                    show = false;
                }
                
                $card.toggle(show);
            });
        }
        
        function savePromoSettings() {
            const globalSettings = {
                master_toggle: $('#promo-master-toggle').val(),
                campaign_name: $('#promo-campaign-name').val(),
                start_date: $('#promo-start-date').val(),
                end_date: $('#promo-end-date').val(),
                default_duration: $('#promo-default-duration').val(),
                default_badge_text: $('#promo-default-badge-text').val()
            };
            
            const packageSettings = [];
            $('.ppm-promo-card').each(function() {
                const $card = $(this);
                const packageId = $card.data('package-id');
                const isActive = $card.find('.ppm-promo-toggle input').is(':checked');
                
                packageSettings.push({
                    id: packageId,
                    promo_active: isActive ? 'yes' : 'no',
                    promo_price: $card.find('.promo-price-input').val(),
                    promo_duration: $card.find('.promo-duration-select').val(),
                    promo_type: $card.find('.promo-type-select').val(),
                    promo_badge_text: $card.find('.promo-badge-text-input').val(),
                    promo_text: $card.find('.promo-text-input').val()
                });
            });
            
            $('#save-promo-settings').prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Saving...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ppm_save_promo_settings',
                    nonce: '<?php echo wp_create_nonce('ppm_nonce'); ?>',
                    global_settings: globalSettings,
                    package_settings: packageSettings
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('Promo settings saved successfully!', 'success');
                    } else {
                        showMessage('Error saving settings: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showMessage('Server error while saving settings', 'error');
                },
                complete: function() {
                    $('#save-promo-settings').prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save All Settings');
                }
            });
        }
        
        function showMessage(message, type) {
            const $msg = $('#ppm-message');
            $msg.removeClass('success error').addClass(type).text(message).fadeIn();
            
            setTimeout(function() {
                $msg.fadeOut();
            }, 3000);
        }
        
        // Global variable to store parsed CSV data
        let csvData = [];
        let validationResults = [];
        
        // Bulk upload functionality
        function initBulkUpload() {
            // File upload handlers
            $('#select-csv-file').on('click', function() {
                $('#csv-file-input').click();
            });
            
            $('#csv-file-input').on('change', function(e) {
                handleFileSelect(e.target.files[0]);
            });
            
            // Drag and drop handlers
            const uploadArea = $('#csv-upload-area');
            uploadArea.on('dragover', function(e) {
                e.preventDefault();
                $(this).addClass('dragover');
            });
            
            uploadArea.on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });
            
            uploadArea.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                const files = e.originalEvent.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelect(files[0]);
                }
            });
            
            uploadArea.on('click', function() {
                $('#csv-file-input').click();
            });
            
            // Export template button
            $('#export-csv-template').on('click', exportCSVTemplate);
            
            // Apply changes button
            $('#apply-bulk-changes').on('click', applyBulkChanges);
        }
        
        function handleFileSelect(file) {
            if (!file) return;
            
            if (file.type !== 'text/csv' && !file.name.endsWith('.csv')) {
                showMessage('Please select a CSV file', 'error');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) { // 5MB limit
                showMessage('File size must be less than 5MB', 'error');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                parseCSVData(e.target.result);
            };
            reader.readAsText(file);
        }
        
        function parseCSVData(csvText) {
            try {
                const lines = csvText.split('\n').filter(line => line.trim() && !line.trim().startsWith('#'));
                if (lines.length < 2) {
                    showMessage('CSV file must contain header and at least one data row', 'error');
                    return;
                }
                
                const headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
                const expectedHeaders = [
                    'Package_ID', 'Package_Name', 'Provider', 'Package_Type', 'Data_Source',
                    'Current_Price', 'New_Price', 'Speed', 'Data_Allowance', 'AUP', 'Throttle',
                    'Has_Promo', 'Promo_Price', 'Promo_Duration', 'Promo_Type', 'Promo_Badge',
                    'Promo_Text', 'Action'
                ];
                
                // Validate headers
                const missingHeaders = expectedHeaders.filter(h => !headers.includes(h));
                if (missingHeaders.length > 0) {
                    showMessage('Missing required headers: ' + missingHeaders.join(', '), 'error');
                    return;
                }
                
                csvData = [];
                for (let i = 1; i < lines.length; i++) {
                    const values = parseCSVLine(lines[i]);
                    if (values.length === headers.length) {
                        const row = {};
                        headers.forEach((header, index) => {
                            row[header] = values[index];
                        });
                        csvData.push(row);
                    }
                }
                
                if (csvData.length === 0) {
                    showMessage('No valid data rows found in CSV', 'error');
                    return;
                }
                
                validateCSVData();
                
            } catch (error) {
                showMessage('Error parsing CSV file: ' + error.message, 'error');
            }
        }
        
        function parseCSVLine(line) {
            const result = [];
            let current = '';
            let inQuotes = false;
            
            for (let i = 0; i < line.length; i++) {
                const char = line[i];
                
                if (char === '"') {
                    inQuotes = !inQuotes;
                } else if (char === ',' && !inQuotes) {
                    result.push(current.trim());
                    current = '';
                } else {
                    current += char;
                }
            }
            
            result.push(current.trim());
            return result;
        }
        
        function validateCSVData() {
            validationResults = [];
            
            csvData.forEach((row, index) => {
                const validation = {
                    index: index,
                    row: row,
                    errors: [],
                    warnings: [],
                    status: 'valid'
                };
                
                // Validate Package ID
                if (!row.Package_ID || row.Package_ID.trim() === '') {
                    validation.errors.push('Package ID is required');
                }
                
                // Validate prices
                if (row.Action === 'update' || row.Action === 'add') {
                    const newPrice = parseFloat(row.New_Price);
                    if (isNaN(newPrice) || newPrice < 0) {
                        validation.errors.push('Invalid new price');
                    }
                }
                
                // Validate action
                if (!['update', 'add', 'delete'].includes(row.Action)) {
                    validation.errors.push('Action must be update, add, or delete');
                }
                
                // Validate provider
                if (!row.Provider || row.Provider.trim() === '') {
                    validation.errors.push('Provider is required');
                }
                
                // Validate package type
                if (!['lte', 'fibre'].includes(row.Package_Type)) {
                    validation.errors.push('Package type must be lte or fibre');
                }
                
                // Validate data source
                if (!['wordpress', 'json'].includes(row.Data_Source)) {
                    validation.errors.push('Data source must be wordpress or json');
                }
                
                // Validate promo fields if promo is enabled
                if (row.Has_Promo === 'yes') {
                    const promoPrice = parseFloat(row.Promo_Price);
                    if (isNaN(promoPrice) || promoPrice < 0) {
                        validation.errors.push('Invalid promo price');
                    }
                    
                    const promoDuration = parseInt(row.Promo_Duration);
                    if (isNaN(promoDuration) || ![1, 2, 3, 6].includes(promoDuration)) {
                        validation.errors.push('Promo duration must be 1, 2, 3, or 6 months');
                    }
                    
                    if (!['general', 'new_customers_only', 'upgrade_special'].includes(row.Promo_Type)) {
                        validation.warnings.push('Unknown promo type');
                    }
                    
                    if (!['hot-deal', 'limited-time', 'special-offer', 'new-customer'].includes(row.Promo_Badge)) {
                        validation.warnings.push('Unknown promo badge style');
                    }
                }
                
                // Set overall status
                if (validation.errors.length > 0) {
                    validation.status = 'error';
                } else if (validation.warnings.length > 0) {
                    validation.status = 'warning';
                }
                
                validationResults.push(validation);
            });
            
            displayPreview();
        }
        
        function displayPreview() {
            const stats = {
                total: validationResults.length,
                valid: validationResults.filter(r => r.status === 'valid').length,
                warnings: validationResults.filter(r => r.status === 'warning').length,
                errors: validationResults.filter(r => r.status === 'error').length,
                updates: validationResults.filter(r => r.row.Action === 'update').length,
                adds: validationResults.filter(r => r.row.Action === 'add').length,
                deletes: validationResults.filter(r => r.row.Action === 'delete').length
            };
            
            // Show stats
            const statsHtml = `
                <div class="ppm-stat-card">
                    <span class="ppm-stat-number">${stats.total}</span>
                    <span class="ppm-stat-label">Total Rows</span>
                </div>
                <div class="ppm-stat-card">
                    <span class="ppm-stat-number" style="color: #00a32a;">${stats.valid}</span>
                    <span class="ppm-stat-label">Valid</span>
                </div>
                <div class="ppm-stat-card">
                    <span class="ppm-stat-number" style="color: #f59e0b;">${stats.warnings}</span>
                    <span class="ppm-stat-label">Warnings</span>
                </div>
                <div class="ppm-stat-card">
                    <span class="ppm-stat-number" style="color: #d63638;">${stats.errors}</span>
                    <span class="ppm-stat-label">Errors</span>
                </div>
                <div class="ppm-stat-card">
                    <span class="ppm-stat-number">${stats.updates}</span>
                    <span class="ppm-stat-label">Updates</span>
                </div>
                <div class="ppm-stat-card">
                    <span class="ppm-stat-number">${stats.adds}</span>
                    <span class="ppm-stat-label">Adds</span>
                </div>
                <div class="ppm-stat-card">
                    <span class="ppm-stat-number">${stats.deletes}</span>
                    <span class="ppm-stat-label">Deletes</span>
                </div>
            `;
            
            $('#preview-stats').html(statsHtml);
            
            // Show preview table
            let tableHtml = '';
            validationResults.forEach(validation => {
                const row = validation.row;
                const currentPrice = parseFloat(row.Current_Price) || 0;
                const newPrice = parseFloat(row.New_Price) || 0;
                const change = newPrice - currentPrice;
                
                let changeClass = '';
                let changeText = '';
                if (change > 0) {
                    changeClass = 'ppm-price-increase';
                    changeText = `+R${change}`;
                } else if (change < 0) {
                    changeClass = 'ppm-price-decrease';
                    changeText = `R${change}`;
                } else {
                    changeText = 'No change';
                }
                
                let statusClass = 'ppm-status-valid';
                let statusText = 'Valid';
                if (validation.status === 'error') {
                    statusClass = 'ppm-status-error';
                    statusText = validation.errors.join(', ');
                } else if (validation.status === 'warning') {
                    statusClass = 'ppm-status-warning';
                    statusText = validation.warnings.join(', ');
                }
                
                tableHtml += `
                    <tr>
                        <td>${row.Package_Name}</td>
                        <td>${row.Provider}</td>
                        <td>${row.Package_Type.toUpperCase()}</td>
                        <td>R${currentPrice}</td>
                        <td>R${newPrice}</td>
                        <td class="ppm-price-change ${changeClass}">${changeText}</td>
                        <td>${row.Action}</td>
                        <td class="${statusClass}">${statusText}</td>
                    </tr>
                `;
            });
            
            $('#preview-tbody').html(tableHtml);
            $('#preview-section').show();
            
            // Show apply button if there are valid rows
            if (stats.valid > 0 || stats.warnings > 0) {
                $('#apply-bulk-changes').show();
            } else {
                $('#apply-bulk-changes').hide();
            }
        }
        
        function exportCSVTemplate() {
            $('#export-csv-template').prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Exporting...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'ppm_export_csv_template',
                    nonce: '<?php echo wp_create_nonce('ppm_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        // Create and download CSV file
                        const csvContent = response.data.csv_content;
                        const blob = new Blob([csvContent], { type: 'text/csv' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'price_manager_template.csv';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        window.URL.revokeObjectURL(url);
                        
                        showMessage('CSV template exported successfully!', 'success');
                    } else {
                        showMessage('Error exporting template: ' + response.data, 'error');
                    }
                },
                error: function() {
                    showMessage('Server error while exporting template', 'error');
                },
                complete: function() {
                    $('#export-csv-template').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export Template');
                }
            });
        }
        
        function applyBulkChanges() {
            const validRows = validationResults.filter(r => r.status === 'valid' || r.status === 'warning');
            
            if (validRows.length === 0) {
                showMessage('No valid rows to process', 'error');
                return;
            }
            
            if (!confirm(`Are you sure you want to apply ${validRows.length} changes? This action cannot be undone.`)) {
                return;
            }
            
            // Show progress section
            $('#progress-section').show();
            $('#apply-bulk-changes').prop('disabled', true);
            
            // Process changes
            processBulkChanges(validRows);
        }
        
        function processBulkChanges(validRows) {
            let processed = 0;
            const total = validRows.length;
            const logContainer = $('#progress-log');
            
            function updateProgress() {
                const percentage = (processed / total) * 100;
                $('#progress-fill').css('width', percentage + '%');
                $('#progress-text').text(`Processing ${processed} of ${total} changes...`);
            }
            
            function logMessage(message, type = 'info') {
                const timestamp = new Date().toLocaleTimeString();
                const logEntry = `<div class="ppm-log-entry ppm-log-${type}">[${timestamp}] ${message}</div>`;
                logContainer.append(logEntry);
                logContainer.scrollTop(logContainer[0].scrollHeight);
            }
            
            logMessage('Starting bulk update process...', 'info');
            updateProgress();
            
            // Process in batches to avoid timeout
            function processBatch(startIndex) {
                const batchSize = 5;
                const endIndex = Math.min(startIndex + batchSize, validRows.length);
                const batch = validRows.slice(startIndex, endIndex);
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ppm_process_bulk_changes',
                        nonce: '<?php echo wp_create_nonce('ppm_nonce'); ?>',
                        changes: batch.map(r => r.row)
                    },
                    success: function(response) {
                        if (response.success) {
                            response.data.results.forEach(result => {
                                if (result.success) {
                                    logMessage(`✓ ${result.message}`, 'success');
                                } else {
                                    logMessage(`✗ ${result.message}`, 'error');
                                }
                            });
                        } else {
                            logMessage(`✗ Batch error: ${response.data}`, 'error');
                        }
                        
                        processed += batch.length;
                        updateProgress();
                        
                        if (endIndex < validRows.length) {
                            // Process next batch
                            setTimeout(() => processBatch(endIndex), 500);
                        } else {
                            // Finished
                            $('#progress-text').text(`Completed! Processed ${processed} changes.`);
                            logMessage('Bulk update process completed!', 'success');
                            $('#apply-bulk-changes').prop('disabled', false);
                            showMessage('Bulk update completed successfully!', 'success');
                        }
                    },
                    error: function() {
                        logMessage(`✗ Server error processing batch ${startIndex}-${endIndex}`, 'error');
                        processed += batch.length;
                        updateProgress();
                        
                        if (endIndex < validRows.length) {
                            setTimeout(() => processBatch(endIndex), 500);
                        } else {
                            $('#progress-text').text(`Completed with errors. Processed ${processed} changes.`);
                            $('#apply-bulk-changes').prop('disabled', false);
                        }
                    }
                });
            }
            
            processBatch(0);
        }
        
        const style = document.createElement('style');
        style.textContent = '.spin { animation: spin 1s linear infinite; }';
        document.head.appendChild(style);
    });
    </script>
    <?php
}

// AJAX handler to load LTE packages
add_action('wp_ajax_ppm_load_lte_packages', 'ppm_load_lte_packages');
function ppm_load_lte_packages() {
    check_ajax_referer('ppm_nonce', 'nonce');
    
    $packages = array();
    $debug_info = array();
    
    try {
        // Debug: Check if we can get any posts at all
        $all_posts = get_posts([
            'post_type' => 'lte_packages',
            'numberposts' => 5,
            'post_status' => 'publish'
        ]);
        $debug_info['total_lte_posts'] = count($all_posts);
        
        // Debug: Check if taxonomy exists
        $providers = get_terms([
            'taxonomy' => 'lte_provider',
            'hide_empty' => false,
        ]);
        $debug_info['providers_found'] = !is_wp_error($providers) ? count($providers) : 'error: ' . $providers->get_error_message();
        
        // Try WordPress packages first
        if (!is_wp_error($providers) && !empty($providers)) {
            foreach ($providers as $provider) {
                $posts = get_posts([
                    'post_type' => 'lte_packages',
                    'numberposts' => -1,
                    'post_status' => 'publish',
                    'tax_query' => [[
                        'taxonomy' => 'lte_provider',
                        'field' => 'slug',
                        'terms' => $provider->slug,
                    ]]
                ]);
                
                $debug_info['provider_' . $provider->slug] = count($posts);
                
                foreach ($posts as $post) {
                    $price = get_post_meta($post->ID, 'price', true);
                    if (!$price) $price = 0;
                    
                    $packages[] = array(
                        'id' => $post->ID,
                        'name' => get_the_title($post),
                        'provider' => $provider->name,
                        'provider_slug' => $provider->slug,
                        'price' => intval($price),
                        'type' => 'fixed-lte',
                        'speed' => get_post_meta($post->ID, 'speed', true) ?: '',
                        'data' => get_post_meta($post->ID, 'data', true) ?: '',
                        'aup' => get_post_meta($post->ID, 'aup', true) ?: '',
                        'throttle' => get_post_meta($post->ID, 'throttle', true) ?: '',
                        'has_promo' => false,
                        'promo_price' => null,
                    );
                }
            }
        }
        
        // If no WordPress packages, try JSON
        if (empty($packages)) {
            $debug_info['trying_json'] = true;
            
            // Check if function exists
            if (function_exists('get_packages_with_promo')) {
                $json_packages = get_packages_with_promo();
                $debug_info['json_packages_found'] = count($json_packages);
                
                if (!empty($json_packages)) {
                    foreach ($json_packages as $pkg) {
                        if (isset($pkg['type']) && in_array($pkg['type'], ['fixed', 'mobile', 'lte'])) {
                            $packages[] = array(
                                'id' => $pkg['id'],
                                'name' => $pkg['name'],
                                'provider' => $pkg['provider'],
                                'provider_slug' => strtolower($pkg['provider']),
                                'price' => intval($pkg['price']),
                                'type' => $pkg['type'] === 'mobile' ? 'mobile-data' : 'fixed-lte',
                                'speed' => $pkg['speed'] ?? '',
                                'data' => $pkg['data'] ?? '',
                                'aup' => $pkg['aup'] ?? '',
                                'throttle' => $pkg['throttle'] ?? '',
                                'has_promo' => $pkg['has_promo'] ?? false,
                                'promo_price' => $pkg['promo_price'] ?? null,
                            );
                        }
                    }
                }
            } else {
                $debug_info['get_packages_with_promo'] = 'function not found';
                
                // Try direct JSON loading
                $packages_file = get_template_directory() . '/packages.json';
                if (file_exists($packages_file)) {
                    $packages_json = file_get_contents($packages_file);
                    $json_packages = json_decode($packages_json, true);
                    
                    if (is_array($json_packages)) {
                        $debug_info['direct_json_packages'] = count($json_packages);
                        foreach ($json_packages as $pkg) {
                            if (isset($pkg['type']) && in_array($pkg['type'], ['fixed', 'mobile', 'lte'])) {
                                $packages[] = array(
                                    'id' => $pkg['id'],
                                    'name' => $pkg['name'],
                                    'provider' => $pkg['provider'],
                                    'provider_slug' => strtolower($pkg['provider']),
                                    'price' => intval($pkg['price']),
                                    'type' => $pkg['type'] === 'mobile' ? 'mobile-data' : 'fixed-lte',
                                    'speed' => $pkg['speed'] ?? '',
                                    'data' => $pkg['data'] ?? '',
                                    'aup' => $pkg['aup'] ?? '',
                                    'throttle' => $pkg['throttle'] ?? '',
                                    'has_promo' => isset($pkg['promo_active']) && $pkg['promo_active'],
                                    'promo_price' => $pkg['promo_price'] ?? null,
                                );
                            }
                        }
                    }
                } else {
                    $debug_info['packages_json'] = 'file not found';
                }
            }
        }
        
        // Final fallback
        if (empty($packages)) {
            $debug_info['using_fallback'] = true;
            $packages = array(
                array(
                    'id' => 'sample_1',
                    'name' => 'MTN Fixed LTE 10Mbps',
                    'provider' => 'MTN',
                    'provider_slug' => 'mtn',
                    'price' => 299,
                    'type' => 'fixed-lte',
                    'speed' => '10Mbps',
                    'data' => 'Unlimited',
                    'aup' => '200GB',
                    'throttle' => '2Mbps',
                    'has_promo' => false,
                    'promo_price' => null,
                ),
                array(
                    'id' => 'sample_2',
                    'name' => 'Vodacom Fixed LTE 20Mbps',
                    'provider' => 'Vodacom',
                    'provider_slug' => 'vodacom',
                    'price' => 399,
                    'type' => 'fixed-lte',
                    'speed' => '20Mbps',
                    'data' => 'Unlimited',
                    'aup' => '300GB',
                    'throttle' => '4Mbps',
                    'has_promo' => false,
                    'promo_price' => null,
                )
            );
        }
        
    } catch (Exception $e) {
        $debug_info['error'] = $e->getMessage();
        $packages = array(
            array(
                'id' => 'error_package',
                'name' => 'Error Loading Packages',
                'provider' => 'System',
                'provider_slug' => 'system',
                'price' => 0,
                'type' => 'error',
                'speed' => '',
                'data' => '',
                'aup' => '',
                'throttle' => '',
                'has_promo' => false,
                'promo_price' => null,
            )
        );
    }
    
    $debug_info['final_package_count'] = count($packages);
    
    wp_send_json_success(array(
        'packages' => $packages,
        'debug' => $debug_info
    ));
}

// AJAX handler to save LTE prices
add_action('wp_ajax_ppm_save_lte_prices', 'ppm_save_lte_prices');
function ppm_save_lte_prices() {
    check_ajax_referer('ppm_nonce', 'nonce');
    
    $prices = isset($_POST['prices']) ? $_POST['prices'] : array();
    $updated = 0;
    
    foreach ($prices as $price_update) {
        $post_id = intval($price_update['id']);
        $new_price = intval($price_update['price']);
        
        if ($post_id > 0) {
            if (function_exists('update_field')) {
                update_field('price', $new_price, $post_id);
            } else {
                update_post_meta($post_id, 'price', $new_price);
            }
            $updated++;
        }
    }
    
    wp_send_json_success('Updated ' . $updated . ' LTE package prices successfully');
}

// AJAX handler to load Fibre packages
add_action('wp_ajax_ppm_load_fibre_packages', 'ppm_load_fibre_packages');
function ppm_load_fibre_packages() {
    check_ajax_referer('ppm_nonce', 'nonce');
    
    $packages = array();
    $debug_info = array();
    
    try {
        // Debug: Check if we can get any posts at all
        $all_posts = get_posts([
            'post_type' => 'fibre_packages',
            'numberposts' => 5,
            'post_status' => 'publish'
        ]);
        $debug_info['total_fibre_posts'] = count($all_posts);
        
        // Use the exact same logic as the original working code
        $fibre_packages = get_posts(array(
            'post_type' => 'fibre_packages',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        $debug_info['fibre_packages_found'] = count($fibre_packages);
        
        foreach ($fibre_packages as $post) {
            $price = get_field('price', $post->ID);
            if (!$price) $price = get_post_meta($post->ID, 'price', true);
            if (!$price) $price = 0;
            
            $download = get_field('download', $post->ID);
            if (!$download) $download = get_post_meta($post->ID, 'download', true);
            if (!$download) $download = 'N/A';
            
            $upload = get_field('upload', $post->ID);
            if (!$upload) $upload = get_post_meta($post->ID, 'upload', true);
            if (!$upload) $upload = 'N/A';
            
            // Try to get provider from taxonomy first
            $provider_terms = get_the_terms($post->ID, 'fibre_provider');
            $provider_name = 'Unknown';
            $provider_slug = 'unknown';
            
            if ($provider_terms && !is_wp_error($provider_terms)) {
                $provider_term = reset($provider_terms);
                $provider_name = $provider_term->name;
                $provider_slug = $provider_term->slug;
            } else {
                // Fallback to extracting from title
                $title = get_the_title($post);
                if (stripos($title, 'vuma') !== false) {
                    $provider_name = 'Vuma';
                    $provider_slug = 'vuma';
                } elseif (stripos($title, 'openserve') !== false) {
                    $provider_name = 'Openserve';
                    $provider_slug = 'openserve';
                } elseif (stripos($title, 'frogfoot') !== false) {
                    $provider_name = 'Frogfoot';
                    $provider_slug = 'frogfoot';
                }
            }
            
            $packages[] = array(
                'id' => $post->ID,
                'title' => get_the_title($post),
                'price' => intval($price),
                'download' => $download,
                'upload' => $upload,
                'provider' => $provider_name,
                'provider_slug' => $provider_slug,
                'has_promo' => (get_post_meta($post->ID, 'promo_active', true) === 'yes'),
                'promo_price' => get_post_meta($post->ID, 'promo_price', true) ?: null,
                'effective_price' => intval($price),
                'promo_savings' => 0,
                'promo_display_text' => '',
                'promo_badge_html' => '',
                'promo_duration' => get_post_meta($post->ID, 'promo_duration', true) ?: 2,
                'promo_type' => get_post_meta($post->ID, 'promo_type', true) ?: 'general'
            );
        }
        
        // If no WordPress packages found, load from JSON
        if (empty($packages)) {
            $debug_info['trying_json'] = true;
            
            if (function_exists('get_packages_with_promo')) {
                $json_packages = get_packages_with_promo();
                $debug_info['json_packages_found'] = count($json_packages);
                
                if (!empty($json_packages)) {
                    foreach ($json_packages as $pkg) {
                        // Only include Fibre packages
                        if (isset($pkg['type']) && $pkg['type'] === 'fibre') {
                            $packages[] = array(
                                'id' => $pkg['id'],
                                'title' => $pkg['name'],
                                'price' => intval($pkg['price']),
                                'download' => $pkg['download'] ?? 'N/A',
                                'upload' => $pkg['upload'] ?? 'N/A',
                                'provider' => $pkg['provider'],
                                'provider_slug' => strtolower(str_replace(' ', '-', $pkg['provider'])),
                                'has_promo' => $pkg['has_promo'] ?? false,
                                'promo_price' => $pkg['promo_price'] ?? null,
                                'effective_price' => $pkg['effective_price'] ?? $pkg['price'],
                                'promo_savings' => $pkg['promo_savings'] ?? 0,
                                'promo_display_text' => $pkg['promo_display_text'] ?? '',
                                'promo_badge_html' => $pkg['promo_badge_html'] ?? '',
                                'promo_duration' => $pkg['promo_duration'] ?? 2,
                                'promo_type' => $pkg['promo_type'] ?? 'general'
                            );
                        }
                    }
                }
            } else {
                $debug_info['get_packages_with_promo'] = 'function not found';
                
                // Try direct JSON loading
                $packages_file = get_template_directory() . '/packages.json';
                if (file_exists($packages_file)) {
                    $packages_json = file_get_contents($packages_file);
                    $json_packages = json_decode($packages_json, true);
                    
                    if (is_array($json_packages)) {
                        $debug_info['direct_json_packages'] = count($json_packages);
                        foreach ($json_packages as $pkg) {
                            if (isset($pkg['type']) && $pkg['type'] === 'fibre') {
                                $packages[] = array(
                                    'id' => $pkg['id'],
                                    'title' => $pkg['name'],
                                    'price' => intval($pkg['price']),
                                    'download' => $pkg['download'] ?? 'N/A',
                                    'upload' => $pkg['upload'] ?? 'N/A',
                                    'provider' => $pkg['provider'],
                                    'provider_slug' => strtolower(str_replace(' ', '-', $pkg['provider'])),
                                    'has_promo' => isset($pkg['promo_active']) && $pkg['promo_active'],
                                    'promo_price' => $pkg['promo_price'] ?? null,
                                    'effective_price' => $pkg['effective_price'] ?? $pkg['price'],
                                    'promo_savings' => $pkg['promo_savings'] ?? 0,
                                    'promo_display_text' => $pkg['promo_display_text'] ?? '',
                                    'promo_badge_html' => $pkg['promo_badge_html'] ?? '',
                                    'promo_duration' => $pkg['promo_duration'] ?? 2,
                                    'promo_type' => $pkg['promo_type'] ?? 'general'
                                );
                            }
                        }
                    }
                } else {
                    $debug_info['packages_json'] = 'file not found';
                }
            }
        }
        
        // Final fallback
        if (empty($packages)) {
            $debug_info['using_fallback'] = true;
            $default_packages = [
                ['title' => 'Vuma 25Mbps Fibre', 'provider' => 'Vuma', 'provider_slug' => 'vuma', 'price' => 599, 'download' => '25Mbps', 'upload' => '25Mbps'],
                ['title' => 'Openserve 50Mbps Fibre', 'provider' => 'Openserve', 'provider_slug' => 'openserve', 'price' => 799, 'download' => '50Mbps', 'upload' => '50Mbps'],
                ['title' => 'Frogfoot 100Mbps Fibre', 'provider' => 'Frogfoot', 'provider_slug' => 'frogfoot', 'price' => 1299, 'download' => '100Mbps', 'upload' => '100Mbps'],
            ];
            
            foreach ($default_packages as $index => $pkg) {
                $pkg['id'] = 'default_fibre_' . $index;
                $pkg['has_promo'] = false;
                $pkg['promo_price'] = null;
                $pkg['effective_price'] = $pkg['price'];
                $pkg['promo_savings'] = 0;
                $pkg['promo_display_text'] = '';
                $pkg['promo_badge_html'] = '';
                $pkg['promo_duration'] = 2;
                $pkg['promo_type'] = 'general';
                $packages[] = $pkg;
            }
        }
        
    } catch (Exception $e) {
        $debug_info['error'] = $e->getMessage();
        $packages = array(
            array(
                'id' => 'error_package',
                'title' => 'Error Loading Packages',
                'provider' => 'System',
                'provider_slug' => 'system',
                'price' => 0,
                'download' => 'N/A',
                'upload' => 'N/A',
                'has_promo' => false,
                'promo_price' => null,
            )
        );
    }
    
    $debug_info['final_package_count'] = count($packages);
    
    wp_send_json_success(array(
        'packages' => $packages,
        'debug' => $debug_info
    ));
}

// AJAX handler to save Fibre prices
add_action('wp_ajax_ppm_save_fibre_prices', 'ppm_save_fibre_prices');
function ppm_save_fibre_prices() {
    check_ajax_referer('ppm_nonce', 'nonce');
    
    $prices = isset($_POST['prices']) ? $_POST['prices'] : array();
    $updated = 0;
    
    foreach ($prices as $price_update) {
        $post_id = intval($price_update['id']);
        $new_price = intval($price_update['price']);
        
        if ($post_id > 0) {
            if (function_exists('update_field')) {
                update_field('price', $new_price, $post_id);
            } else {
                update_post_meta($post_id, 'price', $new_price);
            }
            $updated++;
        }
    }
    
    wp_send_json_success('Updated ' . $updated . ' fibre package prices successfully');
}

// AJAX handler to load promo settings
add_action('wp_ajax_ppm_load_promo_settings', 'ppm_load_promo_settings');
function ppm_load_promo_settings() {
    check_ajax_referer('ppm_nonce', 'nonce');
    
    $settings = get_option('ppm_promo_settings', array(
        'master_toggle' => 'enabled',
        'campaign_name' => '',
        'start_date' => '',
        'end_date' => '',
        'default_duration' => '2',
        'default_badge_text' => 'PROMO'
    ));
    
    wp_send_json_success($settings);
}

// AJAX handler to load promo packages
add_action('wp_ajax_ppm_load_promo_packages', 'ppm_load_promo_packages');
function ppm_load_promo_packages() {
    check_ajax_referer('ppm_nonce', 'nonce');
    
    $packages = array();
    
    // Load LTE packages from WordPress
    $lte_providers = get_terms([
        'taxonomy' => 'lte_provider',
        'hide_empty' => false,
    ]);
    
    if (!is_wp_error($lte_providers) && !empty($lte_providers)) {
        foreach ($lte_providers as $provider) {
            $posts = get_posts([
                'post_type' => 'lte_packages',
                'numberposts' => -1,
                'tax_query' => [[
                    'taxonomy' => 'lte_provider',
                    'field' => 'slug',
                    'terms' => $provider->slug,
                ]]
            ]);
            
            foreach ($posts as $post) {
                $promo_active = get_post_meta($post->ID, 'promo_active', true) ?: 'no';
                $promo_price = get_post_meta($post->ID, 'promo_price', true) ?: '';
                
                $packages[] = array(
                    'id' => $post->ID,
                    'name' => get_the_title($post),
                    'provider' => $provider->name,
                    'price' => get_field('price', $post->ID) ?: get_post_meta($post->ID, 'price', true) ?: 0,
                    'type' => 'lte',
                    'promo_active' => $promo_active,
                    'promo_price' => $promo_price,
                    'promo_duration' => get_post_meta($post->ID, 'promo_duration', true) ?: '2',
                    'promo_type' => get_post_meta($post->ID, 'promo_type', true) ?: 'general',
                    'promo_badge_text' => get_post_meta($post->ID, 'promo_badge_text', true) ?: 'PROMO',
                    'promo_text' => get_post_meta($post->ID, 'promo_text', true) ?: '',
                    'has_promo' => $promo_active === 'yes',
                );
            }
        }
    }
    
    // Load Fibre packages from WordPress
    $fibre_providers = get_terms([
        'taxonomy' => 'fibre_provider',
        'hide_empty' => false,
    ]);
    
    if (!is_wp_error($fibre_providers) && !empty($fibre_providers)) {
        foreach ($fibre_providers as $provider) {
            $posts = get_posts([
                'post_type' => 'fibre_packages',
                'numberposts' => -1,
                'tax_query' => [[
                    'taxonomy' => 'fibre_provider',
                    'field' => 'slug',
                    'terms' => $provider->slug,
                ]]
            ]);
            
            foreach ($posts as $post) {
                $promo_active = get_post_meta($post->ID, 'promo_active', true) ?: 'no';
                $promo_price = get_post_meta($post->ID, 'promo_price', true) ?: '';
                
                $packages[] = array(
                    'id' => $post->ID,
                    'name' => get_the_title($post),
                    'provider' => $provider->name,
                    'price' => get_field('price', $post->ID) ?: get_post_meta($post->ID, 'price', true) ?: 0,
                    'type' => 'fibre',
                    'promo_active' => $promo_active,
                    'promo_price' => $promo_price,
                    'promo_duration' => get_post_meta($post->ID, 'promo_duration', true) ?: '2',
                    'promo_type' => get_post_meta($post->ID, 'promo_type', true) ?: 'general',
                    'promo_badge_text' => get_post_meta($post->ID, 'promo_badge_text', true) ?: 'PROMO',
                    'promo_text' => get_post_meta($post->ID, 'promo_text', true) ?: '',
                    'has_promo' => $promo_active === 'yes',
                );
            }
        }
    }
    
    // If no WordPress packages found, load from JSON
    if (empty($packages)) {
        $packages_file = get_template_directory() . '/packages.json';
        if (file_exists($packages_file)) {
            $packages_json = file_get_contents($packages_file);
            $json_packages = json_decode($packages_json, true);
            
            if (is_array($json_packages)) {
                foreach ($json_packages as $package) {
                    $packages[] = array(
                        'id' => $package['id'],
                        'name' => $package['name'],
                        'provider' => $package['provider'],
                        'price' => $package['price'],
                        'type' => $package['type'],
                        'promo_active' => isset($package['promo_active']) && $package['promo_active'] ? 'yes' : 'no',
                        'promo_price' => isset($package['promo_price']) ? $package['promo_price'] : '',
                        'promo_duration' => isset($package['promo_duration']) ? $package['promo_duration'] : '2',
                        'promo_type' => isset($package['promo_type']) ? $package['promo_type'] : 'general',
                        'promo_badge' => isset($package['promo_badge']) ? $package['promo_badge'] : 'hot-deal',
                        'promo_text' => isset($package['promo_text']) ? $package['promo_text'] : '',
                        'has_promo' => isset($package['promo_active']) && $package['promo_active'],
                        'promo_price' => isset($package['promo_price']) ? $package['promo_price'] : 0,
                    );
                }
            }
        }
    }
    
    // If still no packages, provide sample data
    if (empty($packages)) {
        $packages = array(
            array(
                'id' => 'sample_mtn_lte',
                'name' => 'MTN Fixed LTE 10Mbps',
                'provider' => 'MTN',
                'price' => 299,
                'type' => 'lte',
                'promo_active' => 'no',
                'promo_price' => '',
                'promo_duration' => '2',
                'promo_type' => 'general',
                'promo_badge' => 'hot-deal',
                'promo_text' => '',
                'has_promo' => false,
                'promo_price' => 0,
            ),
            array(
                'id' => 'sample_vuma_fibre',
                'name' => 'Vuma 25Mbps Fibre',
                'provider' => 'Vuma',
                'price' => 599,
                'type' => 'fibre',
                'promo_active' => 'no',
                'promo_price' => '',
                'promo_duration' => '2',
                'promo_type' => 'general',
                'promo_badge' => 'hot-deal',
                'promo_text' => '',
                'has_promo' => false,
                'promo_price' => 0,
            )
        );
    }
    
    wp_send_json_success($packages);
}

// AJAX handler to save promo settings
add_action('wp_ajax_ppm_save_promo_settings', 'ppm_save_promo_settings');
function ppm_save_promo_settings() {
    check_ajax_referer('ppm_nonce', 'nonce');
    
    $global_settings = isset($_POST['global_settings']) ? $_POST['global_settings'] : array();
    $package_settings = isset($_POST['package_settings']) ? $_POST['package_settings'] : array();
    
    // Save global settings
    update_option('ppm_promo_settings', $global_settings);
    
    // Save package-specific settings
    $updated = 0;
    foreach ($package_settings as $package) {
        $post_id = intval($package['id']);
        
        if ($post_id > 0) {
            update_post_meta($post_id, 'promo_active', sanitize_text_field($package['promo_active']));
            update_post_meta($post_id, 'promo_price', intval($package['promo_price']));
            update_post_meta($post_id, 'promo_duration', intval($package['promo_duration']));
            update_post_meta($post_id, 'promo_type', sanitize_text_field($package['promo_type']));
            update_post_meta($post_id, 'promo_badge_text', strtoupper(sanitize_text_field($package['promo_badge_text'])));
            update_post_meta($post_id, 'promo_text', sanitize_text_field($package['promo_text']));
            $updated++;
        }
    }
    
    wp_send_json_success('Updated ' . $updated . ' package promo settings successfully');
}

// AJAX handler to export CSV template
add_action('wp_ajax_ppm_export_csv_template', 'ppm_export_csv_template');
function ppm_export_csv_template() {
    check_ajax_referer('ppm_nonce', 'nonce');
    
    $csv_content = "Package_ID,Package_Name,Provider,Package_Type,Data_Source,Current_Price,New_Price,Speed,Data_Allowance,AUP,Throttle,Has_Promo,Promo_Price,Promo_Duration,Promo_Type,Promo_Badge,Promo_Text,Action\n";
    $csv_content .= "# This is a template file for bulk price updates\n";
    $csv_content .= "# Lines starting with # are comments and will be ignored\n";
    $csv_content .= "# Data_Source: 'wordpress' for custom post types, 'json' for packages.json\n";
    $csv_content .= "# Action: 'update', 'add', or 'delete'\n";
    $csv_content .= "# Has_Promo: 'yes' or 'no'\n";
    $csv_content .= "# Promo_Duration: 1, 2, 3, or 6 months\n";
    $csv_content .= "# Promo_Type: 'general', 'new_customers_only', 'upgrade_special'\n";
    $csv_content .= "# Promo_Badge: 'hot-deal', 'limited-time', 'special-offer', 'new-customer'\n";
    $csv_content .= "#\n";
    $csv_content .= "# WordPress LTE Packages\n";
    
    // Load LTE packages from WordPress
    $lte_providers = get_terms([
        'taxonomy' => 'lte_provider',
        'hide_empty' => false,
    ]);
    
    if (!is_wp_error($lte_providers)) {
        foreach ($lte_providers as $provider) {
            $posts = get_posts([
                'post_type' => 'lte_packages',
                'numberposts' => -1,
                'tax_query' => [[
                    'taxonomy' => 'lte_provider',
                    'field' => 'slug',
                    'terms' => $provider->slug,
                ]]
            ]);
            
            foreach ($posts as $post) {
                $price = get_field('price', $post->ID) ?: get_post_meta($post->ID, 'price', true) ?: 0;
                $speed = get_field('speed', $post->ID) ?: get_post_meta($post->ID, 'speed', true) ?: '';
                $data = get_field('data', $post->ID) ?: get_post_meta($post->ID, 'data', true) ?: '';
                $aup = get_field('aup', $post->ID) ?: get_post_meta($post->ID, 'aup', true) ?: '';
                $throttle = get_field('throttle', $post->ID) ?: get_post_meta($post->ID, 'throttle', true) ?: '';
                
                $promo_active = get_post_meta($post->ID, 'promo_active', true) ?: 'no';
                $has_promo = $promo_active;
                $promo_price = get_post_meta($post->ID, 'promo_price', true) ?: '';
                $promo_duration = get_post_meta($post->ID, 'promo_duration', true) ?: '';
                $promo_type = get_post_meta($post->ID, 'promo_type', true) ?: '';
                $promo_badge = get_post_meta($post->ID, 'promo_badge', true) ?: '';
                $promo_text = get_post_meta($post->ID, 'promo_text', true) ?: '';
                
                $csv_content .= sprintf(
                    "%d,\"%s\",\"%s\",lte,wordpress,%d,%d,\"%s\",\"%s\",\"%s\",\"%s\",%s,%s,%s,\"%s\",\"%s\",\"%s\",update\n",
                    $post->ID,
                    str_replace('"', '""', get_the_title($post)),
                    str_replace('"', '""', $provider->name),
                    $price,
                    $price, // Default new price same as current
                    str_replace('"', '""', $speed),
                    str_replace('"', '""', $data),
                    str_replace('"', '""', $aup),
                    str_replace('"', '""', $throttle),
                    $has_promo,
                    $promo_price,
                    $promo_duration,
                    str_replace('"', '""', $promo_type),
                    str_replace('"', '""', $promo_badge),
                    str_replace('"', '""', $promo_text)
                );
            }
        }
    }
    
    $csv_content .= "#\n# WordPress Fibre Packages\n";
    
    // Load Fibre packages from WordPress
    $fibre_providers = get_terms([
        'taxonomy' => 'fibre_provider',
        'hide_empty' => false,
    ]);
    
    if (!is_wp_error($fibre_providers)) {
        foreach ($fibre_providers as $provider) {
            $posts = get_posts([
                'post_type' => 'fibre_packages',
                'numberposts' => -1,
                'tax_query' => [[
                    'taxonomy' => 'fibre_provider',
                    'field' => 'slug',
                    'terms' => $provider->slug,
                ]]
            ]);
            
            foreach ($posts as $post) {
                $price = get_field('price', $post->ID) ?: get_post_meta($post->ID, 'price', true) ?: 0;
                $download = get_field('download', $post->ID) ?: get_post_meta($post->ID, 'download', true) ?: '';
                $upload = get_field('upload', $post->ID) ?: get_post_meta($post->ID, 'upload', true) ?: '';
                
                $promo_active = get_post_meta($post->ID, 'promo_active', true) ?: 'no';
                $has_promo = $promo_active;
                $promo_price = get_post_meta($post->ID, 'promo_price', true) ?: '';
                $promo_duration = get_post_meta($post->ID, 'promo_duration', true) ?: '';
                $promo_type = get_post_meta($post->ID, 'promo_type', true) ?: '';
                $promo_badge = get_post_meta($post->ID, 'promo_badge', true) ?: '';
                $promo_text = get_post_meta($post->ID, 'promo_text', true) ?: '';
                
                $csv_content .= sprintf(
                    "%d,\"%s\",\"%s\",fibre,wordpress,%d,%d,\"%s\",Unlimited,,,,%s,%s,%s,\"%s\",\"%s\",\"%s\",update\n",
                    $post->ID,
                    str_replace('"', '""', get_the_title($post)),
                    str_replace('"', '""', $provider->name),
                    $price,
                    $price, // Default new price same as current
                    str_replace('"', '""', $download . '/' . $upload),
                    $has_promo,
                    $promo_price,
                    $promo_duration,
                    str_replace('"', '""', $promo_type),
                    str_replace('"', '""', $promo_badge),
                    str_replace('"', '""', $promo_text)
                );
            }
        }
    }
    
    $csv_content .= "#\n# JSON Packages\n";
    
    // Load JSON packages
    $packages_file = get_template_directory() . '/packages.json';
    if (file_exists($packages_file)) {
        $packages_json = file_get_contents($packages_file);
        $packages = json_decode($packages_json, true);
        
        if (is_array($packages)) {
            foreach ($packages as $package) {
                $has_promo = isset($package['promo_active']) && $package['promo_active'] ? 'yes' : 'no';
                $promo_price = isset($package['promo_price']) ? $package['promo_price'] : '';
                $promo_duration = isset($package['promo_duration']) ? $package['promo_duration'] : '';
                $promo_type = isset($package['promo_type']) ? $package['promo_type'] : '';
                $promo_badge = isset($package['promo_badge']) ? $package['promo_badge'] : '';
                $promo_text = isset($package['promo_text']) ? $package['promo_text'] : '';
                
                $csv_content .= sprintf(
                    "\"%s\",\"%s\",\"%s\",%s,json,%d,%d,\"%s\",\"%s\",\"%s\",\"%s\",%s,%s,%s,\"%s\",\"%s\",\"%s\",update\n",
                    str_replace('"', '""', $package['id']),
                    str_replace('"', '""', $package['name']),
                    str_replace('"', '""', $package['provider']),
                    $package['type'] === 'mobile' ? 'lte' : 'lte',
                    $package['price'],
                    $package['price'], // Default new price same as current
                    str_replace('"', '""', isset($package['speed']) ? $package['speed'] : ''),
                    str_replace('"', '""', isset($package['data']) ? $package['data'] : ''),
                    str_replace('"', '""', isset($package['aup']) ? $package['aup'] : ''),
                    str_replace('"', '""', isset($package['throttle']) ? $package['throttle'] : ''),
                    $has_promo,
                    $promo_price,
                    $promo_duration,
                    str_replace('"', '""', $promo_type),
                    str_replace('"', '""', $promo_badge),
                    str_replace('"', '""', $promo_text)
                );
            }
        }
    }
    
    wp_send_json_success(['csv_content' => $csv_content]);
}

// AJAX handler to process bulk changes
add_action('wp_ajax_ppm_process_bulk_changes', 'ppm_process_bulk_changes');
function ppm_process_bulk_changes() {
    check_ajax_referer('ppm_nonce', 'nonce');
    
    $changes = isset($_POST['changes']) ? $_POST['changes'] : array();
    $results = array();
    
    foreach ($changes as $change) {
        $result = array(
            'package_id' => $change['Package_ID'],
            'success' => false,
            'message' => ''
        );
        
        try {
            if ($change['Data_Source'] === 'wordpress') {
                $result = process_wordpress_package_change($change);
            } else if ($change['Data_Source'] === 'json') {
                $result = process_json_package_change($change);
            } else {
                $result['message'] = 'Unknown data source: ' . $change['Data_Source'];
            }
        } catch (Exception $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }
        
        $results[] = $result;
    }
    
    wp_send_json_success(['results' => $results]);
}

function process_wordpress_package_change($change) {
    $package_id = intval($change['Package_ID']);
    $action = $change['Action'];
    
    if ($action === 'update') {
        if ($package_id <= 0) {
            return array(
                'package_id' => $change['Package_ID'],
                'success' => false,
                'message' => 'Invalid package ID for WordPress package'
            );
        }
        
        $post = get_post($package_id);
        if (!$post) {
            return array(
                'package_id' => $change['Package_ID'],
                'success' => false,
                'message' => 'Package not found: ' . $package_id
            );
        }
        
        // Update price
        $new_price = intval($change['New_Price']);
        if (function_exists('update_field')) {
            update_field('price', $new_price, $package_id);
        } else {
            update_post_meta($package_id, 'price', $new_price);
        }
        
        // Update promo settings
        if ($change['Has_Promo'] === 'yes') {
            update_post_meta($package_id, 'promo_active', 'yes');
            update_post_meta($package_id, 'promo_price', intval($change['Promo_Price']));
            update_post_meta($package_id, 'promo_duration', intval($change['Promo_Duration']));
            update_post_meta($package_id, 'promo_type', sanitize_text_field($change['Promo_Type']));
            update_post_meta($package_id, 'promo_badge', sanitize_text_field($change['Promo_Badge']));
            update_post_meta($package_id, 'promo_text', sanitize_text_field($change['Promo_Text']));
        } else {
            update_post_meta($package_id, 'promo_active', 'no');
        }
        
        return array(
            'package_id' => $change['Package_ID'],
            'success' => true,
            'message' => 'Updated ' . $change['Package_Name'] . ' - Price: R' . $new_price
        );
        
    } else if ($action === 'delete') {
        // For WordPress packages, we don't actually delete, just mark as inactive
        update_post_meta($package_id, 'active', 'no');
        
        return array(
            'package_id' => $change['Package_ID'],
            'success' => true,
            'message' => 'Deactivated ' . $change['Package_Name']
        );
    }
    
    return array(
        'package_id' => $change['Package_ID'],
        'success' => false,
        'message' => 'Unsupported action for WordPress packages: ' . $action
    );
}

function process_json_package_change($change) {
    $packages_file = get_template_directory() . '/packages.json';
    
    if (!file_exists($packages_file)) {
        return array(
            'package_id' => $change['Package_ID'],
            'success' => false,
            'message' => 'packages.json file not found'
        );
    }
    
    $packages_json = file_get_contents($packages_file);
    $packages = json_decode($packages_json, true);
    
    if (!is_array($packages)) {
        return array(
            'package_id' => $change['Package_ID'],
            'success' => false,
            'message' => 'Invalid packages.json format'
        );
    }
    
    $package_id = $change['Package_ID'];
    $action = $change['Action'];
    $found_index = -1;
    
    // Find package by ID
    foreach ($packages as $index => $package) {
        if ($package['id'] === $package_id) {
            $found_index = $index;
            break;
        }
    }
    
    if ($action === 'update') {
        if ($found_index === -1) {
            return array(
                'package_id' => $package_id,
                'success' => false,
                'message' => 'Package not found in JSON: ' . $package_id
            );
        }
        
        // Update package
        $packages[$found_index]['price'] = intval($change['New_Price']);
        
        // Update promo settings
        if ($change['Has_Promo'] === 'yes') {
            $packages[$found_index]['promo_active'] = true;
            $packages[$found_index]['promo_price'] = intval($change['Promo_Price']);
            $packages[$found_index]['promo_duration'] = intval($change['Promo_Duration']);
            $packages[$found_index]['promo_type'] = $change['Promo_Type'];
            $packages[$found_index]['promo_badge'] = $change['Promo_Badge'];
            $packages[$found_index]['promo_text'] = $change['Promo_Text'];
        } else {
            $packages[$found_index]['promo_active'] = false;
            unset($packages[$found_index]['promo_price']);
            unset($packages[$found_index]['promo_duration']);
            unset($packages[$found_index]['promo_type']);
            unset($packages[$found_index]['promo_badge']);
            unset($packages[$found_index]['promo_text']);
        }
        
    } else if ($action === 'add') {
        // Add new package
        $new_package = array(
            'id' => $package_id,
            'name' => $change['Package_Name'],
            'provider' => $change['Provider'],
            'price' => intval($change['New_Price']),
            'type' => $change['Package_Type'] === 'lte' ? 'fixed' : 'fibre'
        );
        
        // Add optional fields
        if (!empty($change['Speed'])) $new_package['speed'] = $change['Speed'];
        if (!empty($change['Data_Allowance'])) $new_package['data'] = $change['Data_Allowance'];
        if (!empty($change['AUP'])) $new_package['aup'] = $change['AUP'];
        if (!empty($change['Throttle'])) $new_package['throttle'] = $change['Throttle'];
        
        // Add promo fields if enabled
        if ($change['Has_Promo'] === 'yes') {
            $new_package['promo_active'] = true;
            $new_package['promo_price'] = intval($change['Promo_Price']);
            $new_package['promo_duration'] = intval($change['Promo_Duration']);
            $new_package['promo_type'] = $change['Promo_Type'];
            $new_package['promo_badge'] = $change['Promo_Badge'];
            $new_package['promo_text'] = $change['Promo_Text'];
        }
        
        $packages[] = $new_package;
        
    } else if ($action === 'delete') {
        if ($found_index === -1) {
            return array(
                'package_id' => $package_id,
                'success' => false,
                'message' => 'Package not found for deletion: ' . $package_id
            );
        }
        
        array_splice($packages, $found_index, 1);
    }
    
    // Save updated packages.json
    $updated_json = json_encode($packages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (file_put_contents($packages_file, $updated_json) === false) {
        return array(
            'package_id' => $package_id,
            'success' => false,
            'message' => 'Failed to save packages.json'
        );
    }
    
    return array(
        'package_id' => $package_id,
        'success' => true,
        'message' => ucfirst($action) . 'd ' . $change['Package_Name'] . ' in JSON - Price: R' . $change['New_Price']
    );
}
?>