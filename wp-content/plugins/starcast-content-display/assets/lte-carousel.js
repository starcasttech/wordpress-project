  document.addEventListener('DOMContentLoaded', () => {
    const providers = (window.starcastLteData && window.starcastLteData.providers) ? window.starcastLteData.providers : [];
    const signupUrl = (window.starcastLteData && window.starcastLteData.signupUrl) ? window.starcastLteData.signupUrl : '/router-selection/';
    const debugEnabled = !!(window.starcastLteData && window.starcastLteData.debug);
    const packageDisplay = document.getElementById('provider-package-display');

    if (!packageDisplay) {
      return;
    }

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function sanitizeUrl(value) {
      try {
        const url = new URL(String(value), window.location.origin);
        if (url.protocol === 'http:' || url.protocol === 'https:') {
          return url.href;
        }
      } catch (error) {
        return '';
      }
      return '';
    }

    // Handle empty providers case
    if (!providers || providers.length === 0) {
      packageDisplay.textContent = 'No providers available at the moment.';
      return;
    }

    // Build package info panel as a DOM element (safe, no innerHTML for dynamic data)
    function buildPackageInfoEl(pkg) {
      const panel = document.createElement('div');
      panel.className = 'pkg-info-panel';

      const rows = [
        { label: 'Data',          value: pkg.data    || '' },
        { label: 'Speed',         value: pkg.speed   ? (pkg.speed + (String(pkg.speed).toLowerCase().includes('mbps') ? '' : ' Mbps')) : '' },
        { label: 'AUP',           value: pkg.aup     || '', extraClass: 'pkg-info-aup' },
        { label: 'Speed after AUP', value: pkg.throttle || '' },
      ];

      rows.forEach(function(row) {
        if (!row.value) return;
        const rowEl = document.createElement('div');
        rowEl.className = 'pkg-info-row' + (row.extraClass ? ' ' + row.extraClass : '');

        const labelEl = document.createElement('span');
        labelEl.className = 'pkg-info-label';
        labelEl.textContent = row.label;

        const valueEl = document.createElement('span');
        valueEl.className = 'pkg-info-value';
        valueEl.textContent = row.value;

        rowEl.appendChild(labelEl);
        rowEl.appendChild(valueEl);
        panel.appendChild(rowEl);
      });

      return panel;
    }

    // Create all provider cards
    function createAllProviderCards() {
      packageDisplay.textContent = '';

      providers.forEach((provider, providerIndex) => {
        const packages = provider && provider.packages ? provider.packages : [];

        if (!packages.length) return;

        // Sort: Fixed first, then Uncapped, 5G, Mobile, others — then by price
        const sortedPackages = packages.slice().sort(function(a, b) {
          function priority(pkg) {
            const n = pkg.name.toLowerCase().replace(/^(vodacom|mtn|telkom)\s+/i, '').trim();
            if (n.includes('fixed'))    return 1;
            if (n.includes('uncapped') || (pkg.data && pkg.data.toLowerCase().includes('uncapped'))) return 2;
            if (n.includes('5g'))       return 3;
            if (n.includes('mobile'))   return 4;
            return 5;
          }
          const pa = priority(a), pb = priority(b);
          return pa !== pb ? pa - pb : a.price - b.price;
        });

        let selectedPackage = sortedPackages[0];
        const providerName = provider.name || '';
        const providerLogo = sanitizeUrl(provider.logo || '');

        const providerCard = document.createElement('div');
        providerCard.className = 'provider-package-card';
        providerCard.dataset.providerIndex = providerIndex;

        // --- Logo / provider name ---
        const nameDiv = document.createElement('div');
        nameDiv.className = 'provider-name-main';
        nameDiv.textContent = providerName;

        const logoArea = document.createElement('div');
        logoArea.className = 'provider-logo-area';

        if (providerLogo) {
          const img = document.createElement('img');
          img.src = providerLogo;
          img.alt = providerName;
          img.className = 'provider-logo-main';
          img.onerror = function() {
            this.style.display = 'none';
            logoArea.appendChild(nameDiv);
          };
          logoArea.appendChild(img);
        } else {
          logoArea.appendChild(nameDiv);
        }
        providerCard.appendChild(logoArea);

        // --- Package name ---
        const packageNameEl = document.createElement('div');
        packageNameEl.className = 'package-name-display';
        packageNameEl.textContent = selectedPackage.name || '';
        providerCard.appendChild(packageNameEl);

        // --- Price ---
        const priceDisplay = document.createElement('div');
        priceDisplay.className = 'price-display';
        const priceMain = document.createElement('span');
        priceMain.className = 'price-main';
        priceMain.textContent = 'R' + (selectedPackage.price || '0');
        const pricePeriod = document.createElement('span');
        pricePeriod.className = 'price-period';
        pricePeriod.textContent = 'pm';
        priceDisplay.appendChild(priceMain);
        priceDisplay.appendChild(pricePeriod);
        providerCard.appendChild(priceDisplay);

        // --- Dropdown ---
        const packageSelector = document.createElement('div');
        packageSelector.className = 'package-selector';
        const dropdown = document.createElement('select');
        dropdown.className = 'package-dropdown';
        sortedPackages.forEach(function(pkg) {
          const opt = document.createElement('option');
          opt.value = pkg.id;
          opt.dataset.name     = pkg.name;
          opt.dataset.price    = pkg.price;
          opt.dataset.speed    = pkg.speed    || '';
          opt.dataset.data     = pkg.data     || '';
          opt.dataset.aup      = pkg.aup      || '';
          opt.dataset.throttle = pkg.throttle || '';
          opt.textContent = pkg.name;
          dropdown.appendChild(opt);
        });
        packageSelector.appendChild(dropdown);
        providerCard.appendChild(packageSelector);

        // --- Package info panel (dynamic) ---
        const infoContainer = document.createElement('div');
        infoContainer.className = 'pkg-info-container';
        infoContainer.appendChild(buildPackageInfoEl(selectedPackage));
        providerCard.appendChild(infoContainer);

        // --- Sign Up button ---
        const signUpBtn = document.createElement('button');
        signUpBtn.className = 'check-availability-btn';
        signUpBtn.textContent = 'Sign Up';
        providerCard.appendChild(signUpBtn);

        packageDisplay.appendChild(providerCard);

        // Dropdown change: update name, price, and package info panel
        dropdown.addEventListener('change', function() {
          const selectedOption = this.options[this.selectedIndex];
          packageNameEl.textContent = selectedOption.dataset.name;
          priceMain.textContent = 'R' + selectedOption.dataset.price;

          selectedPackage = packages.find(function(pkg) { return pkg.id == selectedOption.value; });
          if (selectedPackage) {
            infoContainer.textContent = '';
            infoContainer.appendChild(buildPackageInfoEl(selectedPackage));
          }
        });

        // Sign Up button: store package and navigate
        signUpBtn.addEventListener('click', function() {
          const selectedOption = dropdown.options[dropdown.selectedIndex];
          const currentPkg = packages.find(function(pkg) { return pkg.id == selectedOption.value; });

          try {
            const packageData = {
              id:       currentPkg.id,
              name:     currentPkg.name,
              price:    currentPkg.price,
              provider: currentPkg.provider,
              speed:    currentPkg.speed,
              data:     currentPkg.data,
              aup:      currentPkg.aup,
              throttle: currentPkg.throttle,
              type:     currentPkg.type
            };
            sessionStorage.setItem('selectedPackage', JSON.stringify(packageData));
            window.location.href = signupUrl;
          } catch (error) {
            console.error('Error storing package:', error);
            window.location.href = signupUrl;
          }
        });
      });
    }

    // Create scroll indicators for mobile
    function createScrollIndicators() {
      const scrollIndicators = document.getElementById('scroll-indicators');
      if (!scrollIndicators) return;

      scrollIndicators.textContent = '';

      providers.forEach(function(_, index) {
        const dot = document.createElement('div');
        dot.className = 'scroll-dot' + (index === 0 ? ' active' : '');
        dot.addEventListener('click', function() {
          const card = document.querySelector('[data-provider-index="' + index + '"]');
          if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        });
        scrollIndicators.appendChild(dot);
      });
    }

    try { createAllProviderCards(); } catch (e) { console.error('Error creating provider cards:', e); }

    // Auto-snap first card to center on load
    setTimeout(() => {
      const firstCard = packageDisplay.querySelector('.provider-package-card');
      if (firstCard) {
        firstCard.scrollIntoView({ behavior: 'instant', block: 'nearest', inline: 'center' });
      }
    }, 50);

    try { createScrollIndicators(); } catch (e) { console.error('Error creating scroll indicators:', e); }

    // Scroll indicator updates
    function updateScrollIndicators() {
      const scrollDots = document.querySelectorAll('.scroll-dot');
      const cards = packageDisplay.querySelectorAll('.provider-package-card');
      if (!scrollDots.length || !cards.length) return;

      let activeIndex = 0;
      const containerCenter = packageDisplay.scrollLeft + packageDisplay.offsetWidth / 2;
      let minDist = Infinity;
      cards.forEach(function(card, i) {
        const dist = Math.abs((card.offsetLeft + card.offsetWidth / 2) - containerCenter);
        if (dist < minDist) { minDist = dist; activeIndex = i; }
      });
      scrollDots.forEach(function(dot, i) { dot.classList.toggle('active', i === activeIndex); });
    }

    function enhanceScrollSnapping() {
      if (window.innerWidth > 768) return;
      const cards = packageDisplay.querySelectorAll('.provider-package-card');
      let scrollTimer;
      packageDisplay.addEventListener('scroll', function() {
        clearTimeout(scrollTimer);
        scrollTimer = setTimeout(function() {
          const center = packageDisplay.scrollLeft + packageDisplay.offsetWidth / 2;
          let closest = null, minDist = Infinity;
          cards.forEach(function(card) {
            const dist = Math.abs((card.offsetLeft + card.offsetWidth / 2) - center);
            if (dist < minDist) { minDist = dist; closest = card; }
          });
          if (closest) closest.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
        }, 150);
      });
    }

    let scrollTimeout;
    packageDisplay.addEventListener('scroll', function() {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(updateScrollIndicators, 100);
    });
    window.addEventListener('resize', function() {
      setTimeout(updateScrollIndicators, 100);
      enhanceScrollSnapping();
    });
    enhanceScrollSnapping();

    // Desktop scroll arrows
    const leftArrow  = document.getElementById('desktop-scroll-left');
    const rightArrow = document.getElementById('desktop-scroll-right');
    if (leftArrow)  leftArrow.addEventListener('click',  function() { packageDisplay.scrollBy({ left: -400, behavior: 'smooth' }); });
    if (rightArrow) rightArrow.addEventListener('click', function() { packageDisplay.scrollBy({ left:  400, behavior: 'smooth' }); });

    if (debugEnabled) console.log('LTE carousel initialized', { providersCount: providers.length });
  });
