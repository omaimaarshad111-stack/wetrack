<?php
// admin_dashboard.php
session_start();
require_once 'config.php';
require_once 'auth.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    header('Location: admin_login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Generate CSRF token for this session
$csrf_token = Auth::generateCSRFToken();

// Get admin info (you can extend this to get from database)
$admin_name = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeTrack - Admin Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary-color: #b11226;
            --primary-hover: #e11d2e;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --gray-light: #f8f9fa;
            --gray: #666;
            --gray-dark: #212529;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f5f5; }
        
        /* Header */
        .header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .logo { font-size: 1.8rem; font-weight: bold; }
        .logo span { color: #fff; font-weight: 700; }
        
        /* Main Layout */
        .dashboard {
            display: grid;
            grid-template-columns: 300px 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: white;
            padding: 1.5rem;
            border-right: 1px solid #e2e8f0;
            overflow-y: auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: #f8fafc;
            padding: 1.25rem;
            border-radius: 10px;
            border-left: 4px solid;
        }
        
        .stat-card.total { border-color: var(--primary-color); }
        .stat-card.active { border-color: var(--success); }
        .stat-card.inactive { border-color: var(--danger); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2d3748;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #718096;
            margin-top: 0.25rem;
        }
        
        /* Bus List */
        .bus-list {
            margin-top: 1.5rem;
        }
        
        .bus-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .bus-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 2px 5px rgba(177, 18, 38, 0.2);
        }
        
        .bus-item.active {
            background: #fef2f3;
            border-color: var(--primary-color);
        }
        
        .bus-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bus-number {
            font-weight: bold;
            color: #2d3748;
            font-size: 1.1rem;
        }
        
        .bus-status {
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .status-moving { background: var(--success); color: white; }
        .status-stopped { background: var(--warning); color: var(--gray-dark); }
        .status-breakdown { background: var(--danger); color: white; }
        .status-delayed { background: #ff9800; color: white; }
        
        .bus-details {
            margin-top: 0.75rem;
            font-size: 0.875rem;
            color: #4a5568;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
        }
        
        /* Main Content */
        .main-content {
            display: grid;
            grid-template-rows: auto 1fr;
        }
        
        /* Controls */
        .controls {
            background: white;
            padding: 1rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .refresh-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .refresh-btn:hover { background: var(--primary-hover); }
        
        .last-update {
            font-size: 0.875rem;
            color: #718096;
        }
        
        /* Map Container */
        .map-container {
            position: relative;
            height: calc(100vh - 200px);
        }
        
        #map {
            height: 100%;
            width: 100%;
        }
        
        /* Bus Details Panel */
        .details-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 350px;
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: none;
            z-index: 1000;
        }
        
        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #718096;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .info-item {
            margin-bottom: 1rem;
        }
        
        .info-label {
            font-size: 0.75rem;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 500;
            color: #2d3748;
            margin-top: 0.25rem;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .action-btn {
            padding: 0.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .alert-btn { background: var(--danger); color: white; }
        .message-btn { background: var(--success); color: white; }
        .call-btn { background: var(--primary-color); color: white; }
        .history-btn { background: var(--warning); color: var(--gray-dark); }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            width: 500px;
            max-width: 90%;
        }
        
        /* Search */
        .search-box {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            width: 250px;
        }
        
        /* Filter */
        .filter-select {
            padding: 0.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            margin-left: 1rem;
        }
        
        /* User info and logout */
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-name {
            color: white;
            font-weight: 500;
        }
        
        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: background 0.2s;
        }
        
        .logout-btn:hover {
            background: #c53030;
        }
        
        /* Security warning */
        .security-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
            text-align: center;
        }
        
        .security-warning a {
            color: #b11226;
            font-weight: bold;
            text-decoration: underline;
        }
        
        /* Session timeout warning */
        #timeout-warning {
            display: none;
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            z-index: 3000;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">WeTrack <span>Admin</span></div>
        <div class="user-info">
            <span class="user-name">Welcome, <?php echo htmlspecialchars($admin_name); ?></span>
            <button onclick="location.href='logout.php'" class="logout-btn">Logout</button>
        </div>
    </div>
    
    <!-- Session timeout warning -->
    <div id="timeout-warning">
        Your session will expire in <span id="timeout-countdown">5:00</span> minutes. 
        <button onclick="extendSession()" style="margin-left: 10px; padding: 3px 10px;">Stay Logged In</button>
    </div>
    
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Security warning in development mode -->
            <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
            <div class="security-warning">
                ⚠️ <strong>Development Mode</strong> - For production, update config.php and secure database credentials
            </div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-value" id="total-buses">0</div>
                    <div class="stat-label">Total Buses</div>
                </div>
                <div class="stat-card active">
                    <div class="stat-value" id="active-buses">0</div>
                    <div class="stat-label">Active Buses</div>
                </div>
                <div class="stat-card inactive">
                    <div class="stat-value" id="inactive-buses">0</div>
                    <div class="stat-label">Inactive Buses</div>
                </div>
            </div>
            
            <div>
                <input type="text" class="search-box" placeholder="Search buses..." onkeyup="filterBuses()" id="bus-search">
                <select class="filter-select" onchange="filterByStatus()" id="status-filter">
                    <option value="all">All Status</option>
                    <option value="moving">Moving</option>
                    <option value="stopped">Stopped</option>
                    <option value="breakdown">Breakdown</option>
                    <option value="delayed">Delayed</option>
                </select>
            </div>
            
            <div class="bus-list" id="bus-list">
                <!-- Bus items will be loaded here -->
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="controls">
                <div>
                    <span class="last-update">Last updated: <span id="last-update-time">Never</span></span>
                </div>
                <div>
                    <button onclick="toggleHeatmap()" class="refresh-btn" id="heatmap-btn">Show Heatmap</button>
                    <button onclick="exportData()" class="refresh-btn" style="margin-left: 10px;">📊 Export Data</button>
                    <button onclick="showSystemLogs()" class="refresh-btn" style="margin-left: 10px;">📋 System Logs</button>
                </div>
            </div>
            
            <div class="map-container">
                <div id="map"></div>
                
                <!-- Bus Details Panel -->
                <div class="details-panel" id="details-panel">
                    <div class="details-header">
                        <h3 id="details-bus-number">Bus #</h3>
                        <button class="close-btn" onclick="closeDetails()">&times;</button>
                    </div>
                    
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Driver</div>
                            <div class="info-value" id="details-driver">--</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value" id="details-phone">--</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Status</div>
                            <div class="info-value" id="details-status">--</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Passengers</div>
                            <div class="info-value" id="details-passengers">--</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Speed</div>
                            <div class="info-value" id="details-speed">-- km/h</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Last Update</div>
                            <div class="info-value" id="details-updated">--</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Coordinates</div>
                            <div class="info-value" id="details-coords">--</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Next Stop</div>
                            <div class="info-value" id="details-next-stop">--</div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button class="action-btn alert-btn" onclick="sendAlert()">⚠️ Send Alert</button>
                        <button class="action-btn message-btn" onclick="sendMessage()">💬 Message Driver</button>
                        <button class="action-btn call-btn" onclick="callDriver()">📞 Call Driver</button>
                        <button class="action-btn history-btn" onclick="showHistory()">📈 View History</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- History Modal -->
    <div class="modal" id="history-modal">
        <div class="modal-content">
            <h3>Bus History</h3>
            <div id="history-content">
                Loading...
            </div>
            <button onclick="closeModal()" style="margin-top: 20px; padding: 10px;">Close</button>
        </div>
    </div>
    
    <!-- System Logs Modal -->
    <div class="modal" id="logs-modal">
        <div class="modal-content">
            <h3>System Logs</h3>
            <div id="logs-content">
                Loading logs...
            </div>
            <button onclick="closeLogsModal()" style="margin-top: 20px; padding: 10px;">Close</button>
        </div>
    </div>
    
    <!-- Alert Modal -->
    <div class="modal" id="alert-modal">
        <div class="modal-content">
            <h3>Send Alert to Driver</h3>
            <input type="hidden" id="alert-driver-id">
            <div class="input-group" style="margin-bottom: 15px;">
                <label>Alert Type:</label>
                <select id="alert-type" class="filter-select" style="width: 100%;">
                    <option value="info">Information</option>
                    <option value="warning">Warning</option>
                    <option value="danger">Emergency</option>
                </select>
            </div>
            <div class="input-group" style="margin-bottom: 15px;">
                <label>Message:</label>
                <textarea id="alert-message" rows="4" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;" placeholder="Enter alert message..."></textarea>
            </div>
            <div style="display: flex; gap: 10px;">
                <button onclick="sendAlertToDriver()" class="refresh-btn">Send Alert</button>
                <button onclick="closeAlertModal()" class="logout-btn">Cancel</button>
            </div>
        </div>
    </div>
    
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Global variables
        let map;
        let busMarkers = {};
        let heatmapLayer = null;
        let isHeatmap = false;
        let selectedBusId = null;
        let refreshInterval;
        let sessionTimeout = <?php echo SESSION_TIMEOUT; ?>;
        let lastActivity = <?php echo time(); ?>;
        
        // CSRF Token for API calls
        const csrfToken = "<?php echo $csrf_token; ?>";
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            loadBusData();
            
            // Refresh every 10 seconds
            refreshInterval = setInterval(loadBusData, 10000);
            
            // Start session timeout check
            startSessionTimer();
            
            // Warn before session expires (5 minutes before)
            setTimeout(() => {
                document.getElementById('timeout-warning').style.display = 'block';
                startTimeoutCountdown();
            }, (sessionTimeout - 300) * 1000);
        });
        
        // Initialize map
        function initMap() {
            map = L.map('map').setView([24.8607, 67.0011], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
        }
        
        // Secure fetch with CSRF token
        async function secureFetch(url, options = {}) {
            options.headers = options.headers || {};
            options.headers['X-CSRF-Token'] = csrfToken;
            options.headers['Content-Type'] = 'application/json';
            
            return fetch(url, options);
        }
        
        // Load bus data from API
        async function loadBusData() {
            try {
                const response = await secureFetch('admin_api.php?action=getAllBuses');
                const data = await response.json();
                
                if (data.success) {
                    updateStats(data.buses);
                    updateBusList(data.buses);
                    updateMap(data.buses);
                    updateLastUpdate();
                } else {
                    console.error('API Error:', data.message);
                    if (data.message === 'Unauthorized') {
                        alert('Session expired. Please login again.');
                        window.location.href = 'admin_login.php';
                    }
                }
            } catch (error) {
                console.error('Error loading bus data:', error);
                // Fallback to demo data only in development
                <?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
                loadDemoData();
                <?php endif; ?>
            }
        }
        
        // Update statistics
        function updateStats(buses) {
            const total = buses.length;
            const active = buses.filter(b => b.status === 'moving' || b.status === 'stopped').length;
            const inactive = total - active;
            
            document.getElementById('total-buses').textContent = total;
            document.getElementById('active-buses').textContent = active;
            document.getElementById('inactive-buses').textContent = inactive;
        }
        
        // Update bus list in sidebar
        function updateBusList(buses) {
            const busList = document.getElementById('bus-list');
            busList.innerHTML = '';
            
            buses.forEach(bus => {
                const busItem = document.createElement('div');
                busItem.className = `bus-item ${selectedBusId === bus.bus_id ? 'active' : ''}`;
                busItem.onclick = () => showBusDetails(bus);
                
                const statusClass = getStatusClass(bus.bus_status || bus.status);
                
                busItem.innerHTML = `
                    <div class="bus-header">
                        <div class="bus-number">${bus.bus_no}</div>
                        <div class="bus-status ${statusClass}">${bus.bus_status || bus.status || 'unknown'}</div>
                    </div>
                    <div class="bus-details">
                        <div class="detail-row">
                            <span>Driver:</span>
                            <span>${bus.driver_name || 'Unknown'}</span>
                        </div>
                        <div class="detail-row">
                            <span>Passengers:</span>
                            <span>${bus.current_passengers || 0}</span>
                        </div>
                        <div class="detail-row">
                            <span>Last Seen:</span>
                            <span>${formatTime(bus.last_location_time)}</span>
                        </div>
                    </div>
                `;
                
                busList.appendChild(busItem);
            });
        }
        
        // Update map with bus markers
        function updateMap(buses) {
            // Clear existing markers
            Object.values(busMarkers).forEach(marker => {
                if (marker && map.hasLayer(marker)) {
                    map.removeLayer(marker);
                }
            });
            busMarkers = {};
            
            // Add new markers
            buses.forEach(bus => {
                if (bus.latitude && bus.longitude) {
                    const icon = createBusIcon(bus);
                    const marker = L.marker([bus.latitude, bus.longitude], { icon })
                        .addTo(map)
                        .bindPopup(`
                            <b>${bus.bus_no}</b><br>
                            Driver: ${bus.driver_name || 'Unknown'}<br>
                            Status: ${bus.bus_status || bus.status || 'unknown'}<br>
                            Passengers: ${bus.current_passengers || 0}<br>
                            Speed: ${bus.speed || 0} km/h<br>
                            <button onclick="showBusDetails(${JSON.stringify(bus).replace(/"/g, '&quot;')})" 
                                    style="margin-top: 5px; padding: 3px 8px;">
                                View Details
                            </button>
                        `);
                    
                    marker.on('click', () => showBusDetails(bus));
                    busMarkers[bus.bus_id] = marker;
                }
            });
            
            // Fit map to show all markers if there are any
            if (buses.length > 0 && buses.some(b => b.latitude && b.longitude)) {
                const coords = buses
                    .filter(b => b.latitude && b.longitude)
                    .map(b => [b.latitude, b.longitude]);
                map.fitBounds(coords);
            }
        }
        
        // Create custom bus icon based on status
        function createBusIcon(bus) {
            const status = bus.bus_status || bus.status || 'unknown';
            let color = '#b11226'; // Default red
            
            switch(status) {
                case 'moving': color = '#28a745'; break; // Green
                case 'stopped': color = '#ffc107'; break; // Yellow
                case 'breakdown': color = '#dc3545'; break; // Red
                case 'delayed': color = '#ff9800'; break; // Orange
            }
            
            // Create HTML for custom marker
            const iconHtml = `
                <div style="
                    background: ${color};
                    color: white;
                    border-radius: 50%;
                    width: 30px;
                    height: 30px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    border: 2px solid white;
                    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
                ">
                    🚌
                </div>
            `;
            
            return L.divIcon({
                html: iconHtml,
                className: 'bus-marker',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
        }
        
        // Show bus details in panel
        function showBusDetails(bus) {
            selectedBusId = bus.bus_id;
            
            // Update panel content
            document.getElementById('details-bus-number').textContent = bus.bus_no;
            document.getElementById('details-driver').textContent = bus.driver_name || 'Unknown';
            document.getElementById('details-phone').textContent = bus.phone_number || 'N/A';
            document.getElementById('details-status').textContent = bus.bus_status || bus.status || 'unknown';
            document.getElementById('details-passengers').textContent = bus.current_passengers || 0;
            document.getElementById('details-speed').textContent = `${bus.speed || 0} km/h`;
            document.getElementById('details-updated').textContent = formatTime(bus.last_location_time || bus.timestamp);
            document.getElementById('details-coords').textContent = `${bus.latitude?.toFixed(6) || '--'}, ${bus.longitude?.toFixed(6) || '--'}`;
            document.getElementById('details-next-stop').textContent = bus.next_stop_name || 'N/A';
            
            // Store driver ID for alerts
            if (bus.driver_id) {
                document.getElementById('alert-driver-id').value = bus.driver_id;
            }
            
            // Show panel
            document.getElementById('details-panel').style.display = 'block';
            
            // Highlight selected bus in list
            document.querySelectorAll('.bus-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Center map on this bus
            if (bus.latitude && bus.longitude) {
                map.setView([bus.latitude, bus.longitude], 14);
            }
        }
        
        // Close details panel
        function closeDetails() {
            document.getElementById('details-panel').style.display = 'none';
            selectedBusId = null;
            
            // Remove highlight from bus list
            document.querySelectorAll('.bus-item').forEach(item => {
                item.classList.remove('active');
            });
        }
        
        // Utility functions
        function getStatusClass(status) {
            switch(status) {
                case 'moving': return 'status-moving';
                case 'stopped': return 'status-stopped';
                case 'breakdown': return 'status-breakdown';
                case 'delayed': return 'status-delayed';
                default: return 'status-stopped';
            }
        }
        
        function formatTime(timestamp) {
            if (!timestamp) return 'Never';
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }
        
        function updateLastUpdate() {
            const now = new Date();
            document.getElementById('last-update-time').textContent = 
                now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', second:'2-digit'});
        }
        
        // Filter functions
        function filterBuses() {
            const search = document.getElementById('bus-search').value.toLowerCase();
            const items = document.querySelectorAll('.bus-item');
            
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(search) ? '' : 'none';
            });
        }
        
        function filterByStatus() {
            const status = document.getElementById('status-filter').value;
            const items = document.querySelectorAll('.bus-item');
            
            items.forEach(item => {
                const itemStatus = item.querySelector('.bus-status').textContent.toLowerCase();
                item.style.display = (status === 'all' || itemStatus === status) ? '' : 'none';
            });
        }
        
        // Action functions
        function sendAlert() {
            if (selectedBusId) {
                document.getElementById('alert-modal').style.display = 'flex';
            } else {
                alert('Please select a bus first');
            }
        }
        
        async function sendAlertToDriver() {
            const driverId = document.getElementById('alert-driver-id').value;
            const message = document.getElementById('alert-message').value;
            const type = document.getElementById('alert-type').value;
            
            if (!message.trim()) {
                alert('Please enter a message');
                return;
            }
            
            try {
                const response = await secureFetch('admin_api.php?action=sendAlert', {
                    method: 'POST',
                    body: JSON.stringify({
                        driver_id: driverId,
                        message: message,
                        type: type,
                        csrf_token: csrfToken
                    })
                });
                
                const data = await response.json();
                if (data.success) {
                    alert('Alert sent successfully');
                    closeAlertModal();
                } else {
                    alert('Failed to send alert: ' + data.message);
                }
            } catch (error) {
                console.error('Error sending alert:', error);
                alert('Error sending alert');
            }
        }
        
        function closeAlertModal() {
            document.getElementById('alert-modal').style.display = 'none';
            document.getElementById('alert-message').value = '';
        }
        
        function sendMessage() {
            if (selectedBusId) {
                const message = prompt('Enter message for driver:');
                if (message) {
                    // In production, call API to send message
                    alert(`Message sent: "${message}"`);
                }
            }
        }
        
        function callDriver() {
            const phone = document.getElementById('details-phone').textContent;
            if (phone !== 'N/A') {
                alert(`Calling ${phone}...`);
                // In production: window.location.href = `tel:${phone}`;
            }
        }
        
        function showHistory() {
            if (selectedBusId) {
                document.getElementById('history-modal').style.display = 'flex';
                // Load history data
                fetch(`live_tracking_api.php?action=getLocationHistory&bus_id=${selectedBusId}&hours=2`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let html = '<table style="width:100%; margin-top:10px; border-collapse: collapse;">';
                            html += '<tr><th>Time</th><th>Location</th><th>Speed</th></tr>';
                            
                            data.locations.forEach(loc => {
                                html += `<tr>
                                    <td>${formatTime(loc.timestamp)}</td>
                                    <td>${loc.latitude?.toFixed(4)}, ${loc.longitude?.toFixed(4)}</td>
                                    <td>${loc.speed || 0} km/h</td>
                                </tr>`;
                            });
                            
                            html += '</table>';
                            document.getElementById('history-content').innerHTML = html;
                        }
                    });
            }
        }
        
        function closeModal() {
            document.getElementById('history-modal').style.display = 'none';
        }
        
        // Toggle heatmap view
        function toggleHeatmap() {
            const btn = document.getElementById('heatmap-btn');
            
            if (isHeatmap) {
                // Remove heatmap
                if (heatmapLayer) {
                    map.removeLayer(heatmapLayer);
                    heatmapLayer = null;
                }
                btn.textContent = 'Show Heatmap';
                isHeatmap = false;
            } else {
                // Create heatmap (simplified version)
                const points = Object.values(busMarkers)
                    .map(marker => marker.getLatLng())
                    .filter(latlng => latlng);
                
                if (points.length > 0) {
                    heatmapLayer = L.layerGroup();
                    
                    points.forEach(point => {
                        L.circle(point, {
                            color: '#ff0000',
                            fillColor: '#f03',
                            fillOpacity: 0.2,
                            radius: 500
                        }).addTo(heatmapLayer);
                    });
                    
                    heatmapLayer.addTo(map);
                    btn.textContent = 'Hide Heatmap';
                    isHeatmap = true;
                }
            }
        }
        
        // Show all buses on map
        function showAllBuses() {
            const markers = Object.values(busMarkers);
            if (markers.length > 0) {
                const group = new L.featureGroup(markers);
                map.fitBounds(group.getBounds());
            }
        }
        
        // Refresh data
        function refreshData() {
            loadBusData();
        }
        
        // Export data
        function exportData() {
            window.open('admin_api.php?action=exportData&type=json&date=' + new Date().toISOString().split('T')[0], '_blank');
        }
        
        // Show system logs
        async function showSystemLogs() {
            document.getElementById('logs-modal').style.display = 'flex';
            
            try {
                const response = await fetch('get_logs.php');
                const logs = await response.text();
                document.getElementById('logs-content').innerHTML = 
                    `<pre style="max-height: 400px; overflow-y: auto; background: #f5f5f5; padding: 10px; border-radius: 5px;">${logs}</pre>`;
            } catch (error) {
                document.getElementById('logs-content').innerHTML = 
                    `<p>Error loading logs: ${error.message}</p>`;
            }
        }
        
        function closeLogsModal() {
            document.getElementById('logs-modal').style.display = 'none';
        }
        
        // Demo data for testing (only in development)
        function loadDemoData() {
            const demoBuses = [
                {
                    bus_id: 1,
                    bus_no: 'Route 1',
                    driver_name: 'Ali Khan',
                    phone_number: '0312-1234567',
                    bus_status: 'moving',
                    current_passengers: 45,
                    speed: 35,
                    latitude: 24.8607 + (Math.random() - 0.5) * 0.1,
                    longitude: 67.0011 + (Math.random() - 0.5) * 0.1,
                    last_location_time: new Date().toISOString(),
                    driver_id: 1
                },
                {
                    bus_id: 2,
                    bus_no: 'Route 2',
                    driver_name: 'Ahmed Raza',
                    phone_number: '0321-1234568',
                    bus_status: 'stopped',
                    current_passengers: 30,
                    speed: 0,
                    latitude: 24.8607 + (Math.random() - 0.5) * 0.1,
                    longitude: 67.0011 + (Math.random() - 0.5) * 0.1,
                    last_location_time: new Date().toISOString(),
                    driver_id: 2
                },
                {
                    bus_id: 3,
                    bus_no: 'EV-1',
                    driver_name: 'Sara Khan',
                    phone_number: '0333-1234569',
                    bus_status: 'delayed',
                    current_passengers: 25,
                    speed: 20,
                    latitude: 24.8607 + (Math.random() - 0.5) * 0.1,
                    longitude: 67.0011 + (Math.random() - 0.5) * 0.1,
                    last_location_time: new Date().toISOString(),
                    driver_id: 3
                }
            ];
            
            updateStats(demoBuses);
            updateBusList(demoBuses);
            updateMap(demoBuses);
        }
        
        // Session management
        function startSessionTimer() {
            setInterval(() => {
                const now = Math.floor(Date.now() / 1000);
                const elapsed = now - lastActivity;
                
                if (elapsed > sessionTimeout) {
                    alert('Your session has expired. Please login again.');
                    window.location.href = 'logout.php';
                }
            }, 60000); // Check every minute
        }
        
        function startTimeoutCountdown() {
            let minutes = 5;
            let seconds = 0;
            
            const countdown = setInterval(() => {
                if (seconds === 0) {
                    if (minutes === 0) {
                        clearInterval(countdown);
                        document.getElementById('timeout-warning').style.display = 'none';
                        alert('Session expired. Please login again.');
                        window.location.href = 'logout.php';
                        return;
                    }
                    minutes--;
                    seconds = 59;
                } else {
                    seconds--;
                }
                
                document.getElementById('timeout-countdown').textContent = 
                    `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }, 1000);
        }
        
        function extendSession() {
            // Send request to extend session
            fetch('extend_session.php', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': csrfToken,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ extend: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    lastActivity = Math.floor(Date.now() / 1000);
                    document.getElementById('timeout-warning').style.display = 'none';
                    alert('Session extended for 30 minutes');
                }
            });
        }
        
        // Update last activity on user interaction
        ['click', 'keypress', 'mousemove', 'scroll'].forEach(event => {
            document.addEventListener(event, () => {
                lastActivity = Math.floor(Date.now() / 1000);
            });
        });
    </script>
</body>
</html>