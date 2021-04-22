jQuery(document).ready(function () {
  updateWishlistBadgeAtRefresh();

// Update the Badge on click
  $(".put-on-wishlist").on("click", putOnWishlistCallback);
  $(".remove-from-wishlist").on("click", updateWishlistBadgeAtRefresh);

// show badge at first pageload
  updateWishlistBadgeAtRefresh();
});

function removeFromWishlistCallback (event) {
  removeFromBadge();

};

function putOnWishlistCallback (event) {
  addToBadge();
  $(".btn.remove-from-wishlist").on("click", removeFromWishlistCallback);
}

function deleteItemOnGlobalList (event) {
  subWishlistItemBadge();
};


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

window.c4gMapsHooks = window.c4gMapsHooks || {};
window.c4gMapsHooks.proxy_fillPopup = window.c4gMapsHooks.proxy_fillPopup || [];

window.c4gMapsHooks.proxy_fillPopup.push(function(params) {
  window.setTimeout(function () {
    jQuery(".put-on-wishlist").on("click", putOnWishlistCallback);
    jQuery(".remove-from-wishlist").on("click", removeFromWishlistCallback);
  }, 100);
});