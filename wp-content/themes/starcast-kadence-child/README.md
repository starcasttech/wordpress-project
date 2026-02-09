# 🚀 Starcast Pro - Professional ISP Theme

**Version:** 2.0.0
**Parent Theme:** Kadence
**Author:** Starcast Technologies

A modern, high-performance WordPress theme designed specifically for ISP and telecommunications providers. Inspired by industry leaders like MWEB, this theme features advanced filtering, coverage checking, and a conversion-optimized design.

---

## ✨ Features

### 🎨 Design & UX
- **Modern Color Scheme**: Professional dark blue + premium gold accents
- **MWEB-Inspired Layout**: Industry-leading design patterns
- **Responsive Design**: Perfect on all devices (mobile, tablet, desktop)
- **Smooth Animations**: Fade-in effects and hover states
- **Professional Typography**: Inter font family for clarity

### 📦 Package Management
- **Advanced Filtering**: Filter by provider, speed, price, data
- **Real-time Sorting**: Sort by price, speed, popularity
- **Package Cards**: Modern cards with badges (HOT DEAL, RECOMMENDED, BEST VALUE)
- **Dynamic Display**: Automatic package categorization

### 🔍 Coverage Checker
- **Address Search**: Check availability by address
- **AJAX-Powered**: Instant results without page reload
- **Coverage Display**: Shows available packages by location

### 🎯 Conversion Optimization
- **Trust Indicators**: 7-day installation, 24/7 support badges
- **Promotional Banners**: Eye-catching deal announcements
- **Clear CTAs**: Prominent "Get This Package" buttons
- **Social Proof**: Trust bar with key selling points

### ⚡ Performance
- **Lightweight**: Minimal code, fast loading
- **Optimized CSS**: Modern CSS custom properties
- **Cached Assets**: Browser caching for faster loads
- **Mobile-First**: Progressive enhancement approach

---

## 📋 Requirements

- ✅ WordPress 6.3+
- ✅ PHP 7.4+
- ✅ Kadence Theme (parent theme) - Install first!
- ✅ Custom Post Types: `fibre-packages`, `lte-packages`
- ✅ jQuery (included in WordPress core)

---

## 🚀 Installation

### Step 1: Install Parent Theme

