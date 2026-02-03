<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini Flight Radar</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            height: 100vh;
            overflow: hidden;
        }

        .container {
            display: flex;
            height: 100vh;
            gap: 10px;
            padding: 10px;
            background: #f5f5f5;
        }

        #map {
            flex: 1;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            z-index: 1;
            width: 100%;
            height: 100vh;
        }

        .leaflet-popup-content {
            font-size: 13px;
        }

        .popup-table {
            width: 100%;
            font-size: 12px;
        }

        .popup-table tr {
            height: 22px;
        }

        .popup-table td {
            padding: 2px 8px;
        }

        .popup-table td:first-child {
            color: #666;
            font-weight: 500;
        }

        .popup-table td:last-child {
            text-align: right;
            font-weight: 600;
        }

        .status-online {
            color: #4caf50;
            font-weight: 600;
        }

        .status-updating {
            color: #ff9800;
        }
    </style>
</head>
<body>
    <div class="container">
        <div id="map"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        let map;
        let flightMarkers = {};
        let autoRefreshInterval;

        // Initialize map
        function initMap() {
            map = L.map('map').setView([20, 0], 3);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
        }

        // Get flight icon - pure CSS dot for max performance
        function getFlightIcon(heading) {
            return L.divIcon({
                html: '<div style="width:8px;height:8px;background:#000;border-radius:50%;margin:1px;"></div>',
                iconSize: [10, 10],
                className: 'flight-marker',
                popupAnchor: [0, -5]
            });
        }

        // Format altitude
        function formatAltitude(alt) {
            if (!alt) return 'N/A';
            return Math.round(alt) + ' m';
        }

        // Format velocity
        function formatVelocity(vel) {
            if (!vel) return 'N/A';
            return Math.round(vel) + ' m/s';
        }

        // Format heading
        function formatHeading(heading) {
            if (heading === null || heading === undefined) return 'N/A';
            const directions = ['N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'];
            const index = Math.round(heading / 22.5) % 16;
            return Math.round(heading) + '° ' + directions[index];
        }

        // Refresh flights
        function refreshFlights() {
            const timestamp = new Date().toLocaleTimeString();
            console.log('[' + timestamp + '] Fetching flights from API...');
            fetch('/api/flights')
                .then(response => {
                    if (!response.ok) throw new Error('API error: ' + response.status);
                    return response.json();
                })
                .then(data => {
                    const timestamp = new Date().toLocaleTimeString();
                    console.log('[' + timestamp + '] API Response received:', data);
                    if (data.flights && Array.isArray(data.flights)) {
                        console.log('[' + timestamp + '] Updating ' + data.flights.length + ' flights');
                        updateFlights(data.flights);
                    } else {
                        console.warn('[' + timestamp + '] No flights in response');
                    }
                })
                .catch(error => {
                    const timestamp = new Date().toLocaleTimeString();
                    console.error('[' + timestamp + '] Error fetching flights:', error);
                });
        }

        // Update flights on map and list
        function updateFlights(flights) {
            const timestamp = new Date().toLocaleTimeString();
            console.log('[' + timestamp + '] ===== UPDATING ' + flights.length + ' FLIGHTS =====');
            
            let removedCount = 0;
            let updatedCount = 0;
            let addedCount = 0;

            // Remove old markers
            Object.keys(flightMarkers).forEach(icao => {
                if (!flights.find(f => f.icao24 === icao)) {
                    map.removeLayer(flightMarkers[icao].marker);
                    delete flightMarkers[icao];
                    removedCount++;
                }
            });

            // Update or add markers with smooth animation
            flights.forEach(flight => {
                const key = flight.icao24;
                const popupContent = `
                    <div style="min-width: 250px;">
                        <h3 style="margin: 0 0 10px 0; color: #2196F3;">${flight.callsign}</h3>
                        <table class="popup-table">
                            <tr><td>ICAO:</td><td>${flight.icao24}</td></tr>
                            <tr><td>Latitude:</td><td>${flight.latitude?.toFixed(4) || 'N/A'}</td></tr>
                            <tr><td>Longitude:</td><td>${flight.longitude?.toFixed(4) || 'N/A'}</td></tr>
                            <tr><td>Altitude:</td><td>${formatAltitude(flight.baro_altitude)}</td></tr>
                            <tr><td>Speed:</td><td>${formatVelocity(flight.velocity)}</td></tr>
                            <tr><td>Heading:</td><td>${formatHeading(flight.heading)}</td></tr>
                            <tr><td>Vertical Rate:</td><td>${flight.vertical_rate ? Math.round(flight.vertical_rate) + ' m/s' : 'N/A'}</td></tr>
                            <tr><td>Country:</td><td>${flight.country || 'N/A'}</td></tr>
                        </table>
                    </div>
                `;

                if (flightMarkers[key]) {
                    // Update existing marker with smooth animation
                    const oldPos = flightMarkers[key].marker.getLatLng();
                    const newPos = L.latLng(flight.latitude, flight.longitude);
                    
                    // Always animate movement
                    animateMarker(flightMarkers[key].marker, oldPos, newPos, 4900);
                    
                    // Update icon with new heading
                    flightMarkers[key].marker.setIcon(getFlightIcon(flight.heading));
                    flightMarkers[key].marker.setPopupContent(popupContent);
                    
                    flightMarkers[key].flight = flight;
                    updatedCount++;
                } else {
                    // Create new marker
                    const marker = L.marker([flight.latitude, flight.longitude], {
                        icon: getFlightIcon(flight.heading)
                    })
                    .bindPopup(popupContent)
                    .addTo(map);

                    flightMarkers[key] = { marker, flight };
                    addedCount++;
                }
            });
            
            console.log('[' + timestamp + '] Update complete: ' + addedCount + ' added, ' + updatedCount + ' updated, ' + removedCount + ' removed. Total on map: ' + Object.keys(flightMarkers).length);
        }

        // Animate marker movement smoothly with fewer updates
        function animateMarker(marker, fromLatLng, toLatLng, duration) {
            const startTime = Date.now();
            const startLat = fromLatLng.lat;
            const startLng = fromLatLng.lng;
            const endLat = toLatLng.lat;
            const endLng = toLatLng.lng;

            function animate() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);

                const lat = startLat + (endLat - startLat) * progress;
                const lng = startLng + (endLng - startLng) * progress;

                marker.setLatLng([lat, lng]);

                if (progress < 1) {
                    setTimeout(animate, 33); // ~30fps instead of requestAnimationFrame
                }
            }

            animate();
        }

        // Start auto-refresh
        function startAutoRefresh() {
            console.log('Starting auto-refresh...');
            refreshFlights();
            autoRefreshInterval = setInterval(() => {
                console.log('Refreshing flights...');
                refreshFlights();
            }, 5000); // Refresh every 5 seconds for better performance
        }

        // Stop auto-refresh
        function stopAutoRefresh() {
            clearInterval(autoRefreshInterval);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Page loaded, initializing map and flights...');
            initMap();
            startAutoRefresh();
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            stopAutoRefresh();
        });
    </script>
</body>
</html>
