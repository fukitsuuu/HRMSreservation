// --- Reservation modal field wiring ---
document.addEventListener('DOMContentLoaded', function () {
    const driverSelect = document.getElementById('driver_id_select');
    const driverName = document.getElementById('driver_name_field');
    const driverContact = document.getElementById('driver_contact_field');
    const driverImg = document.getElementById('driverImagePreview');
    const driverPlaceholder = document.getElementById('driverImagePlaceholder');

    function normalizeSrc(path) {
        if (!path) return '';
        if (path.startsWith('http')) return path;
        
        // Since database/uploads is outside public_html, use PHP script to serve images
        if (path.startsWith('../')) {
            // Extract filename from path like '../database/uploads/filename.jpg'
            const filename = path.split('/').pop();
            return 'get_image.php?file=' + encodeURIComponent(filename);
        }
        
        // For paths without '../', extract filename and use PHP script
        const filename = path.split('/').pop();
        return 'get_image.php?file=' + encodeURIComponent(filename);
    }

    function updateDriverUIFromOption(opt) {
        if (!opt) return;
        const fullName = opt.getAttribute('data-full-name') || '';
        const contact = opt.getAttribute('data-contact') || '';
        const image = opt.getAttribute('data-image') || '';
        if (driverName) driverName.value = fullName;
        if (driverContact) driverContact.value = contact;
        if (driverImg && driverPlaceholder) {
            if (image) {
                driverImg.src = normalizeSrc(image);
                driverImg.classList.remove('hidden');
                driverPlaceholder.style.display = 'none';
            } else {
                driverImg.src = '';
                driverImg.classList.add('hidden');
                driverPlaceholder.style.display = '';
            }
        }
    }

    if (driverSelect) {
        // Initial fill if default selected
        updateDriverUIFromOption(driverSelect.options[driverSelect.selectedIndex]);
    }

    if (driverSelect && driverName && driverContact) {
        driverSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            updateDriverUIFromOption(opt);
        });
    }

    const vehicleType = document.getElementById('vehicle_type_field');
    const vehicleSelect = document.getElementById('vehicle_id_select');
    const vehicleName = document.getElementById('vehicle_name_field');
    const vehicleImg = document.getElementById('vehicleImagePreview');
    const vehiclePlaceholder = document.getElementById('vehicleImagePlaceholder');

    function filterVehiclesByType() {
        if (!vehicleType || !vehicleSelect) return;
        const vtype = (vehicleType.value || '').trim();
        
        // If no vehicle type selected, show all vehicles
        if (!vtype) {
            // Show all vehicles except placeholder
            Array.from(vehicleSelect.options).forEach(opt => {
                const isPlaceholder = opt.getAttribute('data-role') === 'placeholder' || opt.value === '';
                opt.hidden = isPlaceholder; // Hide placeholder, show all others
            });
            vehicleSelect.disabled = false;
            vehicleSelect.value = '';
            if (vehicleName) vehicleName.value = '';
            return;
        }
        
        // Filter vehicles by type
        let hasAny = false;
        Array.from(vehicleSelect.options).forEach(opt => {
            const isPlaceholder = opt.getAttribute('data-role') === 'placeholder' || opt.value === '';
            if (isPlaceholder) {
                opt.hidden = true; // ALWAYS hide placeholder
                return;
            }
            
            const optType = opt.getAttribute('data-vtype') || '';
            const show = vtype === optType;
            opt.hidden = !show;
            if (show) hasAny = true;
        });
        
        // If no vehicles found for this type, show "No available vehicles" message
        if (!hasAny) {
            // Remove any existing "No available vehicles" option
            Array.from(vehicleSelect.options).forEach(opt => {
                if (opt.textContent === 'No available vehicles') {
                    opt.remove();
                }
            });
            
            // Add "No available vehicles" option
            const noVehiclesOption = document.createElement('option');
            noVehiclesOption.value = '';
            noVehiclesOption.textContent = 'No available vehicles';
            noVehiclesOption.disabled = true;
            vehicleSelect.appendChild(noVehiclesOption);
        } else {
            // Remove "No available vehicles" option if vehicles are found
            Array.from(vehicleSelect.options).forEach(opt => {
                if (opt.textContent === 'No available vehicles') {
                    opt.remove();
                }
            });
        }
        
        vehicleSelect.disabled = false; // Always enable the dropdown
        vehicleSelect.value = '';
        if (vehicleName) vehicleName.value = '';
    }

    if (vehicleType) {
        vehicleType.addEventListener('change', filterVehiclesByType);
        // Initial filter
        filterVehiclesByType();
    }

    function updateVehicleUIFromOption(opt) {
        if (!opt) return;
        const name = opt.getAttribute('data-name') || '';
        const vImage = opt.getAttribute('data-image') || '';
        if (vehicleName) vehicleName.value = name;
        if (vehicleImg && vehiclePlaceholder) {
            if (vImage) {
                vehicleImg.src = normalizeSrc(vImage);
                vehicleImg.classList.remove('hidden');
                vehiclePlaceholder.style.display = 'none';
            } else {
                vehicleImg.src = '';
                vehicleImg.classList.add('hidden');
                vehiclePlaceholder.style.display = '';
            }
        }
    }

    if (vehicleSelect && vehicleName) {
        vehicleSelect.addEventListener('change', function () {
            const opt = this.options[this.selectedIndex];
            updateVehicleUIFromOption(opt);
        });
        // Initial fill if default selected
        updateVehicleUIFromOption(vehicleSelect.options[vehicleSelect.selectedIndex]);
    }

    // Join waypoints addresses into hidden field on submit
    const form = document.getElementById('addRequestForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            console.log('Form submission started');
            
            // Validate required fields before submission (excluding address field)
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                // Skip address field validation - we'll handle it separately
                if (field.name === 'reservation_address') {
                    return;
                }
                
                if (!field.value || field.value.trim() === '') {
                    isValid = false;
                    field.classList.add('border-red-500');
                    field.focus();
                } else {
                    field.classList.remove('border-red-500');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
                return false;
            }
            
            console.log('Form validation passed, proceeding with submission');
            
                        // CORRECT ADDRESS METHOD - Get from waypoints array directly
            try {
                const addressInput = document.getElementById('reservation_address');
                if (addressInput) {
                    let addresses = [];
                    
                    // Get addresses directly from the waypoints array (same as VRDS-1.2.js)
                    if (waypoints && Array.isArray(waypoints) && waypoints.length > 0) {
                        waypoints.forEach((waypoint, index) => {
                            if (waypoint && waypoint.address && waypoint.address.trim()) {
                                addresses.push(waypoint.address.trim());
                                console.log(`Waypoint ${index} address:`, waypoint.address.trim());
                            }
                        });
                    }
                    
                    // If no waypoints found, try manual input
                    if (addresses.length === 0 && addressInput.value.trim()) {
                        addresses.push(addressInput.value.trim());
                        console.log('Using manual address:', addressInput.value.trim());
                    }
                    
                    // Join all addresses with separator
                    const finalAddress = addresses.join(' | ');
                    console.log('Final joined address:', finalAddress);
                    
                    // Set the address input
                    addressInput.value = finalAddress;
                    
                    // Create backup hidden input
                    let hiddenAddress = document.getElementById('hidden_reservation_address');
                    if (!hiddenAddress) {
                        hiddenAddress = document.createElement('input');
                        hiddenAddress.type = 'hidden';
                        hiddenAddress.name = 'reservation_address';
                        hiddenAddress.id = 'hidden_reservation_address';
                        form.appendChild(hiddenAddress);
                    }
                    hiddenAddress.value = finalAddress;
                }
            } catch(e) {
                console.error('Error in correct address method:', e);
            }

            // Copy computed distance/time into hidden inputs consumed by backend
            try {
                const distText = document.getElementById('estimated_distance')?.textContent || '';
                const timeText = document.getElementById('estimated_travel_time')?.textContent || '';
                const distHidden = document.getElementById('hidden_estimated_distance');
                const timeHidden = document.getElementById('hidden_estimated_time');
                if (distHidden) distHidden.value = distText;
                if (timeHidden) timeHidden.value = timeText;
            } catch(_) {}
        });
    }
});

// --- Image Lightbox (copy behavior from VRDS-1.2) ---
document.addEventListener('DOMContentLoaded', function () {
    const lightbox = document.getElementById('imageLightbox');
    const lightboxImg = document.getElementById('lightboxImage');
    const closeBtn = document.getElementById('closeImageLightbox');

    function openLightbox(src) {
        if (!src || !lightbox || !lightboxImg) return;
        lightboxImg.src = src;
        lightbox.classList.remove('hidden');
        lightbox.classList.add('flex');
    }

    function closeLightbox() {
        if (!lightbox || !lightboxImg) return;
        lightbox.classList.add('hidden');
        lightbox.classList.remove('flex');
        lightboxImg.src = '';
    }

    // Bind close handlers
    closeBtn && closeBtn.addEventListener('click', closeLightbox);
    lightbox && lightbox.addEventListener('click', function (e) {
        if (e.target === lightbox) closeLightbox();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeLightbox();
    });

    // Bind enlarge click on previews
    function bindEnlarge(el) {
        if (!el) return;
        el.style.cursor = 'pointer';
        el.addEventListener('click', function () {
            const src = el.getAttribute('src');
            if (src && !el.classList.contains('hidden')) openLightbox(src);
        });
    }

    bindEnlarge(document.getElementById('driverImagePreview'));
    bindEnlarge(document.getElementById('vehicleImagePreview'));
});

// --- Ensure images reset when modal closes ---
window.closeAddModal = function () {
    const modal = document.getElementById('addRequestModal');
    if (!modal) return;
    // Hide modal
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    // Reset form fields
    const form = document.getElementById('addRequestForm');
    if (form) form.reset();

    // Reset driver preview
    const driverImg = document.getElementById('driverImagePreview');
    const driverPlaceholder = document.getElementById('driverImagePlaceholder');
    const driverName = document.getElementById('driver_name_field');
    const driverContact = document.getElementById('driver_contact_field');
    const driverSelect = document.getElementById('driver_id_select');
    if (driverImg) { driverImg.src = ''; driverImg.classList.add('hidden'); }
    if (driverPlaceholder) { driverPlaceholder.style.display = ''; }
    if (driverName) driverName.value = '';
    if (driverContact) driverContact.value = '';
    if (driverSelect) driverSelect.selectedIndex = 0;

    // Reset vehicle preview
    const vehicleImg = document.getElementById('vehicleImagePreview');
    const vehiclePlaceholder = document.getElementById('vehicleImagePlaceholder');
    const vehicleName = document.getElementById('vehicle_name_field');
    const vehicleType = document.getElementById('vehicle_type_field');
    const vehicleSelect = document.getElementById('vehicle_id_select');
    if (vehicleImg) { vehicleImg.src = ''; vehicleImg.classList.add('hidden'); }
    if (vehiclePlaceholder) { vehiclePlaceholder.style.display = ''; }
    if (vehicleName) vehicleName.value = '';
    if (vehicleType) vehicleType.value = '';
    if (vehicleSelect) { vehicleSelect.selectedIndex = 0; vehicleSelect.disabled = true; }
};
function toggleUserDropdown() {
    const dropdown = document.getElementById('userDropdown');
    if (!dropdown) return;
    dropdown.classList.toggle('hidden');
}

