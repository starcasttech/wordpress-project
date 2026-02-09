# WordPress ISP Site - Claude Code Reference

**Project:** WordPress ISP (Internet Service Provider) website  
**Deployment:** Railway (via GitHub)  
**Development:** Docker (local) + WSL (Ubuntu)

---

## 🎯 Project Goals

Build and maintain a professional ISP website with WordPress. Claude Code should:
1. Write clean, secure, production-ready PHP/WordPress code
2. Follow WordPress coding standards and best practices
3. Implement features that work on Railway's infrastructure
4. Never commit secrets or sensitive data

---

## 🔧 Development Environment

### Quick Commands

```bash
# Start local dev (Docker)
sudo docker compose up -d

# Stop local dev
sudo docker compose down

# Access points
# - WordPress: http://localhost:8090
# - phpMyAdmin: http://localhost:8081

# Build commands
npm run build              # Build theme assets (if applicable)
composer install           # Install PHP dependencies

# Test commands
./vendor/bin/phpunit       # Run PHP tests
npm run test               # Run JS tests

# Lint/Check
./vendor/bin/phpcs         # Check PHP code style
npm run lint               # Check JS/CSS
```

### Repository Structure

```
wordpress/
├── wp-content/
│   ├── themes/
│   │   └── starcast-kadence-child/    # Custom theme (EDIT THIS)
│   ├── plugins/                        # Plugins (careful with edits)
│   └── uploads/                        # Media (gitignored)
├── wp-admin/                           # WP core (DON'T EDIT)
├── wp-includes/                        # WP core (DON'T EDIT)
├── CLAUDE.md                           # This file
├── .gitignore                          # Security critical
├── docker-compose.yml                  # Local dev only
└── README-DOCKER.md                    # Docker docs
```

**⚠️ NEVER edit WordPress core files** (wp-admin, wp-includes). Use child themes and plugins instead.

---

## 🛡️ Security & Secrets

### Sensitive Files Location

**ALL sensitive docs, API keys, and credentials are in:**  
`/home/leonard/projects/wordpress-private/`

- `api-docs/` - API keys and integration docs
- `build-docs/` - Internal build notes
- `credentials/` - .env files, passwords

**When you need API keys or credentials:**  
1. Check `/home/leonard/projects/wordpress-private/credentials/`
2. NEVER hardcode secrets in code
3. Use environment variables: `getenv('VARIABLE_NAME')`

### Pre-Commit Safety

```bash
# Run BEFORE every commit
git status
git check-ignore -v .env wp-config.php
git ls-files | grep -E '\.env|\.sql|BUILD|API|SECRET' && echo "❌ SENSITIVE FILE FOUND" || echo "✅ Clean"
```

**NEVER commit:**
- `.env` files (real credentials)
- `wp-config.php` (database passwords)
- `*.sql` files (database dumps)
- Backup files (`*.zip`, `*.bak`)
- API keys or tokens

---

## 📝 WordPress Code Style

### PHP Standards

```php
<?php
/**
 * Use WordPress Coding Standards
 * See: https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/
 */

// ✅ Good: WordPress naming conventions
function starcast_get_user_data( $user_id ) {
    if ( ! $user_id ) {
        return false;
    }
    return get_userdata( $user_id );
}

// ✅ Good: Escape output
echo esc_html( $user_name );
echo esc_url( $link );

// ✅ Good: Prepare database queries
global $wpdb;
$results = $wpdb->get_results( 
    $wpdb->prepare( 
        "SELECT * FROM $wpdb->posts WHERE post_status = %s",
        'publish'
    )
);

// ❌ Bad: Never use raw SQL without prepare()
// $results = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE ID = $id" );
```

### Hooks & Filters

```php
// ✅ Use actions and filters for WordPress integration
add_action( 'init', 'starcast_register_post_types' );
add_filter( 'the_content', 'starcast_modify_content' );

// ✅ Use priority and arg count when needed
add_action( 'wp_enqueue_scripts', 'starcast_enqueue_assets', 20, 1 );
```

### Child Theme Pattern

