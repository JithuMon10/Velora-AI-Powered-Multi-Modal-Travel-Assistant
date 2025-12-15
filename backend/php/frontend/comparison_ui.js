/**
 * Velora Comparison UI
 * Beautiful route comparison cards with Vela integration
 */

class ComparisonUI {
  constructor() {
    this.container = null;
    this.chatBubble = null;
    this.init();
  }
  
  init() {
    // Create Vela chat bubble
    this.createChatBubble();
    
    // Create comparison container
    this.createComparisonContainer();
  }
  
  createChatBubble() {
    this.chatBubble = document.createElement('div');
    this.chatBubble.id = 'velaChatBubble';
    this.chatBubble.style.cssText = `
      position: fixed;
      bottom: 30px;
      left: 30px;
      max-width: 350px;
      background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%);
      color: white;
      padding: 20px;
      border-radius: 20px;
      box-shadow: 0 10px 40px rgba(59, 130, 246, 0.4);
      z-index: 9998;
      display: none;
      animation: slideInLeft 0.5s ease-out;
    `;
    
    this.chatBubble.innerHTML = `
      <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
        <div class="vela-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; font-size: 20px; animation: pulse 2s infinite;">
          ✨
        </div>
        <div style="flex: 1;">
          <div style="font-weight: 600; font-size: 16px;">Vela</div>
          <div style="font-size: 12px; opacity: 0.9;">AI Travel Assistant</div>
        </div>
        <button onclick="document.getElementById('velaChatBubble').style.display='none'" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 24px; height: 24px; border-radius: 50%; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center;">×</button>
      </div>
      <div id="velaMessage" style="font-size: 14px; line-height: 1.6;"></div>
    `;
    
    document.body.appendChild(this.chatBubble);
    
    // Add animation styles
    const style = document.createElement('style');
    style.textContent = `
      @keyframes slideInLeft {
        from { transform: translateX(-400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      @keyframes slideOutLeft {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(-400px); opacity: 0; }
      }
      @keyframes pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
      }
      @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
      }
    `;
    document.head.appendChild(style);
  }
  
  createComparisonContainer() {
    this.container = document.createElement('div');
    this.container.id = 'comparisonContainer';
    this.container.style.cssText = `
      position: fixed;
      top: 0;
      left: 400px;
      bottom: 0;
      width: 350px;
      background: white;
      box-shadow: 2px 0 20px rgba(0,0,0,0.1);
      z-index: 999;
      overflow-y: auto;
      display: none;
      animation: slideInFromLeft 0.3s ease-out;
    `;
    
    document.body.appendChild(this.container);
    
    // Adjust map to make room for side panel
    const mapDiv = document.getElementById('map');
    if (mapDiv) {
      mapDiv.style.transition = 'all 0.3s ease-out';
    }
    
    const slideStyle = document.createElement('style');
    slideStyle.textContent = `
      @keyframes slideInFromLeft {
        from { transform: translateX(-100%); }
        to { transform: translateX(0); }
      }
      @keyframes slideOutToLeft {
        from { transform: translateX(0); }
        to { transform: translateX(-100%); }
      }
      
      /* Adjust map container when panel is open */
      body.comparison-open .map-container {
        position: fixed !important;
        left: 750px !important;
        right: 0 !important;
        width: auto !important;
        flex-grow: 0 !important;
        margin-left: 0 !important;
      }
      
      body.comparison-open #map {
        margin-left: 0 !important;
        width: 100% !important;
      }
      
      /* Tablet responsive */
      @media (max-width: 1024px) {
        #comparisonContainer {
          width: 320px !important;
          left: 320px !important;
        }
        body.comparison-open .map-container {
          position: fixed !important;
          left: 640px !important;
          right: 0 !important;
          width: auto !important;
          flex-grow: 0 !important;
          margin-left: 0 !important;
        }
        body.comparison-open #map {
          margin-left: 0 !important;
          width: 100% !important;
        }
      }
      
      /* Mobile responsive */
      @media (max-width: 768px) {
        #comparisonContainer {
          width: 100% !important;
          left: 0 !important;
          top: 60px !important;
        }
        body.comparison-open .map-container {
          display: none !important;
        }
      }
    `;
    document.head.appendChild(slideStyle);
  }
  
