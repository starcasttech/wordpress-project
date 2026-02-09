<?php
/**
 * Admin Page Class
 * Handles the admin interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Starcast_CSV_Admin_Page {
    
    /**
     * Render main import page
     */
    public static function render_page() {
        ?>
        <div class="wrap starcast-csv-importer">
            <h1><?php _e('CSV Package Importer', 'starcast-csv-importer'); ?></h1>
            
            <?php if (isset($_GET['imported'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Import completed successfully!', 'starcast-csv-importer'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="starcast-csv-container">
                <!-- Step 1: Upload -->
                <div class="starcast-csv-step" id="step-upload">
                    <h2><?php _e('Step 1: Upload CSV File', 'starcast-csv-importer'); ?></h2>
                    
                    <div class="upload-box">
                        <form id="csv-upload-form" enctype="multipart/form-data">
                            <div class="upload-area" id="upload-area">
                                <svg class="upload-icon" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                <p class="upload-text"><?php _e('Drag and drop your CSV file here or click to browse', 'starcast-csv-importer'); ?></p>
                                <input type="file" id="csv-file" name="csv_file" accept=".csv" style="display: none;">
                                <button type="button" class="button button-primary" id="browse-button">
                                    <?php _e('Browse Files', 'starcast-csv-importer'); ?>
                                </button>
                            </div>
                        </form>
                        
                        <div class="file-info" id="file-info" style="display: none;">
                            <h3><?php _e('File Details', 'starcast-csv-importer'); ?></h3>
                            <p><strong><?php _e('Name:', 'starcast-csv-importer'); ?></strong> <span id="file-name"></span></p>
                            <p><strong><?php _e('Size:', 'starcast-csv-importer'); ?></strong> <span id="file-size"></span></p>
                            <p><strong><?php _e('Rows:', 'starcast-csv-importer'); ?></strong> <span id="file-rows"></span></p>
                        </div>
                    </div>
                    
                    <div class="csv-requirements">
                        <h3><?php _e('CSV Requirements', 'starcast-csv-importer'); ?></h3>
                        <ul>
                            <li><?php _e('First row must contain column headers', 'starcast-csv-importer'); ?></li>
                            <li><?php _e('UTF-8 encoding recommended', 'starcast-csv-importer'); ?></li>
                            <li><?php _e('Comma-separated values', 'starcast-csv-importer'); ?></li>
                            <li><?php _e('Text fields can be enclosed in quotes', 'starcast-csv-importer'); ?></li>
                        </ul>
                        
                        <h4><?php _e('Example Format:', 'starcast-csv-importer'); ?></h4>
                        <pre>Provider,Product Name,Download Speed,Upload Speed,Price
Vodacom,Fibre 50/25,50Mbps,25Mbps,599
MTN,Fibre 100/50,100Mbps,50Mbps,899</pre>
                    </div>
                </div>
                
                <!-- Step 2: Configure -->
                <div class="starcast-csv-step" id="step-configure" style="display: none;">
                    <h2><?php _e('Step 2: Configure Import', 'starcast-csv-importer'); ?></h2>
                    
                    <div class="import-config">
                        <div class="config-section">
                            <h3><?php _e('Import Type', 'starcast-csv-importer'); ?></h3>
                            <select id="post-type" class="regular-text">
                                <option value=""><?php _e('Select package type...', 'starcast-csv-importer'); ?></option>
                                <option value="fibre_packages"><?php _e('Fibre Packages', 'starcast-csv-importer'); ?></option>
                                <option value="lte_packages"><?php _e('LTE Packages', 'starcast-csv-importer'); ?></option>
                            </select>
                        </div>
                        
                        <div class="config-section">
                            <h3><?php _e('Import Options', 'starcast-csv-importer'); ?></h3>
                            <label>
                                <input type="checkbox" id="update-existing" checked>
                                <?php _e('Update existing packages', 'starcast-csv-importer'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" id="create-providers" checked>
                                <?php _e('Create missing provider terms', 'starcast-csv-importer'); ?>
                            </label>
                            <br>
                            <label>
                                <input type="checkbox" id="skip-duplicates">
                                <?php _e('Skip duplicate packages', 'starcast-csv-importer'); ?>
                            </label>
                        </div>
                        
                        <div class="config-section">
                            <h3><?php _e('Package Identifier', 'starcast-csv-importer'); ?></h3>
                            <p class="description"><?php _e('Choose how to identify existing packages for updates', 'starcast-csv-importer'); ?></p>
                            <select id="identifier-field" class="regular-text">
                                <option value="title"><?php _e('Package Title', 'starcast-csv-importer'); ?></option>
                                <option value="sku"><?php _e('SKU', 'starcast-csv-importer'); ?></option>
                                <option value="custom"><?php _e('Custom Field', 'starcast-csv-importer'); ?></option>
                            </select>
                            <input type="text" id="identifier-custom" class="regular-text" placeholder="<?php _e('Enter custom field name', 'starcast-csv-importer'); ?>" style="display: none;">
                        </div>
                        
                        <div class="button-group">
                            <button type="button" class="button" id="back-to-upload">
                                <?php _e('← Back', 'starcast-csv-importer'); ?>
                            </button>
                            <button type="button" class="button button-primary" id="continue-to-mapping">
                                <?php _e('Continue to Mapping →', 'starcast-csv-importer'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Field Mapping -->
                <div class="starcast-csv-step" id="step-mapping" style="display: none;">
                    <h2><?php _e('Step 3: Map CSV Fields', 'starcast-csv-importer'); ?></h2>
                    
                    <div class="mapping-container">
                        <div class="mapping-section">
                            <h3><?php _e('CSV Preview', 'starcast-csv-importer'); ?></h3>
                            <div id="csv-preview" class="csv-preview"></div>
                        </div>
                        
                        <div class="mapping-section">
                            <h3><?php _e('Field Mapping', 'starcast-csv-importer'); ?></h3>
                            <div id="field-mapping" class="field-mapping"></div>
                            
                            <div class="mapping-templates">
                                <h4><?php _e('Saved Mappings', 'starcast-csv-importer'); ?></h4>
                                <select id="mapping-template" class="regular-text">
                                    <option value=""><?php _e('Select a saved mapping...', 'starcast-csv-importer'); ?></option>
                                </select>
                                <button type="button" class="button" id="save-mapping">
                                    <?php _e('Save Current Mapping', 'starcast-csv-importer'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="button-group">
                            <button type="button" class="button" id="back-to-configure">
                                <?php _e('← Back', 'starcast-csv-importer'); ?>
                            </button>
                            <button type="button" class="button button-primary" id="start-import">
                                <?php _e('Start Import', 'starcast-csv-importer'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 4: Import Progress -->
                <div class="starcast-csv-step" id="step-import" style="display: none;">
                    <h2><?php _e('Importing...', 'starcast-csv-importer'); ?></h2>
                    
                    <div class="import-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="progress-text" id="progress-text">0%</div>
                        
                        <div class="import-stats">
                            <div class="stat">
                                <span class="stat-label"><?php _e('Imported:', 'starcast-csv-importer'); ?></span>
                                <span class="stat-value" id="stat-imported">0</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label"><?php _e('Updated:', 'starcast-csv-importer'); ?></span>
                                <span class="stat-value" id="stat-updated">0</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label"><?php _e('Skipped:', 'starcast-csv-importer'); ?></span>
                                <span class="stat-value" id="stat-skipped">0</span>
                            </div>
                            <div class="stat">
                                <span class="stat-label"><?php _e('Errors:', 'starcast-csv-importer'); ?></span>
                                <span class="stat-value" id="stat-errors">0</span>
                            </div>
                        </div>
                        
                        <div class="import-log" id="import-log"></div>
                        
                        <div class="button-group" id="import-complete" style="display: none;">
                            <button type="button" class="button button-primary" id="new-import">
                                <?php _e('New Import', 'starcast-csv-importer'); ?>
                            </button>
                            <a href="<?php echo admin_url('admin.php?page=starcast-csv-history'); ?>" class="button">
                                <?php _e('View History', 'starcast-csv-importer'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render history page
     */
    public static function render_history_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Import History', 'starcast-csv-importer'); ?></h1>
            
            <div id="import-history-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'starcast-csv-importer'); ?></th>
                            <th><?php _e('File', 'starcast-csv-importer'); ?></th>
                            <th><?php _e('Type', 'starcast-csv-importer'); ?></th>
                            <th><?php _e('Imported', 'starcast-csv-importer'); ?></th>
                            <th><?php _e('Updated', 'starcast-csv-importer'); ?></th>
                            <th><?php _e('Skipped', 'starcast-csv-importer'); ?></th>
                            <th><?php _e('Errors', 'starcast-csv-importer'); ?></th>
                            <th><?php _e('User', 'starcast-csv-importer'); ?></th>
                            <th><?php _e('Actions', 'starcast-csv-importer'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="history-table-body">
                        <tr>
                            <td colspan="9" class="loading"><?php _e('Loading history...', 'starcast-csv-importer'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public static function render_settings_page() {
        // Save settings if submitted
        if (isset($_POST['submit']) && check_admin_referer('starcast_csv_settings')) {
            $settings = array(
                'batch_size' => intval($_POST['batch_size']),
                'timeout_prevention' => isset($_POST['timeout_prevention']),
                'create_missing_providers' => isset($_POST['create_missing_providers']),
                'update_existing' => isset($_POST['update_existing']),
                'skip_duplicates' => isset($_POST['skip_duplicates']),
                'log_imports' => isset($_POST['log_imports'])
            );
            
            foreach ($settings as $key => $value) {
                Starcast_CSV_Importer::update_option($key, $value);
            }
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'starcast-csv-importer') . '</p></div>';
        }
        
        // Get current settings
        $batch_size = Starcast_CSV_Importer::get_option('batch_size', 50);
        $timeout_prevention = Starcast_CSV_Importer::get_option('timeout_prevention', true);
        $create_missing_providers = Starcast_CSV_Importer::get_option('create_missing_providers', true);
        $update_existing = Starcast_CSV_Importer::get_option('update_existing', true);
        $skip_duplicates = Starcast_CSV_Importer::get_option('skip_duplicates', false);
        $log_imports = Starcast_CSV_Importer::get_option('log_imports', true);
        ?>
        <div class="wrap">
            <h1><?php _e('CSV Importer Settings', 'starcast-csv-importer'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('starcast_csv_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="batch_size"><?php _e('Batch Size', 'starcast-csv-importer'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="batch_size" name="batch_size" value="<?php echo esc_attr($batch_size); ?>" min="10" max="500" class="small-text">
                            <p class="description"><?php _e('Number of rows to process per batch. Lower values prevent timeouts.', 'starcast-csv-importer'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Import Options', 'starcast-csv-importer'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="timeout_prevention" <?php checked($timeout_prevention); ?>>
                                    <?php _e('Enable timeout prevention', 'starcast-csv-importer'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="create_missing_providers" <?php checked($create_missing_providers); ?>>
                                    <?php _e('Create missing provider terms', 'starcast-csv-importer'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="update_existing" <?php checked($update_existing); ?>>
                                    <?php _e('Update existing packages by default', 'starcast-csv-importer'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="skip_duplicates" <?php checked($skip_duplicates); ?>>
                                    <?php _e('Skip duplicate packages by default', 'starcast-csv-importer'); ?>
                                </label>
                                <br>
                                <label>
                                    <input type="checkbox" name="log_imports" <?php checked($log_imports); ?>>
                                    <?php _e('Log import history', 'starcast-csv-importer'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Field Mapping Reference', 'starcast-csv-importer'); ?></h2>
            
            <h3><?php _e('Fibre Packages Fields', 'starcast-csv-importer'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Field', 'starcast-csv-importer'); ?></th>
                        <th><?php _e('Meta Key', 'starcast-csv-importer'); ?></th>
                        <th><?php _e('Description', 'starcast-csv-importer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Download Speed</td>
                        <td><code>download</code></td>
                        <td><?php _e('Download speed (e.g., 50Mbps)', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>Upload Speed</td>
                        <td><code>upload</code></td>
                        <td><?php _e('Upload speed (e.g., 25Mbps)', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>Price</td>
                        <td><code>price</code></td>
                        <td><?php _e('Monthly price (numbers only)', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>Provider</td>
                        <td><em>Taxonomy</em></td>
                        <td><?php _e('Provider name (fibre_provider taxonomy)', 'starcast-csv-importer'); ?></td>
                    </tr>
                </tbody>
            </table>
            
            <h3><?php _e('LTE Packages Fields', 'starcast-csv-importer'); ?></h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th><?php _e('Field', 'starcast-csv-importer'); ?></th>
                        <th><?php _e('Meta Key', 'starcast-csv-importer'); ?></th>
                        <th><?php _e('Description', 'starcast-csv-importer'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Speed</td>
                        <td><code>speed</code></td>
                        <td><?php _e('Connection speed (e.g., 30Mbps)', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>Data</td>
                        <td><code>data</code></td>
                        <td><?php _e('Data allowance (e.g., 50GB, Uncapped)', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>AUP</td>
                        <td><code>aup</code></td>
                        <td><?php _e('Acceptable Use Policy limit', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>Throttle</td>
                        <td><code>throttle</code></td>
                        <td><?php _e('Throttled speed after AUP', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>Price</td>
                        <td><code>price</code></td>
                        <td><?php _e('Monthly price (numbers only)', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>Package Type</td>
                        <td><code>package_type</code></td>
                        <td><?php _e('Type: fixed-lte, fixed-5g, mobile-data', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>Display Order</td>
                        <td><code>display_order</code></td>
                        <td><?php _e('Sort order for display', 'starcast-csv-importer'); ?></td>
                    </tr>
                    <tr>
                        <td>Provider</td>
                        <td><em>Taxonomy</em></td>
                        <td><?php _e('Provider name (lte_provider taxonomy)', 'starcast-csv-importer'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}