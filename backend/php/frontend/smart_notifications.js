// Smart Notifications for Velora
// Proactive alerts and reminders

class SmartNotifications {
  constructor() {
    this.enabled = false;
    this.scheduledNotifications = [];
    this.requestPermission();
  }
  
  async requestPermission() {
    if ('Notification' in window) {
      const permission = await Notification.requestPermission();
      this.enabled = permission === 'granted';
      console.log('[Notifications] Permission:', permission);
    }
  }
  
  scheduleLeaveReminder(departureTime, origin, destination) {
    if (!this.enabled) return;
    
    const now = new Date();
    const departure = new Date(departureTime);
    const timeUntilDeparture = departure - now;
    
    // Notify 15 minutes before
    const reminderTime = timeUntilDeparture - (15 * 60 * 1000);
    
    if (reminderTime > 0) {
      const timeout = setTimeout(() => {
        this.notify(
          '⏰ Time to Leave!',
          `Leave in 15 minutes to reach ${destination} on time.`,
          { tag: 'leave-reminder' }
        );
      }, reminderTime);
      
      this.scheduledNotifications.push(timeout);
    }
    
    // Notify 5 minutes before
    const urgentReminderTime = timeUntilDeparture - (5 * 60 * 1000);
    
    if (urgentReminderTime > 0) {
      const timeout = setTimeout(() => {
        this.notify(
          '🚨 Leaving Soon!',
          `Leave NOW to reach ${destination} on time!`,
          { tag: 'urgent-reminder', requireInteraction: true }
        );
      }, urgentReminderTime);
      
      this.scheduledNotifications.push(timeout);
    }
  }
  
  notifyTrafficUpdate(severity, route) {
    if (!this.enabled) return;
    
    const messages = {
      high: {
        title: '🔴 Heavy Traffic Alert!',
        body: `Traffic is building up on your route. Consider leaving earlier or taking an alternate route.`
      },
      medium: {
        title: '🟠 Moderate Traffic',
        body: `Some traffic on your route. Your journey might take 10-15 minutes longer.`
      },
      low: {
        title: '🟢 Clear Roads!',
        body: `Traffic is light. Perfect time to travel!`
      }
    };
    
    const msg = messages[severity] || messages.low;
    this.notify(msg.title, msg.body, { tag: 'traffic-update' });
  }
  
  notifyBusArrival(busName, minutes) {
    if (!this.enabled) return;
    
    this.notify(
      `🚌 ${busName} Arriving Soon`,
      `Your bus will arrive in ${minutes} minutes. Get ready!`,
      { tag: 'bus-arrival', requireInteraction: true }
    );
  }
  
  notifyWeatherAlert(weather) {
    if (!this.enabled) return;
    
    if (weather.description.includes('rain')) {
      this.notify(
        '🌧️ Rain Alert!',
        `It's raining at your destination. Don't forget your umbrella!`,
        { tag: 'weather-alert' }
      );
    } else if (weather.temp > 35) {
      this.notify(
        '🌡️ Heat Alert!',
        `Very hot weather (${weather.temp}°C). Stay hydrated!`,
        { tag: 'weather-alert' }
      );
    }
  }
  
  notifyRouteSaved(routeName) {
    this.showInlineNotification(
      `⭐ Route saved: ${routeName}`,
      'success'
    );
  }
  
  notifyBookingAvailable(mode, fare) {
    if (!this.enabled) return;
    
    this.notify(
      `🎫 Booking Available`,
      `Book your ${mode} ticket now for ₹${fare}. Tap to book!`,
      { 
        tag: 'booking-available',
        actions: [
          { action: 'book', title: 'Book Now' },
          { action: 'later', title: 'Later' }
        ]
      }
    );
  }
  
  notify(title, body, options = {}) {
    if (!this.enabled) {
      // Fallback to inline notification
      this.showInlineNotification(body, 'info');
      return;
    }
    
    const notification = new Notification(title, {
      body: body,
      icon: '/favicon.ico',
      badge: '/favicon.ico',
      vibrate: [200, 100, 200],
      ...options
    });
    
    notification.onclick = () => {
      window.focus();
      notification.close();
    };
    
    // Auto-close after 10 seconds
    setTimeout(() => notification.close(), 10000);
  }
  
  showInlineNotification(message, type = 'info') {
    const colors = {
      success: '#10b981',
      error: '#ef4444',
      warning: '#f59e0b',
      info: '#3b82f6'
    };
    
    const notification = document.createElement('div');
    notification.className = 'inline-notification';
    notification.style.cssText = `
      position: fixed;
      top: 90px;
      right: 20px;
      background: ${colors[type]};
      color: white;
      padding: 16px 20px;
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.2);
      z-index: 10000;
      max-width: 350px;
      animation: slideInRight 0.3s ease-out;
      font-size: 14px;
      font-weight: 500;
    `;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Add animation
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
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease-out';
      setTimeout(() => notification.remove(), 300);
    }, 5000);
  }
  
  clearAll() {
    this.scheduledNotifications.forEach(timeout => clearTimeout(timeout));
    this.scheduledNotifications = [];
  }
  
  // Smart suggestions based on time
  suggestDepartureTime(arrivalTime, duration) {
    const arrival = new Date(arrivalTime);
    const now = new Date();
    const durationMs = duration * 60 * 1000;
    const suggestedDeparture = new Date(arrival - durationMs);
    
    // Add buffer for traffic
    const hour = suggestedDeparture.getHours();
    let buffer = 0;
    
    if ((hour >= 7 && hour <= 10) || (hour >= 17 && hour <= 20)) {
      buffer = 30 * 60 * 1000; // 30 min buffer during rush hour
      this.showInlineNotification(
        '⚠️ Rush hour! Added 30 minutes buffer to your departure time.',
        'warning'
      );
    } else if (hour >= 12 && hour <= 14) {
      buffer = 15 * 60 * 1000; // 15 min buffer during lunch
    }
    
    const finalDeparture = new Date(suggestedDeparture - buffer);
    
    // Check if we need to leave soon
    const timeUntilDeparture = finalDeparture - now;
    if (timeUntilDeparture < 30 * 60 * 1000 && timeUntilDeparture > 0) {
      this.showInlineNotification(
        `⏰ You should leave in ${Math.round(timeUntilDeparture / 60000)} minutes!`,
        'warning'
      );
    }
    
    return finalDeparture;
  }
}

// Create global instance
window.SmartNotifications = new SmartNotifications();

// Service loaded
