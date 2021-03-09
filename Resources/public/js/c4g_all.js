let reactRenderReadyDone = false;

// DOM ready
$(function () {
    // ============== start - owl carousel ==============
    $(window).on('resize', function () {
        owl();
    });

    window.setTimeout(function () {
        $(window).trigger('resize');
    }, 500);

    owl();
    // ============== end - owl carousel ==============
});

// REACT ready
function reactRenderReady() {
    console.log("reactRenderReady loaded");

    if (!window.reactRenderReadyDone) {

        owl();

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

        window.reactRenderReadyDone = true;
    }
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

    if (window.owlCarousel) {
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