// Close dropdown on outside click
document.addEventListener('click', function(event) {
    const btn = document.getElementById('userDropdownBtn');
    const dropdown = document.getElementById('userDropdown');
    if (!btn || !dropdown) return;
    const clickedInsideButton = btn.contains(event.target);
    const clickedInsideDropdown = dropdown.contains(event.target);
    if (!clickedInsideButton && !clickedInsideDropdown) {
        if (!dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
        }
    }
});

// Close on Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const dropdown = document.getElementById('userDropdown');
        if (dropdown && !dropdown.classList.contains('hidden')) {
            dropdown.classList.add('hidden');
        }
        // Close Add Reservation modal on Escape
        const modal = document.getElementById('addRequestModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeAddModal();
        }
    }
});

// Modal open/close helpers
window.openAddModal = function () {
    const modal = document.getElementById('addRequestModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
};

window.closeAddModal = function () {
    const modal = document.getElementById('addRequestModal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
    // Reset modal content after closing
    try { resetAddRequestModal(); } catch(_) {}
};

// Reset all inputs, selections, suggestions, and map state in the Add Reservation modal
function resetAddRequestModal() {
    // Reset form fields
    const form = document.getElementById('addRequestForm');
    if (form) {
        form.reset();
    }

    // Reset description counter
    const desc = document.getElementById('reservation_description');
    const counter = document.getElementById('vrdsDescCount');
    if (desc && counter) {
        const max = desc.getAttribute('maxlength') ? parseInt(desc.getAttribute('maxlength'), 10) : 400;
        counter.textContent = String(max);
    }

    // Clear address suggestions
    try { hideAddressSuggestions(); } catch(_) {}

    // Clear waypoints and markers
    try {
        if (Array.isArray(waypoints)) {
            waypoints.forEach(wp => {
                if (wp && wp.marker && map && map.hasLayer(wp.marker)) {
                    map.removeLayer(wp.marker);
                }
            });
            waypoints = [];
            try { renderWaypointsList(); } catch(_) {}
        }
    } catch(_) {}

    // Remove destination marker and any route layer
    try {
        if (destinationMarker && map && map.hasLayer(destinationMarker)) {
            map.removeLayer(destinationMarker);
        }
        destinationMarker = null;
        destinationLocation = null;
        if (window.currentRouteLayer && map && map.hasLayer(window.currentRouteLayer)) {
            map.removeLayer(window.currentRouteLayer);
        }
        window.currentRouteLayer = null;
    } catch(_) {}

    // Reset computed outputs
    const distEl = document.getElementById('estimated_distance');
    const arrEl = document.getElementById('estimated_arrival');
    const timeEl = document.getElementById('estimated_travel_time');
    if (distEl) distEl.textContent = '-- km';
    if (arrEl) arrEl.value = '';
    if (timeEl) timeEl.textContent = '-- hours';

    // Clear location/address text inputs
    const locInput = document.getElementById('reservation_location');
    const addrInput = document.getElementById('reservation_address');
    if (locInput) locInput.value = '';
    if (addrInput) addrInput.value = '';

    // Reset departure to now
    try { setDepartureNow(); } catch(_) {}

    // Recenter map to default
    try {
        const lat = (typeof window.DEFAULT_LAT !== 'undefined') ? window.DEFAULT_LAT : 14.5995;
        const lng = (typeof window.DEFAULT_LNG !== 'undefined') ? window.DEFAULT_LNG : 120.9842;
        if (map && typeof map.setView === 'function') {
            map.setView([lat, lng], 7);
            setTimeout(() => { try { map.invalidateSize(); } catch(_) {} }, 50);
        }
    } catch(_) {}
}

// Disable closing when clicking backdrop (outside modal container)
document.addEventListener('click', function (e) {
    const backdrop = document.getElementById('addRequestModal');
    if (!backdrop) return;
    // Intentionally do nothing on backdrop clicks so the modal stays open
}, true);

// Toggle empty state vs table (copy behavior from admin page)
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('requestsTableBody');
    const table = tbody ? tbody.closest('table') : null;
    const emptyState = document.getElementById('emptyState');
    const pagination = document.getElementById('pagination');
    const pageIndicator = document.getElementById('pageIndicator');

    const hasRows = !!(tbody && tbody.querySelector('tr'));

    if (!hasRows) {
        if (emptyState) emptyState.classList.remove('hidden');
        if (table) table.classList.add('hidden');
        if (pagination) pagination.classList.add('hidden');
        if (pageIndicator) pageIndicator.classList.add('hidden');
    } else {
        if (emptyState) emptyState.classList.add('hidden');
        if (table) table.classList.remove('hidden');
        if (pagination) pagination.classList.remove('hidden');
        if (pageIndicator) pageIndicator.classList.remove('hidden');
    }
});

// Pagination copied to match VRDS-1.2 behavior
document.addEventListener('DOMContentLoaded', function () {
    const tbody = document.getElementById('requestsTableBody');
    const pageIndicator = document.getElementById('pageIndicator');
    const pagination = document.getElementById('pagination');
    if (!tbody || !pagination) return;

    let currentPage = 1;
    const rowsPerPage = 10;
    let allRows = Array.from(tbody.querySelectorAll('tr'));
    let filteredRows = [...allRows];

    function renumberRows() {
        const start = (currentPage - 1) * rowsPerPage;
        let visibleIndex = 0;
        filteredRows.forEach((row) => {
            if (row.style.display !== 'none') {
                const numberCell = row.querySelector('td:first-child');
                if (numberCell) numberCell.textContent = String(start + visibleIndex + 1);
                visibleIndex++;
            }
        });
    }

    function updatePageIndicator() {
        if (!pageIndicator) return;
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
        pageIndicator.textContent = `Page ${currentPage} of ${totalPages}`;
    }

    function createButton(label, page) {
        const btn = document.createElement('button');
        btn.textContent = label;
        btn.className = 'px-3 py-1 rounded bg-gray-200 text-gray-800 hover:bg-gray-300 text-sm';
        btn.onclick = () => renderPage(page);
        return btn;
    }

    function renderPagination() {
        pagination.innerHTML = '';
        const totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
        const maxButtons = 5;
        let start = Math.max(1, currentPage - Math.floor(maxButtons / 2));
        let end = Math.min(totalPages, start + maxButtons - 1);
        if (end - start < maxButtons - 1) start = Math.max(1, end - maxButtons + 1);

        if (currentPage > 1) pagination.appendChild(createButton('«', currentPage - 1));
        for (let i = start; i <= end; i++) {
            const btn = createButton(String(i), i);
            if (i === currentPage) btn.classList.add('bg-gray-400', 'text-white');
            pagination.appendChild(btn);
        }
        if (currentPage < totalPages) pagination.appendChild(createButton('»', currentPage + 1));
    }

    function renderPage(page) {
        currentPage = page;
        const start = (page - 1) * rowsPerPage;
        const end = page * rowsPerPage;

        // Hide all rows
        filteredRows.forEach(row => {
            row.style.display = 'none';
            row.classList.remove('bg-white', 'bg-gray-100');
        });

        // Show current page rows with zebra
        filteredRows.forEach((row, i) => {
            if (i >= start && i < end) {
                row.style.display = '';
                row.classList.add((i % 2 === 0) ? 'bg-white' : 'bg-gray-100');
            }
        });

        renumberRows();
        updatePageIndicator();
        renderPagination();
    }

    // Initial render
    renderPage(1);
});


// Map JavaScript

// Global variables for map functionality
let map;
let currentLocationMarker;
let destinationMarker;
let routingControl;
let currentLocation = null;
let destinationLocation = null;
// Waypoints (max 7)
let waypoints = []; // each: { id, lat, lng, address, marker }
const MAX_WAYPOINTS = 7;
// Route request guard to ignore stale async responses
let activeRouteRequestId = 0;

// Global default center (Philippines)
window.DEFAULT_LAT = 14.5995;
window.DEFAULT_LNG = 120.9842;

// Build a red map-pin icon (like the provided image) as an inline SVG
function getDestinationIcon() {
    const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="36" height="48" viewBox="0 0 24 24">
  <path fill="#EF4444" d="M12 2c-4.418 0-8 3.134-8 7c0 6.627 8 13 8 13s8-6.373 8-13c0-3.866-3.582-7-8-7Z"/>
  <circle cx="12" cy="9.75" r="4.25" fill="#ffffff"/>
  <circle cx="12" cy="9.75" r="2.25" fill="none"/>
 </svg>`;
    const url = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    return L.icon({
        iconUrl: url,
        iconSize: [36, 48],
        iconAnchor: [18, 46],
        popupAnchor: [0, -40],
        className: 'destination-pin-icon'
    });
}

// Start icon: circle with blue inner circle
function getStartIcon() {
    const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 28 28">
  <circle cx="14" cy="14" r="12" fill="#E0F2FE" stroke="#60A5FA" stroke-width="2" />
  <circle cx="14" cy="14" r="6" fill="#3B82F6" />
</svg>`;
    const url = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg);
    return L.icon({
        iconUrl: url,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        className: 'start-circle-icon'
    });
}

// Global function to remove destination marker
window.removeDestinationMarker = function () {
    // Clear ALL destinations and route, and reset the search input
    // Remove waypoint markers
    if (Array.isArray(waypoints)) {
        waypoints.forEach(wp => {
            if (wp.marker && map && map.hasLayer(wp.marker)) {
                map.removeLayer(wp.marker);
            }
        });
        waypoints = [];
        renderWaypointsList();
    }

    // Invalidate any in-flight routing responses to avoid stale redraws
    activeRouteRequestId++;

    // Remove any single destination marker (legacy)
    if (destinationMarker && map && map.hasLayer(destinationMarker)) {
        map.removeLayer(destinationMarker);
        destinationMarker = null;
        destinationLocation = null;
    }

    // Remove route layer
    if (window.currentRouteLayer && map && map.hasLayer(window.currentRouteLayer)) {
        map.removeLayer(window.currentRouteLayer);
        window.currentRouteLayer = null;
    }

    // Reset calculated fields
    const distanceElement = document.getElementById('estimated_distance');
    const arrivalElement = document.getElementById('estimated_arrival');
    const travelTimeElement = document.getElementById('estimated_travel_time');
    if (distanceElement) distanceElement.textContent = '-- km';
    if (arrivalElement) arrivalElement.value = '';
    if (travelTimeElement) travelTimeElement.textContent = '-- hours';

    // Clear the search input
    const addrInput = document.getElementById('reservation_address');
    if (addrInput) addrInput.value = '';

    // Hide remove button
    const removeBtn = document.getElementById('removeMarkerBtn');
    if (removeBtn) removeBtn.classList.add('hidden');
};