**Always work in the child theme:** `wp-content/themes/starcast-kadence-child/`

```php
// functions.php
<?php
// Load parent theme styles first
add_action( 'wp_enqueue_scripts', 'starcast_enqueue_parent_styles' );
function starcast_enqueue_parent_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

// Then add your customizations
add_action( 'wp_enqueue_scripts', 'starcast_enqueue_custom_assets' );
function starcast_enqueue_custom_assets() {
    wp_enqueue_style( 
        'starcast-custom', 
        get_stylesheet_directory_uri() . '/assets/css/custom.css',
        array( 'parent-style' ),
        '1.0.0'
    );
}
```

---

## 🧪 Verification & Testing

### Verify Your Work

**After making changes, Claude should:**

1. **Test Locally**
   ```bash
   # Start Docker if not running
   sudo docker compose up -d
   
   # Visit http://localhost:8090
   # Test the feature/page you just built
   ```

2. **Check for PHP Errors**
   ```bash
   # Enable WP_DEBUG in wp-config-local.php
   # Check error_log or debug.log
   tail -f wp-content/debug.log
   ```

3. **Run Linters**
   ```bash
   # PHP CodeSniffer (if installed)
   ./vendor/bin/phpcs wp-content/themes/starcast-kadence-child/
   ```

4. **Confirm Changes Work**
   - Take a screenshot (if UI)
   - Run test cases
   - Check browser console for JS errors
   - Test responsiveness on mobile

---

## 🏗️ WordPress-Specific Workflows

### Adding a New Feature

```
1. Plan Phase (use Plan Mode)
   - Identify files to modify
   - Check existing patterns in child theme
   - List required hooks/filters

2. Implement Phase
   - Edit child theme files ONLY
   - Follow WordPress coding standards
   - Add comments for complex logic

3. Verification Phase
   - Test locally on http://localhost:8090
   - Check debug.log for errors
   - Verify responsive design
   - Test with different user roles

4. Commit Phase
   - Run pre-commit safety check
   - Write clear commit message
   - Push to GitHub (triggers Railway deploy)
```

### Common WordPress Tasks

**Add a Custom Post Type:**
```php
// In child theme functions.php
function starcast_register_services_cpt() {
    register_post_type( 'service', array(
        'labels'      => array(
            'name'          => __( 'Services' ),
            'singular_name' => __( 'Service' )
        ),
        'public'      => true,
        'has_archive' => true,
        'supports'    => array( 'title', 'editor', 'thumbnail' ),
        'rewrite'     => array( 'slug' => 'services' ),
    ));
}
add_action( 'init', 'starcast_register_services_cpt' );
```

**Add a Shortcode:**
```php
function starcast_pricing_table_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'plan' => 'basic',
    ), $atts );
    
    ob_start();
    ?>
    <div class="pricing-table">
        <!-- Your HTML here -->
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'pricing_table', 'starcast_pricing_table_shortcode' );
```

**Add a Custom Widget:**
```php
class Starcast_Custom_Widget extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'starcast_custom_widget',
            __( 'Starcast Custom Widget', 'starcast' ),
            array( 'description' => __( 'A custom widget', 'starcast' ) )
        );
    }
    
    // Implement widget(), form(), update() methods
}

function starcast_register_widgets() {
    register_widget( 'Starcast_Custom_Widget' );
}
add_action( 'widgets_init', 'starcast_register_widgets' );
```

---

## 🚀 Deployment (Railway)

### What Happens on Push

```
git push origin main
    ↓
GitHub webhook → Railway
    ↓
Railway deploys WordPress files
    ↓
Environment variables injected
    ↓
Live on Railway URL
```

### Railway Environment Variables

**Set in Railway dashboard:**
- `WORDPRESS_DB_HOST` - Database host
- `WORDPRESS_DB_NAME` - Database name  
- `WORDPRESS_DB_USER` - Database user
- `WORDPRESS_DB_PASSWORD` - Database password
- `WORDPRESS_DEBUG` - Should be `false` in production
- `WP_ENVIRONMENT_TYPE` - Set to `production`

