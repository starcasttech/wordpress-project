/**
 * Starcast Packages Display JavaScript
 * Handles filtering and sorting of packages
 */

(function($) {
    'use strict';

    // Fibre Packages Filtering
    if ($('.starcast-fibre-packages').length) {
        const $container = $('.starcast-fibre-packages');
        const $providerFilter = $('#provider-filter');
        const $speedFilter = $('#speed-filter');
        const $sortFilter = $('#sort-filter');

        // Filter packages
        function filterPackages() {
            const provider = $providerFilter.val();
            const speed = $speedFilter.val();

            $('.package-card').each(function() {
                const $card = $(this);
                const cardProvider = $card.data('provider');
                const cardSpeed = parseInt($card.data('speed'));

                let showProvider = !provider || cardProvider === provider;
                let showSpeed = true;

                if (speed) {
                    const [min, max] = speed.split('-').map(Number);
                    showSpeed = cardSpeed >= min && cardSpeed <= max;
                }

                if (showProvider && showSpeed) {
                    $card.removeClass('hidden').fadeIn(300);
                } else {
                    $card.addClass('hidden').fadeOut(300);
                }
            });

            // Hide provider sections with no visible packages
            $('.provider-section').each(function() {
                const $section = $(this);
                const visibleCards = $section.find('.package-card:not(.hidden)').length;

                if (visibleCards === 0) {
                    $section.addClass('hidden').hide();
                } else {
                    $section.removeClass('hidden').show();
                }
            });
        }

        // Sort packages
        function sortPackages() {
            const sortBy = $sortFilter.val();

            $('.provider-section').each(function() {
                const $section = $(this);
                const $grid = $section.find('.packages-grid');
                const $cards = $grid.find('.package-card').detach();

                const sorted = $cards.sort(function(a, b) {
                    const priceA = parseInt($(a).data('price')) || 0;
                    const priceB = parseInt($(b).data('price')) || 0;
                    const speedA = parseInt($(a).data('speed')) || 0;
                    const speedB = parseInt($(b).data('speed')) || 0;

                    switch(sortBy) {
                        case 'price-asc':
                            return priceA - priceB;
                        case 'price-desc':
                            return priceB - priceA;
                        case 'speed-asc':
                            return speedA - speedB;
                        case 'speed-desc':
                            return speedB - speedA;
                        default:
                            return 0;
                    }
                });

                $grid.append(sorted);
            });
        }

        // Event listeners
        $providerFilter.on('change', filterPackages);
        $speedFilter.on('change', filterPackages);
        $sortFilter.on('change', sortPackages);

        // Initial sort
        sortPackages();
    }

    // LTE Packages Sorting
    if ($('.starcast-lte-packages').length) {
        const $lteSortFilter = $('#lte-sort-filter');

        function sortLTEPackages() {
            const sortBy = $lteSortFilter.val();
            const $grid = $('.starcast-lte-packages .packages-grid');
            const $cards = $grid.find('.package-card').detach();

            const sorted = $cards.sort(function(a, b) {
                const priceA = parseInt($(a).data('price')) || 0;
                const priceB = parseInt($(b).data('price')) || 0;
                const dataA = parseInt($(a).data('data')) || 0;
                const dataB = parseInt($(b).data('data')) || 0;

                switch(sortBy) {
                    case 'price-asc':
                        return priceA - priceB;
                    case 'price-desc':
                        return priceB - priceA;
                    case 'data-asc':
                        return dataA - dataB;
                    case 'data-desc':
                        return dataB - dataA;
                    default:
                        return 0;
                }
            });

            $grid.append(sorted);
        }

        $lteSortFilter.on('change', sortLTEPackages);

        // Initial sort
        sortLTEPackages();
    }

})(jQuery);