// Initialize map when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM loaded, setting up map functionality...');

    // Set default departure datetime to current local datetime (YYYY-MM-DDTHH:MM)
    // function setDepartureNow() {
    //     const el = document.getElementById('estimated_departure');
    //     if (!el) return;
    //     const now = new Date();
    //     const y = now.getFullYear();
    //     const m = String(now.getMonth() + 1).padStart(2, '0');
    //     const d = String(now.getDate()).padStart(2, '0');
    //     const hh = String(now.getHours()).padStart(2, '0');
    //     const mm = String(now.getMinutes()).padStart(2, '0');
    //     el.value = `${y}-${m}-${d}T${hh}:${mm}`;
    // }
    // setDepartureNow(); // Commented out to prevent auto-filling departure time

    // Add event listeners
    const departureElement = document.getElementById('estimated_departure');
    const addressElement = document.getElementById('reservation_address');

    if (departureElement) {
        departureElement.addEventListener('change', calculateArrivalTime);
    }

    if (addressElement) {
        addressElement.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const q = (this.value || '').trim();
                if (q.length > 0) {
                    // Show suggestions for current query instead of auto-picking
                    searchAddressSuggestions(q);
                }
            }
        });

        // Do NOT remove pinned locations when the search input changes/clears
        addressElement.addEventListener('input', function () {
            if (this.value.trim() === '') {
                hideAddressSuggestions();
            }
        });
    }

    // Add input change listener to remove marker when location is cleared
    const locationElement = document.getElementById('reservation_location');
    if (locationElement) {
        locationElement.addEventListener('input', function () {
            // No automatic clearing of waypoints when this field changes
        });
    }

    // Prevent map buttons from submitting the form; also invoke their actions directly
    document.addEventListener('click', function(e){
        const target = e.target;
        const removeBtn = document.getElementById('removeMarkerBtn');
        const locBtn = document.getElementById('getLocationBtn');
        const isRemove = removeBtn && (target === removeBtn || (removeBtn.contains && removeBtn.contains(target)));
        const isLocate = locBtn && (target === locBtn || (locBtn.contains && locBtn.contains(target)));
        if (isRemove || isLocate) {
            try { e.preventDefault(); } catch(_){}
            try { e.stopPropagation(); } catch(_){}
            const anyFocusable = document.querySelector('#map') || document.body;
            if (anyFocusable && anyFocusable.focus) { try { anyFocusable.focus({preventScroll:true}); } catch(_){} }
            // Perform action explicitly
            if (isRemove && typeof window.removeDestinationMarker === 'function') {
                window.removeDestinationMarker();
            } else if (isLocate && typeof window.getCurrentLocation === 'function') {
                window.getCurrentLocation();
            }
            return false;
        }
    }, true);

    // Ensure map control buttons behave as non-submit controls and call handlers explicitly
    setTimeout(() => {
        const rmBtn = document.getElementById('removeMarkerBtn');
        if (rmBtn) {
            rmBtn.setAttribute('type', 'button');
            rmBtn.addEventListener('click', function(ev){
                try { ev.preventDefault(); } catch(_){ }
                try { ev.stopPropagation(); } catch(_){ }
                if (typeof window.removeDestinationMarker === 'function') {
                    window.removeDestinationMarker();
                }
                return false;
            }, { capture: true });
        }
        const glBtn = document.getElementById('getLocationBtn');
        if (glBtn) {
            glBtn.setAttribute('type', 'button');
            glBtn.addEventListener('click', function(ev){
                try { ev.preventDefault(); } catch(_){ }
                try { ev.stopPropagation(); } catch(_){ }
                if (typeof window.getCurrentLocation === 'function') {
                    window.getCurrentLocation();
                }
                return false;
            }, { capture: true });
        }
    }, 0);

    // Remove marker button event listener will be added when modal opens

    // Disabled tile preloading to avoid provider throttling
    // preloadMapTiles();

    // Try to initialize map immediately if container is available
    setTimeout(() => {
        const mapContainer = document.getElementById('map');
        if (mapContainer && typeof initializeMap === 'function') {
            console.log('Attempting immediate map initialization...');
            initializeMap();
        }
    }, 500);

    // Ensure address suggestion setup is ready
    try { setupAddressSuggestions(); } catch(_) {}
});

// Preload map tiles for better performance
function preloadMapTiles() {
    // Preload essential tiles for extended Philippines area at zoom level 6
    const tileUrls = [
        'https://a.tile.openstreetmap.org/6/54/28.png', // Western Palawan
        'https://b.tile.openstreetmap.org/6/54/28.png',
        'https://c.tile.openstreetmap.org/6/54/28.png',
        'https://a.tile.openstreetmap.org/6/55/28.png', // Central Philippines
        'https://b.tile.openstreetmap.org/6/55/28.png',
        'https://c.tile.openstreetmap.org/6/55/28.png',
        'https://a.tile.openstreetmap.org/6/55/29.png', // Eastern Philippines
        'https://b.tile.openstreetmap.org/6/55/29.png',
        'https://c.tile.openstreetmap.org/6/55/29.png'
    ];

    tileUrls.forEach(url => {
        const img = new Image();
        img.src = url;
    });
}

// Initialize the map
window.initializeMap = function () {
    console.log('Initializing map...');

    // Check if map already exists
    if (map) {
        console.log('Removing existing map...');
        map.remove();
    }

    // Check if map container exists
    const mapContainer = document.getElementById('map');
    if (!mapContainer) {
        console.error('Map container not found');
        return;
    }

    console.log('Map container found:', mapContainer);

    // Check if Leaflet is loaded
    if (typeof L === 'undefined') {
        console.error('Leaflet library not loaded');
        return;
    }

    // Default coordinates (Philippines center)
    const defaultLat = (typeof window.DEFAULT_LAT !== 'undefined') ? window.DEFAULT_LAT : 14.5995;
    const defaultLng = (typeof window.DEFAULT_LNG !== 'undefined') ? window.DEFAULT_LNG : 120.9842;

    // Extended Philippines bounds with reduced southern coverage
    const philippinesBounds = [
        [3.1, 115.0],  // Southwest (reduced southern coverage)
        [22.5, 129.5]  // Northeast (extended north and east)
    ];

    try {
        // Create map instance with performance optimizations
        map = L.map('map', {
            maxBounds: philippinesBounds,
            maxBoundsViscosity: 1.0,
            minZoom: 4.5,
            maxZoom: 18,
            zoomSnap: 0.5,
            zoomDelta: 0.5,
            wheelPxPerZoomLevel: 60,
            tap: false,
            bounceAtZoomLimits: false
        }).setView([defaultLat, defaultLng], 6);

        console.log('Map created successfully');
        // Keep gestures simple; no extra overrides
    } catch (error) {
        console.error('Error creating map:', error);
        return;
    }

    // Add OpenStreetMap tiles with automatic fallback across providers
    let activeTileLayer = null;
    const tileProviders = [
        { url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', options: { attribution: '© OpenStreetMap contributors' } },
        { url: 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', options: { attribution: '© OpenStreetMap contributors' } },
        { url: 'https://{s}.tile.openstreetmap.de/{z}/{x}/{y}.png', options: { attribution: '© OpenStreetMap contributors' } }
    ];

    function useTileProvider(index) {
        if (index >= tileProviders.length) return;
        const provider = tileProviders[index];
        try {
            if (activeTileLayer) {
                activeTileLayer.off('tileerror');
                map.removeLayer(activeTileLayer);
            }
        } catch (e) {}

        activeTileLayer = L.tileLayer(provider.url, Object.assign({
            maxZoom: 19,
            minZoom: 4.5,
            // Load tiles while dragging (not only after moveend)
            updateWhenIdle: false,
            updateWhenZooming: true,
            // Keep a larger buffer to reduce gray edges during fast pans
            keepBuffer: 4,
            maxNativeZoom: 19,
            subdomains: ['a','b','c'],
            crossOrigin: true,
            detectRetina: true
        }, provider.options));

        activeTileLayer.on('tileerror', function () {
            useTileProvider(index + 1);
        });

        activeTileLayer.addTo(map);
    }

    useTileProvider(0);

    // Add scale control
    L.control.scale().addTo(map);

    // No forced redraws

    // Map click event to add a waypoint (do not change the search input)
    map.on('click', function (e) {
        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        addWaypoint({ lat, lng });
    });

    // Discover working proxy path, then get location
    discoverProxyPath().finally(() => {
        getCurrentLocation();
    });

    // Start connectivity monitoring
    startConnectivityMonitoring();

    // Force map to refresh after a short delay with performance optimizations
    setTimeout(() => {
        if (map) {
            console.log('Refreshing map size...');
            map.invalidateSize();
            map.setView([defaultLat, defaultLng], 7);

            // Optimize rendering
            map.getContainer().style.willChange = 'auto';

            console.log('Map initialization complete');
        }
    }, 100);
}

// Get current location
window.getCurrentLocation = function () {
    // Enforce secure context for mobile browsers (HTTPS or localhost)
    const isLocalhost = /^(localhost|127\.0\.0\.1)$/.test(location.hostname);
    const isSecure = (typeof window.isSecureContext !== 'undefined' ? window.isSecureContext : location.protocol === 'https:');
    if (!isSecure && !isLocalhost) {
        console.warn('Geolocation requires a secure context (HTTPS).');
        showSearchError('Location requires HTTPS on mobile. Please open this site over HTTPS.');
        return;
    }

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function (position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;

                currentLocation = { lat, lng };

                // Do not add a pin for current location; we will surface it as a suggestion instead

                // Optionally recenter map to current location WITHOUT changing zoom level
                const boundedLat = Math.max(2.0, Math.min(23.5, lat));
                const boundedLng = Math.max(113.0, Math.min(128.5, lng));
                try {
                    const currentZoom = typeof map.getZoom === 'function' ? map.getZoom() : undefined;
                    if (typeof currentZoom === 'number') {
                        map.setView([boundedLat, boundedLng], currentZoom);
                    } else {
                        // As a fallback, avoid zooming in; use a modest zoom
                        map.setView([boundedLat, boundedLng], 8);
                    }
                } catch(_) {}

                // If destination is set, calculate route
                if (destinationLocation) {
                    calculateRoute();
                }
            },
            function (error) {
                console.error('Error getting location:', error);
                let message = 'Unable to get your current location. Please check your browser settings.';
                if (error && typeof error.code !== 'undefined') {
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Location permission denied. Please allow location access in your browser settings.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Location unavailable. Ensure GPS/Location Services are turned on and try again.';
                            break;
                        case error.TIMEOUT:
                            message = 'Getting your location timed out. Try again with better signal or move to an open area.';
                            break;
                        default:
                            message = 'Unable to get your current location. Please check your browser settings.';
                    }
                }
                showSearchError(message);
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 10000
            }
        );
    } else {
        showSearchError('Geolocation is not supported by this browser.');
    }
}

