function renderTemplateNode(e,n){var r=document.querySelector("#elementTpl").content.querySelector("#element").cloneNode(!0);return r.innerHTML=r.innerHTML.replaceAll("redirectUrl",e.redirectUrl),e.image&&(r.innerHTML=r.innerHTML.replaceAll("elementImageSrc",e.image.src),r.innerHTML=r.innerHTML.replaceAll("elementImageAlt",e.image.alt)),r.innerHTML=r.innerHTML.replaceAll("elementName",e.name),r.innerHTML=r.innerHTML.replaceAll("elementType",e.type),r}document.addEventListener("DOMContentLoaded",()=>{const t=document.querySelector("#nearby-showcase-list");function n(e){let n="/gutesio/operator/nearby_showcase/"+t.getAttribute("module-id");e&&(n=n+"/"+e.join(",")),fetch(n).then(e=>{e.ok?e.json().then(n=>{if(0!==n.length){0<t.children.length&&(t.innerHTML="");for(let e=0;e<n.length;e++){var r=renderTemplateNode(n[e],e);t.appendChild(r)}}else console.error("An Error occurred loading new data from the server.")}):console.error("An Error occurred loading new data from the server.")})}"true"===t.getAttribute("check-position")?navigator.geolocation&&navigator.geolocation.getCurrentPosition(e=>{n([e.coords.longitude,e.coords.latitude])},()=>{n(null)}):n(null)});