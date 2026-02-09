<?php if (!defined('ABSPATH')) exit;

/**
 * Template Name: Router Selection
 */

// Get package data from session storage (will be handled by JavaScript)
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<div class="router-selection-page">
    <div class="container">
        <div class="compact-header">
            <h1>Router Selection</h1>
            <div class="header-content">
                <div class="selected-package" id="selected-package-display"></div>
            </div>
        </div>
        
        <div class="router-options">
            <div class="checkbox-option">
                <input type="radio" name="router-choice" id="router-new" value="new">
                <div class="router-option-content">
                    <label for="router-new">New Router R1,599</label>
                    <div class="router-model">TP-Link MR600</div>
                </div>
                <div class="router-image-right">
                    <img src="<?php echo esc_url(site_url('/wp-content/uploads/website-images/tplink-mr600.png')); ?>" alt="TP-Link MR600" class="router-thumb-right">
                </div>
            </div>
            <div class="checkbox-option">
                <input type="radio" name="router-choice" id="router-refurbished" value="refurbished">
                <div class="router-option-content">
                    <label for="router-refurbished">Refurbished Router R749</label>
                    <div class="router-model">Any available router will be provided</div>
                </div>
            </div>
            <div class="checkbox-option">
                <input type="radio" name="router-choice" id="router-own" value="own">
                <label for="router-own">Use My Own Router</label>
            </div>
        </div>

        <div class="compatibility-section">
            <h4>Compatible with <span id="package-type-display">your package</span></h4>
            <div class="compatibility-list" id="compatibility-list"></div>
        </div>
        
        <button class="continue-btn" id="continue-btn" disabled>Continue</button>
        
        <div class="service-note">
            <div class="note-item"><strong>Location Locked:</strong> Service locked to installation address - call when moving locations.</div>
            <div class="note-item"><strong>Use My Own Router:</strong> Make sure it's in the compatible router list.</div>
        </div>
    </div>
</div>

