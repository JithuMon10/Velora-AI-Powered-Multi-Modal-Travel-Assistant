console.log("Velora transit.js loaded");

// Register service worker for offline capability
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('./velora-sw.js').then(reg => {
    console.log('Velora SW registered:', reg.scope);
  }).catch(err => {
    console.warn('Velora SW registration failed:', err);
  });
}

(function(){
  // Safe config
  const API_BASE = (window.VELORA_CONFIG && window.VELORA_CONFIG.API_BASE) || (window.__VELO_API__ && window.__VELO_API__.baseUrl) || '';

  // LocalStorage helpers for recent searches
  const RECENT_KEY = [REDACTED];
  function getRecentSearches(){
    try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]'); } catch(_){ return []; }
  }
  function saveRecentSearch(origin, dest){
    try {
      let recent = getRecentSearches();
      const entry = { origin, dest, timestamp: Date.now() };
      recent = recent.filter(r=> !(r.origin===origin && r.dest===dest));
      recent.unshift(entry);
      recent = recent.slice(0, 5);
      localStorage.setItem(RECENT_KEY, JSON.stringify(recent));
    } catch(_){ }
  }
  function clearRecentSearches(){
    try { localStorage.removeItem(RECENT_KEY); } catch(_){ }
  }

  // Utils
  function escapeHtml(str){ return String(str||'').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[s])); }

  async function [REDACTED](url, ms){
    const ctrl = new AbortController();
    const t = setTimeout(()=> ctrl.abort(), ms||15000);
    try {
      const r = await fetch(url, { signal: ctrl.signal });
      const txt = await r.text();
      if (!r.ok) throw new Error(`HTTP ${r.status}: ${txt.slice(0,160)}`);
      try { return JSON.parse(txt); } catch(e){ throw new Error('Non-JSON response'); }
    } finally { clearTimeout(t); }
  }

  // Haversine distance in meters
  function distM(aLat,aLon,bLat,bLon){
    const R=6371000; const dLat=(bLat-aLat)*Math.PI/180; const dLon=(bLon-aLon)*Math.PI/180;
    const la1=aLat*Math.PI/180, la2=bLat*Math.PI/180;
    const x=Math.sin(dLat/2)**2 + Math.cos(la1)*Math.cos(la2)*Math.sin(dLon/2)**2;
    return 2*R*Math.atan2(Math.sqrt(x), Math.sqrt(1-x));
  }

  // Voice with mute control
  let voiceMuted = false;
  function speakInstruction(step, prefix=''){
    if (voiceMuted) return;
    try{
      const text = prefix ? `${prefix} ${step.text || ''}` : (step.text || '');
      const utter = new [REDACTED](text);
      utter.lang = 'en-IN'; utter.rate = 1.0; utter.pitch = 1.0;
      speechSynthesis.speak(utter);
    }catch(_){ }
  }
  function toggleVoice(){
    voiceMuted = !voiceMuted;
    const btn = document.getElementById('muteBtn');
    if (btn) btn.textContent = voiceMuted ? '🔇 Unmute' : '🔊 Mute';
  }

  // Render instruction steps list
  function renderInstructions(steps){
    const panel = document.getElementById('instructionsList');
    if (!panel) return;
    const emoji = (m)=>{
      const mm=String(m||'').toLowerCase();
      if (mm==='walk') return '🚶';
      if (mm==='bus') return '🚌';
      if (mm==='drive' || mm==='car' || mm==='taxi') return '➤';
      if (mm==='alight') return '📍';
      return '•';
    };
    panel.innerHTML = (steps && steps.length) ? `${(steps||[]).map((s,i)=>{
      const id=`step-${i}`; return `<div class="step" id="${id}"><span class="icon">${emoji(s.mode)}</span> ${escapeHtml(s.text||'')}</div>`;
    }).join('')}` : '<div class="muted">No directions yet.</div>';
  }

  // Voice navigation state with Google Maps-style announcements
let voiceEnabled = false;
let lastAnnouncedStep = null;
const voiceQueue = [];
let speechSynthesis = window.speechSynthesis;
let currentUtterance = null;
let etaTimer = null;

// Google Maps-style voice announcement with distance preview
function announceStep(step, distanceMeters) {
  if (!voiceEnabled || !speechSynthesis) return;
  
  // Cancel current speech
  if (currentUtterance) {
    speechSynthesis.cancel();
  }
  
  let text = '';
  const mode = (step.mode || '').toLowerCase();
  
  // Distance-based preview (like Google Maps)
  if (distanceMeters && distanceMeters > 50) {
    if (distanceMeters < 200) {
      text = `In ${Math.round(distanceMeters)} meters, `;
    } else if (distanceMeters < 1000) {
      text = `In ${Math.round(distanceMeters / 100) * 100} meters, `;
    } else {
      text = `In ${(distanceMeters / 1000).toFixed(1)} kilometers, `;
    }
  }
  
  // Mode-specific announcements
  if (mode === 'bus') {
    text += step.text || 'board the bus';
  } else if (mode === 'train') {
    text += step.text || 'board the train';
  } else if (mode === 'flight') {
    text += step.text || 'proceed to boarding gate';
  } else if (mode === 'taxi' || mode === 'car') {
    text += step.text || 'continue driving';
  } else if (mode === 'walk') {
    text += step.text || 'walk to next stop';
  } else if (mode === 'alight') {
    text += step.text || 'prepare to alight';
  } else {
    text += step.text || 'continue';
  }
  
  currentUtterance = new [REDACTED](text);
  currentUtterance.rate = 1.0;
  currentUtterance.pitch = 1.0;
  currentUtterance.volume = 1.0;
  
  speechSynthesis.speak(currentUtterance);
  console.log('[Voice] Announced:', text);
}

