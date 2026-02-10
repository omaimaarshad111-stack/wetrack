async function loadBusRoute() {
    const busSelect = document.getElementById('bus-select');
    const busId = busSelect.value;
    
    if (!busId) {
        alert('Please select a bus');
        return;
    }
    
    // Show loading state
    const detailsDiv = document.getElementById('bus-details');
    detailsDiv.innerHTML = '<div class="loading"><div class="spinner"></div>Loading route information...</div>';
    
    try {
        console.log('DEBUG: Loading bus route for ID:', busId);
        
        // Test the URL
        const url = `db.php?action=getBusRoute&bus_id=${busId}`;
        console.log('DEBUG: Fetching URL:', url);
        
        const response = await fetch(url);
        console.log('DEBUG: Response status:', response.status);
        console.log('DEBUG: Response ok:', response.ok);
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        
        const text = await response.text();
        console.log('DEBUG: Raw response:', text);
        
        // Try to parse JSON
        let route;
        try {
            route = JSON.parse(text);
            console.log('DEBUG: Parsed route:', route);
        } catch (parseError) {
            console.error('DEBUG: JSON parse error:', parseError);
            console.error('DEBUG: Raw response:', text);
            throw new Error('Invalid JSON response from server');
        }
        
        // Check if it's an error response
        if (route.error) {
            throw new Error(route.error);
        }
        
        // Check if route is empty
        if (!route || route.length === 0) {
            throw new Error('No route data found for this bus');
        }
        
        // Display the route
        displayBusRoute(route);
        plotBusRouteOnMap(route);
        
    } catch (error) {
        console.error('Error loading bus route:', error);
        document.getElementById('bus-details').innerHTML = `
            <div class="error">
                <h3>Error Loading Route</h3>
                <p>${error.message}</p>
                <p>Please check if the database has data for this bus.</p>
                <button onclick="testDatabase()" style="margin-top: 10px; padding: 5px 10px;">
                    Test Database Connection
                </button>
            </div>
        `;
        clearBusMap();
    }
}

// Add test function
function testDatabase() {
    fetch('db.php?action=getBuses')
        .then(response => response.text())
        .then(text => {
            console.log('Test response:', text);
            alert('Database test complete. Check console for details.');
        })
        .catch(err => {
            console.error('Test failed:', err);
            alert('Database connection failed: ' + err.message);
        });
}