1. Download **Kadence** theme from [WordPress.org](https://wordpress.org/themes/kadence/)
2. Go to **Appearance > Themes > Add New > Upload Theme**
3. Upload `kadence.zip` and activate

### Step 2: Install Starcast Pro Child Theme

1. **Via WordPress Admin (Recommended)**:
   - Go to **Appearance > Themes > Add New > Upload Theme**
   - Upload `starcast-kadence-child.zip`
   - Click **Activate**

2. **Via FTP**:
   ```bash
   # Upload the entire starcast-kadence-child folder to:
   /wp-content/themes/starcast-kadence-child/

   # Then activate via WordPress admin
   ```

3. **Via Command Line**:
   ```bash
   cd /var/www/html-staging/wp-content/themes/
   cp -r /home/admin/wordpress/starcast-kadence-child .
   chown -R www-data:www-data starcast-kadence-child

   # Activate via WP-CLI
   wp theme activate starcast-kadence-child
   ```

### Step 3: Configure Pages

Create the following pages and assign templates:

1. **Homepage**:
   - Create page: "Home"
   - Template: "Starcast Pro - Homepage"
   - Set as **Settings > Reading > Homepage**

2. **Fibre Packages**:
   - Create page: "Fibre"
   - Slug: `fibre`
   - Template: "Starcast Pro - Fibre Packages"

3. **LTE Packages**:
   - Create page: "LTE & 5G"
   - Slug: `lte-5g`
   - Template: "Starcast Pro - LTE Packages"

### Step 4: Navigation Setup

**Appearance > Menus**:

Create a main menu with:
- Home
- Fibre Packages (/fibre)
- LTE & 5G (/lte-5g)
- On-Site Technician (/booking)
- Shop (if using WooCommerce)

Assign to **Primary Navigation**

---

## 🎨 Customization

### Color Scheme

Edit `style.css` and modify CSS variables:

```css
:root {
    --starcast-primary: #0A2540;     /* Your brand color */
    --starcast-accent: #FFD700;      /* Accent/CTA color */
    --starcast-secondary: #00C9FF;   /* Secondary accent */
}
```

### Utility Bar Links

Edit `functions.php` around line 67:

```php
function starcast_utility_bar() {
    // Customize links here
}
```

### Package Badges

Badges are automatically assigned based on:
- **HOT DEAL**: Every 5th package (customizable)
- **RECOMMENDED**: Every 3rd package
- **BEST VALUE**: Packages under R500
- **FASTEST**: Packages with 1000+ Mbps

Edit logic in template files to customize.

---

## 🔧 Shortcodes

### Hero Section
```
[starcast_hero
    title="Your Title"
    accent="Accent Text"
    subtitle="Subtitle"]
```

### Coverage Checker
```
[starcast_coverage_checker]
```

### Trust Bar
```
[starcast_trust_bar]
```

### Package Display (Legacy)
```
[starcast_packages type="fibre" featured="yes" limit="6"]
```

---

## 📱 Responsive Breakpoints

- **Desktop**: 1366px max-width
- **Tablet**: 768px breakpoint
- **Mobile**: < 768px (single column grid)

---

## 🎯 Best Practices

### For Maximum Conversions:

1. **Use High-Quality Images**: Add hero images to homepage
2. **Update Promo Banners**: Keep deals fresh and relevant
3. **Highlight Trust Signals**: Emphasize local service, fast installation
4. **Test CTAs**: A/B test button colors and copy
5. **Mobile Optimization**: 60%+ of traffic is mobile

### SEO Tips:

1. **Page Titles**: Include keywords like "Fibre Internet [City]"
2. **Meta Descriptions**: Write compelling 155-character descriptions
3. **Alt Text**: Add to all package provider logos
4. **Schema Markup**: Consider adding Product schema

---

## 🐛 Troubleshooting

### Issue: Theme looks broken after activation

**Solution**: Make sure Kadence parent theme is installed and activated first!

```bash
# Check active theme
wp theme list --status=active

# Should see: kadence (parent)
# And: starcast-kadence-child (active)
```

### Issue: Filters not working

**Solution**: Check jQuery is loaded:

```bash
# View source and search for "jquery"
# Should see: wp-includes/js/jquery/jquery.min.js
```

### Issue: Packages not displaying

**Solution**: Check custom post types exist:

```bash
wp post-type list

# Should see: fibre-packages, lte-packages
```

### Issue: Coverage checker not working

**Solution**: Check AJAX is configured:

1. View browser console (F12)
2. Look for JavaScript errors
3. Check `starcastData` object exists:
   ```javascript
   console.log(starcastData);
   // Should show: {ajaxurl: "...", nonce: "...", siteUrl: "..."}
   ```

---

## 📂 File Structure

```
starcast-kadence-child/
├── style.css                    # Main stylesheet with all design
├── functions.php                # Theme functions & features
├── README.md                    # This file
├── screenshot.png               # Theme screenshot (optional)
├── assets/
│   ├── css/                     # Additional stylesheets (future)
│   └── js/
│       └── starcast.js          # Interactive features
├── templates/
│   ├── template-homepage.php    # Homepage template
│   ├── template-fibre-packages.php   # Fibre packages page
│   └── template-lte-packages.php     # LTE packages page
└── inc/                         # Additional PHP includes (future)
```

---

## 🔄 Updates & Maintenance

### Updating the Theme:

1. **Always backup first!**
   ```bash
   wp db export backup-$(date +%Y%m%d).sql
   ```

2. **Deactivate and delete old version**
3. **Upload and activate new version**
4. **Test all pages**

### Keeping Parent Theme Updated:

- Check for Kadence updates monthly
- Test on staging before updating production

---

## 💡 Tips for Success

### Homepage Optimization:
- Add customer testimonials
- Include coverage map
- Show latest deals
- Add FAQ section

### Package Pages:
- Keep packages up to date
- Remove old/unavailable packages
- Test filters regularly
- Monitor conversion rates

### Performance:
- Use image optimization plugins (Smush, ShortPixel)
- Enable caching (W3 Total Cache, WP Rocket)
- Use CDN for assets
- Minimize plugins

---

## 🎓 Advanced Customization

### Adding New Badge Types:

Edit template files (template-fibre-packages.php):

```php
// Add custom badge logic
if ($custom_condition) {
    $badge = 'NEW';
    $badge_class = 'starcast-badge-deal';
}
```

### Custom Coverage Integration:

Edit `functions.php` around line 350:

```php
function starcast_check_coverage_ajax() {
    // Add real API integration here
    // Example: Check fibre provider APIs
}
```

### Adding Social Proof:

Add to homepage template:

```html
<div class="starcast-social-proof">
    <div class="stat">
        <strong>10,000+</strong>
        <span>Happy Customers</span>
    </div>
</div>
```

---

## 📞 Support

For theme support:
- **Email**: starcast.tech@gmail.com
- **Documentation**: This README
- **WordPress**: Check parent theme (Kadence) documentation

---

## 📜 License

This theme inherits the GPL v2 license from WordPress and Kadence.

- **Theme License**: GPL v2 or later
- **Font (Inter)**: SIL Open Font License
- **Parent Theme**: Kadence (GPL v3)

---

## 🎉 Credits

- **Design Inspiration**: MWEB.co.za
- **Parent Theme**: Kadence by Kadence WP
- **Icons**: Unicode emoji (system)
- **Fonts**: Google Fonts (Inter)

---

## 🚀 What's Next?

### Phase 2 Features (Future):
- [ ] Real coverage API integration
- [ ] Customer reviews system
- [ ] Package comparison tool
- [ ] Live chat integration
- [ ] Multi-language support
- [ ] Advanced analytics
- [ ] Mobile app integration

---

**Built with ❤️ for Starcast Technologies**

*Version 2.0.0 - January 2026*
