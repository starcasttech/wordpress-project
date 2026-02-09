// Setup promo tooltip functionality
  function setupPromoTooltip(cardElement, packageData) {
    const tooltipTrigger = cardElement.querySelector('.promo-tooltip-trigger');
    const tooltip = cardElement.querySelector('.feature-checklist .promo-tooltip');
    
    if (!tooltipTrigger || !tooltip) return;
    
    // Remove existing event listeners by cloning element
    const newTooltipTrigger = tooltipTrigger.cloneNode(true);
    tooltipTrigger.parentNode.replaceChild(newTooltipTrigger, tooltipTrigger);
    
    // Check if package has promo and custom text
    const hasPromo = packageData.has_promo;
    const promoText = packageData.promo_text;
    
    if (hasPromo && promoText && promoText.trim()) {
      // Package has custom promo text
      tooltip.textContent = promoText;
      newTooltipTrigger.classList.remove('no-promo');
      
      // Add hover/click events
      let tooltipTimeout;
      let isTooltipVisible = false;
      let isMouseOverTrigger = false;
      let isMouseOverTooltip = false;
      
      newTooltipTrigger.addEventListener('mouseenter', () => {
        isMouseOverTrigger = true;
        clearTimeout(tooltipTimeout);
        tooltip.classList.add('show');
        isTooltipVisible = true;
      });
      
      newTooltipTrigger.addEventListener('mouseleave', () => {
        isMouseOverTrigger = false;
        // Small delay to allow moving to tooltip
        tooltipTimeout = setTimeout(() => {
          if (!isMouseOverTrigger && !isMouseOverTooltip) {
            tooltip.classList.remove('show');
            isTooltipVisible = false;
          }
        }, 100); // Small delay for smooth transition
      });
      
      // Add tooltip hover detection to keep it visible
      tooltip.addEventListener('mouseenter', () => {
        isMouseOverTooltip = true;
        clearTimeout(tooltipTimeout);
      });
      
      tooltip.addEventListener('mouseleave', () => {
        isMouseOverTooltip = false;
        // Hide immediately when leaving tooltip
        tooltip.classList.remove('show');
        isTooltipVisible = false;
        clearTimeout(tooltipTimeout);
      });
      
      newTooltipTrigger.addEventListener('click', (e) => {
        e.preventDefault();
        if (isTooltipVisible) {
          tooltip.classList.remove('show');
          isTooltipVisible = false;
          clearTimeout(tooltipTimeout);
        } else {
          tooltip.classList.add('show');
          isTooltipVisible = true;
        }
      });
      
      // Hide tooltip when clicking elsewhere or scrolling (add to document once per card)
      if (!cardElement.hasAttribute('data-tooltip-setup')) {
        cardElement.setAttribute('data-tooltip-setup', 'true');
        
        // Hide on click elsewhere
        document.addEventListener('click', (e) => {
          if (!cardElement.contains(e.target)) {
            tooltip.classList.remove('show');
            isTooltipVisible = false;
            clearTimeout(tooltipTimeout);
          }
        });
        
        // Hide on scroll
        document.addEventListener('scroll', () => {
          tooltip.classList.remove('show');
          isTooltipVisible = false;
          clearTimeout(tooltipTimeout);
        }, true); // Use capture to catch all scroll events
        
        // Hide on container scroll (for horizontal scrolling)
        const packageDisplay = document.getElementById('provider-package-display');
        if (packageDisplay) {
          packageDisplay.addEventListener('scroll', () => {
            tooltip.classList.remove('show');
            isTooltipVisible = false;
            clearTimeout(tooltipTimeout);
          });
        }
      }
      
    } else {
      // No promo or no custom text - disable tooltip
      tooltip.textContent = '';
      newTooltipTrigger.classList.add('no-promo');
      tooltip.classList.remove('show');
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const providers = (window.starcastFibreData && window.starcastFibreData.providers) ? window.starcastFibreData.providers : [];
    const signupUrl = (window.starcastFibreData && window.starcastFibreData.signupUrl) ? window.starcastFibreData.signupUrl : '/signup/';
    const debugEnabled = !!(window.starcastFibreData && window.starcastFibreData.debug);
    const packageDisplay = document.getElementById('provider-package-display');

    if (!packageDisplay) {
      return;
    }

    function buildSignupUrl(packageId) {
      const base = signupUrl || '/signup/';
      const separator = base.includes('?') ? '&' : '?';
      return `${base}${separator}package_id=${packageId}`;
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
        
        // Sort packages by download speed
        const sortedPackages = [...packages].sort((a, b) => a.download_speed - b.download_speed);
        let selectedPackage = sortedPackages[0];
        
        // Check for promo badge
        let promoBadge = '';
        if (selectedPackage.has_promo) {
          promoBadge = '<div class="promo-badge-corner">Promotion</div>';
        }
        
        const providerName = escapeHtml(provider.name || '');
        const downloadText = escapeHtml(String(selectedPackage.download || '').replace(/\s*Mbps/i, ''));
        const uploadText = escapeHtml(String(selectedPackage.upload || '').replace(/\s*Mbps/i, ''));
        const providerLogo = sanitizeUrl(provider.logo || '');

        const providerCard = document.createElement('div');
        providerCard.className = 'provider-package-card';
        providerCard.dataset.providerIndex = providerIndex;
        
        providerCard.innerHTML = `
          ${promoBadge}
          ${providerLogo ? `<img src="${providerLogo}" alt="${providerName}" class="provider-logo-main">` : `<div class="provider-name-main">${providerName}</div>`}
          
          <div class="speed-info">
            <span class="download-arrow">↓</span>
            <span class="current-download">${downloadText}</span>
            <span class="upload-arrow">↑</span>
            <span class="current-upload">${uploadText}</span>
            <span>Mbps</span>
          </div>
          
          <div class="price-display">
            ${selectedPackage.has_promo && selectedPackage.promo_price !== selectedPackage.price ? 
              `<span class="price-strikethrough">R${selectedPackage.price}pm</span>` : ''}
            <span class="price-main current-price">R${selectedPackage.has_promo && selectedPackage.promo_price ? selectedPackage.promo_price : selectedPackage.price}</span>
            <span class="price-period">pm</span>
          </div>
          
          <div class="package-selector">
            <select class="package-dropdown">
              ${sortedPackages.map(pkg => `
                <option value="${escapeHtml(pkg.id)}" data-download="${escapeHtml(pkg.download)}" data-upload="${escapeHtml(pkg.upload)}" data-price="${escapeHtml(pkg.price)}" data-promo-price="${escapeHtml(pkg.promo_price || pkg.price)}" data-has-promo="${escapeHtml(pkg.has_promo)}" data-promo-text="${escapeHtml(pkg.promo_text || '')}">
                  ${escapeHtml(String(pkg.download || '').replace(/\s*Mbps/i, ''))}/${escapeHtml(String(pkg.upload || '').replace(/\s*Mbps/i, ''))} Mbps Uncapped
                </option>
              `).join('')}
            </select>
          </div>
          
          <div class="feature-checklist">
            <div class="promo-tooltip"></div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">
                <span class="promo-tooltip-trigger">How our promotion works</span>
              </div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Free-to-use router</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Free setup worth R2732</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Installation time: 7 days</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Order processing fee: R249</div>
            </div>
            <div class="feature-item">
              <div class="feature-checkmark"></div>
              <div class="feature-text">Pro rata rates apply</div>
            </div>
          </div>
          
          <button class="check-availability-btn">Sign Up</button>
        `;
        
        packageDisplay.appendChild(providerCard);
        
        // Setup promo tooltip functionality
        setupPromoTooltip(providerCard, selectedPackage);
        
        // Add dropdown change handler for this specific card
        const dropdown = providerCard.querySelector('.package-dropdown');
        dropdown.addEventListener('change', function() {
          const selectedOption = this.options[this.selectedIndex];
          const downloadEl = providerCard.querySelector('.current-download');
          const uploadEl = providerCard.querySelector('.current-upload');
          const priceEl = providerCard.querySelector('.current-price');
          const priceDisplay = providerCard.querySelector('.price-display');
          const promoBadge = providerCard.querySelector('.promo-badge-corner');
          
          downloadEl.textContent = selectedOption.dataset.download.replace(/\s*Mbps/i, '');
          uploadEl.textContent = selectedOption.dataset.upload.replace(/\s*Mbps/i, '');
          
          const hasPromo = selectedOption.dataset.hasPromo === 'true';
          const regularPrice = selectedOption.dataset.price;
          const promoPrice = selectedOption.dataset.promoPrice;
          
          // Show/hide promo badge based on selected package
          if (promoBadge) {
            if (hasPromo) {
              promoBadge.style.display = 'block';
            } else {
              promoBadge.style.display = 'none';
            }
          }
          
          if (hasPromo && promoPrice !== regularPrice) {
            priceDisplay.innerHTML = `
              <span class="price-strikethrough">R${regularPrice}pm</span>
              <span class="price-main current-price">R${promoPrice}</span>
              <span class="price-period">pm</span>
            `;
          } else {
            priceDisplay.innerHTML = `
              <span class="price-main current-price">R${regularPrice}</span>
              <span class="price-period">pm</span>
            `;
          }
          
          // Update selected package for this card
          selectedPackage = packages.find(pkg => pkg.id == selectedOption.value);
          
          // Create updated package data for tooltip
          const updatedPackageData = {
            has_promo: selectedOption.dataset.hasPromo === 'true',
            promo_text: selectedOption.dataset.promoText || ''
          };
          
          // Update promo tooltip for new selection
          setupPromoTooltip(providerCard, updatedPackageData);
        });
        
        // Add dropdown click handler to hide arrow over card
        dropdown.addEventListener('mousedown', function() {
          const currentCard = this.closest('.provider-package-card');
          const cardRect = currentCard.getBoundingClientRect();
          const containerRect = packageDisplay.getBoundingClientRect();
          
          // Check if this card is in the center viewport
          const cardCenter = cardRect.left + cardRect.width / 2;
          const containerCenter = containerRect.left + containerRect.width / 2;
          const isCardCentered = Math.abs(cardCenter - containerCenter) < 50;
          
          if (isCardCentered) {
            // Hide the arrow that's over this card
            const leftArrow = document.getElementById('desktop-scroll-left');
            const rightArrow = document.getElementById('desktop-scroll-right');
            
            if (leftArrow && rightArrow) {
              leftArrow.style.opacity = '0';
              rightArrow.style.opacity = '0';
            }
          }
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
              name: provider.name + ' ' + currentSelectedPackage.download + '/' + currentSelectedPackage.upload,
              price: currentSelectedPackage.price,
              provider: currentSelectedPackage.provider,
              download: currentSelectedPackage.download,
              upload: currentSelectedPackage.upload
            };
            
            sessionStorage.setItem('selectedPackage', JSON.stringify(packageData));
            window.location.href = buildSignupUrl(currentSelectedPackage.id);
          } catch (error) {
            console.error('Error storing package:', error);
            window.location.href = buildSignupUrl(currentSelectedPackage.id);
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
    
    // Update scroll indicators based on scroll position
    function updateScrollIndicators() {
      const scrollIndicators = document.getElementById('scroll-indicators');
      const dots = scrollIndicators.querySelectorAll('.scroll-dot');
      const packageDisplay = document.getElementById('provider-package-display');
      const cards = packageDisplay.querySelectorAll('.provider-package-card');
      
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
        
        dots.forEach((dot, index) => {
          dot.classList.toggle('active', index === closestCardIndex);
        });
      }
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
    
    // Add scroll listener for indicators
    let scrollTimeout;
    
    packageDisplay.addEventListener('scroll', () => {
      clearTimeout(scrollTimeout);
      scrollTimeout = setTimeout(updateScrollIndicators, 100);
    });
    
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
        
        // Show arrows after clicking
        setTimeout(() => {
          leftArrow.style.opacity = '1';
          rightArrow.style.opacity = '1';
        }, 300);
      });
      
      rightArrow.addEventListener('click', () => {
        packageDisplay.scrollBy({
          left: 400,
          behavior: 'smooth'
        });
        
        // Show arrows after clicking
        setTimeout(() => {
          leftArrow.style.opacity = '1';
          rightArrow.style.opacity = '1';
        }, 300);
      });
      
      // Update arrow visibility based on scroll position
      function updateArrowVisibility() {
        const scrollLeft = packageDisplay.scrollLeft;
        const scrollWidth = packageDisplay.scrollWidth;
        const clientWidth = packageDisplay.clientWidth;
        
        // Only update opacity if arrows are not currently hidden by dropdown interaction
        if (leftArrow.style.opacity !== '0' && rightArrow.style.opacity !== '0') {
          leftArrow.style.opacity = scrollLeft > 0 ? '1' : '0.5';
          rightArrow.style.opacity = scrollLeft < scrollWidth - clientWidth ? '1' : '0.5';
        }
      }
      
      packageDisplay.addEventListener('scroll', updateArrowVisibility);
      updateArrowVisibility(); // Initial call
    }
    
    // Debug function for mobile card width
    function debugMobileWidth() {
      if (window.innerWidth <= 768) {
        const cards = document.querySelectorAll('.provider-package-card');
        const container = document.getElementById('provider-package-display');
        
        setTimeout(() => {
          cards.forEach((card, index) => {
            const computedStyle = window.getComputedStyle(card);
            console.log(`Card ${index}:`, {
              width: computedStyle.width,
              minWidth: computedStyle.minWidth,
              maxWidth: computedStyle.maxWidth,
              actualWidth: card.offsetWidth,
              screenWidth: window.innerWidth,
              containerWidth: container.offsetWidth
            });
          });
        }, 1000);
      }
    }
    
    // Debug on load and resize
    if (debugEnabled) {
      debugMobileWidth();
      window.addEventListener('resize', debugMobileWidth);
    }

  });
