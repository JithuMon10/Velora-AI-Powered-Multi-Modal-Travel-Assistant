// Cost Breakdown Feature for Velora
// Shows detailed cost analysis with visual charts

class CostBreakdown {
  constructor() {
    this.costs = {
      transport: 0,
      food: 0,
      misc: 0,
      total: 0
    };
  }
  
  calculateCosts(route, duration_hours) {
    // Transport cost from route
    this.costs.transport = route.total_fare || 0;
    
    // Estimate food costs based on duration
    if (duration_hours < 2) {
      this.costs.food = 0; // No meal needed
    } else if (duration_hours < 4) {
      this.costs.food = 50; // Snacks
    } else if (duration_hours < 8) {
      this.costs.food = 150; // One meal + snacks
    } else {
      this.costs.food = 300; // Multiple meals
    }
    
    // Miscellaneous (water, tips, etc.)
    this.costs.misc = Math.round(this.costs.transport * 0.1); // 10% of transport
    
    // Total
    this.costs.total = this.costs.transport + this.costs.food + this.costs.misc;
    
    return this.costs;
  }
  
  renderBreakdown(costs) {
    const transportPercent = (costs.transport / costs.total * 100).toFixed(0);
    const foodPercent = (costs.food / costs.total * 100).toFixed(0);
    const miscPercent = (costs.misc / costs.total * 100).toFixed(0);
    
    return `
      <div class="cost-breakdown" style="background: white; padding: 16px; border-radius: 12px; border: 1px solid #e2e8f0; margin-bottom: 12px;">
        <h4 style="margin: 0 0 12px 0; font-size: 15px; color: #0f172a; display: flex; align-items: center; gap: 8px;">
          <span>💰</span> Cost Breakdown
        </h4>
        
        <!-- Visual Bar -->
        <div style="display: flex; height: 8px; border-radius: 4px; overflow: hidden; margin-bottom: 12px;">
          <div style="width: ${transportPercent}%; background: #3b82f6;" title="Transport: ${transportPercent}%"></div>
          <div style="width: ${foodPercent}%; background: #10b981;" title="Food: ${foodPercent}%"></div>
          <div style="width: ${miscPercent}%; background: #f59e0b;" title="Misc: ${miscPercent}%"></div>
        </div>
        
        <!-- Detailed Costs -->
        <div style="display: flex; flex-direction: column; gap: 8px;">
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #64748b;">
              <span style="width: 8px; height: 8px; border-radius: 50%; background: #3b82f6;"></span>
              Transport
            </span>
            <span style="font-weight: 600; color: #0f172a;">₹${costs.transport}</span>
          </div>
          
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #64748b;">
              <span style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></span>
              Food (est.)
            </span>
            <span style="font-weight: 600; color: #0f172a;">₹${costs.food}</span>
          </div>
          
          <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #64748b;">
              <span style="width: 8px; height: 8px; border-radius: 50%; background: #f59e0b;"></span>
              Misc
            </span>
            <span style="font-weight: 600; color: #0f172a;">₹${costs.misc}</span>
          </div>
          
          <div style="border-top: 2px solid #e2e8f0; padding-top: 8px; margin-top: 4px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="font-weight: 700; color: #0f172a;">Total Estimated</span>
              <span style="font-size: 18px; font-weight: 700; color: #6366f1;">₹${costs.total}</span>
            </div>
          </div>
        </div>
        
        <div style="margin-top: 12px; padding: 10px; background: #f8fafc; border-radius: 8px; font-size: 12px; color: #64748b;">
          💡 <strong>Tip:</strong> Actual costs may vary. Food and misc are estimates based on journey duration.
        </div>
      </div>
    `;
  }
  
  [REDACTED](costs) {
    return `
      <div style="display: flex; gap: 12px; padding: 12px; background: #f8fafc; border-radius: 8px; margin-top: 8px;">
        <div style="flex: 1; text-align: center;">
          <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">Transport</div>
          <div style="font-size: 16px; font-weight: 700; color: #3b82f6;">₹${costs.transport}</div>
        </div>
        <div style="flex: 1; text-align: center;">
          <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">Food</div>
          <div style="font-size: 16px; font-weight: 700; color: #10b981;">₹${costs.food}</div>
        </div>
        <div style="flex: 1; text-align: center;">
          <div style="font-size: 11px; color: #64748b; margin-bottom: 4px;">Total</div>
          <div style="font-size: 16px; font-weight: 700; color: #6366f1;">₹${costs.total}</div>
        </div>
      </div>
    `;
  }
}

// Create global instance
window.CostBreakdown = new CostBreakdown();

// Service loaded

/* v-sync seq: 9 */