// Helper function to get the correct proxy path
function getProxyPath() {
    const isLocalhost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    // Prefer subdomain root API first
    let guessed = isLocalhost ? '/logistic2/api/geocode_proxy.php' : '/api/geocode_proxy.php';
    return guessed;
}

// Robust fetch helper: tries multiple proxy base paths until one succeeds, caches the first working path
let __cachedProxyPath = null;
async function fetchProxyJson(queryString) {
    const candidatesRaw = [
        __cachedProxyPath,
        getProxyPath(),
        // Root-level within current host
        '/api/geocode_proxy.php',
        // Reservation-local paths
        '/reservation/api/geocode_proxy.php',
        '/logistic2/reservation/api/geocode_proxy.php',
        // Relative fallback
        '../api/geocode_proxy.php',
        '/logistic2/api/geocode_proxy.php'
    ];
    // Deduplicate and drop falsy
    const candidates = Array.from(new Set(candidatesRaw.filter(Boolean)));

    for (let i = 0; i < candidates.length; i++) {
        const base = candidates[i];
        try {
            const resp = await fetch(`${base}?${queryString}`);
            if (resp && resp.ok) {
                __cachedProxyPath = base; // cache working path
                return await resp.json();
            }
        } catch (_) {}
    }
    throw new Error('All proxy paths failed');
}

// At startup, probe candidates to find a working proxy path quickly and cache it
async function discoverProxyPath() {
    if (__cachedProxyPath) return __cachedProxyPath;
    const candidates = Array.from(new Set([
        getProxyPath(),
        '/api/geocode_proxy.php',
        '/reservation/api/geocode_proxy.php',
        '/logistic2/reservation/api/geocode_proxy.php',
        '../api/geocode_proxy.php',
        '/logistic2/api/geocode_proxy.php'
    ]));
    for (let i = 0; i < candidates.length; i++) {
        const base = candidates[i];
        try {
            const r = await fetch(`${base}?action=test`, { method: 'GET' });
            if (r && r.ok) {
                __cachedProxyPath = base;
                return base;
            }
        } catch(_) {}
    }
    return null;
}

// Retry geocoding for all waypoints that need it
function retryGeocodingAll() {
    console.log('Retrying geocoding for all waypoints...');
    
    const waypointsNeedingGeocoding = waypoints.filter(wp => wp.needsGeocoding);
    console.log('Found', waypointsNeedingGeocoding.length, 'waypoints needing geocoding');
    
    if (waypointsNeedingGeocoding.length === 0) {
        console.log('No waypoints need geocoding');
        return;
    }
    
    waypointsNeedingGeocoding.forEach((wp, index) => {
        setTimeout(() => {
            console.log('Retrying geocoding for waypoint:', wp.id);
            geocodeWaypoint(wp);
            
            // After the last waypoint is processed, ensure route is calculated
            if (index === waypointsNeedingGeocoding.length - 1) {
                setTimeout(() => {
                    console.log('All waypoints processed, recalculating final route...');
                    calculateRoute();
                }, 2000); // Wait 2 seconds after last geocoding attempt
            }
        }, index * 1000); // 1 second delay between each request
    });
}

// Check internet connectivity and retry geocoding
function checkConnectivityAndRetry() {
    console.log('Testing geocoding proxy connectivity...');
    fetch(`${getProxyPath()}?action=test`, { method: 'GET' })
        .then(r => {
            console.log('Proxy test response status:', r.status);
            return r.json();
        })
        .then(data => {
            console.log('Proxy test successful:', data);
            showSearchSuccess('Internet connection restored! Retrying address lookup...');
            retryGeocodingAll();
        })
        .catch(error => {
            console.log('Proxy test failed:', error);
            if (typeof navigator !== 'undefined' && navigator && navigator.onLine === false) {
                showSearchError('Still no internet connection. Please check your network.');
            }
        });
}

// Start connectivity monitoring
function startConnectivityMonitoring() {
    console.log('Starting connectivity monitoring...');
    
    // Check connectivity every 30 seconds
    setInterval(() => {
        const waypointsNeedingGeocoding = waypoints.filter(wp => wp.needsGeocoding);
        if (waypointsNeedingGeocoding.length > 0) {
            console.log('Found waypoints needing geocoding, checking connectivity...');
            checkConnectivityAndRetry();
        }
    }, 30000); // 30 seconds
    
    // Listen for online event
    window.addEventListener('online', () => {
        console.log('Internet connection detected!');
        setTimeout(() => {
            checkConnectivityAndRetry();
        }, 2000); // Wait 2 seconds after connection is detected
    });
}

// Search for address with performance optimizations
window.searchAddress = function () {
    const address = document.getElementById('reservation_address').value.trim();

    if (!address) {
        showSearchError('Please enter an address to search.');
        return;
    }

    // Clear any previous errors
    clearSearchError();

    // Show loading indicator
    const searchButton = document.querySelector('button[onclick="searchAddress()"]');
    if (searchButton) {
        searchButton.disabled = true;
    }

    // Search ALL places in Philippines with NO restrictions - including keywords
    const buildSearchUrl = () => `${(__cachedProxyPath || getProxyPath())}?action=search&q=${encodeURIComponent(address)}`;

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 10000);

    fetch(buildSearchUrl(), { signal: controller.signal })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // Reset button
            if (searchButton) {
                searchButton.disabled = false;
            }

            if (data && data.length > 0) {
                // Use the first result (best match from API)
                const bestResult = data[0];

                const lat = parseFloat(bestResult.lat);
                const lng = parseFloat(bestResult.lon);

                destinationLocation = { lat, lng };

                // Remove existing destination marker
                if (destinationMarker) {
                    map.removeLayer(destinationMarker);
                }

                // Add destination marker
                destinationMarker = L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'destination-marker',
                        html: '<div id="destination-marker-element" style="background-color: #EF4444; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3); cursor: pointer;" title="Click to remove"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    }),
                    interactive: true,
                    clickTolerance: 0
                }).addTo(map);

                // Click on pin removes it
                destinationMarker.on('click', function (e) {
                    e.originalEvent?.stopPropagation?.();
                    e.originalEvent?.preventDefault?.();
                    removeDestinationMarker();
                });

                // Input listeners moved to global scope for better reliability

                // Function to remove destination marker
                function removeDestinationMarker() {
                    console.log('Removing destination marker...');
                    if (destinationMarker && map.hasLayer(destinationMarker)) {
                        map.removeLayer(destinationMarker);
                        destinationMarker = null;
                        destinationLocation = null;

                        // Remove route if exists
                        if (window.currentRouteLayer && map.hasLayer(window.currentRouteLayer)) {
                            map.removeLayer(window.currentRouteLayer);
                            window.currentRouteLayer = null;
                        }

                        // Clear distance, arrival time, and travel time
                        const distanceElement = document.getElementById('estimated_distance');
                        const arrivalElement = document.getElementById('estimated_arrival');
                        const travelTimeElement = document.getElementById('estimated_travel_time');
                        if (distanceElement) distanceElement.textContent = '-- km';
                        if (arrivalElement) arrivalElement.value = '';
                        if (travelTimeElement) travelTimeElement.textContent = '-- hours';

                        // Hide remove marker button
                        const removeBtn = document.getElementById('removeMarkerBtn');
                        if (removeBtn) removeBtn.classList.add('hidden');
                    }
                }

                // Make function globally accessible
                window.removeDestinationMarker = removeDestinationMarker;

                // Add click event to remove destination marker
                destinationMarker.on('click', function (e) {
                    console.log('Destination marker clicked!');
                    e.originalEvent.stopPropagation();
                    e.originalEvent.preventDefault();
                    e.originalEvent.stopImmediatePropagation();
                    removeDestinationMarker();
                    return false;
                });

                // Add double-click event as backup
                destinationMarker.on('dblclick', function (e) {
                    console.log('Destination marker double-clicked!');
                    e.originalEvent.stopPropagation();
                    e.originalEvent.preventDefault();
                    e.originalEvent.stopImmediatePropagation();
                    removeDestinationMarker();
                    return false;
                });

                // Add right-click context menu
                destinationMarker.on('contextmenu', function (e) {
                    console.log('Destination marker right-clicked!');
                    e.originalEvent.preventDefault();
                    removeDestinationMarker();
                    return false;
                });

                // Add keyboard shortcut (Delete key) to remove marker
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Delete' && destinationMarker) {
                        console.log('Delete key pressed, removing destination marker...');
                        removeDestinationMarker();
                    }
                });

                // Add popup with auto-close after 2 seconds
                destinationMarker.bindPopup(`<b>Destination</b><br>${bestResult.display_name}<br><small style=\"color: #666;\">Click to remove</small>`).openPopup();

                // Show remove marker button
                const removeBtn = document.getElementById('removeMarkerBtn');
                if (removeBtn) removeBtn.classList.remove('hidden');

                setTimeout(() => {
                    if (destinationMarker && destinationMarker.isPopupOpen()) {
                        destinationMarker.closePopup();
                    }
                }, 2000);

                // Update address field with full address
                document.getElementById('reservation_address').value = bestResult.display_name;

                // Calculate route if current location is available
                if (currentLocation) {
                    calculateRoute();
                } else {
                    showSearchError('Please get your current location first before searching for a destination.');
                }

                // Fit map to show destination marker
                if (currentLocation) {
                    const bounds = L.latLngBounds([currentLocation, destinationLocation]);
                    map.fitBounds(bounds, { padding: [20, 20] });
                } else {
                    map.setView(destinationLocation, 12);
                }

                showSearchSuccess('Address found successfully! Route calculation in progress...');

            } else {
                showSearchError('Address not found. Please try a different search term.');
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);

            // Reset button
            if (searchButton) {
                searchButton.innerHTML = '<i class="fas fa-search mr-1"></i>Search on Map';
                searchButton.disabled = false;
            }

            if (error.name === 'AbortError') {
                showSearchError('Search request timed out. Please try again.');
            } else {
                console.error('Error searching address:', error);
                // Retry once using robust helper that discovers working proxy path
                fetchProxyJson(`action=search&q=${encodeURIComponent(address)}`)
                  .then(data => {
                    if (data && data.length > 0) {
                        const bestResult = data[0];
                        const lat = parseFloat(bestResult.lat);
                        const lng = parseFloat(bestResult.lon);
                        destinationLocation = { lat, lng };
                        // Minimal UI update; route will compute if waypoints present
                        showSearchSuccess('Address found successfully!');
                    } else {
                        showSearchError('Address not found. Please try a different search term.');
                    }
                  })
                  .catch(() => {
                    showSearchError('Unable to search address. Please check your internet connection and try again.');
                  });
            }
        });
}