<style>
    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #f0ebe3 0%, #e8dfd5 30%, #ede4d8 70%, #f0ebe3 100%);
        background-attachment: fixed;
        color: #2d2823;
        line-height: 1.4;
    }

    .router-selection-page {
        min-height: 100vh;
        padding: 20px 0;
    }
    
    .container {
        max-width: 600px;
        margin: 0 auto;
        padding: 0 20px;
    }
    
    .compact-header {
        text-align: center;
        margin-bottom: 15px;
    }
    
    .compact-header h1 {
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 6px;
        color: #2d2823;
    }
    
    .header-content {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        flex-wrap: wrap;
        margin-top: -2px;
    }
    
    .selected-package {
        font-size: 14px;
        color: #2d2823;
        padding: 6px 14px;
        background: rgba(74, 144, 226, 0.1);
        border: 1px solid rgba(74, 144, 226, 0.2);
        border-radius: 20px;
        display: inline-block;
    }
    
    .router-image-right {
        display: flex;
        align-items: center;
        margin-left: auto;
    }
    
    .router-thumb-right {
        width: 117px;
        height: 60px;
        object-fit: contain;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.9);
        padding: 2px;
        border: 1px solid #e0e0e0;
    }
    
    .router-options {
        margin-bottom: 25px;
    }
    
    .checkbox-option {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 0;
        border-bottom: 1px solid #eee;
    }
    
    .checkbox-option:last-child {
        border-bottom: none;
    }
    
    .checkbox-option input[type="radio"] {
        width: 24px;
        height: 24px;
        cursor: pointer;
        appearance: none;
        border: 1.5px solid #ddd;
        border-radius: 4px;
        background: white;
        position: relative;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }
    
    .checkbox-option input[type="radio"]:checked {
        background: #4a90e2;
        border-color: #4a90e2;
    }
    
    .checkbox-option input[type="radio"]:checked::after {
        content: "✓";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: white;
        font-size: 14px;
        font-weight: 600;
    }
    
    .router-option-content {
        flex: 1;
        cursor: pointer;
    }
    
    .checkbox-option label {
        font-size: 16px;
        font-weight: 500;
        color: #2d2823;
        cursor: pointer;
        flex: 1;
        display: block;
        margin-bottom: 2px;
    }
    
    .router-model {
        font-size: 13px;
        color: #6b6355;
        font-weight: 400;
        cursor: pointer;
    }
    
    
    .compatibility-section {
        background: #f5f5f7;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 25px;
    }
    
    .compatibility-section h4 {
        font-size: 14px;
        font-weight: 600;
        color: #2d2823;
        margin-bottom: 10px;
        text-transform: capitalize;
    }
    
    .compatibility-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }
    
    .device-tag {
        background: white;
        border: 1px solid #ddd;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        color: #666;
    }
    
    .continue-btn {
        width: 100%;
        padding: 14px;
        background: #d67d3e;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s ease;
        margin-bottom: 20px;
    }
    
    .continue-btn:hover:not(:disabled) {
        background: #c56d31;
    }
    
    .continue-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
    
    .service-note {
        font-size: 12px;
        color: #2d2823;
        text-align: left;
        padding: 16px;
        background: rgba(214, 125, 62, 0.1);
        border-radius: 6px;
        border: 1px solid rgba(214, 125, 62, 0.3);
    }
    
    .note-item {
        margin-bottom: 8px;
        line-height: 1.4;
    }
    
    .note-item:last-child {
        margin-bottom: 0;
    }
    
    @media (max-width: 768px) {
        .container {
            padding: 0 16px;
        }
        
        .compact-header h1 {
            font-size: 22px;
        }
        
        .checkbox-option label {
            font-size: 15px;
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Get package data from sessionStorage
    const selectedPackage = JSON.parse(sessionStorage.getItem('selectedPackage') || '{}');
    
    // Display selected package info
    function displaySelectedPackage() {
        const packageDisplay = document.getElementById('selected-package-display');
        if (Object.keys(selectedPackage).length > 0) {
            packageDisplay.innerHTML = `${selectedPackage.name || 'Package Name'} R${selectedPackage.price || '0'}pm`;
        } else {
            packageDisplay.innerHTML = 'No package selected';
        }
    }
    
    // Device compatibility data based on package type
    const deviceCompatibility = {
        'fixed-lte': [
            'Huawei B525S-23A', 'Huawei B525S-65A', 'Huawei B535-932',
            'Huawei B612-233', 'Huawei B612S-25D', 'Huawei B618S-22D',
            'ZTE MF286A', 'ZTE MF286C', 'ZTE MF296D', 'ZTE MF297D',
            'TP-Link Archer MR600', 'TP-Link Archer MR400', 'TP-Link TL-MR6400'
        ],
        '5g': [
            'Huawei 5G CPE PRO 2', 'Huawei 4G Router 3 Pro',
            'ZTE 5G CPE MC801A', 'ZTE 5G CPE MC888D', 'ZTE 5G MC888',
            'TP-Link NX510v (5G AX3000)', 'Nokia FastMile 5G Gateway 3.2',
            'Brovi 5G CPE 5 H155-381'
        ],
        'mobile-data': [
            'Any mobile device', 'Smartphone', 'Tablet', 'Mobile hotspot device',
            'USB modem', 'MiFi device', 'Laptop with SIM slot'
        ]
    };
    
    // Determine package type from package data
    function getPackageType(packageData) {
        if (!packageData || !packageData.name) return 'fixed-lte';
        
        const name = packageData.name.toLowerCase();
        const type = packageData.type ? packageData.type.toLowerCase() : '';
        
        if (name.includes('5g') || type === 'fixed-5g') return '5g';
        if (name.includes('mobile') || type === 'mobile-data') return 'mobile-data';
        return 'fixed-lte';
    }
    
    // Update compatibility list based on package type
    function updateCompatibilityList(packageData) {
        const packageType = getPackageType(packageData);
        const packageTypeDisplay = document.getElementById('package-type-display');
        const compatibilityList = document.getElementById('compatibility-list');
        
        // Update package type display
        const typeNames = {
            'fixed-lte': 'Fixed LTE/4G Packages',
            '5g': '5G Packages', 
            'mobile-data': 'Mobile Data Packages'
        };
        packageTypeDisplay.textContent = typeNames[packageType] || 'Your Package';
        
        // Update device list
        const devices = deviceCompatibility[packageType] || [];
        compatibilityList.innerHTML = devices.map(device => 
            `<span class="device-tag">${device}</span>`
        ).join('');
    }
    
    // Handle router selection
    function handleRouterSelection() {
        const radioButtons = document.querySelectorAll('input[name="router-choice"]');
        const continueBtn = document.getElementById('continue-btn');
        const headerRouterImage = document.getElementById('header-router-image');
        
        radioButtons.forEach(radio => {
            radio.addEventListener('change', function() {
                // Enable continue button when a selection is made
                continueBtn.disabled = false;
                
            });
        });
    }
    
    // Handle continue button
    function handleContinueButton() {
        const continueBtn = document.getElementById('continue-btn');
        
        continueBtn.addEventListener('click', function() {
            const selectedRadio = document.querySelector('input[name="router-choice"]:checked');
            
            if (selectedRadio) {
                const selectedOption = selectedRadio.value;
                
                // Define router data
                const routerOptions = {
                    'new': { price: 1599, description: 'New Router' },
                    'refurbished': { price: 749, description: 'Refurbished Router' },
                    'own': { price: 0, description: 'Own Compatible Router' }
                };
                
                const option = routerOptions[selectedOption];
                
                if (option) {
                    // Store router selection data
                    const routerData = {
                        option: selectedOption,
                        price: option.price,
                        description: option.description
                    };
                    
                    sessionStorage.setItem('selectedRouter', JSON.stringify(routerData));
                    
                    // Redirect to signup page
                    window.location.href = '<?php echo esc_url(site_url("/lte-signup")); ?>';
                }
            }
        });
    }
    
    // Initialize functions
    displaySelectedPackage();
    
    // Update compatibility list based on selected package
    if (selectedPackage && Object.keys(selectedPackage).length > 0) {
        updateCompatibilityList(selectedPackage);
    }
    
    handleRouterSelection();
    handleContinueButton();
});
</script>

<?php get_footer(); ?>