function renderTemplateNode(data, index) {
    let template = document.querySelector('#elementTpl');
    let cardNode = template.content.querySelector('#element');

    let node = cardNode.cloneNode(true);

    // replace placeholders
    node.innerHTML = node.innerHTML.replaceAll("redirectUrl", data.redirectUrl);
    if (data.image) {
        node.innerHTML = node.innerHTML.replaceAll("elementImageSrc", data.image.src);
        node.innerHTML = node.innerHTML.replaceAll("elementImageAlt", data.image.alt);
    }

    node.innerHTML = node.innerHTML.replaceAll("elementName", data.name);
    node.innerHTML = node.innerHTML.replaceAll("elementType", data.type);

    data.opening_hours = data.opening_hours.replaceAll("Mo", "Montag");
    data.opening_hours = data.opening_hours.replaceAll("Tu", "Dienstag");
    data.opening_hours = data.opening_hours.replaceAll("We", "Mittwoch");
    data.opening_hours = data.opening_hours.replaceAll("Th", "Donnerstag");
    data.opening_hours = data.opening_hours.replaceAll("Do", "Donnerstag");
    data.opening_hours = data.opening_hours.replaceAll("Fr", "Freitag");
    data.opening_hours = data.opening_hours.replaceAll("Sa", "Samstag");
    data.opening_hours = data.opening_hours.replaceAll("Su", "Sonntag");

    node.innerHTML = node.innerHTML.replaceAll("openingHours", data.opening_hours)

    return node;
}

document.addEventListener('DOMContentLoaded', () => {

    const listContainer = document.querySelector('#nearby-showcase-list');

    function handleFetch(position) {
        let moduleId = listContainer.getAttribute('module-id');

        let url = "/gutesio/operator/nearby_showcase/" + moduleId;
        if (position) {
            url = url + "/" + position.join(",");
        }

        fetch(url).then((response) => {
            if (response.ok) {
                response.json().then((data) => {
                    if (data.length !== 0) {
                        if (listContainer.children.length > 0) {
                            listContainer.innerHTML = "";
                        }
                        for (let i = 0; i < data.length; i++) {
                            let card = renderTemplateNode(data[i], i);
                            listContainer.appendChild(card);
                        }
                    } else {
                        console.error("An error occurred loading new data from the server.")
                    }
                });
            } else {
                console.error("An error occurred loading new data from the server.")
            }
        });
    }

    const successCallback = (position) => {
        const coords = [position.coords.longitude, position.coords.latitude];
        handleFetch(coords);
    };

    const errorCallback = () => {
        handleFetch(null);
    };

    if (listContainer.getAttribute("check-position") === "true") {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(successCallback, errorCallback, {maximumAge: 1800000, timeout: 5000});

        }
    } else {
        handleFetch(null);
    }

});


