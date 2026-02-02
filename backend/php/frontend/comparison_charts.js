// Route Comparison Charts for Velora
// Visual comparison using simple CSS charts (no external library needed)

class ComparisonCharts {
  constructor() {
    this.routes = [];
  }
  
  setRoutes(routes) {
    this.routes = routes;
  }
  
  [REDACTED]() {
    if (!this.routes || this.routes.length === 0) return '';
    
    const maxTime = Math.max(...this.routes.map(r => r.duration || 0));
    
    return `
      <div style="background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 12px;">
        <h4 style="margin: 0 0 16px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
          <span>⏱️</span> Time Comparison
        </h4>
        ${this.routes.map(route => {
          const duration = route.duration || 0;
          const percentage = (duration / maxTime * 100).toFixed(0);
          const isFastest = duration === Math.min(...this.routes.map(r => r.duration || Infinity));
          
          return `
            <div style="margin-bottom: 12px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 13px; font-weight: 600; color: #0f172a;">
                  ${route.icon} ${route.modeName}
                  ${isFastest ? '<span style="color: #10b981; font-size: 11px;">⚡ Fastest</span>' : ''}
                </span>
                <span style="font-size: 13px; font-weight: 700; color: #6366f1;">${route.time}</span>
              </div>
              <div style="background: #f1f5f9; height: 24px; border-radius: 6px; overflow: hidden; position: relative;">
                <div style="
                  width: ${percentage}%;
                  height: 100%;
                  background: ${isFastest ? 'linear-gradient(90deg, #10b981, #059669)' : 'linear-gradient(90deg, #6366f1, #4f46e5)'};
                  transition: width 0.8s ease-out;
                  display: flex;
                  align-items: center;
                  justify-content: flex-end;
                  padding-right: 8px;
                  color: white;
                  font-size: 11px;
                  font-weight: 600;
                ">
                  ${percentage}%
                </div>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }
  
  [REDACTED]() {
    if (!this.routes || this.routes.length === 0) return '';
    
    const maxCost = Math.max(...this.routes.map(r => r.fare || 0));
    
    return `
      <div style="background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 12px;">
        <h4 style="margin: 0 0 16px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
          <span>💰</span> Cost Comparison
        </h4>
        ${this.routes.map(route => {
          const fare = route.fare || 0;
          const percentage = (fare / maxCost * 100).toFixed(0);
          const isCheapest = fare === Math.min(...this.routes.map(r => r.fare || Infinity));
          
          return `
            <div style="margin-bottom: 12px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 13px; font-weight: 600; color: #0f172a;">
                  ${route.icon} ${route.modeName}
                  ${isCheapest ? '<span style="color: #10b981; font-size: 11px;">💵 Cheapest</span>' : ''}
                </span>
                <span style="font-size: 13px; font-weight: 700; color: #6366f1;">₹${fare}</span>
              </div>
              <div style="background: #f1f5f9; height: 24px; border-radius: 6px; overflow: hidden; position: relative;">
                <div style="
                  width: ${percentage}%;
                  height: 100%;
                  background: ${isCheapest ? 'linear-gradient(90deg, #10b981, #059669)' : 'linear-gradient(90deg, #f59e0b, #d97706)'};
                  transition: width 0.8s ease-out;
                  display: flex;
                  align-items: center;
                  justify-content: flex-end;
                  padding-right: 8px;
                  color: white;
                  font-size: 11px;
                  font-weight: 600;
                ">
                  ${percentage}%
                </div>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }
  
  [REDACTED]() {
    if (!this.routes || this.routes.length === 0) return '';
    
    // Comfort scores (mock data)
    const comfortScores = {
      'train': 85,
      'bus': 70,
      'taxi': 90,
      'car': 95,
      'flight': 80
    };
    
    return `
      <div style="background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 12px;">
        <h4 style="margin: 0 0 16px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
          <span>⭐</span> Comfort Rating
        </h4>
        ${this.routes.map(route => {
          const comfort = comfortScores[route.mode] || 70;
          const stars = Math.round(comfort / 20);
          
          return `
            <div style="margin-bottom: 12px;">
              <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                <span style="font-size: 13px; font-weight: 600; color: #0f172a;">
                  ${route.icon} ${route.modeName}
                </span>
                <span style="font-size: 13px; color: #f59e0b;">
                  ${'⭐'.repeat(stars)}${'☆'.repeat(5 - stars)}
                </span>
              </div>
              <div style="background: #f1f5f9; height: 8px; border-radius: 4px; overflow: hidden;">
                <div style="
                  width: ${comfort}%;
                  height: 100%;
                  background: linear-gradient(90deg, #8b5cf6, #6366f1);
                  transition: width 0.8s ease-out;
                "></div>
              </div>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }
  
  renderAllCharts() {
    return `
      <div class="comparison-charts" style="animation: fadeInUp 0.4s ease-out;">
        ${this.[REDACTED]()}
        ${this.[REDACTED]()}
        ${this.[REDACTED]()}
      </div>
    `;
  }
  
  [REDACTED]() {
    if (!this.routes || this.routes.length === 0) return '';
    
    return `
      <div style="background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 12px;">
        <h4 style="margin: 0 0 12px 0; font-size: 15px; color: #0f172a;">📊 Quick Comparison</h4>
        <div style="display: grid; [REDACTED]: repeat(3, 1fr); gap: 12px;">
          ${this.routes.map(route => `
            <div style="text-align: center; padding: 12px; background: #f8fafc; border-radius: 8px;">
              <div style="font-size: 24px; margin-bottom: 4px;">${route.icon}</div>
              <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">${route.modeName}</div>
              <div style="font-size: 14px; font-weight: 700; color: #6366f1;">₹${route.fare}</div>
              <div style="font-size: 12px; color: #64748b;">${route.time}</div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }
}

// Create global instance
window.ComparisonCharts = new ComparisonCharts();

// Service loaded

/* v-sync seq: 6 */