// Announce ETA updates
function announceETA(minutesRemaining) {
  if (!voiceEnabled || !speechSynthesis) return;
  
  let text = '';
  if (minutesRemaining < 2) {
    text = 'You will arrive in less than 2 minutes';
  } else if (minutesRemaining < 60) {
    text = `You will arrive in ${minutesRemaining} minutes`;
  } else {
    const hours = Math.floor(minutesRemaining / 60);
    const mins = minutesRemaining % 60;
    text = `You will arrive in ${hours} hour${hours > 1 ? 's' : ''}${mins > 0 ? ` and ${mins} minutes` : ''}`;
  }
  
  const utterance = new [REDACTED](text);
  utterance.rate = 1.0;
  speechSynthesis.speak(utterance);
  console.log('[Voice] ETA:', text);
}
  function stopNavigation(){ 
    try{ 
      if(navTimer){ clearInterval(navTimer); navTimer=null; } 
      if(etaTimer){ clearInterval(etaTimer); etaTimer=null; }
      if(navMarker){ map.removeLayer(navMarker); navMarker=null; } 
      navAnnounced.clear();
      // Clear step highlights
      document.querySelectorAll('.step').forEach(el => { el.style.background = ''; el.style.fontWeight = ''; });
    }catch(_){}
  }

  // Enhanced navigation with distance-based announcements and live step tracking
  let currentStepIdx = 0;
  let baseETA = null;
  let trafficMultiplier = 1.0;
  
  // Simulate traffic-based ETA adjustments (like Google Maps)
  function updateTrafficETA() {
    if (!baseETA) return;
    
    const hour = new Date().getHours();
    const isPeakHour = (hour >= 7 && hour <= 10) || (hour >= 17 && hour <= 20);
    
    // Random traffic variation: 5-15% delay during peak, 0-5% off-peak
    const baseDelay = isPeakHour ? 0.05 + Math.random() * 0.10 : Math.random() * 0.05;
    trafficMultiplier = 1.0 + baseDelay;
    
    const adjustedMinutes = Math.round(baseETA * trafficMultiplier);
    
    // Update ETA display
    const etaDisplay = document.getElementById('etaDisplay');
    if (etaDisplay) {
      const hours = Math.floor(adjustedMinutes / 60);
      const mins = adjustedMinutes % 60;
      const timeStr = hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
      const trafficColor = isPeakHour ? '#ff6b6b' : '#51cf66';
      etaDisplay.innerHTML = `ETA: <span style="color:${trafficColor}">${timeStr}</span> ${isPeakHour ? '🔴' : '🟢'}`;
    }
    
    console.log(`[Traffic] ETA updated: ${adjustedMinutes}min (${Math.round(baseDelay*100)}% delay, peak=${isPeakHour})`);
  }
  function startNavigation(coords, steps){
    stopNavigation(); if (!Array.isArray(coords) || coords.length<2) return;
    navMarker = L.marker(coords[0], { title:'Navigation', icon: L.divIcon({className:'nav-marker', html:'<div style="background:#6366f1;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,0.3);"></div>'}) }).addTo(map);
    let idx = 0;
    currentStepIdx = 0;
    
    // Announce first step
    if (steps && steps[0]) {
      speakInstruction(steps[0]);
      highlightStep(0);
    }
    
    navTimer = setInterval(()=>{
      idx = Math.min(idx+1, coords.length-1);
      navMarker.setLatLng(coords[idx]);
      const [clat, clon] = coords[idx];
      
      // Check all upcoming steps for distance-based announcements
      (steps||[]).forEach((st, sidx)=>{
        const key = st.step_id || String(sidx);
        if (navAnnounced.has(key)) return;
        
        const thr = Math.max(10, +st.notify_when_m || 50);
        const d = distM(clat, clon, +st.lat || clat, +st.lon || clon);
        
        // Announce at 100m before step
        if (d <= 100 && d > thr && !navAnnounced.has(key+'-preview')) {
          speakInstruction(st, 'In 100 meters,');
          navAnnounced.add(key+'-preview');
        }
        
        // Announce when reached
        if (d <= thr){
          highlightStep(sidx);
          speakInstruction(st);
          navAnnounced.add(key);
          currentStepIdx = sidx;
          
          // Check if this is the last step
          if (sidx === steps.length - 1) {
            setTimeout(()=> speakInstruction({text: "You've arrived at your destination."}), 1000);
          }
        }
      });
      
      // Smart re-routing: check if drifted >150m from current step
      if (steps && steps[currentStepIdx]) {
        const currStep = steps[currentStepIdx];
        const driftDist = distM(clat, clon, +currStep.lat || clat, +currStep.lon || clon);
        if (driftDist > 150 && !navAnnounced.has('drift-'+currentStepIdx)) {
          speakInstruction({text: 'Recalculating route...'});
          navAnnounced.add('drift-'+currentStepIdx);
          // In production, trigger actual re-route API call here
        }
      }
      
      if (idx === coords.length-1) { 
        stopNavigation();
        speakInstruction({text: "You've arrived at your destination."});
      }
    }, 500);
    
    // Start ETA countdown timer (updates every 30s)
    updateETA(steps);
    etaTimer = setInterval(()=> updateETA(steps), 30000);
  }
  
  function updateETA(steps){
    if (!steps || !steps.length) return;
    const totalDuration = steps.reduce((sum, st)=> sum + (parseInt(st.duration_s) || 0), 0);
    const now = new Date();
    const eta = new Date(now.getTime() + totalDuration * 1000);
    const etaText = document.getElementById('etaText');
    if (etaText) {
      const timeStr = eta.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit'});
      etaText.textContent = `ETA: ${timeStr}`;
    }
    // Update progress
    const progressText = document.getElementById('progressText');
    if (progressText && steps.length) {
      const completed = currentStepIdx;
      const pct = Math.round((completed / steps.length) * 100);
      progressText.textContent = `Progress: ${pct}%`;
    }
  }
  
  function highlightStep(idx){
    // Clear previous highlights
    document.querySelectorAll('.step').forEach(el => el.style.background = '');
    // Highlight current
    const el = document.getElementById('step-'+idx);
    if (el) {
      el.style.background = 'linear-gradient(90deg, #fef3c7, #fef9c3)';
      el.style.fontWeight = '600';
      el.scrollIntoView({block:'nearest', behavior:'smooth'});
    }
  }

  async function geocode(query){
    const q = (query||'').trim(); if (!q) return [];
    try {
      const res = await fetch(`${API_BASE}/geocode.php?q=${encodeURIComponent(q)}&limit=5`);
      const txt = await res.text(); if (!res.ok) throw new Error(txt);
      const data = JSON.parse(txt);
      if (Array.isArray(data)) return data.map(it=>({ name: it.name || (it.display_name||'').split(',').slice(0,3).join(',').trim(), lat: parseFloat(it.lat||it.latitude), lon: parseFloat(it.lon||it.longitude||it.lng), source:'PHP' })).filter(it=> isFinite(it.lat)&&isFinite(it.lon));
    } catch(_){}
    try {
      const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=5&addressdetails=1`);
      const data = await res.json();
      if (Array.isArray(data)) return data.map(it=>({ name: (it.display_name||'').split(',').slice(0,3).join(',').trim(), lat: parseFloat(it.lat), lon: parseFloat(it.lon), source:'Nominatim' }));
    } catch(_){}
    return [];
  }

  function initAutocomplete(inputEl, listEl, onPick){
    let items = []; let active = -1; let deb = null;
    function close(){ listEl.style.display='none'; listEl.innerHTML=''; active=-1; }
    function open(){ listEl.style.display='block'; }
    function render(){
      listEl.innerHTML = items.map((it,i)=> `<div class="ac-item${i===active?' active':''}" data-idx="${i}"><div class="name">${escapeHtml(it.name||it.stop_name||'')}</div><div class="city">${escapeHtml(it.city||it.source||'')}</div></div>`).join('');
      open();
    }
    async function filter(q){
      const val = q.trim(); if (!val){ close(); return; }
      let geo=[]; try{ geo = await geocode(val);}catch(_){ geo=[]; }
      items = geo; active=-1; items.length?render():close();
    }
    inputEl.addEventListener('input', ()=>{ if (deb) clearTimeout(deb); deb = setTimeout(()=> filter(inputEl.value), 250); });
    inputEl.addEventListener('keydown', (e)=>{
      if (listEl.style.display==='none') return;
      if (e.key==='ArrowDown'){ active=Math.min(active+1, items.length-1); render(); e.preventDefault(); }
      else if (e.key==='ArrowUp'){ active=Math.max(active-1, 0); render(); e.preventDefault(); }
      else if (e.key==='Enter'){ if (active>=0){ onPick(items[active]); close(); e.preventDefault(); } }
      else if (e.key==='Escape'){ close(); }
    });
    listEl.addEventListener('mousedown', (e)=>{ const el=e.target.closest('.ac-item'); if(!el) return; const idx=+el.getAttribute('data-idx'); if(!isNaN(idx)&&items[idx]){ onPick(items[idx]); close(); } });
    document.addEventListener('click', (e)=>{ if (!listEl.contains(e.target) && e.target!==inputEl) close(); });
  }

  document.addEventListener('DOMContentLoaded', function(){
    // Initializing UI fixes
    
    // FIX 1: Make radio button chips clickable
    document.querySelectorAll('.chip').forEach(chip => {
      chip.style.cursor = 'pointer';
      chip.addEventListener('click', function(e) {
        // Don't trigger if clicking the input directly
        if (e.target.matches('input[type="radio"]')) return;
        
        const radio = this.querySelector('input[type="radio"]');
        if (radio) {
          radio.checked = true;
          radio.dispatchEvent(new Event('change', { bubbles: true }));
          
          // Update visual state for this group
          const groupName = radio.getAttribute('name');
          document.querySelectorAll(`input[name="${groupName}"]`).forEach(r => {
            r.closest('.chip')?.classList.remove('active');
          });
          this.classList.add('active');
          
          console.log('[Velora] Selected:', radio.value);
        }
      });
    });
    
    // Initialize active states for checked radios
    document.querySelectorAll('input[type="radio"]:checked').forEach(radio => {
      radio.closest('.chip')?.classList.add('active');
    });
    
    // Test buttons removed for cleaner UI
    
    // Helper function for inline notifications
    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = 'velora-notification';
      notification.style.cssText = `
        position: fixed;
        top: 80px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        padding: 16px 24px;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        z-index: 10000;
        font-weight: 500;
        max-width: 400px;
        animation: slideInRight 0.3s ease-out;
      `;
      notification.textContent = message;
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-out';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }
    
    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideInRight {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
      }
    `;
    document.head.appendChild(style);
    
    // UI fixes applied
    
    // Elements
    const originInput = document.getElementById('originInput');
    const destInput = document.getElementById('destInput');
    const originList = document.getElementById('originList');
    const destList = document.getElementById('destList');
    const planBtn = document.getElementById('planBtn');
    const arriveByInput = document.getElementById('arriveBy');
    const resultsDiv = document.getElementById('results');
    const formMsg = document.getElementById('formMsg');
    const hotelsAsk = document.getElementById('hotelsAsk');
    const modeVelora = document.getElementById('modeVelora');
    const modeDrive = document.getElementById('modeDrive');
    const chipVelora = document.getElementById('chipVelora');
    const chipDrive = document.getElementById('chipDrive');
    const vehicleGroup = document.getElementById('vehicleGroup');

    // Map init (must not fail)
    const map = L.map('map').setView([20.5937, 78.9629], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
    let routeLayer = null; let activeLayers = [];

    function clearRoute(){
      try{ if (routeLayer){ map.removeLayer(routeLayer); routeLayer=null; } }catch(_){ }
      try{ if (activeLayers && activeLayers.length){ for (const l of activeLayers){ try{ map.removeLayer(l);}catch(_){ } } activeLayers=[]; } }catch(_){ }
    }

    function renderResults({message, results}){
      if (message){ resultsDiv.innerHTML = `<div class="muted">${escapeHtml(message)}</div>`; return; }
      if (!Array.isArray(results)||!results.length){ resultsDiv.innerHTML = '<div class="muted">No results.</div>'; return; }
      resultsDiv.innerHTML = results.map(r=> `<div class="trip"><div><strong>${escapeHtml(r.operator_name||'')}</strong> — ${escapeHtml(r.operator_type||'')}</div></div>`).join('');
    }

    async function drawSegments(segments, route_poly){
      console.log('[drawSegments] Called with:', segments?.length, 'segments');
      
      // Clear existing route
      try{ if (routeLayer){ map.removeLayer(routeLayer); routeLayer=null; } }catch(_){ }
      
      if (!Array.isArray(segments) || segments.length === 0) {
        console.warn('[drawSegments] No segments to draw');
        return;
      }
      
      const routeGroup = L.layerGroup();
      const allBounds = [];
      
      segments.forEach((seg, idx) => {
        const mode = (seg.mode || 'walk').toLowerCase();
        let trafficSev = seg.traffic_severity || 'low';
        
        // Only apply traffic colors to specific segments, not all
        // Use segment-specific traffic data if available
        const currentHour = new Date().getHours();
        const isRushHour = (currentHour >= 7 && currentHour <= 10) || (currentHour >= 17 && currentHour <= 20);
        const isLunchHour = currentHour >= 12 && currentHour <= 14;
        
        // Apply traffic only to major routes (simulate real traffic)
        if ((mode === 'bus' || mode === 'taxi' || mode === 'car' || mode === 'drive')) {
          // Only 30% of segments get high traffic during rush hour (realistic)
          const segmentHash = idx % 3;
          if (isRushHour && segmentHash === 0) {
            trafficSev = 'high';
          } else if (isLunchHour && segmentHash === 1) {
            trafficSev = 'medium';
          }
          // Otherwise keep original traffic severity
        }
        
        console.log(`[drawSegments] Segment ${idx}: mode="${mode}", traffic="${trafficSev}"`);
        
        // Color based on mode AND traffic
        let color = '#4285f4'; // Default blue
        
        // For bus, taxi, car - use traffic colors
        if (mode === 'bus' || mode === 'taxi' || mode === 'car' || mode === 'drive') {
          if (trafficSev === 'high') {
            color = '#ea4335'; // Google Maps red
            console.log(`[Traffic] Segment ${idx}: HIGH traffic - RED`);
          } else if (trafficSev === 'medium') {
            color = '#fbbc04'; // Google Maps orange/yellow
            console.log(`[Traffic] Segment ${idx}: MEDIUM traffic - ORANGE`);
          } else {
            color = '#4285f4'; // Blue for low/normal
            console.log(`[Traffic] Segment ${idx}: LOW traffic - BLUE`);
          }
        } else if (mode === 'train') {
          color = '#34a853'; // Green for train
        } else if (mode === 'walk') {
          color = '#9aa0a6'; // Gray for walking
        }
        
        // Get coordinates for this segment
        let coords = [];
        if (Array.isArray(seg.polyline) && seg.polyline.length >= 2) {
          coords = seg.polyline.map(p => Array.isArray(p) ? [p[0], p[1]] : [p.lat, p.lng]);
        } else if (seg.from_lat != null && seg.to_lat != null) {
          coords = [[+seg.from_lat, +seg.from_lon], [+seg.to_lat, +seg.to_lon]];
        }
        
        if (coords.length >= 2) {
          console.log(`[drawSegments] Drawing ${mode} segment with ${coords.length} points, color: ${color}`);
          
          const polyline = L.polyline(coords, {
            color: color,
            weight: 6,
            opacity: 0.85,
            smoothFactor: 1.0,
            lineCap: 'round',
            lineJoin: 'round'
          });
          
          // Add popup with segment info
          const popupContent = `
            <div style="font-family: Arial, sans-serif; font-size: 13px;">
              <strong style="font-size: 14px;">${mode.toUpperCase()}</strong><br>
              <span style="color: #5f6368;">${escapeHtml(seg.from || '')} → ${escapeHtml(seg.to || '')}</span><br>
              <span style="color: #1a73e8;">${seg.distance_km || 0} km</span>
              ${seg.traffic_severity ? `<br><span style="color: ${color};">🚦 Traffic: ${seg.traffic_severity}</span>` : ''}
            </div>
          `;
          polyline.bindPopup(popupContent);
          
          polyline.addTo(routeGroup);
          coords.forEach(c => allBounds.push(c));
        }
      });
      
      // Add markers for each stop
      segments.forEach((seg, idx) => {
        // Add start marker (only for first segment)
        if (idx === 0 && seg.from_lat && seg.from_lon) {
          L.marker([seg.from_lat, seg.from_lon], {
            icon: L.divIcon({
              className: 'custom-marker',
              html: '<div style="background: #4285f4; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">A</div>',
              iconSize: [24, 24]
            })
          }).bindPopup(`<strong>Start:</strong> ${escapeHtml(seg.from || 'Origin')}`).addTo(routeGroup);
        }
        
        // Add end marker for each segment
        if (seg.to_lat && seg.to_lon) {
          const isLastSegment = idx === segments.length - 1;
          const markerColor = isLastSegment ? '#ea4335' : '#34a853'; // Red for end, green for stops
          const markerLabel = isLastSegment ? 'B' : (idx + 1);
          
          L.marker([seg.to_lat, seg.to_lon], {
            icon: L.divIcon({
              className: 'custom-marker',
              html: `<div style="background: ${markerColor}; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">${markerLabel}</div>`,
              iconSize: [24, 24]
            })
          }).bindPopup(`<strong>${isLastSegment ? 'End' : 'Stop ' + (idx + 1)}:</strong> ${escapeHtml(seg.to || 'Destination')}`).addTo(routeGroup);
        }
      });
      
      routeGroup.addTo(map);
      routeLayer = routeGroup;
      
      console.log('[drawSegments] Added route group to map, total bounds:', allBounds.length);
      console.log('[drawSegments] Route layer:', routeLayer);
      
      // Fit map to show entire route
      if (allBounds.length >= 2) {
        try {
          const bounds = L.latLngBounds(allBounds);
          map.fitBounds(bounds, { padding: [50, 50] });
          console.log('[drawSegments] Map fitted to bounds');
        } catch(e) {
          console.error('[drawSegments] Error fitting bounds:', e);
        }
      } else {
        console.warn('[drawSegments] Not enough bounds to fit map:', allBounds.length);
      }
    }

    function modeColor(mode){
      const m = String(mode||'').toLowerCase();
      if (m==='bus') return '#2563eb';
      if (m==='train') return '#7c3aed';
      if (m==='flight' || m==='plane') return '#f59e0b';
      if (m==='car' || m==='taxi') return '#1e88e5';
      if (m==='walk') return '#10b981';
      return '#0ea5e9';
    }

    function iconAndLabelFor(mode, operator_name){
      const m = String(mode||'').toLowerCase();
      if (m==='bus') return { icon:'🚌', label:`Bus${operator_name? ' — '+operator_name: ''}` };
      if (m==='train') return { icon:'🚆', label:`Train${operator_name? ' — '+operator_name: ''}` };
      if (m==='flight' || m==='plane') return { icon:'✈️', label:`Flight${operator_name? ' — '+operator_name: ''}` };
      if (m==='car'){
        const op = String(operator_name||'').toLowerCase();
        if (op==='taxi') return { icon:'🚕', label:'Taxi' };
        if (op==='self-drive' || op==='self drive') return { icon:'🚗', label:'Self-drive' };
        return { icon:'🚗', label:'Car' };
      }
      return { icon:'•', label:(mode||'') };
    }

    function renderItinerary(resp){
      try{
        const legs = Array.isArray(resp?.legs) ? resp.legs : (Array.isArray(resp?.data?.legs) ? resp.data.legs : (Array.isArray(resp?.segments) ? resp.segments : (Array.isArray(resp?.data?.segments) ? resp.data.segments : [])));
        const decision = (resp && (resp.decision || (resp.data && resp.data.decision))) || '';
        const totalTime = (resp && (resp.total_time || (resp.data && resp.data.total_time))) || '-';
        const totalFare = (resp && (resp.total_fare || (resp.data && resp.data.total_fare))) || 0;
        
        // Update summary bar
        const summarySection = document.getElementById('summarySection');
        const totalTimeEl = document.getElementById('totalTime');
        const totalFareEl = document.getElementById('totalFare');
        const decisionLabelEl = document.getElementById('decisionLabel');
        if (summarySection && totalTimeEl && totalFareEl && decisionLabelEl) {
          summarySection.style.display = '';
          totalTimeEl.textContent = totalTime;
          totalFareEl.textContent = '₹' + totalFare;
          
          // Voice announcement with smart time suggestion
          if (window.Vela && window.userArrivalTime) {
            const arrivalTime = window.userArrivalTime.time;
            const leaveByTime = new Date(new Date(window.userArrivalTime.date + ' ' + arrivalTime).getTime() - (parseInt(totalTime) * 60000));
            const leaveByStr = leaveByTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            const bestMode = decision || 'this option';
            window.Vela.speak(`To reach ${dText} by ${arrivalTime}, you should leave by ${leaveByStr}. The best option is ${bestMode} because it costs only ${totalFare} rupees.`);
            
            // Ask about hotels after a delay
            setTimeout(() => {
              if (window.Vela) {
                window.Vela.speak(`Would you like to see hotels near ${dText}?`);
              }
            }, 5000);
          } else if (window.Vela) {
            const bestMode = decision || 'this option';
            window.Vela.speak(`Route found! The best option is ${bestMode}. Total time: ${totalTime}. Fare: ${totalFare} rupees.`);
            
            // Ask about hotels after a delay
            setTimeout(() => {
              if (window.Vela) {
                window.Vela.speak(`Would you like to see hotels near ${dText}?`);
              }
            }, 5000);
          }
          
          const d = String(decision).toLowerCase();
          const label = d==='bus_chain' ? '🚌 BUS' : d==='train' ? '🚆 TRAIN' : d==='flight' ? '✈️ FLIGHT' : (d==='drive'?'🚗 CAR': d.toUpperCase());
          decisionLabelEl.textContent = label;
        }

        let header = '';
        if (decision){
          const d = String(decision).toLowerCase();
          const label = d==='bus_chain' ? '🚌 BUS ROUTE' : d==='train' ? '🚆 TRAIN ROUTE' : d==='flight' ? '✈️ FLIGHT ROUTE' : (d==='drive'?'🚗 DRIVE ROUTE': d.toUpperCase());
          header = `<div class="trip" style="border-color:#c7d2fe;background:linear-gradient(135deg,#eef2ff,#faf5ff);border-width:2px;"><div class="header"><strong>Velora decided:</strong> ${label}</div><div class="muted">Best option based on time, cost, and availability</div></div>`;
        }
        const items = (legs||[]).map((s,idx)=>{
          const { icon, label } = iconAndLabelFor(s.mode, s.operator_name || s.operator);
          const fromName = (s.from_stop && s.from_stop.name) ? s.from_stop.name : (s.from || '');
          const toName = (s.to_stop && s.to_stop.name) ? s.to_stop.name : (s.to || '');
          const fareStr = s.fare ? `<span class="fare-badge">₹${s.fare}</span>` : '';
          const distStr = s.distance_km ? `${s.distance_km} km` : '';
          const durStr = s.duration_s ? `${Math.round(s.duration_s/60)} min` : '';
          
          // Bus/Train/Flight details
          let detailsHtml = '';
          const mode = String(s.mode || '').toLowerCase();
          if (mode === 'bus' && (s.bus_type || s.operator)) {
            detailsHtml = `<div class="muted" style="margin-top:4px;">🚌 ${escapeHtml(s.bus_type || '')} • ${escapeHtml(s.operator || '')}</div>`;
          } else if (mode === 'train' && s.train_number) {
            detailsHtml = `<div class="muted" style="margin-top:4px;">🚂 ${escapeHtml(String(s.train_number))} ${escapeHtml(s.train_name || '')} • ${escapeHtml(s.class || 'Sleeper')}</div>`;
          } else if (mode === 'flight' && s.flight_number) {
            detailsHtml = `<div class="muted" style="margin-top:4px;">✈️ ${escapeHtml(s.operator_name || '')} ${escapeHtml(s.flight_number || '')}</div>`;
          }
          
          const stopsHtml = Array.isArray(s.intermediate_stops) && s.intermediate_stops.length
            ? `<div class="muted" style="margin-top:8px;">Via: ${s.intermediate_stops.map(st=> escapeHtml(st.name||'')).join(' • ')}</div>`
            : '';
          return `
            <div class="trip">
              <div class="header"><span class="icon">${icon}</span> <strong>${escapeHtml(label)}</strong> ${fareStr}</div>
              ${detailsHtml}
              <div class="route-info">${escapeHtml(String(fromName))} → ${escapeHtml(String(toName))}</div>
              <div class="time-info">
                ${s.departure ? `<span>🕐 Departs: ${escapeHtml(String(s.departure))}</span>` : ''}
                ${s.arrival ? `<span>🕑 Arrives: ${escapeHtml(String(s.arrival))}</span>` : ''}
                ${distStr ? `<span>📏 ${distStr}</span>` : ''}
                ${durStr ? `<span>⏱️ ${durStr}</span>` : ''}
              </div>
              ${stopsHtml}
            </div>`;
        });
        resultsDiv.innerHTML = (header + items.join('')) || '<div class="muted">No itinerary.</div>';
        
        // Add booking buttons to bus/train segments
        setTimeout(()=>{
          document.querySelectorAll('.trip').forEach((tripEl,idx)=>{
            const leg = legs[idx-1]; // -1 because first trip is decision card
            if (!leg) return;
            const mode = String(leg.mode||'').toLowerCase();
            const operator = String(leg.operator_name||leg.operator||'').toLowerCase();
            let bookUrl = null;
            if (mode==='bus' && operator.includes('ksrtc')) bookUrl = 'https://online.keralartc.com/';
            else if (mode==='train') bookUrl = 'https://www.irctc.co.in/';
            if (bookUrl) {
              const btn = document.createElement('a');
              btn.href = bookUrl;
              btn.target = '_blank';
              btn.className = 'action-btn';
              btn.style.cssText = 'display:inline-block; margin-top:10px; padding:8px 14px; font-size:13px; text-decoration:none;';
              btn.textContent = mode==='bus' ? '🎫 Book on KSRTC' : '🎫 Book on IRCTC';
              tripEl.appendChild(btn);
            }
          });
        }, 100);
      } catch(e){ resultsDiv.innerHTML = '<div class="muted">Failed to render itinerary.</div>'; console.error(e); }
    }

    function [REDACTED](resp){
      const legs = (resp && Array.isArray(resp.legs)) ? resp.legs : (resp && resp.data && Array.isArray(resp.data.legs) ? resp.data.legs : []);
      if (!legs.length) return;
      try{ if (routeLayer){ map.removeLayer(routeLayer); } }catch(_){ }
      const group = L.featureGroup().addTo(map);
      const boundsPts = [];
      legs.forEach(leg=>{
        let color = modeColor(leg.mode);
        // Apply traffic-based coloring for bus/drive segments
        if ((leg.mode === 'bus' || leg.mode === 'drive' || leg.mode === 'car') && leg.traffic_severity) {
          const sev = String(leg.traffic_severity).toLowerCase();
          if (sev === 'high') color = '#ef4444'; // red
          else if (sev === 'medium') color = '#f59e0b'; // orange
          else if (sev === 'low') color = '#fbbf24'; // yellow
        }
        if (Array.isArray(leg.polyline) && leg.polyline.length>=2){
          const pts = leg.polyline.map(p=> Array.isArray(p)? [p[0],p[1]] : [p.lat,p.lng]);
          L.polyline(pts, { color, weight:6, opacity:0.85 }).addTo(group);
          pts.forEach(pt=> boundsPts.push(pt));
        }
        const fl = parseFloat(leg.from_lat), flo = parseFloat(leg.from_lon);
        if (isFinite(fl) && isFinite(flo)){ 
          const icon = leg.mode === 'bus' ? '🚌' : (leg.mode === 'walk' ? '🚶' : '📍');
          L.marker([fl,flo], { 
            title:String(leg.from||''),
            icon: L.divIcon({className:'stop-marker', html:`<div style="background:#fff;padding:4px 8px;border-radius:8px;border:2px solid ${color};font-size:16px;box-shadow:0 2px 8px rgba(0,0,0,0.2);">${icon}</div>`})
          }).addTo(group); 
          boundsPts.push([fl,flo]); 
        }
        const tl = parseFloat(leg.to_lat), tlo = parseFloat(leg.to_lon);
        if (isFinite(tl) && isFinite(tlo)){ 
          const icon = leg.mode === 'bus' ? '🚌' : (leg.mode === 'walk' ? '🚶' : '📍');
          L.marker([tl,tlo], { 
            title:String(leg.to||''),
            icon: L.divIcon({className:'stop-marker', html:`<div style="background:#fff;padding:4px 8px;border-radius:8px;border:2px solid ${color};font-size:16px;box-shadow:0 2px 8px rgba(0,0,0,0.2);">${icon}</div>`})
          }).addTo(group); 
          boundsPts.push([tl,tlo]); 
        }
      });
      routeLayer = group;
      try{ if (boundsPts.length){ map.fitBounds(L.latLngBounds(boundsPts), { padding:[40,40] }); } }catch(_){ }
    }

    // Autocomplete (store chosen coords on inputs)
    initAutocomplete(originInput, originList, (item)=>{
      originInput.value = item.name || item.stop_name || '';
      originInput.dataset.lat = String(item.lat);
      originInput.dataset.lon = String(item.lon);
      try { if (formMsg) formMsg.textContent=''; } catch(_){}
    });
    initAutocomplete(destInput, destList, (item)=>{
      destInput.value = item.name || item.stop_name || '';
      destInput.dataset.lat = String(item.lat);
      destInput.dataset.lon = String(item.lon);
      try { if (formMsg) formMsg.textContent=''; } catch(_){}
    });

    // Enable Plan button and wire mode chips
    try { if (planBtn) planBtn.disabled = false; } catch(_){ }
    function updateModeUI(){
      const isVelora = !!document.getElementById('modeVelora')?.checked;
      if (chipVelora) chipVelora.classList.toggle('active', isVelora);
      if (chipDrive) chipDrive.classList.toggle('active', !isVelora);
      if (vehicleGroup) vehicleGroup.style.display = isVelora ? '' : 'none';
    }
    if (modeVelora) modeVelora.addEventListener('change', updateModeUI);
    if (modeDrive) modeDrive.addEventListener('change', updateModeUI);
    updateModeUI();

    // Wire vehicle chips
    const chipVehYes = document.getElementById('chipVehYes');
    const chipVehNo = document.getElementById('chipVehNo');
    const vehicleYes = document.getElementById('vehicleYes');
    const vehicleNo = document.getElementById('vehicleNo');
    function updateVehicleUI(){
      const hasVeh = !!vehicleYes?.checked;
      if (chipVehYes) chipVehYes.classList.toggle('active', hasVeh);
      if (chipVehNo) chipVehNo.classList.toggle('active', !hasVeh);
    }
    if (vehicleYes) vehicleYes.addEventListener('change', updateVehicleUI);
    if (vehicleNo) vehicleNo.addEventListener('change', updateVehicleUI);
    updateVehicleUI();

    // Recent searches UI
    function [REDACTED](){
      const recentSearches = document.getElementById('recentSearches');
      const recentList = document.getElementById('recentList');
      if (!recentSearches || !recentList) return;
      const recent = getRecentSearches();
      if (!recent.length) { recentSearches.style.display = 'none'; return; }
      recentSearches.style.display = 'flex';
      recentList.innerHTML = recent.map((r,i)=> `<div class="recent-chip" data-idx="${i}">${escapeHtml(r.origin)} → ${escapeHtml(r.dest)}<span class="clear" data-idx="${i}">×</span></div>`).join('');
      recentList.querySelectorAll('.recent-chip').forEach(el=>{
        el.addEventListener('click', (e)=>{
          if (e.target.classList.contains('clear')) {
            e.stopPropagation();
            const idx = +e.target.getAttribute('data-idx');
            let rec = getRecentSearches();
            rec.splice(idx,1);
            try { localStorage.setItem(RECENT_KEY, JSON.stringify(rec)); } catch(_){ }
            [REDACTED]();
          } else {
            const idx = +el.getAttribute('data-idx');
            const rec = getRecentSearches();
            if (rec[idx]) {
              originInput.value = rec[idx].origin;
              destInput.value = rec[idx].dest;
              delete originInput.dataset.lat; delete originInput.dataset.lon;
              delete destInput.dataset.lat; delete destInput.dataset.lon;
            }
          }
        });
      });
    }
    [REDACTED]();

    // Time picker modal
    const selectTimeBtn = document.getElementById('selectTimeBtn');
    const timeModal = document.getElementById('timeModal');
    const dateInput = document.getElementById('dateInput');
    const customTime = document.getElementById('customTime');
    const cancelTimeBtn = document.getElementById('cancelTimeBtn');
    const confirmTimeBtn = document.getElementById('confirmTimeBtn');
    const selectedTimeText = document.getElementById('selectedTimeText');
    if (selectTimeBtn && timeModal) {
      selectTimeBtn.onclick = ()=> { timeModal.style.display = 'flex'; if (dateInput) dateInput.value = new Date().toISOString().split('T')[0]; };
    }
    if (cancelTimeBtn && timeModal) {
      cancelTimeBtn.onclick = ()=> { timeModal.style.display = 'none'; if (arriveByInput) arriveByInput.value = '23:59:59'; if (selectedTimeText) selectedTimeText.textContent = '⏰ Planning for: Now'; };
    }
    if (confirmTimeBtn && timeModal && customTime && dateInput) {
      confirmTimeBtn.onclick = ()=> {
        const t = customTime.value || '17:00';
        if (arriveByInput) arriveByInput.value = t + ':00';
        const d = dateInput.value || new Date().toISOString().split('T')[0];
        if (selectedTimeText) selectedTimeText.textContent = `⏰ ${d} at ${t}`;
        
        // Store arrival time for later use
        window.userArrivalTime = { date: d, time: t };
        
        // Calculate and show "Leave by" time suggestion
        const arrivalTime = new Date(d + ' ' + t);
        const estimatedDuration = 120; // 2 hours default estimate
        const leaveByTime = new Date(arrivalTime.getTime() - estimatedDuration * 60000);
        const leaveByStr = leaveByTime.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        
        // Show suggestion
        if (window.SmartNotifications) {
          window.SmartNotifications.[REDACTED](
            `💡 To arrive by ${t}, you should leave by ${leaveByStr}`,
            'info'
          );
        }
        
        timeModal.style.display = 'none';
      };
    }

    async function [REDACTED](oText, dText) {
      // Get coordinates
      let geoO = null, geoD = null;
      if (originInput.dataset.lat && originInput.dataset.lon) {
        geoO = { lat: +originInput.dataset.lat, lon: +originInput.dataset.lon, name: oText };
      }
      if (destInput.dataset.lat && destInput.dataset.lon) {
        geoD = { lat: +destInput.dataset.lat, lon: +destInput.dataset.lon, name: dText };
      }
      
      if (!geoO || !geoD) {
        const [oRes, dRes] = await Promise.all([
          geoO ? Promise.resolve([geoO]) : geocode(oText),
          geoD ? Promise.resolve([geoD]) : geocode(dText)
        ]);
        if (!geoO) geoO = oRes && oRes[0] ? oRes[0] : null;
        if (!geoD) geoD = dRes && dRes[0] ? dRes[0] : null;
      }
      
      if (!geoO || !geoD) {
        if (window.Vela) {
          window.Vela.announceError("I couldn't find one or both locations");
        }
        if (window.showError) window.showError('Could not find one or both locations');
        return;
      }
      
      // Show comparison UI
      if (window.ComparisonUI) {
        const departTime = arriveByInput?.value || '08:00';
        await window.ComparisonUI.showComparison(geoO, geoD, departTime);
      }
    }
    
    async function planTrip(){
      try {
        const oText = originInput.value.trim();
        const dText = destInput.value.trim();
        // Get all selected modes from checkboxes
        const selectedModes = Array.from(document.querySelectorAll('input[name="modeChoice"]:checked')).map(cb => cb.value);
        const vehicleChoice = (document.querySelector('input[name="vehChoice"]:checked')?.value || 'no');
        
        if (!oText || !dText){ 
          if (window.showError) window.showError('Please enter both origin and destination');
          return; 
        }
        
        if (selectedModes.length === 0) {
          if (window.showError) window.showError('Please select at least one transport mode');
          return;
        }
        
        // If multiple modes selected, show comparison
        if (selectedModes.length > 1) {
          return await [REDACTED](oText, dText);
        }
        
        // Single mode selected
        const selectedMode = selectedModes[0];
        
        // Drive Yourself → Continue with direct route below

        // Coordinates (prefer dataset)
        let geoO=null, geoD=null;
        if (originInput.dataset.lat && originInput.dataset.lon){ const lat=+originInput.dataset.lat, lon=+originInput.dataset.lon; if (isFinite(lat)&&isFinite(lon)) geoO={lat,lon,name:oText}; }
        if (destInput.dataset.lat && destInput.dataset.lon){ const lat=+destInput.dataset.lat, lon=+destInput.dataset.lon; if (isFinite(lat)&&isFinite(lon)) geoD={lat,lon,name:dText}; }
        if (!geoO || !geoD){
          const [oRes, dRes] = await Promise.all([
            geoO? Promise.resolve([geoO]) : geocode(oText),
            geoD? Promise.resolve([geoD]) : geocode(dText)
          ]);
          if (!geoO) geoO = oRes && oRes[0] ? oRes[0] : null;
          if (!geoD) geoD = dRes && dRes[0] ? dRes[0] : null;
        }
        if (!geoO || !geoD){ 
          if (window.showError) window.showError('Could not find one or both locations');
          return; 
        }

        // Build request URL robustly even when API_BASE is relative or empty
        const resolvedBase = (() => {
          const b = API_BASE || '';
          if (/^https?:/i.test(b)) return b;        // Absolute URL provided
          if (b.startsWith('/')) return b;          // Absolute path
          if (window.location.pathname.includes('/frontend/')) return '..';
          // Fallback to known PHP base path in this project structure
          return '/backend/php';
        })();
        let url;
        if (/^https?:/i.test(resolvedBase)) {
          url = new URL('plan_trip.php', resolvedBase);
        } else if (resolvedBase.startsWith('/')) {
          url = new URL(`${resolvedBase.replace(/\/$/, '')}/plan_trip.php`, window.location.origin);
        } else {
          // Relative like '..' should resolve against current page path
          url = new URL(`${resolvedBase.replace(/\/$/, '')}/plan_trip.php`, window.location.href);
        }
        url.searchParams.set('origin_lat', geoO.lat);
        url.searchParams.set('origin_lon', geoO.lon);
        url.searchParams.set('dest_lat', geoD.lat);
        url.searchParams.set('dest_lon', geoD.lon);
        url.searchParams.set('origin_name', oText);
        url.searchParams.set('dest_name', dText);
        url.searchParams.set('depart_time', arriveByInput?.value || '08:00');
        url.searchParams.set('mode', selectedMode);
        url.searchParams.set('vehicle', vehicleChoice);
        // Add quick mode for faster testing (skips external APIs)
        if (window.location.search.includes('quick=1')) url.searchParams.set('quick', '1');

        // Show simple loading overlay
        const loadingOverlay = document.getElementById('loadingOverlay');
        if (loadingOverlay) loadingOverlay.classList.add('active');
        if (planBtn) planBtn.disabled = true;
        
        // Increase timeout for Velora multimodal plans which may call multiple services
        const timeout = selectedMode==='drive' ? 60000 : 120000;
        console.log('[planTrip] sending', { mode:selectedMode, vehicle: vehicleChoice, url: url.toString() });
        const resp = await [REDACTED](url.toString(), timeout).catch(()=>null);
        
        // Hide loading
        if (loadingOverlay) loadingOverlay.classList.remove('active');
        if (planBtn) planBtn.disabled = false;
        
        if (!resp) {
          try { console.warn('Velora: request failed or timed out', { url: url.toString(), timeout }); } catch(_){}
          if (window.showError) window.showError(selectedMode==='drive' ? 'No driving route found' : 'No route found. Try different locations');
          return;
        }
        
        // Save to recent searches
        saveRecentSearch(oText, dText);
        [REDACTED]();
        // Normalize response so frontend can always read legs/segments
        try {
          resp.legs = (Array.isArray(resp?.legs) ? resp.legs : (resp?.data?.legs || []));
          resp.segments = (Array.isArray(resp?.segments) ? resp.segments : (resp?.data?.segments || []));
        } catch(_) { /* ignore */ }
        // If legs empty, only stop when there's truly nothing to render.
        if (!resp || !Array.isArray(resp.legs) || resp.legs.length === 0) {
          const hasSegments = Array.isArray(resp?.segments) && resp.segments.length > 0;
          const isDrive = selectedMode === 'drive';
          if (!hasSegments) {
            try { console.warn("Velora: legs empty", resp); } catch(_){}
            if (window.showError) window.showError('No route found');
            return;
          }
          // For drive mode, proceed using segments.
          // For velora, if segments exist, continue to render via segments branch below.
        }

        try { window.__VELO_LAST_RESP__ = resp; } catch(_){ }
        try { console.log("VELORA got resp:", resp); } catch(_){ }

        let routePoly = resp.route_poly; // optional
        if ((!routePoly || !Array.isArray(routePoly) || routePoly.length<2) && resp.route_file){
          try {
            const rf = await fetch(resp.route_file, { cache:'no-cache' }).then(r=> r.ok? r.json(): null);
            if (rf && Array.isArray(rf.route_poly)) routePoly = rf.route_poly;
          } catch(e){ console.warn('route_file fetch failed', e); }
        }
        // Collect instruction steps
        const legs = Array.isArray(resp.legs) ? resp.legs : (resp.data && Array.isArray(resp.data.legs) ? resp.data.legs : []);
        const segs = legs.length ? legs : (Array.isArray(resp.segments) ? resp.segments : (resp.data && Array.isArray(resp.data.segments) ? resp.data.segments : []));
        const allSteps = [];
        (segs||[]).forEach(s=>{ if (Array.isArray(s.instructions)){ allSteps.push(...s.instructions); } });

        // Always try to draw route on map
        const segmentsToDraw = resp.segments || resp.legs || segs || [];
        if (segmentsToDraw.length > 0) {
          console.log('[Velora] Drawing', segmentsToDraw.length, 'segments on map');
          await drawSegments(segmentsToDraw, routePoly);
        }
        
        // Render itinerary
        if (Array.isArray(resp.legs) && resp.legs.length){ renderItinerary(resp); }
        else { renderItinerary(resp); }

        // Render instructions + wire navigation
        renderInstructions(allSteps);
        // Build path coords for nav
        let navCoords = [];
        if (Array.isArray(routePoly) && routePoly.length>=2){ navCoords = routePoly.map(p=> Array.isArray(p)? [p[0],p[1]] : [p.lat,p.lng]); }
        else if (Array.isArray(segs) && segs.length){
          for (const s of segs){ if (s.from_lat!=null && s.from_lon!=null) navCoords.push([+s.from_lat,+s.from_lon]); if (s.to_lat!=null && s.to_lon!=null) navCoords.push([+s.to_lat,+s.to_lon]); }
        }
        const startBtn = document.getElementById('startNavBtn'); 
        const cancelBtn = document.getElementById('cancelRouteBtn');
        const muteBtn = document.getElementById('muteBtn');
        if (startBtn){ 
          startBtn.onclick = ()=> {
            startNavigation(navCoords, allSteps);
            if (muteBtn) muteBtn.style.display = '';
          };
        }
        if (cancelBtn){ 
          cancelBtn.onclick = ()=> {
            if (confirm('Start over? This will refresh the page.')) {
              window.location.reload();
            }
          };
        }

        try {
          if (hotelsAsk){
            hotelsAsk.style.display = 'inline-flex';
            hotelsAsk.onclick = async ()=>{
              hotelsAsk.textContent = 'Loading hotels...';
              try {
                const lat = Array.isArray(routePoly) && routePoly.length? (Array.isArray(routePoly.at(-1))? routePoly.at(-1)[0] : routePoly.at(-1).lat) : geoD.lat;
                const lon = Array.isArray(routePoly) && routePoly.length? (Array.isArray(routePoly.at(-1))? routePoly.at(-1)[1] : routePoly.at(-1).lng) : geoD.lon;
                const hu = new URL(`${API_BASE}/get_hotels.php`, window.location.origin); hu.searchParams.set('lat', String(lat)); hu.searchParams.set('lon', String(lon));
                const data = await fetch(hu.toString()).then(r=>r.json()).catch(()=>null);
                const panel = document.getElementById('hotelsPanel'); const list = document.getElementById('hotelList');
                if (!panel || !list || !data || !Array.isArray(data.data)){ hotelsAsk.style.display='none'; return; }
                panel.style.display=''; list.innerHTML = data.data.slice(0,5).map(h=> `<div class="hotel-card"><div><div class="name">${escapeHtml(h.hotel_name||h.name||'Hotel')}</div><div class="muted">${escapeHtml(h.city||'')}</div></div><div class="price">₹${escapeHtml(String(h.price_per_night||''))}/night</div></div>`).join('');
              } finally { hotelsAsk.style.display='none'; }
            };
          }
        } catch(_){ }
      } catch(e){
        try { console.error('Error in planTrip:', e); } catch(_){ }
        if (window.showError) window.showError('Error planning trip. Please try again.');
      }
    }

    if (planBtn) planBtn.addEventListener('click', (e)=>{ e.preventDefault(); planTrip(); });
    
    // Wire mute button
    const muteBtn = document.getElementById('muteBtn');
    if (muteBtn) {
      muteBtn.addEventListener('click', toggleVoice);
    }

    // Compare mode: fetch multiple candidates and show side-by-side
    async function fetchCompareOptions(geoO, geoD, oText, dText){
      try {
        const resolvedBase = (() => {
          const b = API_BASE || '';
          if (/^https?:/i.test(b)) return b;
          if (b.startsWith('/')) return b;
          if (window.location.pathname.includes('/frontend/')) return '..';
          return '/backend/php';
        })();
        
        // Fetch both bus and train options in parallel
        const busUrl = new URL(`${resolvedBase.replace(/\/$/, '')}/plan_trip.php`, window.location.href);
        busUrl.searchParams.set('origin_lat', geoO.lat);
        busUrl.searchParams.set('origin_lon', geoO.lon);
        busUrl.searchParams.set('dest_lat', geoD.lat);
        busUrl.searchParams.set('dest_lon', geoD.lon);
        busUrl.searchParams.set('origin_name', oText);
        busUrl.searchParams.set('dest_name', dText);
        busUrl.searchParams.set('mode', 'bus');
        busUrl.searchParams.set('depart_time', arriveByInput?.value || '08:00');
        
        const trainUrl = new URL(busUrl.toString());
        trainUrl.searchParams.set('mode', 'train');
        
        const [busResp, trainResp] = await Promise.all([
          [REDACTED](busUrl.toString(), 30000).catch(()=>null),
          [REDACTED](trainUrl.toString(), 30000).catch(()=>null)
        ]);
        
        const options = [];
        if (busResp && busResp.success !== false) {
          options.push({
            mode: 'bus',
            label: '🚌 Bus',
            time: busResp.total_time || busResp.data?.total_time || '-',
            fare: busResp.total_fare || busResp.data?.total_fare || 0,
            segments: busResp.legs || busResp.segments || busResp.data?.legs || busResp.data?.segments || [],
            response: busResp
          });
        }
        if (trainResp && trainResp.success !== false) {
          options.push({
            mode: 'train',
            label: '🚆 Train',
            time: trainResp.total_time || trainResp.data?.total_time || '-',
            fare: trainResp.total_fare || trainResp.data?.total_fare || 0,
            segments: trainResp.legs || trainResp.segments || trainResp.data?.legs || trainResp.data?.segments || [],
            response: trainResp
          });
        }
        return options;
      } catch(e) {
        console.error('Compare fetch failed:', e);
        return [];
      }
    }

    function renderCompareMode(options){
      const compareSection = document.getElementById('compareSection');
      const compareGrid = document.getElementById('compareGrid');
      const itinerarySection = document.getElementById('itinerarySection');
      if (!compareSection || !compareGrid || !options.length) return;
      
      // Determine recommended (cheapest or fastest)
      let recommended = 0;
      if (options.length > 1) {
        const sorted = options.slice().sort((a,b)=> (a.fare||999) - (b.fare||999));
        recommended = options.indexOf(sorted[0]);
      }
      
      compareGrid.innerHTML = options.map((opt,idx)=>{
        const isRec = idx === recommended;
        const badge = isRec ? '<div class="badge">⭐ Recommended</div>' : '';
        return `
          <div class="compare-card ${isRec?'recommended':''}" data-idx="${idx}">
            ${badge}
            <div style="font-size:32px; text-align:center; margin-bottom:10px;">${opt.label.split(' ')[0]}</div>
            <div style="font-size:18px; font-weight:700; text-align:center; color:#0f172a; margin-bottom:4px;">${opt.label.split(' ')[1]}</div>
            <div style="display:flex; justify-content:space-around; margin:16px 0;">
              <div style="text-align:center;"><div class="muted">Time</div><div style="font-weight:700; font-size:16px;">${escapeHtml(String(opt.time))}</div></div>
              <div style="text-align:center;"><div class="muted">Fare</div><div style="font-weight:700; font-size:16px; color:#6366f1;">₹${opt.fare}</div></div>
            </div>
            <div class="muted" style="text-align:center; margin-bottom:12px;">${opt.segments.length} segments</div>
            <button class="choose-btn" data-idx="${idx}">Choose ${opt.label.split(' ')[1]}</button>
          </div>
        `;
      }).join('');
      
      compareSection.style.display = '';
      itinerarySection.style.display = 'none';
      
      // Wire choose buttons
      compareGrid.querySelectorAll('.choose-btn').forEach(btn=>{
        btn.addEventListener('click', ()=>{
          const idx = +btn.getAttribute('data-idx');
          const chosen = options[idx];
          if (chosen) {
            compareSection.style.display = 'none';
            itinerarySection.style.display = '';
            renderItinerary(chosen.response);
            [REDACTED](chosen.response);
            // Collect and render instructions
            const allSteps = [];
            (chosen.segments||[]).forEach(s=>{ if (Array.isArray(s.instructions)){ allSteps.push(...s.instructions); } });
            renderInstructions(allSteps);
            // Build nav coords
            let navCoords = [];
            const routePoly = chosen.response.route_poly || chosen.response.data?.route_poly;
            if (Array.isArray(routePoly) && routePoly.length>=2){ navCoords = routePoly.map(p=> Array.isArray(p)? [p[0],p[1]] : [p.lat,p.lng]); }
            else if (Array.isArray(chosen.segments) && chosen.segments.length){
              for (const s of chosen.segments){ if (s.from_lat!=null && s.from_lon!=null) navCoords.push([+s.from_lat,+s.from_lon]); if (s.to_lat!=null && s.to_lon!=null) navCoords.push([+s.to_lat,+s.to_lon]); }
            }
            const startBtn = document.getElementById('startNavBtn'); const stopBtn = document.getElementById('resetBtn');
            if (startBtn){ startBtn.onclick = ()=> startNavigation(navCoords, allSteps); }
            if (stopBtn){ stopBtn.onclick = ()=> stopNavigation(); }
          }
        });
      });
    }

    // Enable compare mode for Velora (optional: add a toggle button or auto-trigger)
    window.veloraCompare = async function(){
      const oText = originInput.value.trim();
      const dText = destInput.value.trim();
      if (!oText || !dText) return;
      let geoO=null, geoD=null;
      if (originInput.dataset.lat && originInput.dataset.lon){ const lat=+originInput.dataset.lat, lon=+originInput.dataset.lon; if (isFinite(lat)&&isFinite(lon)) geoO={lat,lon,name:oText}; }
      if (destInput.dataset.lat && destInput.dataset.lon){ const lat=+destInput.dataset.lat, lon=+destInput.dataset.lon; if (isFinite(lat)&&isFinite(lon)) geoD={lat,lon,name:dText}; }
      if (!geoO || !geoD) return;
      const loadingOverlay = document.getElementById('loadingOverlay');
      const loadingText = document.getElementById('loadingText');
      if (loadingOverlay) loadingOverlay.classList.add('active');
      if (loadingText) loadingText.textContent = '⚖️ Comparing bus and train options...';
      const options = await fetchCompareOptions(geoO, geoD, oText, dText);
      if (loadingOverlay) loadingOverlay.classList.remove('active');
      if (options.length) renderCompareMode(options);
      else if (window.showError) window.showError('Could not fetch route options');
    };
    
    // Compare button removed - now integrated into "Find Routes" button
    
    // Expose functions globally for ComparisonUI
    window.drawSegments = drawSegments;
    window.renderItinerary = renderItinerary;
    window.renderInstructions = renderInstructions;
    
    console.log('[Velora] Global functions exposed for ComparisonUI');

  });
})();

/* v-sync seq: 18 */