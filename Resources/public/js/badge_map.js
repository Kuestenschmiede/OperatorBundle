jQuery(document).ready(function () {

// show badge at first pageload
    updateWishlistBadgeAtRefresh();

    const $removeFromWishlist = $('.remove-from-wishlist');
    const $putOnWishlist = $('.put-on-wishlist');

    $putOnWishlist.click(function (e) {
        $('a span.memo-badge').text(lsAddOneToBadge());
    });

    $removeFromWishlist.click(function (e) {
        $('a span.memo-badge').text(lsSubOneFromBadge());
    });

});

/**
 * Stores data in LocalStorage by key and value.
 * @param key
 * @param value
 */
function lsStoreData(key, value) {

    if (lsCheckIfKeyExists(key)) {
        const actualValue = lsGetValueOf(key);
        let newValue = parseInt(actualValue) + parseInt(value);
        localStorage.setItem(key, newValue);
    } else {
        localStorage.setItem(key, value);
    }
}

/**
 * Returns the value of Merkzettel-Badge stored in localstorage.
 * @returns {number}
 */
function lsGetBadgeCount() {
    const badgeValue = localStorage.getItem('badgeCount');
    return parseInt(badgeValue);
}

/**
 * Gets the value in LocalStorage by key
 * @param key
 * @returns {string}
 */
function lsGetValueOf(key) {
    return localStorage.getItem(key);
}

/**
 * Checks if a key exists in LocalStorage by keyname
 * @param keyname
 * @returns {boolean}
 */
function lsCheckIfKeyExists(keyname) {
    let keyExists = false;

    if (localStorage.getItem(keyname) !== null) {
        keyExists = true;
    }
    return keyExists;
}

/**
 * Adds one to the latest value of Merkzettel-Badge and returns the result.
 * @returns {number}
 */
function lsAddOneToBadge() {
    let sum = lsGetBadgeCount() + 1;
    return sum;
}

/**
 * Subtracts one from value of Merkzettel-Badge
 * @returns {number}
 */
function lsSubOneFromBadge() {
    let sub = lsGetBadgeCount() + 1;
    return sub;
}

/**
 * Get value of items on Merkliste, set Badge and store value in LocalStorage
 */
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
            localStorage.setItem('badgeValue', countItemsServer);
        }
    });
}

function addToBadge(event) {
    let element = event.target.tagName == "I" ? event.target.parentNode : event.target;

    jQuery.post("gutesio/operator/wishlist/add/showcase/" + element.dataset.uuid);
    element.innerText = "Gemerkt";
    jQuery(element).attr("class", "btn btn-warning remove-from-wishlist on-wishlist");
    jQuery(element).on("click", removeFromWishlistCallback)
    jQuery(element).off("click", putOnWishlistCallback)
    let i = document.createElement("i");
    jQuery(i).addClass("fas fa-heart");
    element.appendChild(i);

    let badgeVal = getBadgeValue();

    badgeVal = badgeVal + 1;

    if ($('.memo-badge').length) {
        $('.memo-badge').remove();
    }

    var wishlistBadge = '<span class="badge badge-light memo-badge">' + badgeVal + '</span>';
    $(wishlistBadge).appendTo('a.link-memo');
}

function removeFromBadge(event) {
    let element = event.target.tagName == "I" ? event.target.parentNode : event.target;

    jQuery.post("gutesio/operator/wishlist/remove/" + element.dataset.uuid);
    element.innerText = "Merken";
    jQuery(element).attr("class", "btn btn-primary put-on-wishlist");
    jQuery(element).on("click", putOnWishlistCallback);
    jQuery(element).off("click", removeFromWishlistCallback);
    let i = document.createElement("i");
    jQuery(i).addClass("far fa-heart");
    element.appendChild(i);

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

function removeFromWishlistCallback(event) {
    removeFromBadge(event);
};

function putOnWishlistCallback(event) {
    addToBadge(event);
}

function deleteItemOnGlobalList(event) {
    subWishlistItemBadge();
};

function getBadgeValue() {
    let valBadge = 0;
    if ($('.memo-badge').length) {
        valBadge = parseInt($('.memo-badge').text());
    }
    return valBadge;
}

window.c4gMapsHooks = window.c4gMapsHooks || {};
window.c4gMapsHooks.proxy_fillPopup = window.c4gMapsHooks.proxy_fillPopup || [];

window.c4gMapsHooks.proxy_fillPopup.push(function (params) {
    window.setTimeout(function () {
        jQuery(".put-on-wishlist").on("click", putOnWishlistCallback);
        jQuery(".remove-from-wishlist").on("click", removeFromWishlistCallback);
    }, 100);
});