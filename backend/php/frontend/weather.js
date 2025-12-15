// Weather Integration for Velora
// Uses OpenWeatherMap API (free tier)

class WeatherService {
  constructor() {
    // Free API key - you can get your own at openweathermap.org
    this.apiKey = ''; // Add your key or use mock data
    this.baseUrl = 'https://api.openweathermap.org/data/2.5/weather';
    this.cache = new Map();
    this.cacheTimeout = 30 * 60 * 1000; // 30 minutes
  }
  
  async getWeather(lat, lon, cityName) {
    const cacheKey = `${lat},${lon}`;
    
    // Check cache first
    const cached = this.cache.get(cacheKey);
    if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
      return cached.data;
    }
    
    // Use mock data if no API key
    if (!this.apiKey) {
      return this.getMockWeather(cityName);
    }
    
    try {
      const url = `${this.baseUrl}?lat=${lat}&lon=${lon}&appid=${this.apiKey}&units=metric`;
      const response = await fetch(url);
      const data = await response.json();
      
      const weather = {
        temp: Math.round(data.main.temp),
        feels_like: Math.round(data.main.feels_like),
        description: data.weather[0].description,
        icon: data.weather[0].icon,
        humidity: data.main.humidity,
        wind_speed: data.wind.speed,
        city: data.name
      };
      
      // Cache the result
      this.cache.set(cacheKey, {
        data: weather,
        timestamp: Date.now()
      });
      
      return weather;
    } catch (error) {
      console.error('[Weather] Error fetching weather:', error);
      return this.getMockWeather(cityName);
    }
  }
  
  getMockWeather(cityName) {
    // Realistic mock data for Kerala cities
    const mockData = {
      'Kochi': { temp: 28, feels_like: 32, description: 'partly cloudy', icon: '02d', humidity: 75, wind_speed: 3.5 },
      'Thiruvananthapuram': { temp: 29, feels_like: 33, description: 'sunny', icon: '01d', humidity: 70, wind_speed: 4.2 },
      'Kozhikode': { temp: 27, feels_like: 31, description: 'light rain', icon: '10d', humidity: 80, wind_speed: 5.1 },
      'Thrissur': { temp: 28, feels_like: 32, description: 'cloudy', icon: '03d', humidity: 72, wind_speed: 3.8 },
      'Kollam': { temp: 29, feels_like: 33, description: 'sunny', icon: '01d', humidity: 68, wind_speed: 4.0 },
      'Palakkad': { temp: 30, feels_like: 34, description: 'hot and sunny', icon: '01d', humidity: 65, wind_speed: 2.5 },
      'Alappuzha': { temp: 28, feels_like: 32, description: 'humid', icon: '02d', humidity: 82, wind_speed: 3.2 },
      'Kannur': { temp: 27, feels_like: 30, description: 'pleasant', icon: '02d', humidity: 74, wind_speed: 4.5 },
      'Kottayam': { temp: 26, feels_like: 29, description: 'cool and cloudy', icon: '03d', humidity: 78, wind_speed: 3.0 },
      'Ernakulam': { temp: 28, feels_like: 32, description: 'partly cloudy', icon: '02d', humidity: 75, wind_speed: 3.5 }
    };
    
    const weather = mockData[cityName] || mockData['Kochi'];
    return { ...weather, city: cityName };
  }
  
  getWeatherIcon(iconCode) {
    const icons = {
      '01d': '☀️', '01n': '🌙',
      '02d': '⛅', '02n': '☁️',
      '03d': '☁️', '03n': '☁️',
      '04d': '☁️', '04n': '☁️',
      '09d': '🌧️', '09n': '🌧️',
      '10d': '🌦️', '10n': '🌧️',
      '11d': '⛈️', '11n': '⛈️',
      '13d': '❄️', '13n': '❄️',
      '50d': '🌫️', '50n': '🌫️'
    };
    return icons[iconCode] || '🌤️';
  }
  
  getWeatherAdvice(weather) {
    const temp = weather.temp;
    const desc = weather.description.toLowerCase();
    
    if (desc.includes('rain')) {
      return "🌧️ It's raining! Carry an umbrella and allow extra travel time.";
    } else if (temp > 32) {
      return "🌡️ It's hot! Stay hydrated and use sunscreen.";
    } else if (temp < 20) {
      return "🧥 It's cool! You might want to bring a light jacket.";
    } else if (weather.humidity > 80) {
      return "💧 High humidity! Expect muggy conditions.";
    } else {
      return "✨ Perfect weather for travel!";
    }
  }
  
  renderWeatherCard(weather) {
    const icon = this.getWeatherIcon(weather.icon);
    const advice = this.getWeatherAdvice(weather);
    
    return `
      <div class="weather-card" style="
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 12px;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
      ">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 8px;">
          <span style="font-size: 32px;">${icon}</span>
          <div>
            <div style="font-size: 24px; font-weight: 700;">${weather.temp}°C</div>
            <div style="font-size: 13px; opacity: 0.9;">${weather.city}</div>
          </div>
          <div style="margin-left: auto; text-align: right;">
            <div style="font-size: 12px; opacity: 0.8;">Feels like</div>
            <div style="font-size: 18px; font-weight: 600;">${weather.feels_like}°C</div>
          </div>
        </div>
        <div style="font-size: 13px; opacity: 0.9; text-transform: capitalize; margin-bottom: 8px;">
          ${weather.description}
        </div>
        <div style="background: rgba(255,255,255,0.2); padding: 10px; border-radius: 8px; font-size: 13px;">
          ${advice}
        </div>
      </div>
    `;
  }
}

// Create global instance
window.WeatherService = new WeatherService();

// Weather service loaded
