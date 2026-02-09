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
      packageDisplay.innerHTML = '<div class="no-packages">No providers available at the moment.</div>';
      return;
    }
    
    // Create all provider cards
    function createAllProviderCards() {
      packageDisplay.innerHTML = '';
      
      providers.forEach((provider, providerIndex) => {
        const packages = provider?.packages || [];
        
        if (!packages.length) {
          return; // Skip providers with no packages
        }
        
        // Sort packages by priority: Fixed, Uncapped, 5G, Mobile, then others
        const sortedPackages = [...packages].sort((a, b) => {
          function getTypePriority(pkg) {
            const name = pkg.name.toLowerCase();
            const type = pkg.type ? pkg.type.toLowerCase() : '';
            
            // Remove provider name from beginning to get actual package type
            const cleanName = name
              .replace(/^(vodacom|mtn|telkom)\s+/i, '') // Remove provider prefix
              .trim();
            
            // Priority 1: Fixed packages (highest priority)
            if (cleanName.includes('fixed') || name.includes('fixed')) return 1;
            
            // Priority 2: Uncapped packages  
            if (cleanName.includes('uncapped') || name.includes('uncapped') || 
                pkg.data?.toLowerCase().includes('uncapped')) return 2;
            
            // Priority 3: 5G packages
            if (cleanName.includes('5g') || name.includes('5g') || type === 'fixed-5g') return 3;
            
            // Priority 4: Mobile packages
            if (cleanName.includes('mobile') || name.includes('mobile') || type === 'mobile-data') return 4;
            
            // Priority 5: Everything else (LTE, data packages, etc.)
            return 5;
          }
          
          const aPriority = getTypePriority(a);
          const bPriority = getTypePriority(b);
          
          // Sort by priority first, then by price within same priority
          if (aPriority !== bPriority) return aPriority - bPriority;
          return a.price - b.price;
        });
        let selectedPackage = sortedPackages[0];
        
        const providerName = escapeHtml(provider.name || '');
        const providerLogo = sanitizeUrl(provider.logo || '');

        const providerCard = document.createElement('div');
        providerCard.className = 'provider-package-card';
        providerCard.dataset.providerIndex = providerIndex;
        
        // Create package details HTML
        let packageDetailsHTML = '';
        if (selectedPackage.speed && selectedPackage.speed !== '') {
          const speedValue = escapeHtml(selectedPackage.speed);
          packageDetailsHTML += `<div class="detail-item"><span class="detail-label">Speed:</span><span class="detail-value">${speedValue}${speedValue.includes('Mbps') ? '' : 'Mbps'}</span></div>`;
        }
        if (selectedPackage.data && selectedPackage.data !== '') {
          let dataValue = String(selectedPackage.data).replace(/unlimited/gi, 'Uncapped').replace(/Unlimited/g, 'Uncapped');
          dataValue = escapeHtml(dataValue);
          packageDetailsHTML += `<div class="detail-item"><span class="detail-label">Data:</span><span class="detail-value">${dataValue}</span></div>`;
        }
        if (selectedPackage.aup && selectedPackage.aup !== '') {
          const aupValue = escapeHtml(selectedPackage.aup);
          packageDetailsHTML += `<div class="detail-item"><span class="detail-label">FUP:</span><span class="detail-value">${aupValue}${aupValue.includes('GB') ? '' : 'GB'}</span></div>`;
        }
        if (selectedPackage.throttle && selectedPackage.throttle !== '') {
          const throttleValue = escapeHtml(selectedPackage.throttle);
          packageDetailsHTML += `<div class="detail-item"><span class="detail-label">Throttle:</span><span class="detail-value">${throttleValue}</span></div>`;
        }
        
        providerCard.innerHTML = `
          ${providerLogo ? `<img src="${providerLogo}" alt="${providerName}" class="provider-logo-main">` : `<div class="provider-name-main">${providerName}</div>`}
          
          <div class="package-name-display">${escapeHtml(selectedPackage.name || '')}</div>
          
          <div class="price-display">
            <span class="price-main">R${escapeHtml(selectedPackage.price)}</span>
            <span class="price-period">pm</span>
          </div>
          
          <div class="package-selector">
            <select class="package-dropdown">
              ${sortedPackages.map(pkg => `
                <option value="${escapeHtml(pkg.id)}" data-name="${escapeHtml(pkg.name)}" data-price="${escapeHtml(pkg.price)}" data-speed="${escapeHtml(pkg.speed)}" data-data="${escapeHtml(pkg.data)}" data-aup="${escapeHtml(pkg.aup)}" data-throttle="${escapeHtml(pkg.throttle)}">
                  ${escapeHtml(pkg.name)}
                </option>
              `).join('')}
            </select>
          </div>
          
          <div class="feature-checklist">
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Same day activation</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Free router delivery</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Choose between new or used router</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">New Router R1599</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Refurbished router R749</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Order processing fee R249</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Pro rata rates apply</div>
            </div>
          </div>
          
          <button class="check-availability-btn">Sign Up</button>
        `;
        
        packageDisplay.appendChild(providerCard);
        
        // Add dropdown change handler for this specific card
        const dropdown = providerCard.querySelector('.package-dropdown');
        dropdown.addEventListener('change', function() {
          const selectedOption = this.options[this.selectedIndex];
          const packageNameEl = providerCard.querySelector('.package-name-display');
          const priceEl = providerCard.querySelector('.price-main');
          
          packageNameEl.textContent = selectedOption.dataset.name;
          priceEl.textContent = 'R' + selectedOption.dataset.price;
          
          // Update selected package for this card
          selectedPackage = packages.find(pkg => pkg.id == selectedOption.value);
        });
        
        // Add click handler for availability button
        const availabilityBtn = providerCard.querySelector('.check-availability-btn');
        availabilityBtn.addEventListener('click', function() {
          const dropdown = providerCard.querySelector('.package-dropdown');
          const selectedOption = dropdown.options[dropdown.selectedIndex];
          const currentSelectedPackage = packages.find(pkg => pkg.id == selectedOption.value);
          
          try {
            const packageData = {
              id: currentSelectedPackage.id,
              name: currentSelectedPackage.name,
              price: currentSelectedPackage.price,
              provider: currentSelectedPackage.provider,
              speed: currentSelectedPackage.speed,
              data: currentSelectedPackage.data,
              aup: currentSelectedPackage.aup,
              throttle: currentSelectedPackage.throttle,
              type: currentSelectedPackage.type
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
      if (!scrollIndicators) {
        console.error('Scroll indicators container not found');
        return;
      }
      
      scrollIndicators.innerHTML = '';
      
      providers.forEach((_, index) => {
        const dot = document.createElement('div');
        dot.className = 'scroll-dot';
        if (index === 0) dot.classList.add('active');
        
        dot.addEventListener('click', () => {
          const card = document.querySelector(`[data-provider-index="${index}"]`);
          if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
          }
        });
        
        scrollIndicators.appendChild(dot);
      });
    }
    
    // Initialize by creating all provider cards
    try {
      createAllProviderCards();
    } catch (error) {
      console.error('Error creating provider cards:', error);
    }
    
    // Create scroll indicators
    try {
      createScrollIndicators();
    } catch (error) {
      console.error('Error creating scroll indicators:', error);
    }
    
    // Function to update scroll indicators based on scroll position
    function updateScrollIndicators() {
      const scrollDots = document.querySelectorAll('.scroll-dot');
      const cards = packageDisplay.querySelectorAll('.provider-package-card');
      
      if (!scrollDots.length || !packageDisplay || !cards.length) return;
      
      if (window.innerWidth <= 768) {
        const containerCenter = packageDisplay.offsetLeft + packageDisplay.offsetWidth / 2;
        let closestCardIndex = 0;
        let minDistance = Infinity;
        
        cards.forEach((card, index) => {
          const cardCenter = card.offsetLeft + card.offsetWidth / 2;
          const distance = Math.abs(cardCenter - packageDisplay.scrollLeft - packageDisplay.offsetWidth / 2);
          
          if (distance < minDistance) {
            minDistance = distance;
            closestCardIndex = index;
          }
        });
        
        scrollDots.forEach((dot, index) => {
          dot.classList.toggle('active', index === closestCardIndex);
        });
      } else {
        // Desktop fallback
        const scrollLeft = packageDisplay.scrollLeft;
        const cardWidth = packageDisplay.querySelector('.provider-package-card')?.offsetWidth || 380;
        const gap = 60; // Default gap between cards
        const cardWithGap = cardWidth + gap;
        
        // Calculate which card is most visible
        let activeIndex = Math.round(scrollLeft / cardWithGap);
        activeIndex = Math.max(0, Math.min(activeIndex, providers.length - 1));
        
        // Update active dot
        scrollDots.forEach((dot, index) => {
          dot.classList.toggle('active', index === activeIndex);
        });
      }
    }
    
    // Enhanced scroll snapping for mobile
    function enhanceScrollSnapping() {
      if (window.innerWidth <= 768) {
        const cards = packageDisplay.querySelectorAll('.provider-package-card');
        let isScrolling = false;
        let scrollTimer;
        
        packageDisplay.addEventListener('scroll', () => {
          if (!isScrolling) {
            isScrolling = true;
          }
          
          clearTimeout(scrollTimer);
          scrollTimer = setTimeout(() => {
            isScrolling = false;
            
            // Find the card closest to center
            const containerCenter = packageDisplay.scrollLeft + packageDisplay.offsetWidth / 2;
            let closestCard = null;
            let minDistance = Infinity;
            
            cards.forEach(card => {
              const cardCenter = card.offsetLeft + card.offsetWidth / 2;
              const distance = Math.abs(cardCenter - containerCenter);
              
              if (distance < minDistance) {
                minDistance = distance;
                closestCard = card;
              }
            });
            
            if (closestCard) {
              closestCard.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'center'
              });
            }
          }, 150);
        });
      }
    }
    
    // Add scroll listener for indicators with debouncing
    let scrollTimeout;
    
    if (packageDisplay) {
      packageDisplay.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(updateScrollIndicators, 100);
      });
    }
    
    // Update indicators on window resize
    window.addEventListener('resize', () => {
      setTimeout(updateScrollIndicators, 100);
      enhanceScrollSnapping();
    });
    
    // Initialize scroll snapping
    enhanceScrollSnapping();
    
    // Desktop scroll arrows functionality
    const leftArrow = document.getElementById('desktop-scroll-left');
    const rightArrow = document.getElementById('desktop-scroll-right');
    
    if (leftArrow && rightArrow) {
      leftArrow.addEventListener('click', () => {
        packageDisplay.scrollBy({
          left: -400,
          behavior: 'smooth'
        });
      });
      
      rightArrow.addEventListener('click', () => {
        packageDisplay.scrollBy({
          left: 400,
          behavior: 'smooth'
        });
      });
    }

    if (debugEnabled) {
      console.log('LTE carousel initialized', { providersCount: providers.length });
    }
  });