**API Keys (if needed):**
- Check `/home/leonard/projects/wordpress-private/credentials/`
- Add to Railway dashboard, NEVER commit to git

---

## 🧠 WordPress Knowledge

### Architecture

- **WordPress Core:** Don't modify (`wp-admin`, `wp-includes`)
- **Child Theme:** Your customizations (`wp-content/themes/starcast-kadence-child/`)
- **Plugins:** Use carefully, prefer well-maintained plugins
- **Database:** WordPress uses `wp_` prefixed tables

### Hooks

- **Actions:** Do something at a specific point (`do_action()`)
- **Filters:** Modify data (`apply_filters()`)
- **Priority:** Lower numbers run first (default: 10)
- **Args:** Number of parameters your callback accepts

### Template Hierarchy

WordPress loads templates in this order:
1. Child theme template
2. Parent theme template  
3. WordPress core fallback

Common templates:
- `index.php` - Main template
- `single.php` - Single post
- `page.php` - Pages
- `archive.php` - Archives
- `header.php`, `footer.php`, `sidebar.php` - Partials

### Security Functions

Always escape output:
- `esc_html()` - Plain text
- `esc_attr()` - HTML attributes
- `esc_url()` - URLs
- `esc_js()` - JavaScript strings
- `wp_kses_post()` - Allow safe HTML

Always sanitize input:
- `sanitize_text_field()` - Single-line text
- `sanitize_textarea_field()` - Multi-line text
- `sanitize_email()` - Email addresses
- `absint()` - Positive integers

Check capabilities:
```php
if ( current_user_can( 'edit_posts' ) ) {
    // User can edit posts
}
```

Nonces for form security:
```php
// Create nonce
wp_nonce_field( 'my_action', 'my_nonce' );

// Verify nonce
if ( ! wp_verify_nonce( $_POST['my_nonce'], 'my_action' ) ) {
    die( 'Security check failed' );
}
```

---

## ❌ Common WordPress Mistakes to Avoid

1. **Don't modify WordPress core files**  
   ✅ Use child themes and plugins instead

2. **Don't use deprecated functions**  
   ✅ Check WordPress docs for current alternatives

3. **Don't trust user input**  
   ✅ Always sanitize input and escape output

4. **Don't make direct database queries without `$wpdb->prepare()`**  
   ✅ Use prepared statements to prevent SQL injection

5. **Don't hardcode URLs**  
   ✅ Use `get_site_url()`, `home_url()`, etc.

6. **Don't enqueue scripts/styles in the wrong hook**  
   ✅ Use `wp_enqueue_scripts` action for frontend, `admin_enqueue_scripts` for admin

7. **Don't forget to flush rewrite rules after CPT changes**  
   ✅ Visit Settings → Permalinks or use `flush_rewrite_rules()` (carefully)

---

## 📚 Reference Links

- WordPress Codex: https://codex.wordpress.org/
- Developer Handbook: https://developer.wordpress.org/
- Coding Standards: https://developer.wordpress.org/coding-standards/
- Plugin Handbook: https://developer.wordpress.org/plugins/
- Theme Handbook: https://developer.wordpress.org/themes/
- WP CLI: https://wp-cli.org/

---

## 💡 Tips for Claude Code

**When implementing WordPress features:**
1. Explore first: Check existing child theme files for patterns
2. Plan: List files to modify, hooks to use, functions to call
3. Implement: Write code following WordPress standards
4. Verify: Test locally, check logs, confirm it works

**When stuck:**
- Read child theme's existing code for patterns
- Check WordPress Codex/Developer docs
- Look at parent theme (Kadence) for examples
- Use WordPress CLI if available: `wp plugin list`, `wp theme list`, etc.

**When deploying:**
- Always run pre-commit safety check
- Test locally first
- Write clear commit messages
- Monitor Railway deployment logs after push

---

**Last Updated:** 2026-02-09  
**Maintainer:** Leonard Roelofse  
**For sensitive docs/API keys:** See `/home/leonard/projects/wordpress-private/`
