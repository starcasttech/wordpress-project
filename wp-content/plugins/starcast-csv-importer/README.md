# Starcast CSV Importer Plugin

A powerful WordPress plugin for importing and updating Fibre and LTE packages from CSV files without deleting existing products.

## Features

- **Non-destructive Updates**: Updates existing packages without deleting them
- **Batch Processing**: Prevents timeouts by processing data in configurable batches
- **Field Mapping**: Visual interface for mapping CSV columns to WordPress fields
- **Provider Management**: Automatically creates missing provider taxonomy terms
- **Import History**: Track all imports with detailed statistics
- **Saved Mappings**: Save and reuse field mappings for consistent imports
- **Error Handling**: Comprehensive error logging and validation
- **Progress Tracking**: Real-time import progress with statistics

## Installation

1. Create a new folder called `starcast-csv-importer` in your WordPress `wp-content/plugins/` directory
2. Add all the plugin files to this folder:
   - `starcast-csv-importer.php` (main plugin file)
   - `includes/class-csv-processor.php`
   - `includes/class-admin-page.php`
   - `includes/class-ajax-handler.php`
   - `includes/class-import-history.php`
   - `assets/css/admin.css`
   - `assets/js/admin.js`
3. Activate the plugin through the WordPress admin panel

## File Structure

```
starcast-csv-importer/
├── starcast-csv-importer.php      # Main plugin file
├── includes/
│   ├── class-csv-processor.php    # Core CSV processing logic
│   ├── class-admin-page.php       # Admin interface
│   ├── class-ajax-handler.php     # AJAX request handlers
│   └── class-import-history.php   # Import history management
├── assets/
│   ├── css/
│   │   └── admin.css             # Admin styles
│   └── js/
│       └── admin.js              # Admin JavaScript
└── README.md                     # This file
```

## CSV File Format

### Fibre Packages CSV Example

```csv
Provider,Fibre Product Name,Download Speed,Upload Speed,Retail Price
Vodacom,Fibre 50/25,50Mbps,25Mbps,599
Vodacom,Fibre 100/50,100Mbps,50Mbps,899
MTN,Fibre 200/100,200Mbps,100Mbps,1299
```

### LTE Packages CSV Example

```csv
Provider,Package Name,Speed,Data,AUP,Throttle,Price,Package Type
Vodacom,Fixed LTE 25GB,30Mbps,25GB,,,269,fixed-lte
MTN,Fixed LTE Uncapped,50Mbps,Uncapped,200GB,2Mbps,599,fixed-lte
Telkom,Mobile Data 10GB,,10GB,,,149,mobile-data
```

## Usage Guide

### Step 1: Upload CSV File

1. Navigate to **CSV Importer** in your WordPress admin menu
2. Drag and drop your CSV file or click "Browse Files"
3. The plugin will validate your file and show a preview

### Step 2: Configure Import

1. Select the package type (Fibre Packages or LTE Packages)
2. Choose import options:
   - **Update existing packages**: Updates packages that already exist
   - **Create missing provider terms**: Automatically creates new providers
   - **Skip duplicate packages**: Skips packages that already exist
3. Select how to identify existing packages:
   - By package title (default)
   - By SKU
   - By custom field

### Step 3: Map Fields

1. Map your CSV columns to WordPress fields
2. Available fields for **Fibre Packages**:
   - Basic: Title, Description
   - Provider: Fibre Provider (taxonomy)
   - Custom Fields: Download Speed, Upload Speed, Price

3. Available fields for **LTE Packages**:
   - Basic: Title, Description
   - Provider: LTE Provider (taxonomy)
   - Custom Fields: Speed, Data, AUP, Throttle, Price, Package Type, Display Order

4. Save your mapping for future use (optional)

### Step 4: Import

1. Click "Start Import" to begin
2. Monitor progress in real-time
3. View statistics: Imported, Updated, Skipped, Errors
4. Check the import log for detailed information

## Field Mapping Reference

### Special Mapping Features

1. **Multiple Column Fallback**: Use `||` to specify fallback columns
   ```
   Column1 || Column2 || Column3
   ```

2. **Template Syntax**: Use placeholders to combine multiple columns
   ```
   {{Provider}} - {{Speed}} Package
   ```

### Data Cleaning

The plugin automatically cleans data:
- **Prices**: Removes currency symbols (R, $, €, £)
- **Speeds**: Extracts numeric values from "50Mbps" → "50"
- **Data Values**: Standardizes "unlimited" → "Uncapped"

## Settings

Access settings via **CSV Importer → Settings**:

- **Batch Size**: Number of rows processed per batch (10-500)
- **Timeout Prevention**: Enable to prevent server timeouts
- **Default Import Options**: Set default behaviors

## Import History

View all past imports via **CSV Importer → Import History**:
- Import date and time
- File name and type
- Statistics (imported, updated, skipped, errors)
- User who performed the import
- Import status

## Troubleshooting

### Common Issues

1. **Timeout Errors**
   - Reduce batch size in settings
   - Enable timeout prevention
   - Check server max_execution_time

2. **Memory Errors**
   - Increase PHP memory_limit
   - Process smaller CSV files
   - Reduce batch size

3. **Missing Providers**
   - Enable "Create missing provider terms"
   - Check provider taxonomy exists
   - Verify provider names match exactly

### Server Requirements

- PHP 7.0 or higher
- WordPress 5.0 or higher
- Maximum execution time: 300 seconds (recommended)
- Memory limit: 256MB (recommended)

## Hooks and Filters

### Actions

```php
// After each row is imported
do_action('starcast_csv_after_import_row', $post_id, $row_data, $post_type, $mapping);
```

### Filters

```php
// Modify mapping fields
add_filter('starcast_csv_mapping_fields', function($fields, $post_type) {
    // Add custom fields
    return $fields;
}, 10, 2);
```

## Support

For issues or feature requests, please contact the development team.

## Changelog

### Version 1.0.0
- Initial release
- Support for Fibre and LTE package imports
- Batch processing to prevent timeouts
- Field mapping interface
- Import history tracking
- Saved mapping templates