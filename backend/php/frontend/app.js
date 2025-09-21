(function(){
  const API_BASE = (window.__VELO_API__ && window.__VELO_API__.baseUrl) || 'http://localhost:9000';

  // Map init
  const map = L.map('map', { zoomControl: true }).setView([20.5937, 78.9629], 5);

  // Tile layer (OpenStreetMap). Replace URL with your own tile server if you have local tiles.
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  // Markers layer group
  const markers = L.layerGroup().addTo(map);

  // Simple helpers
  const $ = (sel) => document.querySelector(sel);
  const placeInput = $('#place');
  const latInput = $('#lat');
  const lngInput = $('#lng');
  const saveBtn = $('#saveBtn');
  // Buses UI elements
  const busStateSel = document.getElementById('busState');
  const busOpSel = document.getElementById('busOperator');
  const busTypesDiv = document.getElementById('busTypes');

  // Bus data cache

  // Click to fill lat/lng
  map.on('click', (e) => {
    latInput.value = e.latlng.lat.toFixed(6);
    lngInput.value = e.latlng.lng.toFixed(6);
  });

  // Load existing locations
  async function loadLocations(){
    try {
      const res = await fetch(`${API_BASE}/get_locations.php`);
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Failed to load locations');
      markers.clearLayers();
      data.data.forEach(row => {
        const m = L.marker([row.latitude, row.longitude]);
        const ts = row.timestamp ? new Date(row.timestamp).toLocaleString() : '';
        m.bindPopup(`<strong>${escapeHtml(row.name)}</strong><br>${ts}`);
        m.addTo(markers);
      });
    } catch (err) {
      console.error('loadLocations error:', err);
    }
  }

  // [REDACTED]
  // Buses: fetch, populate, render
  // [REDACTED]
  let busesCache = null;
  async function fetchBusesOnce(){
    if (busesCache) return busesCache;
    const res = await fetch(`${API_BASE}/get_buses.php`);
    const json = await res.json();
    if (!json.success) throw new Error(json.error || 'Failed to load buses');
    busesCache = json;
    return busesCache;
  }

  function uniqueSorted(arr){
    return Array.from(new Set(arr)).sort((a,b)=> String(a).localeCompare(String(b)));
  }

  function populateStates(){
    if (!busesCache || !Array.isArray(busesCache.data)) return;
    const states = uniqueSorted(busesCache.data.map(r => r.state).filter(Boolean));
    busStateSel.innerHTML = '<option value="">All States</option>';
    states.forEach(s => {
      const opt = document.createElement('option');
      opt.value = s; opt.textContent = s; busStateSel.appendChild(opt);
    });
    busOpSel.disabled = false;
    renderBusTypes('', '');
  }

  function populateOperators(state){
    if (!busesCache || !Array.isArray(busesCache.data)) return;
    const filtered = state ? busesCache.data.filter(r => r.state === state) : busesCache.data;
    const operators = uniqueSorted(filtered.map(r => r.operator).filter(Boolean));
    busOpSel.innerHTML = '<option value="">All Operators</option>';
    operators.forEach(op => {
      const opt = document.createElement('option');
      opt.value = op; opt.textContent = op; busOpSel.appendChild(opt);
    });
    busOpSel.disabled = false;
  }

  // Render bus type list based on selected state/operator
  function renderBusTypes(state, operator){
    if (!busesCache || !Array.isArray(busesCache.data)) return;
    let rows = busesCache.data;
    if (state) rows = rows.filter(r => r.state === state);
    if (operator) rows = rows.filter(r => r.operator === operator);

    const types = uniqueSorted(rows.map(r => r.category || r.type).filter(Boolean));
    if (!types.length) {
      busTypesDiv.innerHTML = '<div class="empty">No buses available</div>';
      return;
    }
    const ul = document.createElement('ul');
    types.forEach(t => {
      const li = document.createElement('li');
      li.textContent = t;
      ul.appendChild(li);
    });
    busTypesDiv.innerHTML = '';
    busTypesDiv.appendChild(ul);
  }

  // Dropdown interactions
  if (busStateSel) {
    busStateSel.addEventListener('change', () => {
      const state = busStateSel.value;
      populateOperators(state);
      renderBusTypes(state, '');
      if (busOpSel) busOpSel.value = '';
    });
  }

  if (busOpSel) {
    busOpSel.addEventListener('change', () => {
      const state = busStateSel ? busStateSel.value : '';
      const operator = busOpSel.value;
      renderBusTypes(state, operator);
    });
  }
  async function saveLocation(){
    try {
      const name = (placeInput.value || '').trim();
      const latitude = parseFloat(latInput.value);
      const longitude = parseFloat(lngInput.value);
      if (!name || Number.isNaN(latitude) || Number.isNaN(longitude)) {
        alert('Please enter a valid name, latitude and longitude.');
        return;
      }
      saveBtn.disabled = true;
      const res = await fetch(`${API_BASE}/save_location.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, latitude, longitude })
      });
      const data = await res.json();
      if (!data.success) throw new Error(data.error || 'Save failed');
      // Add marker immediately
      const m = L.marker([latitude, longitude]).addTo(markers).bindPopup(`<strong>${escapeHtml(name)}</strong>`);
      m.openPopup();
      placeInput.value = '';
      await loadLocations();
    } catch (err) {
      console.error('saveLocation error:', err);
      alert('Save failed: ' + err.message);
    } finally {
      saveBtn.disabled = false;
    }
  }

  // Utility: basic HTML escaping
  function escapeHtml(str){
    return String(str).replace(/[&<>"]+/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[s]));
  }

  // --- Routing demo with Leaflet Routing Machine ---
  let routeControl = null;
  const modeButtons = document.querySelectorAll('.mode-row button');
  let currentMode = 'car';

  function setActiveMode(mode){
    currentMode = mode;
    modeButtons.forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
    updateRouting();
  }

  modeButtons.forEach(b => {
    b.addEventListener('click', () => setActiveMode(b.dataset.mode));
  });

  function getLineStyle(mode){
    switch(mode){
      case 'car': return { color: '#2563eb', weight: 5 };
      case 'bike': return { color: '#10b981', weight: 5, dashArray: '6,6' };
      case 'bus': return { color: '#f59e0b', weight: 5 };
      case 'train': return { color: '#ef4444', weight: 5, dashArray: '2,8' };
      default: return { color: '#2563eb', weight: 5 };
    }
  }

  function getOsrmProfile(mode){
    switch(mode){
      case 'car': return 'driving';
      case 'bike': return 'cycling';
      default: return 'foot'; // fallback for bus/train
    }
  }

  function updateRouting(){
    if (routeControl) {
      try { map.removeControl(routeControl); } catch(_){}
      routeControl = null;
    }
    routeControl = L.Routing.control({
      waypoints: [
        L.latLng(28.6139, 77.2090), // New Delhi
        L.latLng(19.0760, 72.8777)  // Mumbai
      ],
      draggableWaypoints: true,
      addWaypoints: true,
      routeWhileDragging: true,
      lineOptions: getLineStyle(currentMode),
      router: L.Routing.osrmv1({
        serviceUrl: `https://router.project-osrm.org/route/v1`,
        profile: getOsrmProfile(currentMode)
      })
    });
    routeControl.addTo(map);
  }

  function [REDACTED](){
    const busRoute = L.polyline([
      [26.9124, 75.7873], // Jaipur
      [27.1767, 78.0081]  // Agra
    ], getLineStyle('bus')).addTo(map);
    busRoute.bindPopup('Bus Route: Jaipur → Agra (fake schedule: every 30m)');

    const trainRoute = L.polyline([
      [13.0827, 80.2707], // Chennai
      [17.3850, 78.4867]  // Hyderabad
    ], getLineStyle('train')).addTo(map);
    trainRoute.bindPopup('Train Route: Chennai → Hyderabad (fake schedule: every 2h)');
  }

  (async function init(){
    await loadLocations();
    try {
      await fetchBusesOnce();
      populateStates();
      // Operators list for initial (all states)
      populateOperators('');
    } catch (e) {
      console.warn('Buses load failed:', e);
      if (busTypesDiv) busTypesDiv.innerHTML = '<div class="empty">No buses available</div>';
    }
    updateRouting();
    [REDACTED]();
  })();
})();
