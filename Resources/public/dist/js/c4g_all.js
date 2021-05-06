let reactRenderReadyDone = false;

// DOM ready
$(function () {
    // ============== start - owl carousel ==============
    // if (window.owlCarousel) {
    $(window).on('resize', function () {
        owl();
    });

    window.setTimeout(function () {
        $(window).trigger('resize');
    }, 500);

    owl();
    // }
    // ============== end - owl carousel ==============

});

window.c4gHooks = window.c4gHooks || {};
window.c4gHooks.addToWishlist = window.c4gHooks.addToWishlist || [];

window.c4gHooks.addToWishlist.push(function (field, data) {
    lsAddOneToBadgeAndStore();
});

window.c4gHooks = window.c4gHooks || {};
window.c4gHooks.removeFromWishlist = window.c4gHooks.removeFromWishlist || [];

window.c4gHooks.removeFromWishlist.push(function (field, data) {
    lsSubOneFromBadgeAndStore();
});

/**
 * Adds one to the latest value of Merkzettel-Badge and returns the result.
 * @returns {number}
 */
function lsAddOneToBadgeAndStore() {
    let badgeVal = lsGetBadgeCount();
    let sum = badgeVal + 1;
    showBadgeAndText(sum);
}

/**
 * Subtracts one from value of Merkzettel-Badge
 * @returns {number}
 */
function lsSubOneFromBadgeAndStore() {
    let badgeVal = lsGetBadgeCount();
    let sub = badgeVal - 1;
    showBadgeAndText(sub);
}

/**
 * Adds Badge with BadgeValue
 * @param val
 */
function showBadgeAndText(val) {
    localStorage.setItem("badgeValue", val);
    var wishlistBadge = '<span class="badge badge-light memo-badge">' + val + '</span>';

    if ($('.memo-badge').length) {
        $('a span.memo-badge').text(val);
    } else {
        $(wishlistBadge).appendTo('a.link-memo');
    }
}

/**
 * Returns the value of Merkzettel-Badge stored in localstorage.
 * @returns {number}
 */
function lsGetBadgeCount() {
    const badgeValue = localStorage.getItem('badgeValue');
    return parseInt(badgeValue);
}

