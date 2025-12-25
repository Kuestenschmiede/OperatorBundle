(function() {
    if (window.gutesioLocstyleTriggerInitialized) return;
    window.gutesioLocstyleTriggerInitialized = true;

    console.log("gutesio locstyle trigger: starting initialization");

    if (!window.c4gMapsHooks) window.c4gMapsHooks = {};
    if (!window.c4gMapsHooks.hook_layer) window.c4gMapsHooks.hook_layer = [];
    if (!window.c4gMapsHooks.loaded) window.c4gMapsHooks.loaded = [];
    if (!window.c4gMapsHooks.proxy_layer_loaded) window.c4gMapsHooks.proxy_layer_loaded = [];

    window.gutesioProxies = window.gutesioProxies || new Map();
    window.gutesioPendingLayers = window.gutesioPendingLayers || new Map();
    
    let gutsioStyleFunctionMap = new Map();
    window.gutesioLastMap = null;
    window.gutsioStyleCalls = 0;
    window.gutsioVisualVerify = false; // Disabled by default now, as we have fallbacks and icons

    // Flag to track if we should disable visual verification after a while if things look stable
    let stableTimer = setTimeout(() => {
        // window.gutsioVisualVerify = false; 
    }, 30000);

    // Global collection for tracking styles we already tried to load
    window._gutsioMissingStylesTriggered = window._gutsioMissingStylesTriggered || new Set();

    function patchLayerInstance(layer) {
        if (!layer || layer._gutsioLayerPatched) return;
        layer._gutsioLayerPatched = true;
        
        // Check if it's a Cluster source and handle it
        let source = layer.getSource();
        if (source && (typeof source.getDistance === 'function' || (source.getSource && source.getSource() && typeof source.getSource().getDistance === 'function'))) {
            console.log("gutesio locstyle trigger: detected cluster source on layer", layer);
            // Ensure clusters are visible and have a style
            if (typeof layer.setZIndex === 'function') layer.setZIndex(10000000);
        }

        let originalSetStyle = layer.setStyle;
        if (typeof originalSetStyle === 'function') {
            layer.setStyle = function(style) {
                if (layer.get('isGutesio') || layer.isGutesio) {
                     // Force our style function for GutesIO layers
                     // We need access to gutsioStyleFunction here. 
                     // Since it's map-specific, we should probably look it up.
                     let map = layer.getMap ? layer.getMap() : null; // layer.getMap is not standard OL
                     // In con4gis, we might need a different way to find the map or the style function
                     return originalSetStyle.call(this, gutsioStyleFunctionMap.get(window.gutesioLastMap) || style);
                }
                return originalSetStyle.apply(this, arguments);
            };
            // Initial style set
            let map = window.gutesioLastMap;
            let gutsioStyleFunction = gutsioStyleFunctionMap.get(map);
            if (gutsioStyleFunction && (layer.get('isGutesio') || layer.isGutesio)) {
                layer.setStyle(gutsioStyleFunction);
            }
        }
        
        if (layer.getSource && layer.getSource()) {
            let source = layer.getSource();
            let actualSource = (typeof source.getSource === 'function') ? source.getSource() : source;
            if (actualSource && typeof actualSource.on === 'function') {
                actualSource.on('addfeature', function() {
                    if (layer.get('isGutesio') || layer.isGutesio) {
                         let gutsioStyleFunction = gutsioStyleFunctionMap.get(window.gutesioLastMap);
                         if (gutsioStyleFunction) layer.setStyle(gutsioStyleFunction);
                    }
                });
            }
        }

        if (layer.setVisible) {
            let originalSetVisible = layer.setVisible;
            layer.setVisible = function(visible) {
                if (visible === false && (layer.get('isGutesio') || layer.isGutesio)) {
                    console.warn("gutesio locstyle trigger: prevented setVisible(false) on GutesIO layer");
                    return originalSetVisible.call(this, true);
                }
                return originalSetVisible.apply(this, arguments);
            };
            if (layer.get('isGutesio') || layer.isGutesio) originalSetVisible.call(layer, true);
        }

        if (layer.setOpacity) {
            let originalSetOpacity = layer.setOpacity;
            layer.setOpacity = function(opacity) {
                if (opacity < 1 && (layer.get('isGutesio') || layer.isGutesio)) {
                    console.warn("gutesio locstyle trigger: prevented setOpacity < 1 on GutesIO layer");
                    return originalSetOpacity.call(this, 1);
                }
                return originalSetOpacity.apply(this, arguments);
            };
            if (layer.get('isGutesio') || layer.isGutesio) originalSetOpacity.call(layer, 1);
        }

        if (layer.setSource) {
            let originalSetSource = layer.setSource;
            layer.setSource = function(source) {
                if (source) patchSource(source, layer);
                return originalSetSource.apply(this, arguments);
            };
            if (layer.getSource()) patchSource(layer.getSource(), layer);
        }
        
        if (typeof layer.setDeclutter === 'function') layer.setDeclutter(false);
        if (layer.get('isGutesio') || layer.isGutesio) {
            if (layer.setZIndex) layer.setZIndex(10000000);
        }
    };

    function patchSource(source, layer) {
        if (!source || source._gutsioSourcePatched) return;
        source._gutsioSourcePatched = true;

        let actualSource = (typeof source.getSource === 'function') ? source.getSource() : source;
        if (actualSource) {
            // Hook into feature changes to ensure they keep the isGutesio flag
            if (typeof actualSource.on === 'function') {
                actualSource.on('addfeature', function(event) {
                    if (event.feature) {
                        event.feature.set('isGutesio', true);
                        if (layer && (layer.get('isGutesio') || layer.isGutesio)) event.feature.set('isGutesio', true);
                    }
                });
            }

            // Prevent some common con4gis behaviors that might hide features
            let originalGetFeatures = actualSource.getFeatures;
            if (typeof originalGetFeatures === 'function') {
                actualSource.getFeatures = function() {
                    let features = originalGetFeatures.apply(this, arguments);
                    if (features && Array.isArray(features)) {
                        features.forEach(f => {
                            if (f && f.set && !f.get('isGutesio')) f.set('isGutesio', true);
                        });
                    }
                    return features;
                };
            }
            
            let originalClear = actualSource.clear;
            if (typeof originalClear === 'function') {
                actualSource.clear = function() {
                    if (layer && (layer.get('isGutesio') || layer.isGutesio)) {
                        console.warn("gutesio locstyle trigger: prevented clear() on GutesIO source");
                        return;
                    }
                    return originalClear.apply(this, arguments);
                };
            }

            let originalRemoveFeature = actualSource.removeFeature;
            if (typeof originalRemoveFeature === 'function') {
                actualSource.removeFeature = function(feature) {
                    if (feature && feature.get('isGutesio') && layer && (layer.get('isGutesio') || layer.isGutesio)) {
                        console.warn("gutesio locstyle trigger: prevented removeFeature() on GutesIO source");
                        return;
                    }
                    return originalRemoveFeature.apply(this, arguments);
                };
            }
        }
    }

    function registerProxy(proxy) {
        if (proxy && proxy.options && proxy.options.mapController && proxy.options.mapController.map) {
            let map = proxy.options.mapController.map;
            if (!window.gutesioProxies.has(map)) {
                console.log("gutesio locstyle trigger: proxy registered for map", map);
                window.gutesioLastMap = map;
                window.gutesioProxies.set(map, proxy);
                map.isGutesio = true;
                patchMap(map);
                patchLayerController(proxy);
                patchVectorCollection(proxy);

                let pending = window.gutesioPendingLayers.get(map);
                if (pending) {
                    console.log("gutesio locstyle trigger: processing pending layers for map");
                    pending.forEach(layerData => processLayerData(proxy, layerData));
                    window.gutesioPendingLayers.delete(map);
                }
            }
        }
    }

    function patchMap(map) {
        if (map._gutsioPatched) return;
        map._gutsioPatched = true;
        
        try {
            if (typeof map.set === 'function') map.set('declutter', false);
        } catch (e) {}

        let originalRemoveLayer = map.removeLayer;
        map.removeLayer = function(layer) {
            if (layer && (layer.get('isGutesio') || layer.isGutesio)) {
                console.warn("gutesio locstyle trigger: prevented removal of GutesIO layer from map");
                return;
            }
            return originalRemoveLayer.apply(this, arguments);
        };

    // Aggressively prevent clear() on all sources that might contain GutesIO features
    let originalAddLayer = map.addLayer;
    map.addLayer = function(layer) {
        let result = originalAddLayer.apply(this, arguments);
        if (layer) {
            if (layer.get && (layer.get('isGutesio') || layer.isGutesio)) {
                // Already marked
            } else if (layer.getSource && layer.getSource()) {
                // Check if this layer might be one of ours that was just added
                let source = layer.getSource();
                let actualSource = (typeof source.getSource === 'function') ? source.getSource() : source;
                if (actualSource && actualSource.getFeatures) {
                    let features = actualSource.getFeatures();
                    if (features && features.some(f => f && f.get && f.get('isGutesio'))) {
                        layer.isGutesio = true;
                        if (layer.set) layer.set('isGutesio', true);
                    }
                }
            }
            patchLayerInstance(layer);
        }
        return result;
    };
    }

    function patchVectorCollection(proxy) {
        let controller = proxy.layerController;
        if (!controller || !controller.vectorCollection) return;
        
        let collection = controller.vectorCollection;
        if (collection._gutsioPatched) return;
        collection._gutsioPatched = true;
        
        let originalRemove = collection.remove;
        collection.remove = function(element) {
            if (element && typeof element.get === 'function' && element.get('isGutesio')) { }
            return originalRemove.apply(this, arguments);
        };
    }

    function patchLayerController(proxy) {
        if (!proxy.layerController) return;
        let controller = proxy.layerController;
        let map = proxy.options.mapController.map;
        
        if (proxy.options.mapController.data) {
            proxy.options.mapController.data.cluster_all = true;
            proxy.options.mapController.data.cluster = true;
            proxy.options.mapController.data.cluster_distance = 40;
        }
        
        if (!controller._gutsioHandleZoomPatched) {
            let originalHandleZoomChilds = controller.handleZoomChilds;
            controller.handleZoomChilds = function(zoom, childState, child) {
                let result = originalHandleZoomChilds.apply(this, arguments);
                if (child && (child.isGutesio || child.type === 'GeoJSON' || child.format === 'GeoJSON' || child.type === 'gutes' || child.type === 'gutesPart' || child.type === 'gutesElem')) {
                    if (child.zoom) { child.zoom.min = "0"; child.zoom.max = "40"; }
                    if (result) { result.greyed = false; result.active = true; }
                    if (childState) { childState.greyed = false; childState.active = true; }
                }
                return result;
            };
            controller._gutsioHandleZoomPatched = true;
        }

        if (!controller._gutsioShowHidePatched) {
            let originalShow = controller.show;
            let originalHide = controller.hide;
            
            controller.show = function(id, showElement, layerId, layerKey) {
                return originalShow.apply(this, arguments);
            };
            
            controller.hide = function(id, hideElement, layerId, layerKey) {
                let shouldPrevent = false;
                if (Array.isArray(hideElement) && hideElement.length > 0) {
                    if (hideElement[0] && typeof hideElement[0].get === 'function' && hideElement[0].get('isGutesio')) shouldPrevent = true;
                }
                if (!shouldPrevent && hideElement && typeof hideElement.getSource === 'function') {
                    if (hideElement.get('isGutesio') || hideElement.isGutesio) shouldPrevent = true;
                }
                if (!shouldPrevent) {
                    let searchId = layerId || id;
                    let layer = (this.objLayers && Array.isArray(this.objLayers)) ? this.objLayers.find(l => l.id == searchId) : (this.objLayers ? this.objLayers[searchId] : null);
                    if (layer && (layer.isGutesio || layer.type === 'GeoJSON' || layer.format === 'GeoJSON')) shouldPrevent = true;
                }
                if (shouldPrevent) {
                    if (Array.isArray(hideElement)) {
                        hideElement.forEach(f => { if (f.set) f.set('greyed', false); });
                    }
                    return; 
                }
                return originalHide.apply(this, arguments);
            };
            controller._gutsioShowHidePatched = true;
        }

        let gutsioStyleFunction = function(feature, resolution) {
            try {
                window.gutsioStyleCalls++;
                if (!feature || typeof feature.get !== 'function') return [];

                let features = feature.get('features');
                let targetFeature = (features && features.length > 0) ? features[0] : feature;
                
                let isGutesio = targetFeature.get('isGutesio') || targetFeature.get('tid') || false;
                
                if (isGutesio) {
                    let locstyleId = targetFeature.get('locstyle') || targetFeature.get('locationStyle');
                    if (locstyleId) locstyleId = parseInt(locstyleId);
                    
                    // Handle clusters
                    if (features && features.length > 1) {
                        try {
                            let olObj = window.ol || {};
                            let olStyleObj = olObj.style || window.olStyle || {};
                            
                            let Circle = olStyleObj.Circle || (window.ol && window.ol.style && window.ol.style.Circle);
                            let Fill = olStyleObj.Fill || (window.ol && window.ol.style && window.ol.style.Fill);
                            let Stroke = olStyleObj.Stroke || (window.ol && window.ol.style && window.ol.style.Stroke);
                            let Style = olStyleObj.Style || (window.ol && window.ol.style && window.ol.style.Style);
                            let Text = olStyleObj.Text || (window.ol && window.ol.style && window.ol.style.Text);
                            
                            if (Style && Circle && Fill && Text) {
                                let clusterStyle = new Style({
                                    image: new Circle({
                                        radius: 22, // Slightly larger for better visibility
                                        stroke: new Stroke({color: '#ffffff', width: 3}),
                                        fill: new Fill({color: '#3399CC'})
                                    }),
                                    text: new Text({
                                        text: features.length.toString(),
                                        fill: new Fill({color: '#ffffff'}),
                                        font: 'bold 16px sans-serif',
                                        textAlign: 'center',
                                        textBaseline: 'middle'
                                    }),
                                    zIndex: 10000005 // Even higher zIndex for clusters
                                });
                                if (window.gutsioStyleCalls < 100) console.log("gutesio locstyle trigger: cluster style created for " + features.length + " features", clusterStyle);
                                return [clusterStyle]; // Always return an array
                            }
                        } catch (e) {
                            if (window.gutsioStyleCalls < 100) console.error("gutesio locstyle trigger: cluster style error", e);
                        }
                    }

                    if (locstyleId && proxy.locationStyleController) {
                        if (proxy.locationStyleController.arrLocStyles[locstyleId]) {
                            let styleObj = proxy.locationStyleController.arrLocStyles[locstyleId];
                            if (!styleObj.style || typeof styleObj.style !== 'function') {
                                try {
                                    styleObj.style = styleObj.getStyleFunction();
                                } catch (e) {
                                    if (window.gutsioStyleCalls < 50) console.warn("gutesio locstyle trigger: getStyleFunction failed for " + locstyleId, e);
                                }
                            }
                            try {
                                let style = (typeof styleObj.style === 'function') ? styleObj.style(targetFeature, resolution) : styleObj.style;
                                
                                // Visual verification mode: If a feature is GutesIO, make sure it has SOMETHING visible
                                if (window.gutsioVisualVerify) {
                                    if (!styleObj._gutsioVerifyStyle) {
                                        try {
                                            let Circle = (window.ol && window.ol.style && window.ol.style.Circle) ? window.ol.style.Circle : (window.olStyle ? window.olStyle.Circle : null);
                                            let Fill = (window.ol && window.ol.style && window.ol.style.Fill) ? window.ol.style.Fill : (window.olStyle ? window.olStyle.Fill : null);
                                            let Stroke = (window.ol && window.ol.style && window.ol.style.Stroke) ? window.ol.style.Stroke : (window.olStyle ? window.olStyle.Stroke : null);
                                            let Style = (window.ol && window.ol.style && window.ol.style.Style) ? window.ol.style.Style : (window.olStyle ? window.olStyle.Style : null);
                                            let Text = (window.ol && window.ol.style && window.ol.style.Text) ? window.ol.style.Text : (window.olStyle ? window.olStyle.Text : null);
                                            if (Circle && Fill && Stroke && Style) {
                                                styleObj._gutsioVerifyStyle = new Style({
                                                    image: new Circle({
                                                        radius: 20,
                                                        fill: new Fill({color: 'rgba(0, 255, 0, 0.4)'}),
                                                        stroke: new Stroke({color: '#00ff00', width: 3})
                                                    }),
                                                    text: Text ? new Text({
                                                        text: locstyleId.toString(),
                                                        font: 'bold 12px sans-serif',
                                                        fill: new Fill({color: '#000000'}),
                                                        stroke: new Stroke({color: '#ffffff', width: 2})
                                                    }) : null,
                                                    zIndex: 10000001
                                                });
                                            }
                                        } catch (e) {}
                                    }
                                    if (styleObj._gutsioVerifyStyle) {
                                        if (Array.isArray(style)) return style.concat([styleObj._gutsioVerifyStyle]);
                                        return [style, styleObj._gutsioVerifyStyle];
                                    }
                                }

                                if (style && (!Array.isArray(style) || style.length > 0)) {
                                    if (Array.isArray(style)) style.forEach(s => { if (s.setZIndex) s.setZIndex(10000000); });
                                    else if (style.setZIndex) style.setZIndex(10000000);
                                    
                                    // Check if the style has an image and if it's loaded
                                    if (style.getImage && typeof style.getImage === 'function') {
                                        let img = style.getImage();
                                        if (img && img.getSrc && typeof img.getSrc === 'function') {
                                            let src = img.getSrc();
                                            if (!src || src === 'undefined' || src.length < 5) {
                                                if (window.gutsioStyleCalls % 50 === 0) {
                                                    console.warn("gutesio locstyle trigger: invalid src for style", {id: locstyleId, src: src});
                                                }
                                                if (styleObj._gutsioFallbackStyle) return [styleObj._gutsioFallbackStyle];
                                            }
                                        }
                                    }

                                    // Debug first few calls
                                    if (window.gutsioStyleCalls < 100) {
                                        let debugStyle = Array.isArray(style) ? style[0] : style;
                                        let imgSrc = 'no image';
                                        let styletype = styleObj.locStyleArr ? styleObj.locStyleArr.styletype : 'unknown';
                                        if (debugStyle && debugStyle.getImage && debugStyle.getImage()) {
                                            let img = debugStyle.getImage();
                                            if (img.getSrc && typeof img.getSrc === 'function') {
                                                imgSrc = img.getSrc();
                                            } else {
                                                // RegularShape/Circle don't have getSrc, that's fine
                                                let shapeName = 'unknown shape';
                                                if (img.constructor && img.constructor.name && img.constructor.name.length > 1) shapeName = img.constructor.name;
                                                else if (img.points_ !== undefined) shapeName = 'RegularShape';
                                                else if (img.radius_ !== undefined) shapeName = 'Circle';
                                                else if (img.constructor && img.constructor.name === 'h') shapeName = 'RegularShape (minified)';
                                                imgSrc = 'vector shape (' + shapeName + ')';
                                            }
                                            if (imgSrc === undefined || imgSrc === 'undefined') imgSrc = 'undefined';
                                            
                                            // Try to find src in the style object itself if getSrc fails
                                            if ((!imgSrc || imgSrc === 'undefined' || imgSrc.indexOf('vector shape') === 0) && styleObj.locStyleArr) {
                                                let p = styleObj.locStyleArr.icon_src || styleObj.locStyleArr.svgSrc;
                                                if (p) imgSrc = p + ' (from locStyleArr)';
                                            }
                                        }
                                        console.log("gutesio locstyle trigger: style returned", {
                                            locstyleId: locstyleId,
                                            style: style,
                                            zIndex: debugStyle ? (debugStyle.getZIndex ? debugStyle.getZIndex() : 'none') : 'none',
                                            imgSrc: imgSrc,
                                            styletype: styletype,
                                            resolution: resolution,
                                            zoom: map.getView().getZoom(),
                                            locStyleArr: styleObj.locStyleArr,
                                            featureProperties: targetFeature.getProperties(),
                                            availableStyles: proxy.locationStyleController ? Object.keys(proxy.locationStyleController.arrLocStyles) : 'none'
                                        });
                                    }

                                    return Array.isArray(style) ? style : [style];
                                }
                                if (styleObj._gutsioFallbackStyle) return [styleObj._gutsioFallbackStyle];
                            } catch (e) { }
                        } else {
                            // Style missing in controller, try to trigger a load if we haven't too many calls
                                    if (window.gutsioStyleCalls % 50 === 0) {
                                        console.warn("gutesio locstyle trigger: no style found for feature", {
                                            id: targetFeature.getId(),
                                            locstyleId: locstyleId,
                                            hasController: !!proxy.locationStyleController,
                                            availableStyles: proxy.locationStyleController ? Object.keys(proxy.locationStyleController.arrLocStyles) : 'none'
                                        });
                                        
                                        // Aggressively force load if controller exists
                                        if (proxy.locationStyleController && typeof proxy.locationStyleController.loadLocationStyles === 'function') {
                                            if (!window._gutsioMissingStylesTriggered.has(locstyleId)) {
                                                window._gutsioMissingStylesTriggered.add(locstyleId);
                                                console.log("gutesio locstyle trigger: forcing load for missing style " + locstyleId);
                                                proxy.locationStyleController.loadLocationStyles([locstyleId], {
                                                    always: function() {
                                                        console.log("gutesio locstyle trigger: load completed for " + locstyleId);
                                                        // Ensure the newly loaded style is also patched
                                                        let newlyLoadedStyle = proxy.locationStyleController.arrLocStyles[locstyleId];
                                                        if (newlyLoadedStyle) {
                                                            let data = newlyLoadedStyle.locStyleArr || newlyLoadedStyle;
                                                            let path = data.icon_src || data.svgSrc;
                                                            if (!path || (path.indexOf('files/') !== 0 && path.indexOf('/') !== 0)) {
                                                                applyFallback(newlyLoadedStyle, locstyleId, 'ffff00');
                                                            } else {
                                                                data.minzoom = "0"; data.maxzoom = "40";
                                                                try {
                                                                    newlyLoadedStyle.style = newlyLoadedStyle.getStyleFunction();
                                                                } catch (e) {}
                                                            }
                                                        }
                                                        triggerRedraw(proxy);
                                                    }
                                                });
                                            }
                                        }
                                        
                                        // Trigger a re-scan of layers to find missing styles
                                        setTimeout(() => {
                                            if (proxy.options && proxy.options.mapController && proxy.options.mapController.data) {
                                                processLayerData(proxy, proxy.options.mapController.data);
                                            }
                                        }, 1000); // Increased timeout to let async loads finish
                                    }
                            
                            // If style missing, we still want visual verification!
                            if (window.gutsioVisualVerify) {
                                if (!proxy._gutsioMissingStyle) {
                                    try {
                                        let Circle = (window.ol && window.ol.style && window.ol.style.Circle) ? window.ol.style.Circle : (window.olStyle ? window.olStyle.Circle : null);
                                        let Fill = (window.ol && window.ol.style && window.ol.style.Fill) ? window.ol.style.Fill : (window.olStyle ? window.olStyle.Fill : null);
                                        let Stroke = (window.ol && window.ol.style && window.ol.style.Stroke) ? window.ol.style.Stroke : (window.olStyle ? window.olStyle.Stroke : null);
                                        let Style = (window.ol && window.ol.style && window.ol.style.Style) ? window.ol.style.Style : (window.olStyle ? window.olStyle.Style : null);
                                        let Text = (window.ol && window.ol.style && window.ol.style.Text) ? window.ol.style.Text : (window.olStyle ? window.olStyle.Text : null);
                                        if (Circle && Fill && Stroke && Style) {
                                            proxy._gutsioMissingStyle = new Style({
                                                image: new Circle({
                                                    radius: 25, // Larger for missing
                                                    fill: new Fill({color: 'rgba(255, 0, 0, 0.5)'}),
                                                    stroke: new Stroke({color: '#ff0000', width: 4})
                                                }),
                                                text: Text ? new Text({
                                                    text: "MISSING: " + locstyleId,
                                                    font: 'bold 12px sans-serif',
                                                    fill: new Fill({color: '#000000'}),
                                                    stroke: new Stroke({color: '#ffffff', width: 2})
                                                }) : null,
                                                zIndex: 10000002
                                            });
                                        }
                                    } catch (e) {}
                                }
                                if (proxy._gutsioMissingStyle) return [proxy._gutsioMissingStyle];
                            }
                        }
                    }
                }
            } catch (err) {
                if (window.gutsioStyleCalls < 20) console.error("gutesio locstyle trigger: CRITICAL style error", err);
            }
            return []; 
        };

        controller.clusterStyleFunction = gutsioStyleFunction;
        gutsioStyleFunctionMap.set(map, gutsioStyleFunction);
        
        if (!proxy.options.mapController._gutsioSetLayersPatched) {
            let originalSetObjLayers = proxy.options.mapController.setObjLayers;
            proxy.options.mapController.setObjLayers = function(objLayers) {
                if (Array.isArray(objLayers)) {
                    objLayers.forEach(function mark(l) {
                        if (l && (l.isGutesio || l.type === 'GeoJSON' || l.format === 'GeoJSON' || l.type === 'gutes' || l.type === 'gutesPart' || l.type === 'gutesElem')) {
                            l.isGutesio = true; l.hide = false; l.data_hidelayer = "0";
                        }
                        if (l && l.childs) l.childs.forEach(mark);
                    });
                }
                return originalSetObjLayers.apply(this, arguments);
            };
            proxy.options.mapController._gutsioSetLayersPatched = true;
        }

        map.getLayers().forEach(function scan(l) {
            if (!l) return;
            let source = (typeof l.getSource === 'function') ? l.getSource() : null;
            if (source) patchLayerInstance(l);
            if (typeof l.getLayers === 'function') l.getLayers().forEach(scan);
        });
    }

    window.c4gMapsHooks.loaded.push(registerProxy);
    window.c4gMapsHooks.proxy_layer_loaded.push(registerProxy);

    window.c4gMapsHooks.hook_layer.push(function(data) {
        if (!data || !data.layer || !data.map) return;
        let proxy = window.gutesioProxies.get(data.map);
        if (!proxy) {
            let pending = window.gutesioPendingLayers.get(data.map) || [];
            pending.push(data.layer);
            window.gutesioPendingLayers.set(data.map, pending);
            return;
        }
        processLayerData(proxy, data.layer);
    });

    function applyFallback(styleObj, id, color) {
        let styleData = styleObj.locStyleArr || styleObj;
        styleData.styletype = 'square';
        styleData.icon_src = ""; styleData.svgSrc = ""; styleData.style_function_js = "";
        styleData.fillcolor = [color || 'ffff00', '100']; styleData.strokecolor = ['000000', '100'];
        styleData.strokewidth = {value: 3, unit: 'px'}; styleData.radius = {value: 12, unit: 'px'};
        styleData.minzoom = "0"; styleData.maxzoom = "40";
        
        try {
            if (typeof styleObj.getStyleFunction === 'function') {
                styleObj.style = styleObj.getStyleFunction();
            }
        } catch (e) {
            console.warn("gutesio locstyle trigger: applyFallback getStyleFunction failed", e);
        }
        
        try {
            let Style = (window.ol && window.ol.style && window.ol.style.Style) ? window.ol.style.Style : (window.olStyle ? window.olStyle.Style : null);
            let Fill = (window.ol && window.ol.style && window.ol.style.Fill) ? window.ol.style.Fill : (window.olStyle ? window.olStyle.Fill : null);
            let Stroke = (window.ol && window.ol.style && window.ol.style.Stroke) ? window.ol.style.Stroke : (window.olStyle ? window.olStyle.Stroke : null);
            let RegularShape = (window.ol && window.ol.style && window.ol.style.RegularShape) ? window.ol.style.RegularShape : (window.olStyle ? window.olStyle.RegularShape : null);
            let Text = (window.ol && window.ol.style && window.ol.style.Text) ? window.ol.style.Text : (window.olStyle ? window.olStyle.Text : null);

            if (Style && Fill && Stroke && RegularShape) {
                styleObj._gutsioFallbackStyle = new Style({
                    image: new RegularShape({
                        fill: new Fill({color: '#' + (color || 'ffff00')}),
                        stroke: new Stroke({color: '#000000', width: 3}),
                        points: 4, radius: 12, angle: Math.PI / 4
                    }),
                    text: Text ? new Text({
                        text: id.toString(),
                        font: 'bold 10px sans-serif',
                        fill: new Fill({color: '#000000'})
                    }) : null,
                    zIndex: 1000000
                });
            }
        } catch (e) {}
    }

    function processLayerData(proxy, layers) {
        let controller = proxy.locationStyleController;
        if (!controller) return;

        let locstyleIds = new Set();
        let providedStyles = [];

        function scan(layer) {
            if (layer.isGutesio || layer.type === 'GeoJSON' || layer.format === 'GeoJSON' || layer.type === 'gutes' || layer.type === 'gutesPart' || layer.type === 'gutesElem') {
                layer.isGutesio = true; layer.minzoom = "0"; layer.maxzoom = "40";
                if (layer.zoom) { layer.zoom.min = "0"; layer.zoom.max = "40"; }
                layer.hide = false; layer.data_hidelayer = "0"; layer.excludeFromSingleLayer = false;
            }
            if (layer.locstyle) locstyleIds.add(parseInt(layer.locstyle));
            if (layer.locationStyle) locstyleIds.add(parseInt(layer.locationStyle));
            
            if (layer.locstyles && Array.isArray(layer.locstyles)) providedStyles = providedStyles.concat(layer.locstyles);
            if (layer.childs) for (let i in layer.childs) scan(layer.childs[i]);
            if (layer.content && Array.isArray(layer.content)) {
                for (let i in layer.content) {
                    let c = layer.content[i];
                    if (c.locstyle) locstyleIds.add(parseInt(c.locstyle));
                    if (c.locationStyle) locstyleIds.add(parseInt(c.locationStyle));
                    if (c.data && c.data.features) {
                        c.data.features.forEach(f => {
                            if (f.properties) {
                                f.properties.isGutesio = true;
                                if (f.properties.locstyle) locstyleIds.add(parseInt(f.properties.locstyle));
                                if (f.properties.locationStyle) locstyleIds.add(parseInt(f.properties.locationStyle));
                            }
                        });
                    }
                }
            }
        }

        if (Array.isArray(layers)) layers.forEach(scan); else scan(layers);

        let C4gLocationStyle = (window.c4gMaps && window.c4gMaps.C4gLocationStyle) ? window.c4gMaps.C4gLocationStyle : undefined;
        if (!C4gLocationStyle) {
            let existing = Object.values(controller.arrLocStyles)[0];
            if (existing && existing.constructor) C4gLocationStyle = existing.constructor;
        }

        if (providedStyles.length > 0 && C4gLocationStyle) {
            providedStyles.forEach(styleData => {
                if (!styleData || !styleData.id) return;
                if (!controller.arrLocStyles[styleData.id]) {
                    let styleObj = new C4gLocationStyle(styleData, controller);
                    let path = styleData.icon_src || styleData.svgSrc;
                    let isValidPath = path && (path.indexOf('files/') === 0 || path.indexOf('/') === 0 || path.indexOf('http') === 0 || (path.indexOf('.') !== -1 && path.length > 3));
                    if (!isValidPath) {
                        let hexPath = "";
                        if (path) {
                            for (let i=0; i<path.length; i++) hexPath += path.charCodeAt(i).toString(16).padStart(2, '0') + " ";
                        }
                        console.warn("gutesio locstyle trigger: provided style " + styleData.id + " has no valid path: " + path + " (hex: " + hexPath + ")");
                        applyFallback(styleObj, styleData.id, 'ff00ff');
                    } else {
                        styleData.minzoom = "0"; styleData.maxzoom = "40";
                        styleObj.style = styleObj.getStyleFunction();
                    }
                    controller.arrLocStyles[styleData.id] = styleObj;
                }
            });
        }

        let missing = [...locstyleIds].filter(id => id > 0 && !controller.arrLocStyles[id]);
        if (missing.length > 0) {
            controller.loadLocationStyles(missing, {
                always: function() {
                    missing.forEach(id => {
                        let style = controller.arrLocStyles[id];
                        if (style) {
                            let data = style.locStyleArr || style;
                            let path = data.icon_src || data.svgSrc;
                            let isValidPath = path && (path.indexOf('files/') === 0 || path.indexOf('/') === 0 || path.indexOf('http') === 0 || (path.indexOf('.') !== -1 && path.length > 3));
                            if (!isValidPath) {
                                let hexPath = "";
                                if (path) {
                                    for (let i=0; i<path.length; i++) hexPath += path.charCodeAt(i).toString(16).padStart(2, '0') + " ";
                                }
                                console.warn("gutesio locstyle trigger: loaded style " + id + " has no valid path: " + path + " (hex: " + hexPath + ")");
                                applyFallback(style, id, 'ffff00');
                            } else {
                                data.minzoom = "0"; data.maxzoom = "40";
                                style.style = style.getStyleFunction();
                            }
                        } else if (C4gLocationStyle) {
                            let fallback = new C4gLocationStyle({id: id}, controller);
                            applyFallback(fallback, id, 'ff0000');
                            controller.arrLocStyles[id] = fallback;
                        }
                    });
                    triggerRedraw(proxy);
                }
            });
        }
        
        patchLayerController(proxy);
        triggerRedraw(proxy);
    }

    function triggerRedraw(proxy) {
        let redrawCount = 0;
        let redraw = function() {
            let map = proxy.options.mapController.map;
            let vectorLayersFound = 0;
            let gutsioStyleFunction = gutsioStyleFunctionMap.get(map);
            let fromLonLat = (window.ol && window.ol.proj && window.ol.proj.fromLonLat) ? window.ol.proj.fromLonLat : (map.fromLonLat || null);
            
            let processLayer = function(l) {
                if (!l) return;
                
                // Force visibility and z-index on every check
                if (l.get && (l.get('isGutesio') || l.isGutesio)) {
                    if (l.setVisible && (typeof l.getVisible !== 'function' || !l.getVisible())) {
                        console.log("gutesio locstyle trigger: restoring visibility for layer", l);
                        l.setVisible(true);
                        if (typeof l.changed === 'function') l.changed();
                    }
                    if (l.setOpacity && (typeof l.getOpacity !== 'function' || l.getOpacity() < 1)) {
                        console.log("gutesio locstyle trigger: restoring opacity for layer", l);
                        l.setOpacity(1);
                        if (typeof l.changed === 'function') l.changed();
                    }
                    if (l.setZIndex && (typeof l.getZIndex !== 'function' || l.getZIndex() < 10000000)) l.setZIndex(10000000);
                }

                let source = (typeof l.getSource === 'function') ? l.getSource() : null;
                if (source) {
                    let isVector = false; let features = [];
                    if (typeof source.getFeatures === 'function') { isVector = true; features = source.getFeatures(); }
                    else if (typeof source.getSource === 'function' && source.getSource()) {
                        let inner = source.getSource(); if (inner && typeof inner.getFeatures === 'function') { isVector = true; features = inner.getFeatures(); }
                    }
                    if (isVector) {
                        let hasGutesio = l.isGutesio || l.get('isGutesio') || features.some(f => f && typeof f.get === 'function' && f.get('isGutesio'));
                        if (hasGutesio) {
                            l.isGutesio = true; l.set('isGutesio', true); vectorLayersFound++; 
                            if (gutsioStyleFunction && (typeof l.getStyle !== 'function' || l.getStyle() !== gutsioStyleFunction)) {
                                console.log("gutesio locstyle trigger: restoring style function for layer", l);
                                l.setStyle(gutsioStyleFunction);
                            }
                            
                            // l.changed(); // Remove l.changed() to see if it reduces flickering
                            if (l.setVisible && (typeof l.getVisible !== 'function' || !l.getVisible())) {
                                console.log("gutesio locstyle trigger: restoring visibility for layer in redraw", l);
                                l.setVisible(true);
                            }
                            if (l.setOpacity && (typeof l.getOpacity !== 'function' || l.getOpacity() === 0)) l.setOpacity(1);
                            if (l.setZIndex) l.setZIndex(10000000);
                            if (typeof l.setDeclutter === 'function') l.setDeclutter(false);
                            
                            if (features.length > 0) {
                                features.forEach(f => { 
                                    if (f.set && !f.get('isGutesio')) f.set('isGutesio', true); 
                                    if (f.setStyle && f.getStyle()) f.setStyle(null);
                                    let geom = f.getGeometry();
                                    if (geom && geom.getType() === 'Point' && fromLonLat) {
                                        let coord = geom.getCoordinates();
                                        // If coordinates are in lon/lat range, transform them
                                        if (Array.isArray(coord) && coord.length >= 2 && 
                                            Math.abs(coord[0]) <= 180 && Math.abs(coord[1]) <= 90 &&
                                            (Math.abs(coord[0]) > 0.1 || Math.abs(coord[1]) > 0.1)) {
                                            geom.setCoordinates(fromLonLat(coord));
                                        }
                                    }
                                });
                                let f = features[0];
                                if (redrawCount % 5 === 0) {
                                    console.log("gutesio locstyle trigger: redraw GutesIO layer", { 
                                        features: features.length,
                                        styleCalls: window.gutsioStyleCalls,
                                        coord: f.getGeometry() ? JSON.stringify(f.getGeometry().getCoordinates()) : 'none'
                                    });
                                }
                            }
                        }
                    }
                }
                if (typeof l.getLayers === 'function') l.getLayers().forEach(processLayer);
            };

            map.getLayers().forEach(processLayer);
            redrawCount++;
            map.render();
        };

        redraw();
        [100, 250, 500, 750, 1000, 1500, 2000, 3000, 5000].forEach(t => setTimeout(redraw, t));
        setInterval(redraw, 5000); // Continuous redraw every 5s to fight async state changes
    }
})();
