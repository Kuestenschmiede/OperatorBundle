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

// REACT ready
function reactRenderReady() {

    if (!window.reactRenderReadyDone) {

        updateWishlistBadgeAtRefresh();

        $(".put-on-wishlist").on("click", updateWishlistBadgeAtRefresh);

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

        $('[data-toggle="popover"]').popover({
            placement: 'top'
        });

        // show anchor-menu on urlaub page
        $('.js-on-react-ready').show();

        // show badge at first pageload
        updateWishlistBadgeAtRefresh();

        var removeFromWishlistCallback = function (event) {
            removeFromBadge();
            // $(this).off('click');
            // $(this).on('click', putOnWishlistCallback);
            // deleteWishlistItem($(this).attr('data-moreurl'));
        };
        // $('.on-wishlist').on('click', removeFromWishlistCallback);

        var putOnWishlistCallback = function (event) {
            addToBadge();
            $(".btn.remove-from-wishlist").on("click", removeFromWishlistCallback);

            // $(this).addClass('btn-warning on-wishlist disabled');
            // $(this).html('Gemerkt <i class="fas fa-heart"></i>');

            // updateWishlistItemsBadge();
            //
            // window.setTimeout(() => {
            //     updateWishlistItemsBadge();
            // }, 300);
        };

        // $(".btn.put-on-wishlist").on("click", putOnWishlistCallback);
        // $(".btn.put-on-wishlist").on("click", putOnWishlistCallback);
        // $(".btn.remove-from-wishlist").on("click", removeFromWishlistCallback);

        // $('.btn').click(function () {
        //     var foo = $(this).attr('class');
        //     console.log('class = ' + foo);
        // });

        // $(".on-wishlist").html('Gemerkt <i class="fas fa-heart"></i>');

        var deleteItemOnGlobalList = function (event) {
            subWishlistItemBadge();
        };


        // deletes item on global list visually (just to show deleted item is gone)
        $('.js-deleteFromGlobalList').on("click", deleteItemOnGlobalList);

        // delete all items on global wishlist
        $('.js-delete-list').on("click", deleteAllOnGlobalList);

        // insert Merken-Btn on Detail Page
        const buildPutOnWishlistBtn = '<button class="btn btn-primary js-putDetailOnWishlist" title="Auf den Merkzettel setzen."><i class="fas fa-heart"></i></button>';
        const buildRemoveFromWishlistBtn = '<button class="btn btn-warning js-removeDetailFromWishlist" title="Vom Merkzettel entfernen."><i class="fas fa-heart"></i></button>';
        if (window.frameworkData[0].components.detail
            && window.frameworkData[0].components.detail.data) {
            const onList = window.frameworkData[0].components.detail.data['on_wishlist'];
            // $('#anchor-menu .share').prepend(buildPutOnWishlistBtn);
            // $('#anchor-menu .share').prepend(buildRemoveFromWishlistBtn);
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
                    addToBadge();
                });
            };

            var handleRemoveFromWishlist = function (event) {
                $(".js-putDetailOnWishlist").css("display", "block");
                $(".js-removeDetailFromWishlist").css("display", "none");
                const detailUuid = window.frameworkData[0].components.detail.data['uuid'];
                const postUrl = '/gutesio/operator/wishlist/remove/' + detailUuid;

                $.post(postUrl).done(() => {
                    removeFromBadge();
                });
            };

            $(".js-putDetailOnWishlist").on('click', handlePutOnWishlist);
            $(".js-removeDetailFromWishlist").on('click', handleRemoveFromWishlist);
        }

        window.reactRenderReadyDone = true;
    }
}

function updateWishlistBadgeAtRefresh() {

    var getItemsRoute = '/gutesio/operator/wishlist/getItemCount';

    $.get(getItemsRoute).done((data) => {
        var countItemsServer = 0;
        if (data.count > 0) {
            countItemsServer = data.count;

            if ($('.memo-badge').length) {
                $('.memo-badge').remove();
            }
            var wishlistBadge = '<span class="badge badge-light memo-badge">' + countItemsServer + '</span>';
            $(wishlistBadge).appendTo('a.link-memo');
        }
    });
}

// scroll to filter button / start search button in filter on listing page
// function scrollToFilterButton() {
//     const closestId = ".projects-tile-list-module";
// console.log("closestId = " + closestId);
//     const $filterBtn = $(".executeSubmit").closest(closestId);
//     $("html, body").animate({scrollTop: $filterBtn.offset().top - 200}, 500);
// }

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
    // scrollToFilterButton();
    // executeFormSubmit();

}

function executeFormSubmit() {
    $(".executeSubmit").one("click");
    $(".executeSubmit").click();
}

function setFilterButtonDefault() {
    $('.c4g-btn-filter').removeClass('executeSubmit').text('Suchen');
}

// owl carousel
function owl() {
    // if (window.owlCarousel) {
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
    // }
}