// REACT ready
function reactRenderReady() {

    if (!window.reactRenderReadyDone) {

        updateWishlistBadgeAtRefresh();


        // if (window.owlCarousel) {
        owl();
        // }
        // delete all items on global wishlist
        $('.js-delete-list').on("click", deleteAllOnGlobalList);

        /* filter actions on listing page */
        $('.tag-filter__filter-item > label > input').on('click', function () {

            // check if tag-filter is checked or not
            if ($(this).is(':checked')) {
                $(this).parent(".c4g-form-label").addClass('checked-tag-filter');
                $(this).addClass('checked-tag-filter');
                findAllFilterState();
            } else {
                $(this).parent(".c4g-form-label").removeClass('checked-tag-filter');
                $(this).removeClass('checked-tag-filter');
                findAllFilterState();
            }
        });

        // all the actions in filter
        const $inputSearch = $('.form-view__searchinput > input');
        const $datepickerInput = $(".react-datepicker__input-container");
        const $datepickerPopper = $(".react-datepicker-popper");
        const $inputPriceRadio = $(".form-check-input[type=radio]");

        $inputSearch.on('keydown focusout click', findAllFilterState);
        // $inputSearch.on('focusout', findAllFilterState);
        $datepickerInput.on('focusin focusout click', findAllFilterState);
        $inputPriceRadio.on('click', setFilterButtonActive);

        // set label text as placeholder for input
        const $offerLabel = $(".offer-filter__searchinput label");
        const $offerLabelText = $(".offer-filter__searchinput label").text();
        $offerLabel.hide();
        const $offerInputField = $(".offer-filter__searchinput input");

        $offerInputField.attr("placeholder", $offerLabelText);

        $("#sharebutton").prependTo(".anchor-menu__share");

        // $('[data-toggle="popover"]').popover({
        //     placement: 'top'
        // });

        // show anchor-menu on urlaub page
        $('.js-on-react-ready').show();

        var removeFromWishlistCallback = function (event) {
            removeFromBadge();
        };
        // $('.on-wishlist').on('click', removeFromWishlistCallback);

        var putOnWishlistCallback = function (event) {
            addToBadge();
            $(".btn.remove-from-wishlist").on("click", removeFromWishlistCallback);
        };

        var deleteItemOnGlobalList = function (event) {
            subWishlistItemBadge();
        };


        // deletes item on global list visually (just to show deleted item is gone)
        $('.js-deleteFromGlobalList').on("click", deleteItemOnGlobalList);

        // delete all items on global wishlist
        $('.js-delete-list').on("click", deleteAllOnGlobalList);

        // insert Merken-Btn on Detail Page
        const buildPutOnWishlistBtn = '<button class="c4g-btn c4g-btn-primary c4g-btn-putonwishlist js-putDetailOnWishlist" title="Auf den Merkzettel setzen."><i class="fas fa-heart"></i></button>';
        const buildRemoveFromWishlistBtn = '<button class="c4g-btn c4g-btn-warning c4g-btn-removefromwishlist js-removeDetailFromWishlist" title="Vom Merkzettel entfernen."><i class="fas fa-heart"></i></button>';

        // Share Button on Detail Page
        const buildShareBtn = '<button type="button" class="c4g-btn c4g-btn-primary c4g-btn-sharedetail js-modalShare" data-toggle="modal" data-target="#shareModal" title="Teilen"><i class="fas fa-share-alt"></i></button>';

        if (window.frameworkData[0].components.detail
            && window.frameworkData[0].components.detail.data) {
            const onList = window.frameworkData[0].components.detail.data['on_wishlist'];
            // $('#anchor-menu .share').prepend(buildPutOnWishlistBtn);
            // $('#anchor-menu .share').prepend(buildRemoveFromWishlistBtn);
            $('.anchor-menu__share').prepend(buildShareBtn);
            $('.anchor-menu__share').prepend(buildPutOnWishlistBtn);
            $('.anchor-menu__share').prepend(buildRemoveFromWishlistBtn);

            if (onList) {
                $(".js-putDetailOnWishlist").css("display", "none");
            } else {
                $(".js-removeDetailFromWishlist").css("display", "none");
            }

            var handlePutOnWishlist = function (event) {
                $(".js-putDetailOnWishlist").css("display", "none");
                $(".js-removeDetailFromWishlist").css("display", "block");
                const detailType = window.frameworkData[0].components.detail.data['internal_type'];
                const detailUuid = window.frameworkData[0].components.detail.data['uuid'];
                const postUrl = '/gutesio/operator/wishlist/add/' + detailType + '/' + detailUuid;
                $.post(postUrl).done(() => {
                    // addToBadge();
                    lsAddOneToBadgeAndStore();
                });
            };

            var handleRemoveFromWishlist = function (event) {
                $(".js-putDetailOnWishlist").css("display", "block");
                $(".js-removeDetailFromWishlist").css("display", "none");
                const detailUuid = window.frameworkData[0].components.detail.data['uuid'];
                const postUrl = '/gutesio/operator/wishlist/remove/' + detailUuid;

                $.post(postUrl).done(() => {
                    // removeFromBadge();
                    lsSubOneFromBadgeAndStore();
                });
            };

            $(".js-putDetailOnWishlist").on('click', handlePutOnWishlist);
            $(".js-removeDetailFromWishlist").on('click', handleRemoveFromWishlist);
        }


        // TODO: try to takeout search-input and search-submit-button
        const $filterButtonWrapper = $(".c4g-listfilter-button-wrapper");
        const wrapperForSearch = '<div class="js-done c4g-listfilter__search-and-submit">test</div>';

        $filterButtonWrapper.append(wrapperForSearch);

        window.reactRenderReadyDone = true;
    }
}

