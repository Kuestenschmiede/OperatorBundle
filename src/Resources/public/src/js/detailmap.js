jQuery(document).ready(() => {
  let mapDataUrl = "/gutesio/operator/showcase_detail_get_map_data";
  let mapDiv = document.createElement("div");
  mapDiv.className = "c4g_map";
  mapDiv.id = "c4g-map-container";
  fetch(mapDataUrl)
    .then(response => response.json())
    .then(data => {
      mapDiv.id += "-" + data.mapId;
      data.mapDiv = mapDiv.id;
      if (document.querySelector("#react__detail-view__map")) {
        document.querySelector("#react__detail-view__map").appendChild(mapDiv);
      }
      let mapData = {};
      mapData[data.mapId] = data;
      window.initMaps(mapData);
    }
  );
});