

jQuery(document).ready(function () {
    'use strict';
// show badge at first pageload
    updateWishlistBadgeAtRefresh();

    // START badgefix
    const $removeFromWishlist = jQuery('.remove-from-wishlist');
    const $putOnWishlist = jQuery('.put-on-wishlist');
    const $removeFromDetailWishlist = jQuery('.js-removeDetailFromWishlist');
    const $putOnDetailWishlist = jQuery('.js-putDetailOnWishlist');

    $removeFromWishlist.on("click", lsSubOneFromBadgeAndStore);
    $putOnWishlist.on("click", lsAddOneToBadgeAndStore);
    $removeFromDetailWishlist.on("click", lsSubOneFromBadgeAndStore);
    $putOnDetailWishlist.on("click", lsAddOneToBadgeAndStore);

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

});

/**
 * Adds Badge with BadgeValue
 * @param val
 */
function showBadgeAndText(val) {
    localStorage.setItem("badgeValue", val);
    var wishlistBadge = '<span class="badge badge-light memo-badge">' + val + '</span>';

    if (jQuery('.memo-badge').length) {
        jQuery('a span.memo-badge').text(val);
    } else {
        jQuery(wishlistBadge).appendTo('a.link-memo');
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
    localStorage.setItem("badgeValue", sum);
    return sum;
}

/**
 * Subtracts one from value of Merkzettel-Badge
 * @returns {number}
 */
function lsSubOneFromBadge() {
    let sub = lsGetBadgeCount() - 1;
    localStorage.setItem("badgeValue", sub);
    return sub;
}

/**
 * Get value of items on Merkliste, set Badge and store value in LocalStorage
 */
function updateWishlistBadgeAtRefresh() {

    var getItemsRoute = '/gutesio/operator/wishlist/getItemCount';

    /*
    const scripts = document.getElementsByTagName('script');
    for (let script of scripts) {
        //to prevent duplicated server call
        if (script.src && script.src.includes("c4g_all.js")) {
            return true;
        }
    }*/

    jQuery.get(getItemsRoute).done((data) => {
        var countItemsServer = 0;
        if (data.count > 0) {
            countItemsServer = data.count;
            localStorage.setItem("badgeValue", countItemsServer);

            var wishlistBadge = '<span class="badge badge-light memo-badge">' + countItemsServer + '</span>';

            if (jQuery('.memo-badge').length) {
                jQuery('a span.memo-badge').text(countItemsServer);
            } else {
                jQuery(wishlistBadge).appendTo('a.link-memo');
            }
        } else {
            localStorage.setItem("badgeValue", "0");
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
    jQuery(i).addClass("fas fa-heart ml-2");
    element.appendChild(i);

    let badgeVal= String(getBadgeValue() +1);

    if (jQuery('.memo-badge')) {
        jQuery('.memo-badge').remove();
    }

    var wishlistBadge = '<span class="badge badge-light memo-badge">' + badgeVal + '</span>';
    jQuery(wishlistBadge).appendTo('a.link-memo');
}

function removeFromBadge(event) {
    let element = event.target.tagName == "I" ? event.target.parentNode : event.target;

    jQuery.post("gutesio/operator/wishlist/remove/" + element.dataset.uuid);
    element.innerText = "Merken";
    jQuery(element).attr("class", "btn btn-primary put-on-wishlist");
    jQuery(element).on("click", putOnWishlistCallback);
    jQuery(element).off("click", removeFromWishlistCallback);
    let i = document.createElement("i");
    jQuery(i).addClass("far fa-heart ml-2");
    element.appendChild(i);

    let badgeVal = String(getBadgeValue() -1);

    if (jQuery('.memo-badge')) {
        jQuery('.memo-badge').remove();
    }

    if (badgeVal > 0) {
        var wishlistBadge = '<span class="badge badge-light memo-badge">' + badgeVal + '</span>';
        jQuery(wishlistBadge).appendTo('a.link-memo');

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
    if (jQuery('.memo-badge')) {
        valBadge = parseInt(document.getElementsByClassName('memo-badge')[0].innerHTML);
    }
    return valBadge;
}

window.c4gMapsHooks = window.c4gMapsHooks || {};
window.c4gMapsHooks.proxy_fillPopup = window.c4gMapsHooks.proxy_fillPopup || [];

window.c4gMapsHooks.proxy_fillPopup.push(function (params) {
    window.setTimeout(function () {
        jQuery(".put-on-wishlist:not(.isclickable)").on("click", putOnWishlistCallback);
        jQuery(".put-on-wishlist").addClass("isclickable");
        jQuery(".remove-from-wishlist:not(.isclickable)").on("click", removeFromWishlistCallback);
        jQuery(".remove-from-wishlist").addClass("isclickable");
    }, 100);
});