function updateWishlistBadgeAtRefresh() {

    var getItemsRoute = '/gutesio/operator/wishlist/getItemCount';

    $.get(getItemsRoute).done((data) => {
        var countItemsServer = 0;
        if (data.count > 0) {
            countItemsServer = data.count;
            localStorage.setItem("badgeValue", countItemsServer);

            var wishlistBadge = '<span class="badge badge-light memo-badge">' + countItemsServer + '</span>';

            if ($('.memo-badge').length) {
                $('a span.memo-badge').text(countItemsServer);
            } else {
                $(wishlistBadge).appendTo('a.link-memo');
            }
        } else {
            localStorage.setItem("badgeValue", "0");
        }
    });
}

// this function check and set the filter button state
function findAllFilterState() {
    let searchInput = false;
    let filterTag = false;
    let datepickerInput = false;
    let priceFilterRadio = false;

    const tagFilterClass = "checked-tag-filter";

    // check search input
    const $inputSearch = $('.form-view__searchinput > input');
    if ($inputSearch.val()) {
        searchInput = true;
    }

    // check filter tags
    const $tagFilterItemLabel = $(".tag-filter__filter-item > label");
    if ($tagFilterItemLabel.hasClass("checked-tag-filter")) {
        filterTag = true;
    }

    // check datepicker input value
    const $datepickerInput = $(".react-datepicker__input-container input");
    if ($datepickerInput.val()) {
        datepickerInput = true;
    }

    // todo: check price filter - need a label class like 'checked-price-filter'
    // const $inputPriceRadio = $(".form-check-input[type=radio]");
    // if ($inputPriceRadio.is(":checked")) {
    //     priceFilterRadio = true;
    // }

    // check all cases and set the button state
    if (searchInput || filterTag || datepickerInput || priceFilterRadio) {
        setFilterButtonActive();
    } else {
        setFilterButtonDefault();
    }
}

function deleteAllOnGlobalList() {
    var url = '/gutesio/operator/wishlist/clearItems';
    $.post(url).done((data) => {
        if (data.success) {
            location.reload();
        }
    });
}

function checkInput() {
    let state = false;
    var text_value = $(this).val();
    if (text_value != '') {
        state = true;
    } else {
        state = false;
    }
    return state;
}

function setFilterButtonActive() {
    $('.c4g-btn-filter').addClass('executeSubmit').text('Suche starten');

}

function executeFormSubmit() {
    $(".executeSubmit").one("click");
    $(".executeSubmit").click();
}

function setFilterButtonDefault() {
    $('.c4g-btn-filter').removeClass('executeSubmit').text('Suchen');
}

function addToBadge() {
    let badgeVal = getBadgeValue();

    badgeVal = badgeVal + 1;

    if ($('.memo-badge').length) {
        $('.memo-badge').remove();
    }

    var wishlistBadge = '<span class="badge badge-light memo-badge">' + badgeVal + '</span>';
    $(wishlistBadge).appendTo('a.link-memo');
}

function removeFromBadge() {
    let badgeVal = getBadgeValue();

    if ($('.memo-badge').length) {
        $('.memo-badge').remove();
    }

    if (badgeVal > 0) {
        badgeVal = badgeVal - 1;
        var wishlistBadge = '<span class="badge badge-light memo-badge">' + badgeVal + '</span>';
        $(wishlistBadge).appendTo('a.link-memo');

    }
}

function getBadgeValue() {
    let valBadge = 0;
    if ($('.memo-badge').length) {
        valBadge = parseInt($('.memo-badge').text());
    }
    return valBadge;
}

// owl carousel
function owl() {

    if ($('.owl-carousel').owlCarousel && typeof $('.owl-carousel').owlCarousel === "function") {

        $('.owl-carousel').owlCarousel({
            // center: true,
            loop: true,
            autoplay: false,
            autoplaySpeed: 500,
            autoplayTimeout: 1000,
            autoplayHoverPause: true,
            margin: 15,
            // stagePadding: 40,
            responsiveClass: true,
            nav: true,
            autoHeight: false,
            autoHeightClass: 'owl-height',
            responsive: {
                0: {
                    items: 1,

                },
                600: {
                    items: 2,

                },
                1000: {
                    items: 4,
                    loop: false
                }
            }
        });
    }
}

