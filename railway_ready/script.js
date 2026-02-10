// Main variables
let map, busMap;
let currentTrip = null;
let liveBusInterval = null;

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initBusMap();
    loadAllBuses();
    loadAllStops();
});

// Navigation functions
function showHome() {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById('home-page').classList.add('active');
}

function showRouteFinder() {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById('route-finder').classList.add('active');
    refreshMap();
}

function showBusInfo() {
    document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
    document.getElementById('bus-info').classList.add('active');
    refreshBusMap();
}

// Initialize maps
function initMap() {
    if (!map && document.getElementById('map')) {
        map = L.map('map').setView([24.8607, 67.0011], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
    }
}

function initBusMap() {
    if (!busMap && document.getElementById('bus-map')) {
        busMap = L.map('bus-map').setView([24.8607, 67.0011], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(busMap);
    }
}

// Refresh maps
function refreshMap() {
    setTimeout(() => map?.invalidateSize(), 100);
}

function refreshBusMap() {
    setTimeout(() => busMap?.invalidateSize(), 100);
}

// Load data
async function loadAllStops() {
    try {
        const response = await fetch('db.php?action=getStops');
        window.allStops = await response.json();
    } catch (error) {
        console.log('Could not load stops');
    }
}

async function loadAllBuses() {
    try {
        const response = await fetch('db.php?action=getBuses');
        const buses = await response.json();
        const select = document.getElementById('bus-select');
        if (select) {
            select.innerHTML = '<option value="">Choose a bus...</option>';
            buses.forEach(bus => {
                const option = document.createElement('option');
                option.value = bus.bus_id;
                option.textContent = `${bus.bus_no} - ${bus.route_description}`;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.log('Could not load buses');
    }
}

// Find route
async function findRoute() {
    const from = document.getElementById('from-stop').value.trim();
    const to = document.getElementById('to-stop').value.trim();
    
    if (!from || !to) {
        alert('Please enter both locations');
        return;
    }
    
    try {
        const response = await fetch(`db.php?action=findBuses&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`);
        const buses = await response.json();
        
        if (buses.length > 0) {
            const bus = buses[0];
            currentTrip = {
                from: from,
                to: to,
                bus: bus.bus_no,
                route: bus.route_description
            };
            displayTrip(currentTrip);
            plotRouteOnMap(currentTrip);
            startLiveTracking();
        } else {
            alert('No bus found for this route');
        }
    } catch (error) {
        console.error('Route find error:', error);
        alert('Could not find route');
    }
}

// Load bus route
async function loadBusRoute() {
    const select = document.getElementById('bus-select');
    const busId = select.value;
    
    if (!busId) {
        alert('Please select a bus');
        return;
    }
    
    try {
        const response = await fetch(`db.php?action=getBusRoute&bus_id=${busId}`);
        const route = await response.json();
        displayBusRoute(route);
        plotBusRouteOnMap(route);
    } catch (error) {
        console.error('Route load error:', error);
        alert('Could not load bus route');
    }
}

// Display functions
function displayTrip(trip) {
    const panel = document.getElementById('trip-panel');
    panel.style.display = 'block';
    panel.innerHTML = `
        <div class="trip-header">
            <h3>Your Route</h3>
            <button class="clear-btn" onclick="clearTrip()">Clear</button>
        </div>
        <div class="trip-info">
            <p><strong>From:</strong> ${trip.from}</p>
            <p><strong>To:</strong> ${trip.to}</p>
            <p><strong>Bus:</strong> ${trip.bus}</p>
            <p><strong>Route:</strong> ${trip.route}</p>
            <p><strong>Est. Time:</strong> 45 minutes</p>
        </div>
    `;
}

function displayBusRoute(route) {
    const details = document.getElementById('bus-details');
    if (route.length === 0) {
        details.innerHTML = '<p>No route information available</p>';
        return;
    }
    
    const bus = route[0];
    let html = `
        <h3>Bus ${bus.bus_no}</h3>
        <p><strong>Route:</strong> ${bus.route_description}</p>
        <h4>Stops:</h4>
        <div class="stops-list">
    `;
    
    route.forEach((stop, index) => {
        html += `
            <div class="stop-item">
                <span class="stop-number">${index + 1}</span>
                <span class="stop-name">${stop.stop_name}</span>
            </div>
        `;
    });
    
    html += '</div>';
    details.innerHTML = html;
}

// Map plotting
function plotRouteOnMap(trip) {
    if (!map) return;
    
    // Clear previous markers
    map.eachLayer(layer => {
        if (layer instanceof L.Marker || layer instanceof L.Polyline) {
            map.removeLayer(layer);
        }
    });
    
    // Add start and end markers
    L.marker([24.8607, 67.0011]).addTo(map)
        .bindPopup(`<b>Start:</b> ${trip.from}`);
    
    L.marker([24.8707, 67.0111]).addTo(map)
        .bindPopup(`<b>End:</b> ${trip.to}`);
    
    // Add route line
    L.polyline([
        [24.8607, 67.0011],
        [24.8707, 67.0111]
    ], {color: '#b11226'}).addTo(map);
}

function plotBusRouteOnMap(route) {
    if (!busMap) return;
    
    // Clear map
    busMap.eachLayer(layer => {
        if (layer instanceof L.Marker || layer instanceof L.Polyline) {
            busMap.removeLayer(layer);
        }
    });
    
    // Plot stops
    route.forEach(stop => {
        if (stop.latitude && stop.longitude) {
            L.marker([stop.latitude, stop.longitude])
                .addTo(busMap)
                .bindPopup(`<b>${stop.stop_name}</b>`);
        }
    });
}

// Live tracking (simulated)
function startLiveTracking() {
    if (liveBusInterval) clearInterval(liveBusInterval);
    
    let progress = 0;
    liveBusInterval = setInterval(() => {
        progress = Math.min(100, progress + 5);
        updateProgress(progress);
    }, 2000);
}

function updateProgress(percent) {
    const bar = document.getElementById('progress-bar');
    const marker = document.getElementById('bus-marker');
    if (bar) bar.style.width = percent + '%';
    if (marker) marker.style.left = percent + '%';
}

// Clear functions
function clearTrip() {
    if (liveBusInterval) clearInterval(liveBusInterval);
    document.getElementById('trip-panel').style.display = 'none';
    if (map) map.eachLayer(layer => map.removeLayer(layer));
}

// Setup demo routes
function setupDemoRoutes() {
    const demos = [
        { from: "Khokrapar", to: "Tower" },
        { from: "Power House", to: "Indus Hospital" },
        { from: "Numaish", to: "Ibrahim Hyderi" }
    ];
    
    const container = document.createElement('div');
    container.className = 'demo-routes';
    container.innerHTML = '<p>Try these examples:</p>';
    
    demos.forEach(route => {
        const btn = document.createElement('button');
        btn.className = 'demo-btn';
        btn.textContent = `${route.from} → ${route.to}`;
        btn.onclick = () => {
            document.getElementById('from-stop').value = route.from;
            document.getElementById('to-stop').value = route.to;
            findRoute();
        };
        container.appendChild(btn);
    });
    
    document.querySelector('.controls').appendChild(container);
}