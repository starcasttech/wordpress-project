<?php
/**
 * Plugin Name: Facebook Ad Image Generator INTERACTIVE
 * Description: Interactive Facebook ad generator with drag-drop text editing, resizing, and full customization
 * Version: 3.0
 * Author: Starcast Technologies
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu
add_action('admin_menu', 'faig_add_admin_menu');
function faig_add_admin_menu() {
    add_menu_page(
        'FB Ad Generator',
        'FB Ad Generator',
        'manage_options',
        'facebook-ad-generator',
        'faig_admin_page',
        'dashicons-format-image',
        28
    );
}

// Main admin page
function faig_admin_page() {
    // Get all fibre packages with complete data
    $fibre_packages = get_posts(array(
        'post_type' => 'fibre_packages',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    // Get LTE packages with complete data
    $lte_packages = array();
    $lte_providers = get_terms(array(
        'taxonomy' => 'lte_provider',
        'hide_empty' => false
    ));

    foreach ($lte_providers as $provider) {
        $posts = get_posts(array(
            'post_type' => 'lte_packages',
            'numberposts' => -1,
            'tax_query' => array(array(
                'taxonomy' => 'lte_provider',
                'field' => 'slug',
                'terms' => $provider->slug
            ))
        ));
        
        foreach ($posts as $post) {
            $price = get_field('price', $post->ID);
            if (!$price) $price = get_post_meta($post->ID, 'price', true);
            if (!$price) $price = 0;
            
            $speed = get_field('speed', $post->ID);
            if (!$speed) $speed = get_post_meta($post->ID, 'speed', true);
            if (!$speed) $speed = '';
            
            $data = get_field('data', $post->ID);
            if (!$data) $data = get_post_meta($post->ID, 'data', true);
            if (!$data) $data = '';
            
            $aup = get_field('aup', $post->ID);
            if (!$aup) $aup = get_post_meta($post->ID, 'aup', true);
            if (!$aup) $aup = '';
            
            $throttle = get_field('throttle', $post->ID);
            if (!$throttle) $throttle = get_post_meta($post->ID, 'throttle', true);
            if (!$throttle) $throttle = '';
            
            $lte_packages[] = array(
                'id' => $post->ID,
                'title' => get_the_title($post),
                'provider' => $provider->name,
                'price' => intval($price),
                'speed' => $speed,
                'data' => $data,
                'aup' => $aup,
                'throttle' => $throttle,
                'type' => 'lte'
            );
        }
    }
    
    // Process fibre packages with complete data
    $processed_fibre = array();
    foreach ($fibre_packages as $package) {
        $price = get_field('price', $package->ID);
        $download = get_field('download', $package->ID);
        $upload = get_field('upload', $package->ID);
        $provider_terms = wp_get_post_terms($package->ID, 'fibre_provider');
        $provider_name = !empty($provider_terms) ? $provider_terms[0]->name : 'Unknown';
        
        if (strtolower($provider_name) === 'vumatel') {
            $provider_name = 'Vuma';
        }
        
        $data_allocation = get_field('data_allocation', $package->ID);
        if (!$data_allocation) $data_allocation = 'Unlimited';
        
        $processed_fibre[] = array(
            'id' => $package->ID,
            'title' => get_the_title($package),
            'provider' => $provider_name,
            'price' => intval($price),
            'download' => $download,
            'upload' => $upload,
            'data_allocation' => $data_allocation,
            'type' => 'fibre'
        );
    }
    ?>
    
    <div class="wrap">
        <h1 class="wp-heading-inline">🎨 Interactive Facebook Ad Generator</h1>
        <p>Drag, resize, and edit text elements to create perfect Facebook ads</p>
        
        <div class="faig-container">
            <!-- Controls Panel -->
            <div class="faig-controls">
                <div class="control-section">
                    <h3>📦 Package Selection</h3>
                    <label for="package-type">Package Type:</label>
                    <select id="package-type" onchange="updatePackageList()">
                        <option value="">Select Type</option>
                        <option value="fibre">Fibre Internet</option>
                        <option value="lte">LTE Internet</option>
                    </select>
                    
                    <label for="package-select">Package:</label>
                    <select id="package-select" onchange="updatePreview()">
                        <option value="">Select Package</option>
                    </select>
                </div>
                
                <div class="control-section">
                    <h3>🎨 Template Style</h3>
                    <div class="template-grid">
                        <div class="template-option active" data-template="modern" onclick="selectTemplate('modern')">
                            <div class="template-preview modern-preview"></div>
                            <span>Modern</span>
                        </div>
                        <div class="template-option" data-template="professional" onclick="selectTemplate('professional')">
                            <div class="template-preview professional-preview"></div>
                            <span>Professional</span>
                        </div>
                        <div class="template-option" data-template="bold" onclick="selectTemplate('bold')">
                            <div class="template-preview bold-preview"></div>
                            <span>Bold</span>
                        </div>
                        <div class="template-option" data-template="minimal" onclick="selectTemplate('minimal')">
                            <div class="template-preview minimal-preview"></div>
                            <span>Minimal</span>
                        </div>
                        <div class="template-option" data-template="neon" onclick="selectTemplate('neon')">
                            <div class="template-preview neon-preview"></div>
                            <span>Neon</span>
                        </div>
                        <div class="template-option" data-template="corporate" onclick="selectTemplate('corporate')">
                            <div class="template-preview corporate-preview"></div>
                            <span>Corporate</span>
                        </div>
                    </div>
                </div>
                
                <div class="control-section">
                    <h3>🎨 Background</h3>
                    <label>
                        <input type="radio" name="bg-type" value="gradient" checked onchange="updateBackground()"> Template Gradient
                    </label>
                    <label>
                        <input type="radio" name="bg-type" value="solid" onchange="updateBackground()"> Solid Color
                    </label>
                    <label>
                        <input type="radio" name="bg-type" value="image" onchange="updateBackground()"> Upload Image
                    </label>
                    
                    <div id="color-picker" style="margin-top: 10px; display: none;">
                        <label for="bg-color">Background Color:</label>
                        <input type="color" id="bg-color" value="#1e40af" onchange="updatePreview()">
                    </div>
                    
                    <div id="image-upload" style="display: none; margin-top: 10px;">
                        <input type="file" id="bg-image" accept="image/*" onchange="handleImageUpload(this)">
                    </div>
                </div>
                
                <div class="control-section" id="text-controls" style="display: none;">
                    <h3>✏️ Text Editor</h3>
                    <div id="selected-element-info" style="margin-bottom: 15px;">
                        <p>Click on any text element to edit it</p>
                    </div>
                    
                    <div id="text-editor" style="display: none;">
                        <label for="element-text">Text Content:</label>
                        <textarea id="element-text" rows="3" onchange="updateSelectedElement()" style="width: 100%; margin-bottom: 10px;"></textarea>
                        
                        <label for="element-font-size">Font Size:</label>
                        <input type="range" id="element-font-size" min="12" max="120" value="48" onchange="updateSelectedElement()" style="width: 100%; margin-bottom: 10px;">
                        <span id="font-size-display">48px</span>
                        
                        <label for="element-color">Text Color:</label>
                        <input type="color" id="element-color" value="#ffffff" onchange="updateSelectedElement()" style="width: 100%; margin-bottom: 10px;">
                        
                        <label for="element-font">Font Family:</label>
                        <select id="element-font" onchange="updateSelectedElement()" style="width: 100%; margin-bottom: 10px;">
                            <option value="Arial">Arial</option>
                            <option value="Arial Black">Arial Black</option>
                            <option value="Helvetica">Helvetica</option>
                            <option value="Impact">Impact</option>
                            <option value="Georgia">Georgia</option>
                            <option value="Times New Roman">Times New Roman</option>
                        </select>
                        
                        <div class="element-controls">
                            <button onclick="bringToFront()" class="btn-small">Bring to Front</button>
                            <button onclick="sendToBack()" class="btn-small">Send to Back</button>
                            <button onclick="deleteElement()" class="btn-small btn-danger">Delete</button>
                        </div>
                    </div>
                </div>
                
                <div class="control-section">
                    <h3>➕ Add Elements</h3>
                    <button onclick="addTextElement()" class="btn-add">Add Text Element</button>
                    <button onclick="resetLayout()" class="btn-reset">Reset Layout</button>
                </div>
                
                <div class="control-section">
                    <h3>📍 Logo Position</h3>
                    <select id="logo-position" onchange="updatePreview()">
                        <option value="top-left">Top Left</option>
                        <option value="top-right">Top Right</option>
                        <option value="bottom-center">Bottom Center</option>
                        <option value="bottom-left">Bottom Left</option>
                        <option value="none">No Logo Space</option>
                    </select>
                </div>
                
                <div class="control-section">
                    <h3>📐 Export</h3>
                    <select id="export-size">
                        <option value="square">Square (1080x1080)</option>
                        <option value="landscape">Landscape (1200x628)</option>
                        <option value="story">Story (1080x1920)</option>
                    </select>
                    
                    <button class="download-btn" onclick="downloadImage()" style="margin-top: 15px; width: 100%;">
                        <span class="dashicons dashicons-download"></span>
                        Download Ad Image
                    </button>
                </div>
            </div>
            
            <!-- Interactive Preview Panel -->
            <div class="faig-preview">
                <h3>🖼️ Interactive Editor</h3>
                <div class="editor-instructions">
                    <p><strong>Instructions:</strong> Click text to select • Drag to move • Use corner handles to resize • Double-click to edit</p>
                </div>
                
                <div class="preview-container">
                    <div id="canvas-container" style="position: relative; display: inline-block;">
                        <canvas id="ad-canvas" width="1080" height="1080"></canvas>
                        <div id="interactive-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"></div>
                    </div>
                </div>
                
                <div class="editor-controls">
                    <button onclick="undoAction()" class="btn-small">↶ Undo</button>
                    <button onclick="redoAction()" class="btn-small">↷ Redo</button>
                    <button onclick="centerAllElements()" class="btn-small">Center All</button>
                    <button onclick="alignHorizontally()" class="btn-small">Align Horizontal</button>
                </div>
                
                <p class="preview-note">Drag elements around, resize with corners, double-click to edit text</p>
            </div>
        </div>
    </div>
    
    <style>
    .faig-container {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 30px;
        margin-top: 20px;
    }
    
    .faig-controls {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        height: fit-content;
        border: 1px solid #e5e7eb;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .control-section {
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f3f4f6;
    }
    
    .control-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
    }
    
    .control-section h3 {
        margin: 0 0 15px 0;
        color: #1e40af;
        font-size: 1rem;
        font-weight: 600;
    }
    
    .control-section label {
        display: block;
        margin: 10px 0 5px 0;
        font-weight: 500;
        font-size: 0.9rem;
        color: #374151;
    }
    
    .control-section select,
    .control-section input[type="color"],
    .control-section textarea {
        width: 100%;
        padding: 8px;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        font-size: 14px;
    }
    
    .template-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .template-option {
        text-align: center;
        cursor: pointer;
        padding: 10px 6px;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        transition: all 0.3s ease;
        background: white;
    }
    
    .template-option:hover {
        border-color: #93c5fd;
        transform: translateY(-1px);
    }
    
    .template-option.active {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    
    .template-preview {
        width: 100%;
        height: 40px;
        margin: 0 auto 8px;
        border-radius: 4px;
    }
    
    .modern-preview { background: linear-gradient(135deg, #1e40af, #3b82f6); }
    .professional-preview { background: linear-gradient(135deg, #f8fafc, #e2e8f0); border: 1px solid #cbd5e1; }
    .bold-preview { background: linear-gradient(135deg, #dc2626, #ef4444); }
    .minimal-preview { background: linear-gradient(135deg, #ffffff, #f9fafb); border: 1px solid #e5e7eb; }
    .neon-preview { background: linear-gradient(135deg, #7c3aed, #a855f7, #ec4899); }
    .corporate-preview { background: linear-gradient(135deg, #1f2937, #374151); }
    
    .template-option span {
        font-size: 0.8rem;
        font-weight: 500;
        color: #374151;
    }
    
    .faig-preview {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        text-align: center;
        border: 1px solid #e5e7eb;
    }
    
    .faig-preview h3 {
        margin: 0 0 15px 0;
        color: #1e40af;
        font-size: 1.2rem;
        font-weight: 600;
    }
    
    .editor-instructions {
        background: #eff6ff;
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 20px;
        border: 1px solid #bfdbfe;
    }
    
    .editor-instructions p {
        margin: 0;
        font-size: 0.9rem;
        color: #1e40af;
        line-height: 1.4;
    }
    
    .preview-container {
        margin: 20px 0;
        display: flex;
        justify-content: center;
        background: #f9fafb;
        padding: 20px;
        border-radius: 10px;
    }
    
    #ad-canvas {
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        max-width: 100%;
        height: auto;
        cursor: crosshair;
    }
    
    .editor-controls {
        margin: 15px 0;
        display: flex;
        gap: 10px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .btn-small {
        padding: 6px 12px;
        font-size: 12px;
        border: 1px solid #d1d5db;
        background: white;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .btn-small:hover {
        background: #f3f4f6;
        border-color: #9ca3af;
    }
    
    .btn-add {
        width: 100%;
        padding: 10px;
        background: #10b981;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 500;
        margin-bottom: 8px;
    }
    
    .btn-reset {
        width: 100%;
        padding: 8px;
        background: #6b7280;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
    }
    
    .btn-danger {
        background: #ef4444 !important;
        color: white !important;
        border-color: #dc2626 !important;
    }
    
    .element-controls {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 5px;
        margin-top: 15px;
    }
    
    .element-controls .btn-danger {
        grid-column: 1 / -1;
    }
    
    .download-btn {
        background: linear-gradient(135deg, #10b981, #059669) !important;
        border: none !important;
        color: white !important;
        font-weight: 600 !important;
        padding: 12px 20px !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        transition: all 0.3s ease !important;
    }
    
    .download-btn:hover {
        background: linear-gradient(135deg, #059669, #047857) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3) !important;
    }
    
    .preview-note {
        font-size: 0.85rem;
        color: #6b7280;
        margin: 10px 0 0 0;
        font-style: italic;
    }
    
    input[type="radio"] {
        margin-right: 8px;
        accent-color: #3b82f6;
    }
    
    label input[type="radio"] {
        width: auto;
        display: inline;
    }
    
    #font-size-display {
        font-size: 0.9rem;
        color: #6b7280;
        margin-left: 10px;
    }
    
    /* Interactive element styles */
    .text-element {
        position: absolute;
        cursor: move;
        user-select: none;
        pointer-events: auto;
        min-width: 50px;
        min-height: 20px;
        border: 2px dashed transparent;
        transition: border-color 0.2s ease;
    }
    
    .text-element.selected {
        border-color: #3b82f6;
        background: rgba(59, 130, 246, 0.1);
    }
    
    .text-element.selected::after {
        content: '';
        position: absolute;
        width: 10px;
        height: 10px;
        background: #3b82f6;
        bottom: -5px;
        right: -5px;
        cursor: se-resize;
        border-radius: 2px;
    }
    
    @media (max-width: 1400px) {
        .faig-container {
            grid-template-columns: 1fr;
        }
        
        .template-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (max-width: 768px) {
        .template-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .faig-controls {
            padding: 20px;
        }
        
        .faig-preview {
            padding: 20px;
        }
        
        .editor-controls {
            flex-direction: column;
        }
    }
    </style>
    
    <script>
    const fibrePackages = <?php echo json_encode($processed_fibre); ?>;
    const ltePackages = <?php echo json_encode($lte_packages); ?>;
    
    let currentTemplate = 'modern';
    let currentPackage = null;
    let backgroundImage = null;
    let textElements = [];
    let selectedElement = null;
    let isDragging = false;
    let isResizing = false;
    let dragOffset = { x: 0, y: 0 };
    let undoStack = [];
    let redoStack = [];
    
    // Canvas and overlay references
    let canvas, ctx, overlay;
    let canvasScale = 1;
    
    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        canvas = document.getElementById('ad-canvas');
        ctx = canvas.getContext('2d');
        overlay = document.getElementById('interactive-overlay');
        
        // Set up event listeners
        setupCanvasEvents();
        
        drawPlaceholder();
    });
    
    function setupCanvasEvents() {
        const canvasContainer = document.getElementById('canvas-container');
        
        // Mouse events
        canvasContainer.addEventListener('mousedown', handleMouseDown);
        canvasContainer.addEventListener('mousemove', handleMouseMove);
        canvasContainer.addEventListener('mouseup', handleMouseUp);
        canvasContainer.addEventListener('click', handleCanvasClick);
        canvasContainer.addEventListener('dblclick', handleDoubleClick);
        
        // Keyboard events
        document.addEventListener('keydown', handleKeyDown);
        
        // Prevent context menu
        canvasContainer.addEventListener('contextmenu', e => e.preventDefault());
    }
    
    function updatePackageList() {
        const type = document.getElementById('package-type').value;
        const select = document.getElementById('package-select');
        
        select.innerHTML = '<option value="">Select Package</option>';
        
        const packages = type === 'fibre' ? fibrePackages : ltePackages;
        
        packages.forEach((pkg, index) => {
            const option = document.createElement('option');
            option.value = index;
            option.textContent = pkg.provider + ' - ' + pkg.title + ' - R' + pkg.price;
            select.appendChild(option);
        });
        
        updatePreview();
    }
    
    function selectTemplate(template) {
        currentTemplate = template;
        document.querySelectorAll('.template-option').forEach(opt => {
            opt.classList.remove('active');
        });
        document.querySelector(`[data-template="${template}"]`).classList.add('active');
        
        // Reset layout when changing templates
        resetLayout();
    }
    
    function updateBackground() {
        const bgType = document.querySelector('input[name="bg-type"]:checked').value;
        
        document.getElementById('color-picker').style.display = 
            bgType === 'solid' ? 'block' : 'none';
        document.getElementById('image-upload').style.display = 
            bgType === 'image' ? 'block' : 'none';
        
        updatePreview();
    }
    
    function handleImageUpload(input) {
        const file = input.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    backgroundImage = img;
                    updatePreview();
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }
    
    function updatePreview() {
        const packageType = document.getElementById('package-type').value;
        const packageIndex = document.getElementById('package-select').value;
        
        if (!packageType || packageIndex === '') {
            drawPlaceholder();
            document.getElementById('text-controls').style.display = 'none';
            return;
        }
        
        const packages = packageType === 'fibre' ? fibrePackages : ltePackages;
        currentPackage = packages[packageIndex];
        
        // Show text controls
        document.getElementById('text-controls').style.display = 'block';
        
        // Create default text elements if none exist
        if (textElements.length === 0) {
            createDefaultTextElements();
        }
        
        drawAdImage();
        updateInteractiveOverlay();
    }
    
    function createDefaultTextElements() {
        if (!currentPackage) return;
        
        textElements = [];
        
        // Speed/Service element
        let speedText;
        if (currentPackage.type === 'fibre') {
            speedText = currentPackage.download + '/' + currentPackage.upload;
        } else {
            speedText = (currentPackage.speed || 'HIGH SPEED').replace(/[^0-9]/g, '') + 'Mbps';
        }
        
        textElements.push({
            id: 'speed',
            text: speedText,
            x: 540,
            y: 200,
            fontSize: 84,
            color: '#ffffff',
            fontFamily: 'Arial',
            fontWeight: 'bold',
            textAlign: 'center',
            layer: 3
        });
        
        // Service type
        textElements.push({
            id: 'service-type',
            text: currentPackage.type === 'fibre' ? 'FIBRE INTERNET' : 'LTE INTERNET',
            x: 540,
            y: 250,
            fontSize: 36,
            color: '#ffffff',
            fontFamily: 'Arial',
            fontWeight: 'normal',
            textAlign: 'center',
            layer: 3
        });
        
        // Price
        textElements.push({
            id: 'price',
            text: 'R' + currentPackage.price,
            x: 540,
            y: 400,
            fontSize: 110,
            color: '#fbbf24',
            fontFamily: 'Arial',
            fontWeight: 'bold',
            textAlign: 'center',
            layer: 4
        });
        
        // Per month
        textElements.push({
            id: 'per-month',
            text: 'per month',
            x: 540,
            y: 440,
            fontSize: 32,
            color: '#ffffff',
            fontFamily: 'Arial',
            fontWeight: 'normal',
            textAlign: 'center',
            layer: 3
        });
        
        // Benefits
        if (currentPackage.type === 'fibre') {
            const benefits = [
                '✓ Free Router & Installation',
                '✓ ' + (currentPackage.data_allocation || 'Unlimited Data'),
                '✓ Pro Rata Rates Apply'
            ];
            
            benefits.forEach((benefit, index) => {
                textElements.push({
                    id: 'benefit-' + index,
                    text: benefit,
                    x: 540,
                    y: 600 + (index * 50),
                    fontSize: 28,
                    color: '#ffffff',
                    fontFamily: 'Arial',
                    fontWeight: 'normal',
                    textAlign: 'center',
                    layer: 2
                });
            });
        } else {
            const benefits = ['✓ Buy Optional Router', '✓ Pro Rata Rates Apply'];
            if (currentPackage.data) benefits.unshift('✓ ' + currentPackage.data);
            if (currentPackage.aup) benefits.push('✓ ' + currentPackage.aup + ' FUP');
            
            benefits.forEach((benefit, index) => {
                textElements.push({
                    id: 'benefit-' + index,
                    text: benefit,
                    x: 540,
                    y: 600 + (index * 40),
                    fontSize: 26,
                    color: '#ffffff',
                    fontFamily: 'Arial',
                    fontWeight: 'normal',
                    textAlign: 'center',
                    layer: 2
                });
            });
        }
    }
    
    function drawPlaceholder() {
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        
        ctx.fillStyle = '#64748b';
        ctx.font = 'bold 32px Arial';
        ctx.textAlign = 'center';
        ctx.fillText('SELECT A PACKAGE', canvas.width/2, canvas.height/2 - 20);
        
        ctx.font = '20px Arial';
        ctx.fillText('to start creating your ad', canvas.width/2, canvas.height/2 + 20);
    }
    
    function drawAdImage() {
        if (!currentPackage) return;
        
        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Draw background
        drawBackground();
        
        // Draw logo placeholder
        drawLogoPlaceholder();
        
        // Draw all text elements sorted by layer
        const sortedElements = [...textElements].sort((a, b) => a.layer - b.layer);
        sortedElements.forEach(element => {
            drawTextElement(element);
        });
    }
    
    function drawBackground() {
        const bgType = document.querySelector('input[name="bg-type"]:checked').value;
        const color = document.getElementById('bg-color').value;
        
        if (bgType === 'image' && backgroundImage) {
            // Draw background image
            const aspect = backgroundImage.width / backgroundImage.height;
            const canvasAspect = canvas.width / canvas.height;
            
            let drawWidth, drawHeight, offsetX = 0, offsetY = 0;
            
            if (aspect > canvasAspect) {
                drawHeight = canvas.height;
                drawWidth = drawHeight * aspect;
                offsetX = (canvas.width - drawWidth) / 2;
            } else {
                drawWidth = canvas.width;
                drawHeight = drawWidth / aspect;
                offsetY = (canvas.height - drawHeight) / 2;
            }
            
            ctx.drawImage(backgroundImage, offsetX, offsetY, drawWidth, drawHeight);
            
            // Add overlay for text readability
            ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        } else if (bgType === 'solid') {
            ctx.fillStyle = color;
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        } else {
            // Template gradients
            drawTemplateBackground();
        }
    }
    
    function drawTemplateBackground() {
        let gradient;
        
        switch(currentTemplate) {
            case 'modern':
                gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
                gradient.addColorStop(0, '#1e40af');
                gradient.addColorStop(1, '#3b82f6');
                break;
            case 'professional':
                gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
                gradient.addColorStop(0, '#f8fafc');
                gradient.addColorStop(1, '#e2e8f0');
                break;
            case 'bold':
                gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
                gradient.addColorStop(0, '#dc2626');
                gradient.addColorStop(1, '#ef4444');
                break;
            case 'minimal':
                gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
                gradient.addColorStop(0, '#ffffff');
                gradient.addColorStop(1, '#f9fafb');
                break;
            case 'neon':
                gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
                gradient.addColorStop(0, '#7c3aed');
                gradient.addColorStop(0.5, '#a855f7');
                gradient.addColorStop(1, '#ec4899');
                break;
            case 'corporate':
                gradient = ctx.createLinearGradient(0, 0, canvas.width, canvas.height);
                gradient.addColorStop(0, '#1f2937');
                gradient.addColorStop(1, '#374151');
                break;
        }
        
        ctx.fillStyle = gradient;
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
    
    function drawTextElement(element) {
        ctx.save();
        
        // Set text properties
        ctx.font = `${element.fontWeight || 'normal'} ${element.fontSize}px ${element.fontFamily}`;
        ctx.fillStyle = element.color;
        ctx.textAlign = element.textAlign || 'center';
        ctx.textBaseline = 'top';
        
        // Add text shadow for better readability
        ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
        ctx.shadowBlur = 4;
        ctx.shadowOffsetX = 2;
        ctx.shadowOffsetY = 2;
        
        // Handle multi-line text
        const lines = element.text.split('\n');
        lines.forEach((line, index) => {
            const lineY = element.y + (index * element.fontSize * 1.2);
            ctx.fillText(line, element.x, lineY);
        });
        
        ctx.restore();
    }
    
    function drawLogoPlaceholder() {
        const position = document.getElementById('logo-position').value;
        
        if (position === 'none') return;
        
        let x, y, width = 160, height = 90;
        
        switch(position) {
            case 'top-left':
                x = 40;
                y = 40;
                break;
            case 'top-right':
                x = canvas.width - width - 40;
                y = 40;
                break;
            case 'bottom-center':
                x = (canvas.width - width) / 2;
                y = canvas.height - height - 40;
                break;
            case 'bottom-left':
                x = 40;
                y = canvas.height - height - 40;
                break;
        }
        
        // Draw logo placeholder
        ctx.save();
        
        ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
        ctx.fillRect(x, y, width, height);
        
        ctx.strokeStyle = 'rgba(59, 130, 246, 0.5)';
        ctx.setLineDash([8, 4]);
        ctx.lineWidth = 2;
        ctx.strokeRect(x, y, width, height);
        
        ctx.fillStyle = 'rgba(59, 130, 246, 0.7)';
        ctx.font = 'bold 16px Arial';
        ctx.textAlign = 'center';
        ctx.setLineDash([]);
        ctx.fillText('YOUR LOGO', x + width/2, y + height/2 - 5);
        ctx.font = '12px Arial';
        ctx.fillText('SPACE', x + width/2, y + height/2 + 15);
        
        ctx.restore();
    }
    
    function updateInteractiveOverlay() {
        // Calculate canvas scale for proper mouse positioning
        const canvasRect = canvas.getBoundingClientRect();
        canvasScale = canvas.width / canvasRect.width;
        
        // Enable pointer events on overlay
        overlay.style.pointerEvents = 'auto';
    }
    
    function getMousePos(e) {
        const canvasRect = canvas.getBoundingClientRect();
        return {
            x: (e.clientX - canvasRect.left) * canvasScale,
            y: (e.clientY - canvasRect.top) * canvasScale
        };
    }
    
    function getElementAtPosition(x, y) {
        // Check elements in reverse order (top layer first)
        const sortedElements = [...textElements].sort((a, b) => b.layer - a.layer);
        
        for (let element of sortedElements) {
            const bounds = getTextBounds(element);
            if (x >= bounds.left && x <= bounds.right && 
                y >= bounds.top && y <= bounds.bottom) {
                return element;
            }
        }
        return null;
    }
    
    function getTextBounds(element) {
        // Estimate text bounds
        ctx.font = `${element.fontWeight || 'normal'} ${element.fontSize}px ${element.fontFamily}`;
        const metrics = ctx.measureText(element.text);
        const width = metrics.width;
        const height = element.fontSize;
        
        let left, right;
        if (element.textAlign === 'center') {
            left = element.x - width / 2;
            right = element.x + width / 2;
        } else if (element.textAlign === 'right') {
            left = element.x - width;
            right = element.x;
        } else {
            left = element.x;
            right = element.x + width;
        }
        
        return {
            left: left - 20, // Add padding
            right: right + 20,
            top: element.y - 10,
            bottom: element.y + height + 10
        };
    }
    
    function handleMouseDown(e) {
        const mousePos = getMousePos(e);
        const element = getElementAtPosition(mousePos.x, mousePos.y);
        
        if (element) {
            selectElement(element);
            
            // Check if clicking resize handle
            const bounds = getTextBounds(element);
            const handleX = bounds.right - 10;
            const handleY = bounds.bottom - 10;
            
            if (mousePos.x >= handleX - 10 && mousePos.x <= handleX + 10 &&
                mousePos.y >= handleY - 10 && mousePos.y <= handleY + 10) {
                isResizing = true;
            } else {
                isDragging = true;
                dragOffset.x = mousePos.x - element.x;
                dragOffset.y = mousePos.y - element.y;
            }
        } else {
            selectElement(null);
        }
        
        e.preventDefault();
    }
    
    function handleMouseMove(e) {
        if (!selectedElement) return;
        
        const mousePos = getMousePos(e);
        
        if (isDragging) {
            selectedElement.x = mousePos.x - dragOffset.x;
            selectedElement.y = mousePos.y - dragOffset.y;
            
            // Keep element within canvas bounds
            selectedElement.x = Math.max(50, Math.min(canvas.width - 50, selectedElement.x));
            selectedElement.y = Math.max(20, Math.min(canvas.height - 50, selectedElement.y));
            
            drawAdImage();
            saveState();
        } else if (isResizing) {
            const bounds = getTextBounds(selectedElement);
            const newSize = Math.max(12, selectedElement.fontSize + (mousePos.y - bounds.bottom) / 2);
            selectedElement.fontSize = Math.min(150, newSize);
            
            // Update UI slider
            document.getElementById('element-font-size').value = selectedElement.fontSize;
            document.getElementById('font-size-display').textContent = selectedElement.fontSize + 'px';
            
            drawAdImage();
            saveState();
        }
        
        // Update cursor
        if (selectedElement) {
            const bounds = getTextBounds(selectedElement);
            const handleX = bounds.right - 10;
            const handleY = bounds.bottom - 10;
            
            if (mousePos.x >= handleX - 10 && mousePos.x <= handleX + 10 &&
                mousePos.y >= handleY - 10 && mousePos.y <= handleY + 10) {
                canvas.style.cursor = 'se-resize';
            } else if (mousePos.x >= bounds.left && mousePos.x <= bounds.right &&
                       mousePos.y >= bounds.top && mousePos.y <= bounds.bottom) {
                canvas.style.cursor = 'move';
            } else {
                canvas.style.cursor = 'crosshair';
            }
        }
    }
    
    function handleMouseUp(e) {
        isDragging = false;
        isResizing = false;
        canvas.style.cursor = 'crosshair';
    }
    
    function handleCanvasClick(e) {
        // Already handled in mousedown
    }
    
    function handleDoubleClick(e) {
        const mousePos = getMousePos(e);
        const element = getElementAtPosition(mousePos.x, mousePos.y);
        
        if (element) {
            selectElement(element);
            // Focus on text input for editing
            document.getElementById('element-text').focus();
            document.getElementById('element-text').select();
        }
    }
    
    function handleKeyDown(e) {
        if (!selectedElement) return;
        
        const step = e.shiftKey ? 10 : 1;
        
        switch(e.key) {
            case 'ArrowLeft':
                selectedElement.x -= step;
                break;
            case 'ArrowRight':
                selectedElement.x += step;
                break;
            case 'ArrowUp':
                selectedElement.y -= step;
                break;
            case 'ArrowDown':
                selectedElement.y += step;
                break;
            case 'Delete':
                deleteElement();
                return;
            case 'Escape':
                selectElement(null);
                return;
            default:
                return;
        }
        
        // Keep within bounds
        selectedElement.x = Math.max(50, Math.min(canvas.width - 50, selectedElement.x));
        selectedElement.y = Math.max(20, Math.min(canvas.height - 50, selectedElement.y));
        
        drawAdImage();
        saveState();
        e.preventDefault();
    }
    
    function selectElement(element) {
        selectedElement = element;
        
        const textEditor = document.getElementById('text-editor');
        const selectedInfo = document.getElementById('selected-element-info');
        
        if (element) {
            // Show editor controls
            textEditor.style.display = 'block';
            selectedInfo.innerHTML = `<strong>Selected:</strong> ${element.id.replace('-', ' ').toUpperCase()}`;
            
            // Update form fields
            document.getElementById('element-text').value = element.text;
            document.getElementById('element-font-size').value = element.fontSize;
            document.getElementById('font-size-display').textContent = element.fontSize + 'px';
            document.getElementById('element-color').value = element.color;
            document.getElementById('element-font').value = element.fontFamily;
        } else {
            // Hide editor controls
            textEditor.style.display = 'none';
            selectedInfo.innerHTML = '<p>Click on any text element to edit it</p>';
        }
        
        drawAdImage();
    }
    
    function updateSelectedElement() {
        if (!selectedElement) return;
        
        // Update element properties from form
        selectedElement.text = document.getElementById('element-text').value;
        selectedElement.fontSize = parseInt(document.getElementById('element-font-size').value);
        selectedElement.color = document.getElementById('element-color').value;
        selectedElement.fontFamily = document.getElementById('element-font').value;
        
        // Update font size display
        document.getElementById('font-size-display').textContent = selectedElement.fontSize + 'px';
        
        drawAdImage();
        saveState();
    }
    
    function addTextElement() {
        const newElement = {
            id: 'custom-' + Date.now(),
            text: 'New Text Element',
            x: canvas.width / 2,
            y: canvas.height / 2,
            fontSize: 36,
            color: '#ffffff',
            fontFamily: 'Arial',
            fontWeight: 'normal',
            textAlign: 'center',
            layer: textElements.length + 1
        };
        
        textElements.push(newElement);
        selectElement(newElement);
        drawAdImage();
        saveState();
    }
    
    function deleteElement() {
        if (!selectedElement) return;
        
        const index = textElements.findIndex(el => el.id === selectedElement.id);
        if (index > -1) {
            textElements.splice(index, 1);
            selectElement(null);
            drawAdImage();
            saveState();
        }
    }
    
    function bringToFront() {
        if (!selectedElement) return;
        
        const maxLayer = Math.max(...textElements.map(el => el.layer));
        selectedElement.layer = maxLayer + 1;
        drawAdImage();
        saveState();
    }
    
    function sendToBack() {
        if (!selectedElement) return;
        
        const minLayer = Math.min(...textElements.map(el => el.layer));
        selectedElement.layer = minLayer - 1;
        drawAdImage();
        saveState();
    }
    
    function resetLayout() {
        textElements = [];
        selectedElement = null;
        selectElement(null);
        
        if (currentPackage) {
            createDefaultTextElements();
            drawAdImage();
        }
        
        saveState();
    }
    
    function centerAllElements() {
        textElements.forEach(element => {
            element.x = canvas.width / 2;
            element.textAlign = 'center';
        });
        drawAdImage();
        saveState();
    }
    
    function alignHorizontally() {
        if (textElements.length < 2) return;
        
        const avgY = textElements.reduce((sum, el) => sum + el.y, 0) / textElements.length;
        textElements.forEach(element => {
            element.y = avgY;
        });
        drawAdImage();
        saveState();
    }
    
    function saveState() {
        const state = {
            textElements: JSON.parse(JSON.stringify(textElements)),
            template: currentTemplate,
            package: currentPackage
        };
        
        undoStack.push(state);
        redoStack = []; // Clear redo stack when new action is performed
        
        // Limit undo stack size
        if (undoStack.length > 20) {
            undoStack.shift();
        }
    }
    
    function undoAction() {
        if (undoStack.length === 0) return;
        
        const currentState = {
            textElements: JSON.parse(JSON.stringify(textElements)),
            template: currentTemplate,
            package: currentPackage
        };
        
        redoStack.push(currentState);
        
        const previousState = undoStack.pop();
        textElements = previousState.textElements;
        
        selectElement(null);
        drawAdImage();
    }
    
    function redoAction() {
        if (redoStack.length === 0) return;
        
        const currentState = {
            textElements: JSON.parse(JSON.stringify(textElements)),
            template: currentTemplate,
            package: currentPackage
        };
        
        undoStack.push(currentState);
        
        const nextState = redoStack.pop();
        textElements = nextState.textElements;
        
        selectElement(null);
        drawAdImage();
    }
    
    function downloadImage() {
        if (!currentPackage) {
            alert('Please select a package first!');
            return;
        }
        
        const size = document.getElementById('export-size').value;
        const exportCanvas = document.createElement('canvas');
        const exportCtx = exportCanvas.getContext('2d');
        
        // Set dimensions
        switch(size) {
            case 'square':
                exportCanvas.width = 1080;
                exportCanvas.height = 1080;
                break;
            case 'landscape':
                exportCanvas.width = 1200;
                exportCanvas.height = 628;
                break;
            case 'story':
                exportCanvas.width = 1080;
                exportCanvas.height = 1920;
                break;
        }
        
        // Store original canvas context
        const originalCtx = ctx;
        ctx = exportCtx;
        
        // Temporarily resize canvas
        const originalWidth = canvas.width;
        const originalHeight = canvas.height;
        canvas.width = exportCanvas.width;
        canvas.height = exportCanvas.height;
        
        // Scale text elements for export size
        const scaleX = exportCanvas.width / originalWidth;
        const scaleY = exportCanvas.height / originalHeight;
        
        textElements.forEach(element => {
            element.x *= scaleX;
            element.y *= scaleY;
            element.fontSize *= Math.min(scaleX, scaleY);
        });
        
        // Draw at export size
        drawAdImage();
        
        // Copy to export canvas
        exportCtx.drawImage(canvas, 0, 0);
        
        // Restore original sizes
        textElements.forEach(element => {
            element.x /= scaleX;
            element.y /= scaleY;
            element.fontSize /= Math.min(scaleX, scaleY);
        });
        
        canvas.width = originalWidth;
        canvas.height = originalHeight;
        ctx = originalCtx;
        drawAdImage(); // Redraw at original size
        
        // Generate filename and download
        const packageName = currentPackage.provider.replace(/\s+/g, '_') + 
                           '_' + currentPackage.price + 
                           '_' + currentTemplate;
        const filename = packageName + '_' + size + '_interactive.png';
        
        const link = document.createElement('a');
        link.download = filename;
        link.href = exportCanvas.toDataURL('image/png', 1.0);
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        console.log('Downloaded:', filename);
    }
    </script>
    <?php
}
?>
