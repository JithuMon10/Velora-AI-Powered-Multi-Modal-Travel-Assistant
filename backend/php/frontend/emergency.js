// Emergency Contacts Feature for Velora
// Quick access to important numbers and location sharing

class EmergencyService {
  constructor() {
    this.contacts = {
      police: { number: '100', name: 'Police', icon: '🚓' },
      ambulance: { number: '108', name: 'Ambulance', icon: '🚑' },
      fire: { number: '101', name: 'Fire Brigade', icon: '🚒' },
      women: { number: '1091', name: 'Women Helpline', icon: '👮‍♀️' },
      railway: { number: '139', name: 'Railway Helpline', icon: '🚂' },
      roadside: { number: '1073', name: 'Roadside Assistance', icon: '🔧' },
      disaster: { number: '1078', name: 'Disaster Management', icon: '⚠️' },
      child: { number: '1098', name: 'Child Helpline', icon: '👶' }
    };
    
    this.createEmergencyButton();
  }
  
  createEmergencyButton() {
    const btn = document.createElement('button');
    btn.id = 'emergencyBtn';
    btn.innerHTML = '🚨';
    btn.title = 'Emergency Contacts';
    btn.style.cssText = `
      position: fixed;
      bottom: 20px;
      left: 20px;
      width: 56px;
      height: 56px;
      border-radius: 50%;
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      border: none;
      font-size: 24px;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
      z-index: 9998;
      transition: all 0.3s;
      animation: pulse 2s infinite;
    `;
    
    btn.onmouseover = () => {
      btn.style.transform = 'scale(1.1)';
      btn.style.boxShadow = '0 6px 16px rgba(239, 68, 68, 0.6)';
    };
    
    btn.onmouseout = () => {
      btn.style.transform = 'scale(1)';
      btn.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.4)';
    };
    
    btn.onclick = () => this.showEmergencyPanel();
    
    document.body.appendChild(btn);
    
    // Add pulse animation
    const style = document.createElement('style');
    style.textContent = `
      @keyframes pulse {
        0%, 100% { box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4); }
        50% { box-shadow: 0 4px 20px rgba(239, 68, 68, 0.8); }
      }
    `;
    document.head.appendChild(style);
  }
  
  showEmergencyPanel() {
    const panel = document.createElement('div');
    panel.id = 'emergencyPanel';
    panel.style.cssText = `
      position: fixed;
      bottom: 90px;
      left: 20px;
      width: 320px;
      max-height: 500px;
      background: white;
      border-radius: 16px;
      box-shadow: 0 10px 40px rgba(0,0,0,0.2);
      z-index: 9999;
      overflow: hidden;
      animation: slideUp 0.3s ease-out;
    `;
    
    panel.innerHTML = `
      <div style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
          <h3 style="margin: 0; font-size: 18px; font-weight: 700;">🚨 Emergency</h3>
          <button onclick="document.getElementById('emergencyPanel').remove()" style="background: rgba(255,255,255,0.2); border: none; color: white; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; font-size: 18px;">×</button>
        </div>
        <p style="margin: 8px 0 0 0; font-size: 13px; opacity: 0.9;">Quick access to help</p>
      </div>
      
      <div style="padding: 16px; max-height: 380px; overflow-y: auto;">
        ${this.renderContacts()}
        ${this.renderLocationShare()}
      </div>
    `;
    
    // Remove existing panel if any
    const existing = document.getElementById('emergencyPanel');
    if (existing) existing.remove();
    
    document.body.appendChild(panel);
  }
  
  renderContacts() {
    return Object.entries(this.contacts).map(([key, contact]) => `
      <div class="emergency-contact" style="
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: all 0.2s;
      " onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1'" onmouseout="this.style.background='white'; this.style.borderColor='#e2e8f0'" onclick="window.EmergencyService.call('${contact.number}', '${contact.name}')">
        <span style="font-size: 24px;">${contact.icon}</span>
        <div style="flex: 1;">
          <div style="font-weight: 600; color: #0f172a; font-size: 14px;">${contact.name}</div>
          <div style="font-size: 12px; color: #64748b;">${contact.number}</div>
        </div>
        <span style="color: #10b981; font-size: 20px;">📞</span>
      </div>
    `).join('');
  }
  
  renderLocationShare() {
    return `
      <div style="margin-top: 16px; padding: 12px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px;">
        <div style="font-weight: 600; color: #166534; margin-bottom: 8px; font-size: 14px;">📍 Share Location</div>
        <button onclick="window.EmergencyService.shareLocation()" style="
          width: 100%;
          padding: 10px;
          background: #10b981;
          color: white;
          border: none;
          border-radius: 8px;
          font-weight: 600;
          cursor: pointer;
          font-size: 13px;
          transition: all 0.2s;
        " onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
          Share My Location
        </button>
        <div style="font-size: 11px; color: #64748b; margin-top: 6px; text-align: center;">
          Send your live location to emergency contacts
        </div>
      </div>
    `;
  }
  
  call(number, name) {
    if (confirm(`Call ${name} at ${number}?`)) {
      window.location.href = `tel:${number}`;
    }
  }
  
  async shareLocation() {
    try {
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject);
      });
      
      const lat = position.coords.latitude;
      const lon = position.coords.longitude;
      const locationUrl = `https://www.google.com/maps?q=${lat},${lon}`;
      
      if (navigator.share) {
        await navigator.share({
          title: 'My Emergency Location',
          text: `I need help! My current location:`,
          url: locationUrl
        });
      } else {
        // Fallback: copy to clipboard
        await navigator.clipboard.writeText(`Emergency! My location: ${locationUrl}`);
        alert('Location copied to clipboard! Share it with your contacts.');
      }
    } catch (error) {
      console.error('[Emergency] Error sharing location:', error);
      alert('Could not access location. Please enable location services.');
    }
  }
}

// Create global instance
window.EmergencyService = new EmergencyService();

// Service loaded
