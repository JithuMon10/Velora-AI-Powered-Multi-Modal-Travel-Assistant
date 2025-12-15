/**
 * Velora Comparison Engine
 * Fetches and compares multiple route options
 */

class ComparisonEngine {
  constructor() {
    this.routes = [];
    this.recommended = null;
  }
  
  async fetchAllOptions(origin, destination, departTime) {
    console.log('[Comparison] Fetching all route options...');
    
    const API_BASE = (window.VELORA_CONFIG && window.VELORA_CONFIG.API_BASE) || 
                     (window.__VELO_API__ && window.__VELO_API__.baseUrl) || '';
    
    const resolvedBase = (() => {
      const b = API_BASE || '';
      if (/^https?:/i.test(b)) return b;
      if (b.startsWith('/')) return b;
      if (window.location.pathname.includes('/frontend/')) return '..';
      return '/backend/php';
    })();
    
    const modes = ['bus', 'train', 'velora'];
    const promises = modes.map(mode => this.fetchRoute(resolvedBase, origin, destination, departTime, mode));
    
    const results = await Promise.allSettled(promises);
    
    this.routes = results
      .map((result, idx) => {
        if (result.status === 'fulfilled' && result.value && !result.value.error) {
          return {
            mode: modes[idx],
            data: result.value,
            score: this.calculateScore(result.value, modes[idx])
          };
        }
        return null;
      })
      .filter(r => r !== null);
    
    // Determine recommended route
    if (this.routes.length > 0) {
      this.routes.sort((a, b) => b.score - a.score);
      this.recommended = this.routes[0];
    }
    
    console.log('[Comparison] Found', this.routes.length, 'options');
    console.log('[Comparison] Recommended:', this.recommended?.mode);
    
    return this.routes;
  }
  
  async fetchRoute(baseUrl, origin, destination, departTime, mode) {
    const url = new URL(`${baseUrl.replace(/\/$/, '')}/plan_trip.php`, window.location.href);
    url.searchParams.set('origin_lat', origin.lat);
    url.searchParams.set('origin_lon', origin.lon);
    url.searchParams.set('dest_lat', destination.lat);
    url.searchParams.set('dest_lon', destination.lon);
    url.searchParams.set('origin_name', origin.name);
    url.searchParams.set('dest_name', destination.name);
    url.searchParams.set('mode', mode);
    url.searchParams.set('depart_time', departTime || '08:00');
    if (window.location.search.includes('quick=1')) url.searchParams.set('quick', '1');
    
    try {
      const response = await fetch(url.toString(), { timeout: 30000 });
      const data = await response.json();
      return data;
    } catch (e) {
      console.error(`[Comparison] Error fetching ${mode}:`, e);
      return null;
    }
  }
  
  calculateScore(route, mode) {
    let score = 100;
    
    // Extract data
    const fare = parseInt(route.total_fare) || 0;
    const timeStr = route.total_time || '0h 0m';
    const minutes = this.parseTimeToMinutes(timeStr);
    const legs = route.legs || route.segments || [];
    
    // Time factor (faster = better)
    if (minutes > 0) {
      score -= (minutes / 10); // Penalty for long trips
    }
    
    // Cost factor (cheaper = better)
    if (fare > 0) {
      score -= (fare / 100); // Penalty for expensive trips
    }
    
    // Comfort factor
    const transfers = legs.length - 1;
    score -= (transfers * 5); // Penalty for transfers
    
    // Mode preferences
    if (mode === 'train') score += 10; // Trains are reliable
    if (mode === 'bus') score += 5; // Buses are convenient
    
    // Traffic consideration
    const hasHighTraffic = legs.some(leg => leg.traffic_severity === 'high');
    if (hasHighTraffic) score -= 15;
    
    return Math.max(0, score);
  }
  
  parseTimeToMinutes(timeStr) {
    const match = timeStr.match(/(\d+)h\s*(\d+)m/);
    if (match) {
      return parseInt(match[1]) * 60 + parseInt(match[2]);
    }
    return 0;
  }
  
  getRecommendationReason(route, mode) {
    const fare = parseInt(route.total_fare) || 0;
    const timeStr = route.total_time || '0h 0m';
    const minutes = this.parseTimeToMinutes(timeStr);
    
    // Find what makes this route best
    const allFares = this.routes.map(r => parseInt(r.data.total_fare) || 0);
    const allTimes = this.routes.map(r => this.parseTimeToMinutes(r.data.total_time || '0h 0m'));
    
    const isCheapest = fare === Math.min(...allFares);
    const isFastest = minutes === Math.min(...allTimes);
    
    if (isFastest && isCheapest) {
      return "It's both the fastest and cheapest option!";
    } else if (isFastest) {
      return "It's the fastest way to reach your destination!";
    } else if (isCheapest) {
      return `It's the most economical option, saving you ₹${Math.max(...allFares) - fare}!`;
    } else if (mode === 'train') {
      return "Trains are reliable and comfortable for this journey!";
    } else if (mode === 'bus') {
      return "Buses offer good connectivity for this route!";
    } else {
      return "This option offers the best balance of time, cost, and comfort!";
    }
  }
  
  getComparisonData() {
    return this.routes.map(route => {
      const isRecommended = route === this.recommended;
      const reason = isRecommended ? this.getRecommendationReason(route.data, route.mode) : '';
      
      return {
        mode: route.mode,
        modeName: this.getModeName(route.mode),
        icon: this.getModeIcon(route.mode),
        fare: route.data.total_fare,
        time: route.data.total_time,
        legs: route.data.legs || route.data.segments || [],
        score: route.score,
        recommended: isRecommended,
        reason: reason,
        data: route.data
      };
    });
  }
  
  getModeName(mode) {
    const names = {
      bus: 'Bus',
      train: 'Train',
      velora: 'Velora Smart',
      taxi: 'Taxi',
      flight: 'Flight'
    };
    return names[mode] || mode;
  }
  
  getModeIcon(mode) {
    const icons = {
      bus: '🚌',
      train: '🚂',
      velora: '✨',
      taxi: '🚕',
      flight: '✈️'
    };
    return icons[mode] || '🚗';
  }
}

window.ComparisonEngine = ComparisonEngine;
console.log('[Comparison] Engine loaded ✅');
