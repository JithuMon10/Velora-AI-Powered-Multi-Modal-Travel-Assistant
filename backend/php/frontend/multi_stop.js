// Multi-Stop Routes for Velora
// Plan trips with multiple destinations

class MultiStopRoutes {
  constructor() {
    this.stops = [];
    this.maxStops = 5;
    this.createMultiStopUI();
  }
  
  createMultiStopUI() {
    // Multi-stop feature disabled per user request
    console.log('[MultiStop] Feature disabled');
  }
  
  addStop() {
    if (this.stops.length >= this.maxStops) {
      alert(`Maximum ${this.maxStops} stops allowed`);
      return;
    }
    
    const stopIndex = this.stops.length;
    const stopId = `stop${stopIndex}`;
    
    const stopDiv = document.createElement('div');
    stopDiv.className = 'ac-wrap';
    stopDiv.id = `${stopId}Wrap`;
    stopDiv.style.cssText = 'position: relative; animation: fadeInUp 0.3s ease-out;';
    
    stopDiv.innerHTML = `
      <div style="display: flex; gap: 8px; align-items: center;">
        <span style="font-size: 18px;">📍</span>
        <input id="${stopId}Input" class="ac-input" type="text" 
               placeholder="Stop ${stopIndex + 1}: Add destination" 
               autocomplete="off" style="flex: 1;" />
        <button onclick="window.MultiStopRoutes.removeStop(${stopIndex})" 
                style="background: #fee2e2; border: none; color: #dc2626; 
                       width: 32px; height: 32px; border-radius: 6px; 
                       cursor: pointer; font-size: 16px; transition: all 0.2s;"
                onmouseover="this.style.background='#fecaca'" 
                onmouseout="this.style.background='#fee2e2'">×</button>
      </div>
      <input id="${stopId}Id" type="hidden" />
      <div id="${stopId}List" class="ac-list" style="display:none;"></div>
    `;
    
    document.getElementById('stopsContainer').appendChild(stopDiv);
    this.stops.push({ id: stopId, name: '', lat: null, lon: null });
    
    // Initialize autocomplete for this stop
    this.initAutocomplete(stopId);
    
    // Update button text
    this.updateAddButton();
  }
  
  removeStop(index) {
    const stopId = `stop${index}`;
    const stopWrap = document.getElementById(`${stopId}Wrap`);
    if (stopWrap) {
      stopWrap.style.animation = 'fadeOut 0.3s ease-out';
      setTimeout(() => {
        stopWrap.remove();
        this.stops.splice(index, 1);
        this.renumberStops();
        this.updateAddButton();
      }, 300);
    }
  }
  
  renumberStops() {
    // Renumber remaining stops
    this.stops.forEach((stop, idx) => {
      const input = document.getElementById(`${stop.id}Input`);
      if (input) {
        input.placeholder = `Stop ${idx + 1}: Add destination`;
      }
    });
  }
  
  updateAddButton() {
    const btn = document.getElementById('addStopBtn');
    if (btn) {
      if (this.stops.length >= this.maxStops) {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.innerHTML = `➕ Max ${this.maxStops} stops`;
      } else {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.innerHTML = `➕ Add Stop (${this.stops.length}/${this.maxStops})`;
      }
    }
  }
  
  initAutocomplete(stopId) {
    // Simple autocomplete initialization
    // This would connect to the existing autocomplete system
    console.log(`[MultiStop] Initialized autocomplete for ${stopId}`);
  }
  
  getStops() {
    return this.stops.filter(s => s.name && s.lat && s.lon);
  }
  
  [REDACTED](origin, destination, stops) {
    // Simple optimization: visit stops in order
    // Advanced: TSP algorithm for optimal order
    const allPoints = [origin, ...stops, destination];
    
    let totalDistance = 0;
    let totalTime = 0;
    
    for (let i = 0; i < allPoints.length - 1; i++) {
      const from = allPoints[i];
      const to = allPoints[i + 1];
      const dist = this.haversineDistance(from.lat, from.lon, to.lat, to.lon);
      totalDistance += dist;
      totalTime += (dist / 40) * 60; // 40 km/h average
    }
    
    return {
      stops: allPoints,
      totalDistance: Math.round(totalDistance),
      totalTime: Math.round(totalTime),
      optimized: false // Set to true if using TSP
    };
  }
  
  haversineDistance(lat1, lon1, lat2, lon2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
  }
  
  [REDACTED](route) {
    return `
      <div style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%); 
                  color: white; padding: 16px; border-radius: 12px; margin-bottom: 12px;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
          <span style="font-size: 24px;">🗺️</span>
          <div>
            <div style="font-weight: 700; font-size: 16px;">Multi-Stop Route</div>
            <div style="font-size: 12px; opacity: 0.9;">${route.stops.length} destinations</div>
          </div>
        </div>
        <div style="display: flex; gap: 16px;">
          <div>
            <div style="font-size: 11px; opacity: 0.8;">Total Distance</div>
            <div style="font-size: 20px; font-weight: 700;">${route.totalDistance} km</div>
          </div>
          <div>
            <div style="font-size: 11px; opacity: 0.8;">Total Time</div>
            <div style="font-size: 20px; font-weight: 700;">${Math.floor(route.totalTime / 60)}h ${route.totalTime % 60}m</div>
          </div>
        </div>
      </div>
    `;
  }
}

// Create global instance
window.MultiStopRoutes = new MultiStopRoutes();

// Service loaded
