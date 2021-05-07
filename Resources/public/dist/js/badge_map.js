function showBadgeAndText(e){localStorage.setItem("badgeValue",e);var t='<span class="badge badge-light memo-badge">'+e+"</span>";$(".memo-badge").length?$("a span.memo-badge").text(e):$(t).appendTo("a.link-memo")}function lsGetBadgeCount(){var e=localStorage.getItem("badgeValue");return parseInt(e)}function lsGetValueOf(e){return localStorage.getItem(e)}function lsCheckIfKeyExists(e){let t=!1;return null!==localStorage.getItem(e)&&(t=!0),t}function lsAddOneToBadge(){var e=lsGetBadgeCount()+1;return localStorage.setItem("badgeValue",e),e}function lsSubOneFromBadge(){var e=lsGetBadgeCount()-1;return localStorage.setItem("badgeValue",e),e}function updateWishlistBadgeAtRefresh(){$.get("/gutesio/operator/wishlist/getItemCount").done(e=>{var t;0<e.count?(t=e.count,localStorage.setItem("badgeValue",t),e='<span class="badge badge-light memo-badge">'+t+"</span>",$(".memo-badge").length?$("a span.memo-badge").text(t):$(e).appendTo("a.link-memo")):localStorage.setItem("badgeValue","0")})}function addToBadge(e){let t="I"==e.target.tagName?e.target.parentNode:e.target;jQuery.post("gutesio/operator/wishlist/add/showcase/"+t.dataset.uuid),t.innerText="Gemerkt",jQuery(t).attr("class","btn btn-warning remove-from-wishlist on-wishlist"),jQuery(t).on("click",removeFromWishlistCallback),jQuery(t).off("click",putOnWishlistCallback);e=document.createElement("i");jQuery(e).addClass("fas fa-heart ml-2"),t.appendChild(e);e=getBadgeValue();e+=1,$(".memo-badge").length&&$(".memo-badge").remove(),$('<span class="badge badge-light memo-badge">'+e+"</span>").appendTo("a.link-memo")}function removeFromBadge(e){let t="I"==e.target.tagName?e.target.parentNode:e.target;jQuery.post("gutesio/operator/wishlist/remove/"+t.dataset.uuid),t.innerText="Merken",jQuery(t).attr("class","btn btn-primary put-on-wishlist"),jQuery(t).on("click",putOnWishlistCallback),jQuery(t).off("click",removeFromWishlistCallback);e=document.createElement("i");jQuery(e).addClass("far fa-heart ml-2"),t.appendChild(e);e=getBadgeValue();$(".memo-badge").length&&$(".memo-badge").remove(),0<e&&(e-=1,$('<span class="badge badge-light memo-badge">'+e+"</span>").appendTo("a.link-memo"))}function removeFromWishlistCallback(e){removeFromBadge(e)}function putOnWishlistCallback(e){addToBadge(e)}function deleteItemOnGlobalList(e){subWishlistItemBadge()}function getBadgeValue(){let e=0;return $(".memo-badge").length&&(e=parseInt($(".memo-badge").text())),e}jQuery(document).ready(function(){updateWishlistBadgeAtRefresh();const e=$(".remove-from-wishlist"),t=$(".put-on-wishlist"),a=$(".js-removeDetailFromWishlist"),o=$(".js-putDetailOnWishlist");function l(){showBadgeAndText(lsGetBadgeCount()+1)}function n(){showBadgeAndText(lsGetBadgeCount()-1)}e.on("click",n),t.on("click",l),a.on("click",n),o.on("click",l)}),window.c4gMapsHooks=window.c4gMapsHooks||{},window.c4gMapsHooks.proxy_fillPopup=window.c4gMapsHooks.proxy_fillPopup||[],window.c4gMapsHooks.proxy_fillPopup.push(function(e){window.setTimeout(function(){jQuery(".put-on-wishlist").on("click",putOnWishlistCallback),jQuery(".remove-from-wishlist").on("click",removeFromWishlistCallback)},100)});