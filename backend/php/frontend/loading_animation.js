// Beautiful Loading Animation with AI Taglines for Velora

class LoadingAnimation {
  constructor() {
    this.taglines = [
      "🤖 AI is analyzing 45,000 stations across India...",
      "🚌 Finding the fastest buses for you...",
      "🚂 Checking train schedules...",
      "🚕 Calculating taxi routes...",
      "🗺️ Mapping the perfect journey...",
      "⚡ Optimizing for speed and comfort...",
      "💰 Finding the most economical option...",
      "🌟 Velora AI is working its magic...",
      "🎯 Almost there! Finalizing your route...",
      "✨ Preparing your personalized itinerary..."
    ];
    
    this.currentTaglineIndex = 0;
    this.progressInterval = null;
    this.taglineInterval = null;
  }
  
  show(mode = 'velora') {
    const overlay = document.getElementById('loadingOverlay');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const loadingText = document.getElementById('loadingText');
    
    if (!overlay) return;
    
    // Set mode-specific taglines and animation
    this.setModeTaglines(mode);
    this.addVehicleAnimation(mode);
    
    // Show overlay
    overlay.classList.add('active');
    
    // Reset progress
    if (progressBar) progressBar.style.width = '0%';
    this.currentTaglineIndex = 0;
    
    // Animate progress bar
    let progress = 0;
    this.progressInterval = setInterval(() => {
      progress += Math.random() * 15;
      if (progress > 90) progress = 90; // Stop at 90% until complete
      
      if (progressBar) progressBar.style.width = progress + '%';
      if (progressText) progressText.textContent = Math.round(progress) + '%';
    }, 300);
    
    // Rotate taglines
    this.taglineInterval = setInterval(() => {
      this.currentTaglineIndex = (this.currentTaglineIndex + 1) % this.taglines.length;
      if (loadingText) {
        loadingText.style.opacity = '0';
        setTimeout(() => {
          loadingText.textContent = this.taglines[this.currentTaglineIndex];
          loadingText.style.opacity = '1';
        }, 200);
      }
    }, 2000);
  }
  
  setModeTaglines(mode) {
    const modeTaglines = {
      bus: [
        "🚌 Finding the best bus routes...",
        "🚌 Checking 1000+ bus operators...",
        "🚌 Comparing fares and timings...",
        "🚌 Analyzing bus schedules..."
      ],
      train: [
        "🚂 Checking Indian Railways schedules...",
        "🚂 Finding fastest trains...",
        "🚂 Analyzing 45,000 stations...",
        "🚂 Comparing train options..."
      ],
      flight: [
        "✈️ Searching for flights...",
        "✈️ Comparing airlines...",
        "✈️ Finding best deals...",
        "✈️ Checking flight schedules..."
      ],
      drive: [
        "🚗 Calculating driving route...",
        "🚗 Checking traffic conditions...",
        "🚗 Finding fastest path...",
        "🚗 Analyzing road conditions..."
      ],
      velora: [
        "✨ Analyzing all transport options...",
        "✨ Comparing bus, train, flight, and car...",
        "✨ Finding your perfect route...",
        "✨ Velora AI is working its magic..."
      ]
    };
    
    this.taglines = modeTaglines[mode] || modeTaglines.velora;
  }
  
  addVehicleAnimation(mode) {
    // Remove existing animation
    const existing = document.getElementById('vehicleAnimation');
    if (existing) existing.remove();
    
    const overlay = document.getElementById('loadingOverlay');
    if (!overlay) return;
    
    const vehicle = document.createElement('div');
    vehicle.id = 'vehicleAnimation';
    vehicle.style.cssText = `
      position: absolute;
      top: 40%;
      font-size: 48px;
      animation: driveAcross 3s linear infinite;
    `;
    
    const icons = {
      bus: '🚌',
      train: '🚂',
      flight: '✈️',
      drive: '🚗',
      velora: '✨🚌🚂✈️🚗'
    };
    
    vehicle.textContent = icons[mode] || icons.velora;
    overlay.firstElementChild.appendChild(vehicle);
    
    // Add animation CSS if not exists
    if (!document.getElementById([REDACTED])) {
      const style = document.createElement('style');
      style.id = [REDACTED];
      style.textContent = `
        @keyframes driveAcross {
          0% { left: -100px; }
          100% { left: calc(100% + 100px); }
        }
      `;
      document.head.appendChild(style);
    }
  }
  
  hide() {
    const overlay = document.getElementById('loadingOverlay');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    
    // Complete progress
    if (progressBar) progressBar.style.width = '100%';
    if (progressText) progressText.textContent = '100%';
    
    // Clear intervals
    if (this.progressInterval) clearInterval(this.progressInterval);
    if (this.taglineInterval) clearInterval(this.taglineInterval);
    
    // Hide after brief delay
    setTimeout(() => {
      if (overlay) overlay.classList.remove('active');
    }, 500);
  }
  
  updateProgress(percent, message) {
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const loadingText = document.getElementById('loadingText');
    
    if (progressBar) progressBar.style.width = percent + '%';
    if (progressText) progressText.textContent = Math.round(percent) + '%';
    if (message && loadingText) loadingText.textContent = message;
  }
}

// Create global instance
window.LoadingAnimation = new LoadingAnimation();

// Add smooth transition to loading text
const style = document.createElement('style');
style.textContent = `
  #loadingText {
    transition: opacity 0.3s ease-in-out;
  }
`;
document.head.appendChild(style);

// Service loaded

/* v-sync seq: 12 */