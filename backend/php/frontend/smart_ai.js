// Smart AI Features for Velora
// Smart Suggestions + Predictive Booking

class SmartAI {
  constructor() {
    this.userHistory = this.loadHistory();
    this.patterns = this.analyzePatterns();
  }
  
  loadHistory() {
    // Load from localStorage (mock data for demo)
    const stored = localStorage.getItem('velora_history');
    if (stored) return JSON.parse(stored);
    
    // Mock history
    return [
      { from: 'Mallappally', to: 'Kochi', time: '08:00', mode: 'bus', date: '2025-10-01' },
      { from: 'Mallappally', to: 'Kochi', time: '08:15', mode: 'bus', date: '2025-10-02' },
      { from: 'Mallappally', to: 'Kochi', time: '08:00', mode: 'bus', date: '2025-10-03' },
      { from: 'Kochi', to: 'Mallappally', time: '17:30', mode: 'bus', date: '2025-10-01' },
      { from: 'Kochi', to: 'Mallappally', time: '17:45', mode: 'bus', date: '2025-10-02' }
    ];
  }
  
  saveToHistory(from, to, time, mode) {
    this.userHistory.push({
      from, to, time, mode,
      date: new Date().toISOString().split('T')[0]
    });
    
    // Keep only last 50
    if (this.userHistory.length > 50) {
      this.userHistory = this.userHistory.slice(-50);
    }
    
    localStorage.setItem('velora_history', JSON.stringify(this.userHistory));
    this.patterns = this.analyzePatterns();
  }
  
  analyzePatterns() {
    const patterns = {
      frequentRoutes: {},
      preferredTimes: {},
      preferredModes: {}
    };
    
    this.userHistory.forEach(trip => {
      const route = `${trip.from}-${trip.to}`;
      patterns.frequentRoutes[route] = (patterns.frequentRoutes[route] || 0) + 1;
      
      const hour = parseInt(trip.time.split(':')[0]);
      patterns.preferredTimes[hour] = (patterns.preferredTimes[hour] || 0) + 1;
      
      patterns.preferredModes[trip.mode] = (patterns.preferredModes[trip.mode] || 0) + 1;
    });
    
    return patterns;
  }
  
  getSuggestions() {
    const suggestions = [];
    const now = new Date();
    const currentHour = now.getHours();
    
    // Suggest based on time
    if (currentHour >= 7 && currentHour <= 9) {
      const morningRoute = this.getMostFrequentRoute(7, 9);
      if (morningRoute) {
        suggestions.push({
          type: 'time-based',
          icon: '🌅',
          title: 'Morning Commute?',
          message: `You usually go to ${morningRoute.to} around this time.`,
          action: 'Plan Route',
          route: morningRoute
        });
      }
    }
    
    if (currentHour >= 17 && currentHour <= 19) {
      const eveningRoute = this.getMostFrequentRoute(17, 19);
      if (eveningRoute) {
        suggestions.push({
          type: 'time-based',
          icon: '🌆',
          title: 'Heading Home?',
          message: `You usually travel to ${eveningRoute.to} in the evening.`,
          action: 'Plan Route',
          route: eveningRoute
        });
      }
    }
    
    // Suggest based on day
    const dayOfWeek = now.getDay();
    if (dayOfWeek >= 1 && dayOfWeek <= 5) {
      suggestions.push({
        type: 'pattern',
        icon: '📅',
        title: 'Weekday Pattern',
        message: 'Traffic is usually heavy during rush hours. Consider leaving 15 minutes earlier.',
        action: 'View Traffic'
      });
    }
    
    // Suggest based on weather
    suggestions.push({
      type: 'weather',
      icon: '🌧️',
      title: 'Weather Alert',
      message: 'Rain expected this evening. Allow extra travel time.',
      action: 'Check Weather'
    });
    
    return suggestions;
  }
  