// Calculate route between current location and destination with multiple fallback options
window.calculateRoute = function () {
    // Build route starting from the first waypoint
    if (waypoints.length < 2) {
        return;
    }

    // Remove existing routing control
    if (routingControl) {
        map.removeControl(routingControl);
    }

    // Show loading indicator
    const distanceElement = document.getElementById('estimated_distance');
    const durationElement = document.getElementById('estimated_duration');
    if (distanceElement) distanceElement.textContent = 'Calculating...';
    if (durationElement) durationElement.textContent = 'Calculating...';

    // Guard against stale responses
    const requestId = ++activeRouteRequestId;

    // Try multiple routing services with fallbacks, using waypoint[0] as start
    const routingServices = [
        { name: 'OSRM', url: buildOsrmUrl('https://router.project-osrm.org/route/v1/driving'), timeout: 20000 },
        { name: 'GraphHopper', url: buildGraphhopperUrl('https://graphhopper.com/api/1/route?vehicle=car&key=demo&instructions=true&calc_points=true&points_encoded=false&snap_prevention=trunk&details=time&details=distance&optimize=false'), timeout: 20000 },
        { name: 'OSRM Alternative', url: buildOsrmUrl('https://routing.openstreetmap.de/routed-car/route/v1/driving'), timeout: 20000 }
    ];

    // Try each routing service
    tryRoutingService(routingServices, 0, null, distanceElement, durationElement, requestId);
}

// Try routing services with fallback
function tryRoutingService(services, index, fallbackDistance, distanceElement, durationElement, requestId) {
    // If a newer request started, abort
    if (requestId !== activeRouteRequestId) return;
    if (index >= services.length) {
        // All services failed, use realistic estimated calculation to the last waypoint
        const lastWp = waypoints[waypoints.length - 1];
        if (!lastWp) return;
        const estimatedDistance = calculateStraightLineDistance(currentLocation, { lat: lastWp.lat, lng: lastWp.lng });
        const realisticDistance = (estimatedDistance * 1.6).toFixed(1); // More realistic road distance

        if (distanceElement) distanceElement.textContent = `${realisticDistance} km (estimated)`;

        // Calculate arrival time even with estimated route
        calculateArrivalTime();

        showSearchError('Using estimated route. Routing services are temporarily unavailable.');
        return;
    }

    const service = services[index];
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), service.timeout);

    fetch(service.url, { signal: controller.signal })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (requestId !== activeRouteRequestId) return; // stale
            clearTimeout(timeoutId);
            console.log(`${service.name} response:`, data);

            if (service.name === 'OSRM' && data.routes && data.routes.length > 0) {
                // OSRM response
                const route = data.routes[0];
                console.log(`OSRM route: ${(route.distance / 1000).toFixed(1)}km, ${Math.round(route.duration / 60)}mins`);
                displayRoute(route.geometry, route.distance, route.duration, distanceElement, durationElement, requestId);
            } else if (service.name === 'OSRM Alternative' && data.routes && data.routes.length > 0) {
                // OSRM Alternative response
                const route = data.routes[0];
                console.log(`OSRM Alt route: ${(route.distance / 1000).toFixed(1)}km, ${Math.round(route.duration / 60)}mins`);
                displayRoute(route.geometry, route.distance, route.duration, distanceElement, durationElement, requestId);
            } else if (service.name === 'GraphHopper' && data.paths && data.paths.length > 0) {
                // GraphHopper response
                const path = data.paths[0];
                console.log(`GraphHopper route: ${(path.distance / 1000).toFixed(1)}km, ${Math.round(path.time / 60000)}mins`);
                // Convert GraphHopper points to GeoJSON if available
                if (path.points && path.points.coordinates) {
                    const geometry = {
                        type: "LineString",
                        coordinates: path.points.coordinates
                    };
                    displayRoute(geometry, path.distance, path.time, distanceElement, durationElement, requestId);
                } else {
                    displayRoute(null, path.distance, path.time, distanceElement, durationElement, requestId);
                }
            } else {
                console.log(`${service.name} failed, trying next service...`);
                // Try next service
                tryRoutingService(services, index + 1, fallbackDistance, distanceElement, durationElement, requestId);
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            console.log(`${service.name} routing failed:`, error.message);
            if (requestId !== activeRouteRequestId) return; // stale
            // Try next service immediately
            setTimeout(() => {
                tryRoutingService(services, index + 1, fallbackDistance, distanceElement, durationElement, requestId);
            }, 100);
        });
}

// Display route on map
function displayRoute(geometry, distance, duration, distanceElement, durationElement, requestId) {
    if (requestId !== undefined && requestId !== activeRouteRequestId) return; // stale
    // Display route on map if geometry is available
    if (geometry) {
        // Remove previous route layer first
        if (window.currentRouteLayer) {
            try { map.removeLayer(window.currentRouteLayer); } catch (e) {}
            window.currentRouteLayer = null;
        }

        const routeLayer = L.geoJSON(geometry, {
            style: {
                color: '#2563EB',
                weight: 5,
                opacity: 0.9
            }
        }).addTo(map);

        // Store route layer for cleanup
        window.currentRouteLayer = routeLayer;

        // Fit to full route so nothing looks cut off
        try {
            const bounds = routeLayer.getBounds();
            if (bounds && bounds.isValid()) {
                map.fitBounds(bounds, { padding: [24, 24] });
            }
        } catch (e) {}
    }

    // Update distance
    const distanceKm = (distance / 1000).toFixed(1); // Convert to km

    if (distanceElement) distanceElement.textContent = `${distanceKm} km`;

    // Calculate arrival time
    calculateArrivalTime();
}

// Use fallback calculation when routing services fail
function useFallbackCalculation(distance, distanceElement, durationElement) {
    // Remove any existing route layer
    if (window.currentRouteLayer) {
        map.removeLayer(window.currentRouteLayer);
        window.currentRouteLayer = null;
    }

    // Draw straight line between points
    const routeLayer = L.polyline([currentLocation, destinationLocation], {
        color: '#2563EB',
        weight: 4,
        opacity: 0.8,
        dashArray: '8, 8'
    }).addTo(map);

    window.currentRouteLayer = routeLayer;

    // Fit to the straight-line route
    try {
        const bounds = routeLayer.getBounds();
        if (bounds && bounds.isValid()) {
            map.fitBounds(bounds, { padding: [24, 24] });
        }
    } catch (e) {}

    // Use straight-line distance with 30% buffer for realistic road distance
    const realisticDistance = (distance * 1.3).toFixed(1);

    // Calculate travel time in hours and minutes (average 30 km/h for city driving with traffic)
    const totalMinutesFallback = Math.round((realisticDistance / 30) * 60);
    const hoursFallback = Math.floor(totalMinutesFallback / 60);
    const minutesFallback = totalMinutesFallback % 60;
    
    // Estimate duration in minutes for arrival calculation
    const estimatedDuration = totalMinutesFallback;
    
    // Format as "X hr Y min" or "Y min" if less than 1 hour
    let timeDisplayFallback;
    if (hoursFallback > 0) {
        timeDisplayFallback = minutesFallback > 0 ? `${hoursFallback} hr ${minutesFallback} min` : `${hoursFallback} hr`;
    } else {
        timeDisplayFallback = `${minutesFallback} min`;
    }
    
    // Update travel time display
    const travelTimeElement = document.getElementById('estimated_travel_time');
    if (travelTimeElement) {
        travelTimeElement.textContent = `${timeDisplayFallback} (estimated)`;
    }

    if (distanceElement) distanceElement.textContent = `${realisticDistance} km (estimated)`;
    if (durationElement) durationElement.textContent = `${estimatedDuration} mins (estimated)`;

    // Calculate arrival time
    calculateArrivalTime();

    // Show info message
    console.log('Using estimated route calculation due to routing service unavailability');
    showSearchError('Routing services are temporarily unavailable. Showing estimated route.');
}

// Calculate straight-line distance between two points
function calculateStraightLineDistance(point1, point2) {
    const R = 6371; // Earth's radius in kilometers
    const dLat = (point2.lat - point1.lat) * Math.PI / 180;
    const dLon = (point2.lng - point1.lng) * Math.PI / 180;
    const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(point1.lat * Math.PI / 180) * Math.cos(point2.lat * Math.PI / 180) *
        Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return R * c;
}

// Show search error with speech bubble notification
function showSearchError(message) {
    // Remove any existing error notification
    const existingNotification = document.getElementById('error-notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    // Create error notification bubble
    const errorNotification = document.createElement('div');
    errorNotification.id = 'error-notification';
    errorNotification.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-3 rounded-lg shadow-lg z-50 max-w-sm transform translate-x-full transition-transform duration-300';
    errorNotification.innerHTML = `
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-white"></i>
            </div>
            <div class="ml-3 flex-1">
                <p class="text-sm font-medium">${message}</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <button onclick="this.closest('#error-notification').remove()" class="text-white hover:text-red-200 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <!-- Speech bubble tail -->
        <div class="absolute top-full right-6 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-red-500"></div>
    `;

    // Add to body
    document.body.appendChild(errorNotification);

    // Animate in
    setTimeout(() => {
        errorNotification.classList.remove('translate-x-full');
    }, 100);

    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (errorNotification && errorNotification.parentElement) {
            errorNotification.classList.add('translate-x-full');
            setTimeout(() => {
                if (errorNotification && errorNotification.parentElement) {
                    errorNotification.remove();
                }
            }, 300);
        }
    }, 5000);
}

// Clear search error when starting new search
function clearSearchError() {
    const errorNotification = document.getElementById('error-notification');
    if (errorNotification) {
        errorNotification.remove();
    }
}

// Cache for address suggestions to improve speed
const addressCache = new Map();
const CACHE_DURATION = 5 * 60 * 1000; // 5 minutes

// Simple retry mechanism
async function fetchWithRetry(url, options = {}, maxRetries = 2) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            console.log(`Attempt ${attempt}/${maxRetries} for: ${url}`);
            const response = await fetch(url, options);
            if (response.ok) {
                return response;
            }
            if (attempt === maxRetries) {
                throw new Error(`HTTP ${response.status}`);
            }
        } catch (error) {
            console.log(`Attempt ${attempt} failed:`, error.message);
            if (attempt === maxRetries) {
                throw error;
            }
            // Short wait before retry
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    }
}