  showVelaMessage(message, autoHide = true) {
    const messageEl = document.getElementById('velaMessage');
    if (messageEl) {
      messageEl.textContent = message;
      this.chatBubble.style.display = 'block';
      
      // Clear existing timeout
      if (this.hideTimeout) {
        clearTimeout(this.hideTimeout);
      }
      
      // Auto-hide after 5 seconds
      if (autoHide) {
        this.hideTimeout = setTimeout(() => {
          this.chatBubble.style.animation = 'slideOutLeft 0.5s ease-out';
          setTimeout(() => {
            this.chatBubble.style.display = 'none';
            this.chatBubble.style.animation = 'slideInLeft 0.5s ease-out';
          }, 500);
        }, 5000);
      }
      
      // Speak if Vela is available
      if (window.Vela) {
        window.Vela.speak(message, { interrupt: false });
      }
    }
  }
  
  hideVelaMessage() {
    if (this.chatBubble) {
      this.chatBubble.style.display = 'none';
    }
  }
  
  calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth's radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
  }
  
  async showComparison(origin, destination, departTime) {
    // Calculate distance for smarter message
    const distance = this.calculateDistance(origin.lat, origin.lon, destination.lat, destination.lon);
    
    // Show Vela analyzing message (context-aware)
    let message;
    if (distance < 50) {
      message = "Finding the best routes for you. Just a moment...";
    } else if (distance < 200) {
      message = "Analyzing routes across Kerala. This will just take a moment...";
    } else if (distance < 500) {
      message = "Searching routes across South India. One moment please...";
    } else {
      message = "Analyzing routes across India. This will take a moment...";
    }
    
    this.showVelaMessage(message);
    
    // Fetch all options
    const engine = new ComparisonEngine();
    const routes = await engine.fetchAllOptions(origin, destination, departTime);
    
    if (routes.length === 0) {
      this.showVelaMessage("I couldn't find any routes. Try different locations or transport modes.");
      return;
    }
    
    // Get comparison data
    const comparison = engine.getComparisonData();
    
    // Update Vela message
    const recommended = comparison.find(r => r.recommended);
    if (recommended) {
      this.showVelaMessage(`I found ${routes.length} great options for you! I recommend ${recommended.modeName}. ${recommended.reason}`);
      
      // Announce recommendation
      if (window.Vela) {
        setTimeout(() => {
          window.Vela.announceRecommendation(recommended.mode, recommended.reason);
        }, 1000);
      }
    } else {
      this.showVelaMessage(`I found ${routes.length} options for you! Swipe to compare.`);
    }
    
    // Render comparison cards
    this.renderComparisonCards(comparison);
    
    // Show container and adjust layout
    this.container.style.display = 'block';
    document.body.classList.add('comparison-open');
    
    // Trigger map resize after layout change
    setTimeout(() => {
      if (window.map && window.map.invalidateSize) {
        window.map.invalidateSize();
      }
    }, 350);
    
    // Auto-select first route
    if (comparison.length > 0) {
      await this.selectRouteForViewing(0);
    }
  }
  
  closeComparison() {
    this.container.style.animation = 'slideOutToLeft 0.3s ease-out';
    setTimeout(() => {
      this.container.style.display = 'none';
      this.container.style.animation = 'slideInFromLeft 0.3s ease-out';
      document.body.classList.remove('comparison-open');
      
      // Resize map back
      if (window.map && window.map.invalidateSize) {
        window.map.invalidateSize();
      }
    }, 300);
  }
  
  renderComparisonCards(comparison) {
    const html = `
      <div style="padding: 20px; height: 100%; display: flex; flex-direction: column;">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #f1f5f9;">
          <div>
            <h3 style="margin: 0; font-size: 18px; color: #1a202c; font-weight: 700;">Your Route Options</h3>
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">${comparison.length} routes found</p>
          </div>
          <button onclick="window.ComparisonUI.closeComparison()" style="background: #f1f5f9; border: none; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; color: #64748b; font-size: 20px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">×</button>
        </div>
        
        <!-- Route Cards (All Visible) -->
        <div style="flex: 1; overflow-y: auto; margin-bottom: 16px;">
          ${comparison.map((route, idx) => this.renderSideRouteCard(route, idx)).join('')}
        </div>
        
        <!-- Action Buttons -->
        <div style="padding-top: 16px; border-top: 2px solid #f1f5f9; display: flex; gap: 12px;">
          <button onclick="window.ComparisonUI.closeComparison()" style="flex: 1; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; background: white; color: #64748b; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#cbd5e1'" onmouseout="this.style.borderColor='#e2e8f0'">
            ← New Search
          </button>
          <button id="startNavBtn" style="flex: 1; padding: 12px; border: none; border-radius: 10px; background: linear-gradient(135deg, #3b82f6 0%, #06b6d4 100%); color: white; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
            Start Navigation →
          </button>
        </div>
      </div>
    `;
    
    this.container.innerHTML = html;
    this.currentComparison = comparison;
    this.selectedRouteIndex = 0;
  }
  
  renderSideRouteCard(route, index) {
    const isRecommended = route.recommended;
    const isSelected = index === (this.selectedRouteIndex || 0);
    
    return `
      <div 
        class="side-route-card" 
        data-index="${index}"
        style="
          border: 2px solid ${isSelected ? (isRecommended ? '#10b981' : '#3b82f6') : '#e5e7eb'};
          border-radius: 12px;
          padding: 16px;
          margin-bottom: 12px;
          background: ${isSelected ? (isRecommended ? '#ecfdf5' : '#eff6ff') : 'white'};
          cursor: pointer;
          transition: all 0.2s;
          position: relative;
        "
        onclick="window.ComparisonUI.selectRouteForViewing(${index})"
        onmouseover="if(${index} !== window.ComparisonUI.selectedRouteIndex) this.style.borderColor='#cbd5e1'"
        onmouseout="if(${index} !== window.ComparisonUI.selectedRouteIndex) this.style.borderColor='#e5e7eb'"
      >
        ${isRecommended ? `
          <div style="position: absolute; top: -8px; right: 12px; background: #10b981; color: white; padding: 4px 10px; border-radius: 999px; font-size: 10px; font-weight: 700; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);">
            ⭐ RECOMMENDED
          </div>
        ` : ''}
        
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
          <div style="font-size: 28px;">${route.icon}</div>
          <div style="flex: 1;">
            <div style="font-weight: 700; font-size: 16px; color: #1a202c;">${route.modeName}</div>
            <div style="font-size: 12px; color: #64748b;">${route.legs.length} ${route.legs.length === 1 ? 'leg' : 'legs'}</div>
          </div>
          ${isSelected ? '<div style="color: #10b981; font-size: 20px;">✓</div>' : ''}
        </div>
        
        <div style="display: flex; gap: 16px; margin-bottom: 12px;">
          <div>
            <div style="font-size: 11px; color: #64748b; margin-bottom: 2px;">Fare</div>
            <div style="font-size: 18px; font-weight: 700; color: #1a202c;">₹${route.fare}</div>
          </div>
          <div>
            <div style="font-size: 11px; color: #64748b; margin-bottom: 2px;">Time</div>
            <div style="font-size: 18px; font-weight: 700; color: #1a202c;">${route.time}</div>
          </div>
        </div>
        
        ${isRecommended && route.reason ? `
          <div style="background: white; border-radius: 8px; padding: 10px; border-left: 3px solid #10b981; margin-bottom: 8px;">
            <div style="font-size: 11px; color: #059669; font-weight: 600; margin-bottom: 4px;">Why this route?</div>
            <div style="font-size: 12px; color: #374151; line-height: 1.4;">${route.reason}</div>
          </div>
        ` : ''}
        
        <button 
          style="
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 8px;
            background: ${isSelected ? (isRecommended ? '#10b981' : '#3b82f6') : '#f1f5f9'};
            color: ${isSelected ? 'white' : '#64748b'};
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
          "
          onmouseover="this.style.transform='scale(1.02)'"
          onmouseout="this.style.transform='scale(1)'"
        >
          ${isSelected ? '✓ Viewing on Map' : 'View Route'}
        </button>
      </div>
    `;
  }
  
  renderRouteCard(route, index) {
    const isRecommended = route.recommended;
    const borderColor = isRecommended ? '#10b981' : '#e5e7eb';
    const bgGradient = isRecommended ? 'linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%)' : '#ffffff';
    
    return `
      <div class="route-card" style="
        border: 2px solid ${borderColor};
        border-radius: 16px;
        padding: 20px;
        background: ${bgGradient};
        position: relative;
        cursor: pointer;
        transition: all 0.3s ease;
        animation: fadeIn 0.5s ease-out ${index * 0.1}s both;
      " onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 12px 24px rgba(0,0,0,0.1)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'" onclick="window.ComparisonUI.selectRoute(${index})">
        
        ${isRecommended ? `
          <div style="position: absolute; top: -12px; right: 12px; background: #10b981; color: white; padding: 6px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);">
            ⭐ VELA RECOMMENDS
          </div>
        ` : ''}
        
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
          <div style="font-size: 32px;">${route.icon}</div>
          <div>
            <div style="font-weight: 700; font-size: 18px; color: #1a202c;">${route.modeName}</div>
            <div style="font-size: 13px; color: #64748b;">${route.legs.length} ${route.legs.length === 1 ? 'leg' : 'legs'}</div>
          </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
          <div>
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Fare</div>
            <div style="font-size: 20px; font-weight: 700; color: #1a202c;">₹${route.fare}</div>
          </div>
          <div>
            <div style="font-size: 12px; color: #64748b; margin-bottom: 4px;">Time</div>
            <div style="font-size: 20px; font-weight: 700; color: #1a202c;">${route.time}</div>
          </div>
        </div>
        
        ${isRecommended && route.reason ? `
          <div style="background: white; border-radius: 12px; padding: 12px; margin-bottom: 12px; border-left: 3px solid #10b981;">
            <div style="font-size: 12px; color: #059669; font-weight: 600; margin-bottom: 4px;">Why this route?</div>
            <div style="font-size: 13px; color: #374151; line-height: 1.5;">${route.reason}</div>
          </div>
        ` : ''}
        
        <button style="
          width: 100%;
          padding: 12px;
          border: none;
          border-radius: 10px;
          background: ${isRecommended ? '#10b981' : '#3b82f6'};
          color: white;
          font-weight: 600;
          font-size: 14px;
          cursor: pointer;
          transition: all 0.2s;
        " onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
          ${isRecommended ? 'Choose This Route ⭐' : 'View Details'}
        </button>
      </div>
    `;
  }
  
  async selectRouteForViewing(index) {
    console.log('[ComparisonUI] Viewing route:', index);
    
    if (!this.currentComparison || !this.currentComparison[index]) {
      console.error('[ComparisonUI] No route data for index:', index);
      return;
    }
    
    // Update selected index
    this.selectedRouteIndex = index;
    
    // Re-render cards to show selection
    const comparison = this.currentComparison;
    const cardsContainer = this.container.querySelector('[style*="flex: 1"]');
    if (cardsContainer) {
      cardsContainer.innerHTML = comparison.map((route, idx) => this.renderSideRouteCard(route, idx)).join('');
    }
    
    // Display route on map
    const selectedRoute = this.currentComparison[index];
    await this.displaySelectedRoute(selectedRoute);
    
    // Announce with Vela
    if (window.Vela) {
      window.Vela.speak(`Showing ${selectedRoute.modeName} route on map`, { interrupt: true });
    }
  }
  
  async selectRoute(index) {
    console.log('[ComparisonUI] Route selected:', index);
    
    if (!this.currentComparison || !this.currentComparison[index]) {
      console.error('[ComparisonUI] No route data for index:', index);
      return;
    }
    
    const selectedRoute = this.currentComparison[index];
    console.log('[ComparisonUI] Selected route data:', selectedRoute);
    
    // Confirm with Vela
    if (window.Vela) {
      window.Vela.confirmSelection(selectedRoute.modeName);
    }
    
    // Hide comparison
    this.container.style.display = 'none';
    this.hideVelaMessage();
    
    // Show success notification
    if (window.showNotification) {
      window.showNotification(`Planning ${selectedRoute.modeName} route...`, 'success');
    }
    
    // Actually plan the route with selected mode
    if (selectedRoute.data) {
      // Trigger the actual route planning
      await this.displaySelectedRoute(selectedRoute);
    }
  }
  
  async displaySelectedRoute(route) {
    console.log('[ComparisonUI] Displaying route:', route);
    
    // Check if functions are available
    if (!window.drawSegments) {
      console.error('[ComparisonUI] drawSegments not available!');
      alert('Error: Map functions not loaded. Please refresh the page.');
      return;
    }
    
    // Get the response data
    const resp = route.data;
    console.log('[ComparisonUI] Route data:', resp);
    
    // Draw on map
    const segmentsToDraw = resp.segments || resp.legs || [];
    console.log('[ComparisonUI] Segments to draw:', segmentsToDraw.length);
    
    if (segmentsToDraw.length > 0) {
      try {
        console.log('[ComparisonUI] Calling drawSegments...');
        await window.drawSegments(segmentsToDraw, resp.route_poly);
        console.log('[ComparisonUI] drawSegments completed');
      } catch (e) {
        console.error('[ComparisonUI] Error drawing segments:', e);
      }
    } else {
      console.warn('[ComparisonUI] No segments to draw');
    }
    
    // Render itinerary
    if (window.renderItinerary) {
      try {
        console.log('[ComparisonUI] Calling renderItinerary...');
        window.renderItinerary(resp);
        console.log('[ComparisonUI] renderItinerary completed');
      } catch (e) {
        console.error('[ComparisonUI] Error rendering itinerary:', e);
      }
    } else {
      console.error('[ComparisonUI] renderItinerary not available!');
    }
    
    // Render instructions
    const allSteps = [];
    segmentsToDraw.forEach(s => {
      if (Array.isArray(s.instructions)) {
        allSteps.push(...s.instructions);
      }
    });
    
    console.log('[ComparisonUI] Total steps:', allSteps.length);
    
    if (window.renderInstructions && allSteps.length > 0) {
      try {
        console.log('[ComparisonUI] Calling renderInstructions...');
        window.renderInstructions(allSteps);
        console.log('[ComparisonUI] renderInstructions completed');
      } catch (e) {
        console.error('[ComparisonUI] Error rendering instructions:', e);
      }
    }
    
    // Show results section
    const resultsDiv = document.getElementById('results');
    const itinerarySection = document.getElementById('itinerarySection');
    if (resultsDiv) {
      console.log('[ComparisonUI] Scrolling to results');
      resultsDiv.scrollIntoView({ behavior: 'smooth' });
    }
    if (itinerarySection) {
      itinerarySection.style.display = 'block';
    }
    
    console.log('[ComparisonUI] Display complete!');
  }
}

// Create global instance
window.ComparisonUI = new ComparisonUI();

console.log('[ComparisonUI] Loaded ✅');
