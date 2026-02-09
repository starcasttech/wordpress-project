/**
 * Starcast Pro - Interactive Features
 * @version 2.0.0
 */

(function($) {
    'use strict';

    /**
     * Package Filtering System
     */
    class StarcastPackageFilter {
        constructor() {
            this.container = $('#starcast-packages-container');
            this.cards = $('.starcast-package-card');
            this.init();
        }

        init() {
            // Provider filter
            $('#starcast-filter-provider').on('change', () => this.applyFilters());

            // Speed filter
            $('#starcast-filter-speed').on('change', () => this.applyFilters());

            // Sort filter
            $('#starcast-filter-sort').on('change', () => this.applySort());

            console.log('Starcast Package Filter initialized');
        }

        applyFilters() {
            const provider = $('#starcast-filter-provider').val().toLowerCase();
            const speedRange = $('#starcast-filter-speed').val();

            $('.starcast-package-card').each(function() {
                const $card = $(this);
                let show = true;

                // Provider filter
                if (provider !== '') {
                    const cardProvider = $card.data('provider');
                    if (cardProvider !== provider) {
                        show = false;
                    }
                }

                // Speed filter
                if (speedRange !== '') {
                    const speed = parseInt($card.data('speed'));
                    const [min, max] = speedRange.split('-').map(v => v === '+' ? Infinity : parseInt(v));

                    if (max) {
                        if (speed < min || speed > max) {
                            show = false;
                        }
                    } else {
                        if (speed < min) {
                            show = false;
                        }
                    }
                }

                // Show/hide card
                if (show) {
                    $card.fadeIn(300);
                } else {
                    $card.fadeOut(300);
                }
            });

            this.updateResultsCount();
        }

        applySort() {
            const sortBy = $('#starcast-filter-sort').val();
            const $cards = $('.starcast-package-card');

            const sorted = $cards.sort((a, b) => {
                const $a = $(a);
                const $b = $(b);

                switch(sortBy) {
                    case 'price-asc':
                        return parseFloat($a.data('price')) - parseFloat($b.data('price'));
                    case 'price-desc':
                        return parseFloat($b.data('price')) - parseFloat($a.data('price'));
                    case 'speed-asc':
                        return parseInt($a.data('speed')) - parseInt($b.data('speed'));
                    case 'speed-desc':
                        return parseInt($b.data('speed')) - parseInt($a.data('speed'));
                    default:
                        return 0;
                }
            });

            this.container.html(sorted);
            this.animateCards();
        }

        animateCards() {
            $('.starcast-package-card').each(function(index) {
                $(this).css({
                    'animation': 'none',
                    'opacity': '0'
                });

                setTimeout(() => {
                    $(this).css({
                        'animation': 'fadeInUp 0.6s ease-out forwards',
                        'animation-delay': (index * 0.1) + 's'
                    });
                }, 10);
            });
        }

        updateResultsCount() {
            const visibleCount = $('.starcast-package-card:visible').length;
            const totalCount = $('.starcast-package-card').length;

            // Update or create results count
            let $counter = $('.starcast-results-count');
            if ($counter.length === 0) {
                $('.starcast-filters').after(
                    '<div class="starcast-results-count" style="padding: 1rem; text-align: center; color: var(--starcast-gray-600);"></div>'
                );
                $counter = $('.starcast-results-count');
            }

            $counter.text(`Showing ${visibleCount} of ${totalCount} packages`);
        }
    }

    /**
     * Coverage Checker
     */
    window.starcastCheckCoverage = function() {
        const address = $('#starcast-address-input').val().trim();
        const resultsDiv = $('#starcast-coverage-results');

        if (!address) {
            resultsDiv.html('<p style="color: var(--starcast-danger); text-align: center;">Please enter an address</p>');
            return;
        }

        // Show loading
        resultsDiv.html(`
            <div style="text-align: center; padding: 2rem;">
                <div class="starcast-loading" style="margin: 0 auto;"></div>
                <p style="margin-top: 1rem; color: var(--starcast-gray-600);">Checking coverage...</p>
            </div>
        `);

        // AJAX request
        $.ajax({
            url: starcastData.ajaxurl,
            type: 'POST',
            data: {
                action: 'starcast_check_coverage',
                nonce: starcastData.nonce,
                address: address
            },
            success: function(response) {
                if (response.success) {
                    let html = `
                        <div style="background: var(--starcast-success); color: white; padding: 1.5rem; border-radius: var(--starcast-radius-lg); text-align: center; margin-bottom: 1rem;">
                            <h3 style="margin: 0 0 0.5rem 0;">✓ ${response.message}</h3>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div style="background: var(--starcast-gray-100); padding: 1.5rem; border-radius: var(--starcast-radius-lg); text-align: center;">
                                <div style="font-size: 2rem; font-weight: 800; color: var(--starcast-primary);">15</div>
                                <div>Fibre Packages</div>
                                <a href="${starcastData.siteUrl}/fibre" class="starcast-btn starcast-btn-primary" style="margin-top: 1rem; font-size: 0.875rem; padding: 0.5rem 1rem;">View Fibre</a>
                            </div>
                            <div style="background: var(--starcast-gray-100); padding: 1.5rem; border-radius: var(--starcast-radius-lg); text-align: center;">
                                <div style="font-size: 2rem; font-weight: 800; color: var(--starcast-primary);">8</div>
                                <div>LTE Packages</div>
                                <a href="${starcastData.siteUrl}/lte-5g" class="starcast-btn starcast-btn-primary" style="margin-top: 1rem; font-size: 0.875rem; padding: 0.5rem 1rem;">View LTE</a>
                            </div>
                        </div>
                    `;
                    resultsDiv.html(html);
                } else {
                    resultsDiv.html(`
                        <div style="background: var(--starcast-warning); color: white; padding: 1.5rem; border-radius: var(--starcast-radius-lg); text-align: center;">
                            <p style="margin: 0;">${response.message}</p>
                        </div>
                    `);
                }
            },
            error: function() {
                resultsDiv.html(`
                    <div style="background: var(--starcast-danger); color: white; padding: 1.5rem; border-radius: var(--starcast-radius-lg); text-align: center;">
                        <p style="margin: 0;">An error occurred. Please try again.</p>
                    </div>
                `);
            }
        });
    };

    /**
     * Google Maps init for coverage checker.
     */
    window.starcastInitCoverageMap = function() {
        const mapEl = document.getElementById('starcast-coverage-map');
        if (!mapEl || !window.google || !window.google.maps) {
            return;
        }

        const center = { lat: -26.2041, lng: 28.0473 };
        const map = new google.maps.Map(mapEl, {
            center: center,
            zoom: 10,
            disableDefaultUI: true,
            zoomControl: true,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false
        });

        new google.maps.Marker({
            position: center,
            map: map,
            title: 'Starcast Coverage'
        });
    };

    // Allow Enter key to trigger coverage check
    $(document).on('keypress', '#starcast-address-input', function(e) {
        if (e.which === 13) {
            starcastCheckCoverage();
        }
    });

    /**
     * Smooth Scroll for Anchor Links
     */
    $('a[href*="#"]:not([href="#"])').on('click', function(e) {
        const target = $(this.hash);
        if (target.length) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: target.offset().top - 100
            }, 800);
        }
    });

    /**
     * Add scroll effects
     */
    function addScrollEffects() {
        $(window).on('scroll', function() {
            const scrollPos = $(window).scrollTop();

            // Fade in elements on scroll
            $('.starcast-animate-fade-in-up').each(function() {
                const elementTop = $(this).offset().top;
                const windowBottom = scrollPos + $(window).height();

                if (windowBottom > elementTop + 100) {
                    $(this).css('opacity', '1');
                }
            });
        });
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        console.log('Starcast Pro initialized');

        // Initialize package filter if on packages page
        if ($('.starcast-packages-grid').length > 0) {
            new StarcastPackageFilter();
        }

        // Initialize scroll effects
        addScrollEffects();

        // Add loading state to buttons
        $('.starcast-btn').on('click', function() {
            const $btn = $(this);
            if (!$btn.hasClass('loading')) {
                $btn.addClass('loading');
                $btn.html('<span class="starcast-loading"></span> Loading...');
            }
        });
    });

    /**
     * Add card hover effects
     */
    $('.starcast-package-card').hover(
        function() {
            $(this).find('.starcast-btn').addClass('starcast-pulse');
        },
        function() {
            $(this).find('.starcast-btn').removeClass('starcast-pulse');
        }
    );

})(jQuery);