  getMostFrequentRoute(startHour, endHour) {
    const relevantTrips = this.userHistory.filter(trip => {
      const hour = parseInt(trip.time.split(':')[0]);
      return hour >= startHour && hour <= endHour;
    });
    
    if (relevantTrips.length === 0) return null;
    
    const routeCounts = {};
    relevantTrips.forEach(trip => {
      const key = `${trip.from}-${trip.to}`;
      routeCounts[key] = (routeCounts[key] || 0) + 1;
    });
    
    const mostFrequent = Object.entries(routeCounts)
      .sort((a, b) => b[1] - a[1])[0];
    
    if (!mostFrequent) return null;
    
    const [from, to] = mostFrequent[0].split('-');
    return { from, to };
  }
  
  renderSuggestions() {
    const suggestions = this.getSuggestions();
    if (suggestions.length === 0) return '';
    
    return `
      <div class="smart-suggestions" style="margin-bottom: 16px; animation: fadeInUp 0.4s ease-out;">
        <h4 style="margin: 0 0 12px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
          <span>🧠</span> Smart Suggestions
        </h4>
        ${suggestions.map(s => `
          <div class="suggestion-card" style="
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 100%);
            border: 1px solid #c7d2fe;
            border-radius: 12px;
            padding: 14px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
          " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(99, 102, 241, 0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
            <div style="display: flex; align-items: start; gap: 12px;">
              <span style="font-size: 28px;">${s.icon}</span>
              <div style="flex: 1;">
                <div style="font-weight: 700; color: #4338ca; margin-bottom: 4px; font-size: 14px;">${s.title}</div>
                <div style="font-size: 13px; color: #6366f1; margin-bottom: 8px;">${s.message}</div>
                <button style="
                  padding: 6px 12px;
                  background: #6366f1;
                  color: white;
                  border: none;
                  border-radius: 6px;
                  font-size: 12px;
                  font-weight: 600;
                  cursor: pointer;
                  transition: all 0.2s;
                " onmouseover="this.style.background='#4f46e5'" onmouseout="this.style.background='#6366f1'">
                  ${s.action}
                </button>
              </div>
            </div>
          </div>
        `).join('')}
      </div>
    `;
  }
  
  // Predictive Booking
  suggestBooking(route) {
    return {
      available: true,
      platform: route.mode === 'train' ? 'IRCTC' : route.mode === 'bus' ? 'RedBus' : 'Ola',
      fare: route.fare,
      discount: Math.random() > 0.5 ? 10 : 0,
      seatsLeft: Math.floor(Math.random() * 20) + 5
    };
  }
  
  renderBookingSuggestion(route) {
    const booking = this.suggestBooking(route);
    
    return `
      <div style="
        background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
        border: 2px solid #10b981;
        border-radius: 12px;
        padding: 16px;
        margin-top: 12px;
        animation: celebrate 0.5s ease-in-out;
      ">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
          <span style="font-size: 32px;">🎫</span>
          <div>
            <div style="font-weight: 700; color: #065f46; font-size: 16px;">Booking Available!</div>
            <div style="font-size: 12px; color: #059669;">Book now on ${booking.platform}</div>
          </div>
        </div>
        
        <div style="display: flex; gap: 16px; margin-bottom: 12px;">
          <div>
            <div style="font-size: 11px; color: #059669;">Fare</div>
            <div style="font-size: 20px; font-weight: 700; color: #065f46;">₹${booking.fare}</div>
          </div>
          ${booking.discount > 0 ? `
            <div>
              <div style="font-size: 11px; color: #059669;">Discount</div>
              <div style="font-size: 20px; font-weight: 700; color: #10b981;">${booking.discount}% OFF</div>
            </div>
          ` : ''}
          <div>
            <div style="font-size: 11px; color: #059669;">Seats Left</div>
            <div style="font-size: 20px; font-weight: 700; color: #065f46;">${booking.seatsLeft}</div>
          </div>
        </div>
        
        <button onclick="alert('Booking feature coming soon! This will redirect to ${booking.platform}')" style="
          width: 100%;
          padding: 12px;
          background: #10b981;
          color: white;
          border: none;
          border-radius: 8px;
          font-weight: 700;
          font-size: 14px;
          cursor: pointer;
          transition: all 0.2s;
        " onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
          Book Now on ${booking.platform} →
        </button>
      </div>
    `;
  }
}

// Create global instance
window.SmartAI = new SmartAI();

// Service loaded