// Show address suggestions
function showAddressSuggestions(suggestions) {
    const suggestionsContainer = document.getElementById('address-suggestions');
    if (!suggestionsContainer) return;

    // Always start fresh
    suggestionsContainer.innerHTML = '';

    // Determine if the input is empty; only then show "Your location"
    const addressInput = document.getElementById('reservation_address');
    const currentQuery = addressInput ? addressInput.value.trim() : '';
    const shouldShowYourLocation = currentQuery.length === 0;

    // Prepend synthetic "Your location" when available and input is empty
    if (shouldShowYourLocation && typeof currentLocation === 'object' && currentLocation && typeof currentLocation.lat === 'number' && typeof currentLocation.lng === 'number') {
        const yourLocItem = document.createElement('div');
        yourLocItem.className = 'px-4 py-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100 transition-colors';
        const yourLocSuggestion = {
            display_name: 'Your location',
            lat: currentLocation.lat,
            lon: currentLocation.lng,
            isCurrentLocation: true
        };
        yourLocItem.dataset.suggestion = JSON.stringify(yourLocSuggestion);
        yourLocItem.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-location-arrow text-blue-500 mr-3"></i>
                <div class="flex-1">
                    <div class="text-sm font-medium text-gray-900">Your location</div>
                    <div class="text-xs text-gray-500">Use your current position</div>
                </div>
            </div>
        `;
        yourLocItem.addEventListener('click', () => {
            // Reverse geocode to get the actual address for current location
    // Show loading state
    const loadingText = yourLocItem.querySelector('.text-sm.font-medium');
    if (loadingText) loadingText.textContent = 'Getting address...';
    
    fetch(`${getProxyPath()}?action=reverse&lat=${currentLocation.lat}&lng=${currentLocation.lng}`)
        .then(r => {
            if (!r.ok) throw new Error(`HTTP ${r.status}`);
            return r.json();
        })
        .then(data => {
            const fullAddress = data.address || 'Your location';
            const updatedSuggestion = {
                ...yourLocSuggestion,
                display_name: fullAddress
            };
            selectAddressSuggestion(updatedSuggestion);
        })
        .catch((error) => {
            console.log('Reverse geocoding failed, using fallback:', error);
            // Fallback: use coordinate-based address
            const fallbackAddress = `Your location (${currentLocation.lat.toFixed(6)}, ${currentLocation.lng.toFixed(6)})`;
            const updatedSuggestion = {
                ...yourLocSuggestion,
                display_name: fallbackAddress
            };
            selectAddressSuggestion(updatedSuggestion);
        });
        });
        suggestionsContainer.appendChild(yourLocItem);
    }

    suggestions.forEach((suggestion, index) => {
        const suggestionItem = document.createElement('div');
        suggestionItem.className = 'px-4 py-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-b-0 transition-colors';
        suggestionItem.dataset.suggestion = JSON.stringify(suggestion);
        suggestionItem.innerHTML = `
            <div class="flex items-center">
                <i class="fas fa-map-marker-alt text-gray-400 mr-3"></i>
                <div class="flex-1">
                    <div class="text-sm font-medium text-gray-900">${suggestion.display_name.split(',')[0]}</div>
                    <div class="text-xs text-gray-500">${suggestion.display_name.split(',').slice(1).join(',').trim()}</div>
                </div>
            </div>
        `;

        suggestionItem.addEventListener('click', () => {
            selectAddressSuggestion(suggestion);
        });

        suggestionsContainer.appendChild(suggestionItem);
    });

    // Hide only if we rendered nothing
    if (suggestionsContainer.children.length === 0) {
        suggestionsContainer.classList.add('hidden');
    } else {
        suggestionsContainer.classList.remove('hidden');
    }
}

// Hide address suggestions
function hideAddressSuggestions() {
    const suggestionsContainer = document.getElementById('address-suggestions');
    if (suggestionsContainer) {
        suggestionsContainer.classList.add('hidden');
    }
}

// Select address suggestion
function selectAddressSuggestion(suggestion) {
    const addressInput = document.getElementById('reservation_address');
    // Add selected suggestion as a waypoint (keep existing pins)
    if (suggestion && suggestion.lat && suggestion.lon) {
        addWaypoint({ lat: parseFloat(suggestion.lat), lng: parseFloat(suggestion.lon), address: suggestion.display_name });
    } else {
        searchAddress();
    }
    // Clear the search box and close suggestions after selection
    if (addressInput) {
        addressInput.value = '';
        addressInput.focus();
    }
    hideAddressSuggestions();
}

// Trigger address suggestions when search button is clicked
window.triggerAddressSuggestions = function () {
    const addressInput = document.getElementById('reservation_address');
    const query = addressInput.value.trim();

    if (query.length > 0) {
        // Show suggestions for the current input
        searchAddressSuggestions(query);
    } else {
        // If input is empty, show default suggestions (Your location)
        showAddressSuggestions([]);
    }
}

// Search for address suggestions as user types
function searchAddressSuggestions(query) {
    if (query.length < 1) {
        hideAddressSuggestions();
        return;
    }

    // Check cache first for instant results
    const cacheKey = query.toLowerCase().trim();
    const cached = addressCache.get(cacheKey);
    if (cached && (Date.now() - cached.timestamp) < CACHE_DURATION) {
        console.log('Using cached results for:', query);
        showAddressSuggestions(cached.data);
        return;
    }

    console.log('=== Address Search Debug ===');
    console.log('Searching for:', query);
    console.log('Current hostname:', window.location.hostname);
    console.log('Resolved proxy path:', getProxyPath());

    // Show loading state immediately
    showAddressSuggestions([{
        display_name: 'Searching...',
        place_id: 'loading',
        lat: '0',
        lon: '0'
    }]);

    // Fast approach: Try both proxy and direct API simultaneously
    const proxyPromise = fetch(`${getProxyPath()}?action=search&q=${encodeURIComponent(query)}`)
        .then(response => {
            console.log('Proxy response status:', response.status);
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.json();
        })
        .then(data => {
            console.log('Proxy response data:', data);
            if (data && data.error) {
                throw new Error('Proxy error: ' + data.error);
            }
            return data;
        });

    const directPromise = fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=10&addressdetails=1&countrycodes=ph&bounded=0&dedupe=0`, {
        headers: {
            'User-Agent': 'Logistic2-App/1.0',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        console.log('Direct API response status:', response.status);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    });

    // Race both requests - use whichever responds first
    Promise.race([proxyPromise, directPromise])
        .then(data => {
            console.log('Fast response data:', data);
            if (data && Array.isArray(data) && data.length > 0) {
                // Cache the results
                addressCache.set(cacheKey, {
                    data: data,
                    timestamp: Date.now()
                });
                showAddressSuggestions(data);
            } else {
                hideAddressSuggestions();
            }
        })
        .catch(error => {
            console.log('Both methods failed, trying fallback:', error.message);
            // If both fail, try a simple fallback
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query + ' Philippines')}&limit=5&addressdetails=1&countrycodes=ph`)
                .then(response => response.json())
                .then(data => {
                    if (data && Array.isArray(data) && data.length > 0) {
                        addressCache.set(cacheKey, {
                            data: data,
                            timestamp: Date.now()
                        });
                        showAddressSuggestions(data);
                    } else {
                        hideAddressSuggestions();
                    }
                })
                .catch(() => {
                    hideAddressSuggestions();
                });
        });
}

// Try broader search without Philippines restriction
function tryBroaderSearch(address, searchButton) {
    // Try a broader search without country restriction
    const broaderSearchUrl = `${getProxyPath()}?action=search&q=${encodeURIComponent(address)}`;

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000); // 8 second timeout for broader search

    fetch(broaderSearchUrl, { signal: controller.signal })
        .then(response => {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data && data.length > 0) {
                const result = data[0];

                // Check if result is actually in Philippines
                if (result.address && result.address.country === 'Philippines') {
                    // Process the result same as before
                    const lat = parseFloat(result.lat);
                    const lng = parseFloat(result.lon);

                    destinationLocation = { lat, lng };

                    // Remove existing destination marker
                    if (destinationMarker) {
                        map.removeLayer(destinationMarker);
                    }

                    // Add destination marker
                    destinationMarker = L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'destination-marker',
                            html: '<div style="background-color: #EF4444; width: 20px; height: 20px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>',
                            iconSize: [20, 20],
                            iconAnchor: [10, 10]
                        })
                    }).addTo(map);

                    // Add popup with auto-close after 2 seconds
                    destinationMarker.bindPopup(`<b>Destination</b><br>${result.display_name}`).openPopup();

                    setTimeout(() => {
                        if (destinationMarker && destinationMarker.isPopupOpen()) {
                            destinationMarker.closePopup();
                        }
                    }, 2000);

                    // Update address field with full address
                    document.getElementById('reservation_address').value = result.display_name;

                    // Calculate route if current location is available
                    if (currentLocation) {
                        calculateRoute();
                    } else {
                        showSearchError('Please get your current location first before searching for a destination.');
                    }

                    // Fit map to show destination marker
                    if (currentLocation) {
                        const bounds = L.latLngBounds([currentLocation, destinationLocation]);
                        map.fitBounds(bounds, { padding: [20, 20] });
                    } else {
                        map.setView(destinationLocation, 12);
                    }

                    showSearchSuccess('Address found successfully! Route calculation in progress...');
                } else {
                    showSearchError('Address not found in Philippines. Please try a different search term.');
                }
            } else {
                showSearchError('Address not found. Please try a different search term.');
            }
        })
        .catch(error => {
            clearTimeout(timeoutId);
            if (searchButton) {
                searchButton.innerHTML = '<i class="fas fa-search mr-1"></i>Search on Map';
                searchButton.disabled = false;
            }

            if (error.name === 'AbortError') {
                showSearchError('Search request timed out. Please try again.');
            } else {
                console.error('Error in broader search:', error);
                showSearchError('Unable to search address. Please check your internet connection and try again.');
            }
        });
}

// Setup address suggestions functionality
function setupAddressSuggestions() {
    const addressInput = document.getElementById('reservation_address');
    if (!addressInput) return;

    let debounceTimer;

    // Add input event listener for real-time suggestions
    addressInput.addEventListener('input', function () {
        const query = this.value.trim();

        // Clear previous timer
        clearTimeout(debounceTimer);

        // Set new timer for debounced search
        debounceTimer = setTimeout(() => {
            if (query.length === 0) {
                // Show default suggestions when empty (Your location)
                showAddressSuggestions([]);
            } else {
                searchAddressSuggestions(query);
            }
        }, 100); // 100ms delay for faster response
    });

    // Hide suggestions when input loses focus
    addressInput.addEventListener('blur', function () {
        // Delay hiding to allow clicking on suggestions
        setTimeout(() => {
            hideAddressSuggestions();
        }, 200);
    });

    // Show suggestions when input gains focus
    addressInput.addEventListener('focus', function () {
        const query = this.value.trim();
        if (query.length === 0) {
            showAddressSuggestions([]);
        } else if (query.length >= 1) {
            searchAddressSuggestions(query);
        }
    });

    // Handle keyboard navigation
    addressInput.addEventListener('keydown', function (e) {
        const suggestionsContainer = document.getElementById('address-suggestions');
        if (!suggestionsContainer || suggestionsContainer.classList.contains('hidden')) return;

        const suggestions = suggestionsContainer.querySelectorAll('[class*="hover:bg-gray-100"]');
        let currentIndex = -1;

        // Find currently selected suggestion
        suggestions.forEach((item, index) => {
            if (item.classList.contains('bg-blue-100')) {
                currentIndex = index;
                item.classList.remove('bg-blue-100');
            }
        });

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            currentIndex = (currentIndex + 1) % suggestions.length;
            suggestions[currentIndex].classList.add('bg-blue-100');
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            currentIndex = currentIndex <= 0 ? suggestions.length - 1 : currentIndex - 1;
            suggestions[currentIndex].classList.add('bg-blue-100');
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (currentIndex >= 0) {
                const selectedSuggestion = suggestions[currentIndex];
                const suggestionData = selectedSuggestion.dataset.suggestion;
                if (suggestionData) {
                    selectAddressSuggestion(JSON.parse(suggestionData));
                }
            }
        } else if (e.key === 'Escape') {
            hideAddressSuggestions();
        }
    });
}

// Waypoints helpers
function addWaypoint({ lat, lng, address }) {
    if (waypoints.length >= MAX_WAYPOINTS) {
        showSearchError(`Maximum of ${MAX_WAYPOINTS} destinations reached.`);
        return;
    }

    const id = Date.now() + '-' + Math.random().toString(36).slice(2);
    const icon = waypoints.length === 0 ? getStartIcon() : getDestinationIcon();
    const marker = L.marker([lat, lng], { icon, interactive: true }).addTo(map);
    marker.on('click', (e) => {
        e.originalEvent?.stopPropagation?.();
        removeWaypoint(id);
    });

    const wp = { id, lat, lng, address: address || '', marker, needsGeocoding: !address };
    waypoints.push(wp);

    // Reverse geocode if no address yet
    if (!address) {
        console.log('No address provided, starting geocoding for waypoint:', wp.id);
        geocodeWaypoint(wp);
    } else {
        console.log('Address provided, no geocoding needed:', address);
    }

    renderWaypointsList();
    calculateRoute();
}

// Function to geocode a waypoint
function geocodeWaypoint(wp) {
    // Show loading state in waypoint
    wp.address = 'Getting address...';
    renderWaypointsList();
    
    console.log('Geocoding waypoint:', wp.lat, wp.lng);
    
    fetchProxyJson(`action=reverse&lat=${wp.lat}&lng=${wp.lng}`)
        .then(d => {
            console.log('Response data:', d);
            
            const address = d.address || d.display_name || '';
            wp.address = address;
            wp.needsGeocoding = false;
            
            console.log('✅ Address set:', address);
            renderWaypointsList();
            calculateRoute();
        })
        .catch((error) => {
            console.log('Geocoding failed:', error);
            wp.address = `Location (${wp.lat.toFixed(4)}, ${wp.lng.toFixed(4)})`;
            wp.needsGeocoding = true;
            renderWaypointsList();
        });
}

function removeWaypoint(id) {
    const idx = waypoints.findIndex(w => w.id === id);
    if (idx >= 0) {
        // If removing the starting point (first waypoint), clear all waypoints
        if (idx === 0) {
            // Remove all waypoint markers from map
            waypoints.forEach(wp => {
                if (wp.marker && map && map.hasLayer(wp.marker)) {
                    map.removeLayer(wp.marker);
                }
            });
            waypoints = [];
            renderWaypointsList();
            
            // Clear route and stats
            activeRouteRequestId++;
            if (window.currentRouteLayer && map && map.hasLayer(window.currentRouteLayer)) {
                map.removeLayer(window.currentRouteLayer);
                window.currentRouteLayer = null;
            }
            const distanceElement = document.getElementById('estimated_distance');
            const arrivalElement = document.getElementById('estimated_arrival');
            const travelTimeElement = document.getElementById('estimated_travel_time');
            if (distanceElement) distanceElement.textContent = '-- km';
            if (arrivalElement) arrivalElement.value = '';
            if (travelTimeElement) travelTimeElement.textContent = '-- hours';
        } else {
            // Remove specific waypoint
            const wp = waypoints[idx];
            if (wp.marker && map && map.hasLayer(wp.marker)) {
                map.removeLayer(wp.marker);
            }
            waypoints.splice(idx, 1);
            
            // Update marker icons after removal (first becomes start, others become destinations)
            if (waypoints.length > 0) {
                waypoints.forEach((w, wIdx) => {
                    if (w.marker) {
                        try { w.marker.setIcon(wIdx === 0 ? getStartIcon() : getDestinationIcon()); } catch (_) {}
                    }
                });
            }
            
            renderWaypointsList();
            if (waypoints.length >= 2) {
                calculateRoute();
            } else {
                // Invalidate any in-flight routing responses to avoid stale redraws
                activeRouteRequestId++;
                // Not enough waypoints for route: clear route and stats
                if (window.currentRouteLayer && map && map.hasLayer(window.currentRouteLayer)) {
                    map.removeLayer(window.currentRouteLayer);
                    window.currentRouteLayer = null;
                }
                const distanceElement = document.getElementById('estimated_distance');
                const arrivalElement = document.getElementById('estimated_arrival');
                const travelTimeElement = document.getElementById('estimated_travel_time');
                if (distanceElement) distanceElement.textContent = '-- km';
                if (arrivalElement) arrivalElement.value = '';
                if (travelTimeElement) travelTimeElement.textContent = '-- hours';
            }
        }
    }
}

function renderWaypointsList() {
    const list = document.getElementById('waypointsList');
    if (!list) return;
    list.innerHTML = '';

    waypoints.forEach((wp, index) => {
        const row = document.createElement('div');
        row.className = 'flex items-center gap-2';
        row.dataset.id = wp.id;
        row.setAttribute('draggable', 'true');
        
        // Show loading state or retry button for waypoints that need geocoding
        let addressDisplay = wp.address || `Destination ${index+1}`;
        let retryButton = '';
        
        if (wp.address === 'Getting address...') {
            addressDisplay = 'Getting address...';
        } else if (wp.needsGeocoding && wp.address.includes('Location (')) {
            retryButton = '<button type="button" onclick="geocodeWaypoint(waypoints.find(w => w.id === \'' + wp.id + '\'))" class="px-2 py-1 text-blue-600 text-xs" title="Retry geocoding">🔄 Retry geocoding</button>';
        }
        
        row.innerHTML = `
            <button type="button" class="cursor-move text-gray-500" title="Drag to reorder">⋮⋮</button>
            <input type="text" class="flex-1 border border-gray-300 rounded px-3 py-2 text-sm" value="${addressDisplay}" readonly />
            ${retryButton}
            <button type="button" class="px-2 py-1 text-red-600" title="Remove">✕</button>
        `;
        const removeBtn = row.querySelector('button:last-child');
        removeBtn.addEventListener('click', () => removeWaypoint(wp.id));
        // Drag and drop handlers
        row.addEventListener('dragstart', (e) => {
            try { e.dataTransfer.setData('text/plain', wp.id); } catch(_) {}
            row.classList.add('opacity-50');
        });
        row.addEventListener('dragend', () => {
            row.classList.remove('opacity-50');
        });
        row.addEventListener('dragover', (e) => {
            e.preventDefault();
            row.classList.add('bg-gray-50');
        });
        row.addEventListener('dragleave', () => {
            row.classList.remove('bg-gray-50');
        });
        row.addEventListener('drop', (e) => {
            e.preventDefault();
            row.classList.remove('bg-gray-50');
            let draggedId = '';
            try { draggedId = e.dataTransfer.getData('text/plain'); } catch(_) {}
            const targetId = wp.id;
            reorderWaypoints(draggedId, targetId);
        });
        list.appendChild(row);
    });

    const addBtn = document.getElementById('addDestinationBtn');
    if (addBtn) {
        addBtn.disabled = waypoints.length >= MAX_WAYPOINTS;
        addBtn.onclick = function(){
            const addr = document.getElementById('reservation_address');
            if (addr) addr.focus();
        };
    }
}

function reorderWaypoints(draggedId, targetId) {
    if (!draggedId || !targetId || draggedId === targetId) return;
    const fromIndex = waypoints.findIndex(w => w.id === draggedId);
    const toIndex = waypoints.findIndex(w => w.id === targetId);
    if (fromIndex === -1 || toIndex === -1) return;
    const [moved] = waypoints.splice(fromIndex, 1);
    waypoints.splice(toIndex, 0, moved);
    // Update start marker icon after reorder
    if (waypoints.length > 0) {
        waypoints.forEach((w, idx) => {
            if (w.marker) {
                try { w.marker.setIcon(idx === 0 ? getStartIcon() : getDestinationIcon()); } catch (_) {}
            }
        });
    }
    renderWaypointsList();
    calculateRoute();
}

// Build routing URLs from currentLocation + waypoints
function buildOsrmUrl(base) {
    // Start at first waypoint, then the rest
    const start = waypoints[0];
    const rest = waypoints.slice(1);
    const coords = [ [start.lng, start.lat].join(',') ]
        .concat(rest.map(w => [w.lng, w.lat].join(',')))
        .join(';');
    return `${base}/${coords}?overview=full&geometries=geojson&steps=true&annotations=duration,distance&continue_straight=false`;
}

function buildGraphhopperUrl(base) {
    const start = waypoints[0];
    const rest = waypoints.slice(1);
    const points = [`point=${start.lat},${start.lng}`]
        .concat(rest.map(w => `point=${w.lat},${w.lng}`))
        .join('&');
    return `${base}&${points}`;
}

// Show search success message with speech bubble notification
function showSearchSuccess(message) {
    // Remove any existing success notification
    const existingNotification = document.getElementById('success-notification');
    if (existingNotification) {
        existingNotification.remove();
    }

    // Create success notification bubble
    const successNotification = document.createElement('div');
    successNotification.id = 'success-notification';
    successNotification.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-3 rounded-lg shadow-lg z-50 max-w-sm transform translate-x-full transition-transform duration-300';
    successNotification.innerHTML = `
        <div class="flex items-start">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle text-white"></i>
            </div>
            <div class="ml-3 flex-1">
                <p class="text-sm font-medium">${message}</p>
            </div>
            <div class="ml-4 flex-shrink-0">
                <button onclick="this.closest('#success-notification').remove()" class="text-white hover:text-green-200 transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <!-- Speech bubble tail -->
        <div class="absolute top-full right-6 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-green-500"></div>
    `;

    // Add to body
    document.body.appendChild(successNotification);

    // Animate in
    setTimeout(() => {
        successNotification.classList.remove('translate-x-full');
    }, 100);

    // Auto-remove after 3 seconds
    setTimeout(() => {
        if (successNotification && successNotification.parentElement) {
            successNotification.classList.add('translate-x-full');
            setTimeout(() => {
                if (successNotification && successNotification.parentElement) {
                    successNotification.remove();
                }
            }, 300);
        }
    }, 3000);
}

// Calculate travel time in hours and arrival time based on departure time and distance
window.calculateArrivalTime = function () {
    const departureInput = document.getElementById('estimated_departure');
    const distanceText = document.getElementById('estimated_distance').textContent;

    if (departureInput.value && distanceText && distanceText !== '-- km' && distanceText !== 'Calculating...') {
        // Extract distance number from text (e.g., "534.5 km (estimated)" -> 534.5)
        const distanceMatch = distanceText.match(/(\d+(?:\.\d+)?)/);
        if (distanceMatch) {
            const distance = parseFloat(distanceMatch[1]);

            // Calculate travel time in hours and minutes (average 30 km/h for city driving)
            const totalMinutesMain = Math.round((distance / 30) * 60);
            const hoursMain = Math.floor(totalMinutesMain / 60);
            const minutesMain = totalMinutesMain % 60;
            
            // Format as "X hr Y min" or "Y min" if less than 1 hour
            let timeDisplayMain;
            if (hoursMain > 0) {
                timeDisplayMain = minutesMain > 0 ? `${hoursMain} hr ${minutesMain} min` : `${hoursMain} hr`;
            } else {
                timeDisplayMain = `${minutesMain} min`;
            }
            
            // Update travel time display
            const travelTimeElement = document.getElementById('estimated_travel_time');
            if (travelTimeElement) {
                travelTimeElement.textContent = timeDisplayMain;
            }

            // Estimate duration in minutes for arrival calculation
            const estimatedDuration = Math.round((distance / 30) * 60); // Convert to minutes

            // Parse departure datetime-local
            const departureDate = new Date(departureInput.value);

            // Add duration
            const arrivalDate = new Date(departureDate.getTime() + estimatedDuration * 60000);

            // Format arrival datetime-local
            const arrivalInput = document.getElementById('estimated_arrival');
            const year = arrivalDate.getFullYear();
            const month = String(arrivalDate.getMonth() + 1).padStart(2, '0');
            const day = String(arrivalDate.getDate()).padStart(2, '0');
            const hours = String(arrivalDate.getHours()).padStart(2, '0');
            const minutes = String(arrivalDate.getMinutes()).padStart(2, '0');

            const arrivalDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
            arrivalInput.value = arrivalDateTime;
        }
    }
}

// Page Navigation Functions for 2-Page Modal
let currentModalPage = 1;

// Show specific page
window.showPage = function(pageNumber) {
    const page1 = document.getElementById('page1');
    const page2 = document.getElementById('page2');
    const page1Btn = document.getElementById('page1Btn');
    const page2Btn = document.getElementById('page2Btn');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    if (!page1 || !page2 || !page1Btn || !page2Btn || !prevBtn || !nextBtn) return;

    // Hide all pages
    page1.classList.add('hidden');
    page2.classList.add('hidden');

    // Remove active state from all page buttons
    page1Btn.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
    page1Btn.classList.add('border-transparent', 'text-gray-500');
    page2Btn.classList.remove('border-blue-500', 'text-blue-600', 'bg-blue-50');
    page2Btn.classList.add('border-transparent', 'text-gray-500');

    // Show selected page
    if (pageNumber === 1) {
        page1.classList.remove('hidden');
        page1Btn.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
        page1Btn.classList.remove('border-transparent', 'text-gray-500');
        // Hide form action buttons on page 1
        const formActions = document.getElementById('formActions');
        if (formActions) formActions.classList.add('hidden');
        currentModalPage = 1;
    } else if (pageNumber === 2) {
        page2.classList.remove('hidden');
        page2Btn.classList.add('border-blue-500', 'text-blue-600', 'bg-blue-50');
        page2Btn.classList.remove('border-transparent', 'text-gray-500');
        // Show form action buttons on page 2
        const formActions = document.getElementById('formActions');
        if (formActions) formActions.classList.remove('hidden');
        currentModalPage = 2;

        // Ensure Leaflet map renders correctly after becoming visible
        setTimeout(() => {
            try {
                if (typeof map !== 'undefined' && map) {
                    map.invalidateSize();
                    // Always start at max zoom-out when page becomes visible
                    setTimeout(() => {
                        try {
                            const lat = (typeof window.DEFAULT_LAT !== 'undefined') ? window.DEFAULT_LAT : 14.5995;
                            const lng = (typeof window.DEFAULT_LNG !== 'undefined') ? window.DEFAULT_LNG : 120.9842;
                            map.setView([lat, lng], 10);
                        } catch (_) {}
                    }, 100);
                } else if (typeof initializeMap === 'function') {
                    initializeMap();
                    setTimeout(() => {
                        try {
                            const lat = (typeof window.DEFAULT_LAT !== 'undefined') ? window.DEFAULT_LAT : 14.5995;
                            const lng = (typeof window.DEFAULT_LNG !== 'undefined') ? window.DEFAULT_LNG : 120.9842;
                            map.setView([lat, lng], 10);
                        } catch (_) {}
                    }, 400);
                }
            } catch (e) {
                console.error('Error ensuring map visibility:', e);
            }
        }, 150);
    }

    // Update navigation buttons
    updateNavigationButtons();
}

// Go to next page
window.nextPage = function() {
    if (currentModalPage < 2) {
        showPage(currentModalPage + 1);
    }
}

// Go to previous page
window.previousPage = function() {
    if (currentModalPage > 1) {
        showPage(currentModalPage - 1);
    }
}

// Update navigation buttons visibility and text
function updateNavigationButtons() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    if (!prevBtn || !nextBtn) return;

    if (currentModalPage === 1) {
        prevBtn.classList.add('hidden');
        nextBtn.classList.remove('hidden');
        nextBtn.textContent = 'Next';
        nextBtn.innerHTML = 'Next<i class="fas fa-arrow-right ml-2"></i>';
        // Ensure the button advances pages, not submitting the form
        nextBtn.onclick = function() { window.nextPage(); };
    } else if (currentModalPage === 2) {
        prevBtn.classList.remove('hidden');
        // Hide the left-side button on the final page to avoid duplicate submit UI
        nextBtn.classList.add('hidden');
        // Restore default navigation handler in case it was changed previously
        nextBtn.onclick = function() { window.nextPage(); };
    }
}

// Reset modal to page 1 when opened
const originalOpenAddModal = window.openAddModal;
window.openAddModal = function() {
    // Call original function
    if (originalOpenAddModal) {
        originalOpenAddModal();
    }
    
    // Reset to page 1
    setTimeout(() => {
        showPage(1);
        // Refresh default departure time to now whenever modal opens
        try { setDepartureNow(); } catch(_) {}
        // Also hard-reset previews and selects on open to avoid stale images
        try {
            const driverImg = document.getElementById('driverImagePreview');
            const driverPlaceholder = document.getElementById('driverImagePlaceholder');
            const driverName = document.getElementById('driver_name_field');
            const driverContact = document.getElementById('driver_contact_field');
            const driverSelect = document.getElementById('driver_id_select');
            if (driverImg) { driverImg.src = ''; driverImg.classList.add('hidden'); }
            if (driverPlaceholder) driverPlaceholder.style.display = '';
            if (driverName) driverName.value = '';
            if (driverContact) driverContact.value = '';
            if (driverSelect) driverSelect.selectedIndex = 0;

            const vehicleImg = document.getElementById('vehicleImagePreview');
            const vehiclePlaceholder = document.getElementById('vehicleImagePlaceholder');
            const vehicleName = document.getElementById('vehicle_name_field');
            const vehicleType = document.getElementById('vehicle_type_field');
            const vehicleSelect = document.getElementById('vehicle_id_select');
            if (vehicleImg) { vehicleImg.src = ''; vehicleImg.classList.add('hidden'); }
            if (vehiclePlaceholder) vehiclePlaceholder.style.display = '';
            if (vehicleName) vehicleName.value = '';
            if (vehicleType) vehicleType.value = '';
            if (vehicleSelect) { vehicleSelect.selectedIndex = 0; vehicleSelect.disabled = true; }
        } catch(_) {}
    });
}

// Search and Sort functionality for reservations table
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const sortSelect = document.getElementById('sortSelect');
    const sortAscBtn = document.getElementById('sortAscBtn');
    const sortDescBtn = document.getElementById('sortDescBtn');
    const tableBody = document.getElementById('requestsTableBody');

    let currentSortDirection = 'asc';

    // Initialize search and sort functionality
    if (searchInput && sortSelect && sortAscBtn && sortDescBtn && tableBody) {
        searchInput.addEventListener('input', applySearchAndSort);
        sortSelect.addEventListener('change', applySearchAndSort);
        sortAscBtn.addEventListener('click', function() {
            currentSortDirection = 'asc';
            sortAscBtn.classList.add('bg-blue-500', 'text-white');
            sortDescBtn.classList.remove('bg-blue-500', 'text-white');
            applySearchAndSort();
        });
        sortDescBtn.addEventListener('click', function() {
            currentSortDirection = 'desc';
            sortDescBtn.classList.add('bg-blue-500', 'text-white');
            sortAscBtn.classList.remove('bg-blue-500', 'text-white');
            applySearchAndSort();
        });

        // Initialize sort buttons
        sortAscBtn.classList.add('bg-blue-500', 'text-white');
    }

    function applySearchAndSort() {
        const query = searchInput.value.toLowerCase();
        const sortKey = sortSelect.value;
        const rows = Array.from(tableBody.querySelectorAll('tr'));

        // Filter rows based on search query
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matches = text.includes(query);
            row.style.display = matches ? '' : 'none';
        });

        // Sort rows if sort key is selected
        if (sortKey) {
            const visibleRows = rows.filter(row => row.style.display !== 'none');
            
            visibleRows.sort((a, b) => {
                let aValue, bValue;
                
                switch(sortKey) {
                    case 'requestid':
                        aValue = a.cells[1]?.textContent || '';
                        bValue = b.cells[1]?.textContent || '';
                        break;
                    case 'location':
                        aValue = a.cells[2]?.textContent || '';
                        bValue = b.cells[2]?.textContent || '';
                        break;
                    case 'date':
                        aValue = a.cells[3]?.textContent || '';
                        bValue = b.cells[3]?.textContent || '';
                        break;
                    case 'status':
                        aValue = a.cells[4]?.textContent || '';
                        bValue = b.cells[4]?.textContent || '';
                        break;
                    default:
                        aValue = a.cells[1]?.textContent || '';
                        bValue = b.cells[1]?.textContent || '';
                }

                // Convert to comparable values
                if (sortKey === 'date') {
                    aValue = new Date(aValue);
                    bValue = new Date(bValue);
                } else {
                    aValue = aValue.toString().toLowerCase();
                    bValue = bValue.toString().toLowerCase();
                }

                if (currentSortDirection === 'asc') {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });

            // Reorder rows in the table
            visibleRows.forEach(row => {
                tableBody.appendChild(row);
            });
        }

        // Update row numbers
        updateRowNumbers();
    }

    function updateRowNumbers() {
        const visibleRows = Array.from(tableBody.querySelectorAll('tr')).filter(row => row.style.display !== 'none');
        visibleRows.forEach((row, index) => {
            const numberCell = row.cells[0];
            if (numberCell) {
                numberCell.textContent = index + 1;
            }
        });
    }
});