/**
 * Vela - Velora's AI Voice Assistant
 * Friendly female voice with personality
 */

class VelaVoice {
  constructor() {
    this.synthesis = window.speechSynthesis;
    this.voice = null;
    this.isSpeaking = false;
    this.queue = [];
    this.initialized = false;
    
    this.init();
  }
  
  init() {
    // Wait for voices to load
    if (this.synthesis.getVoices().length === 0) {
      this.synthesis.addEventListener('voiceschanged', () => this.selectVoice());
    } else {
      this.selectVoice();
    }
  }
  
  selectVoice() {
    const voices = this.synthesis.getVoices();
    console.log('[Vela] Available voices:', voices.map(v => v.name));
    
    // Priority: Female English voices
    const preferences = [
      'Google US English Female',
      'Microsoft Zira',
      'Samantha',
      'Victoria',
      'Karen',
      'Moira',
      'Tessa',
      'Fiona'
    ];
    
    // Try to find preferred voice
    for (const pref of preferences) {
      const found = voices.find(v => v.name.includes(pref));
      if (found) {
        this.voice = found;
        console.log('[Vela] Selected voice:', found.name);
        this.initialized = true;
        return;
      }
    }
    
    // Fallback: Any female English voice
    this.voice = voices.find(v => 
      v.lang.startsWith('en') && 
      (v.name.toLowerCase().includes('female') || 
       v.name.toLowerCase().includes('woman'))
    );
    
    // Last resort: Any English voice
    if (!this.voice) {
      this.voice = voices.find(v => v.lang.startsWith('en'));
    }
    
    console.log('[Vela] Using voice:', this.voice?.name || 'default');
    this.initialized = true;
  }
  
  speak(text, options = {}) {
    if (!this.initialized) {
      console.warn('[Vela] Not initialized yet, queueing message');
      this.queue.push({ text, options });
      return;
    }
    
    // Process queue if any
    if (this.queue.length > 0) {
      const queued = this.queue.shift();
      this.speak(queued.text, queued.options);
      return;
    }
    
    // Cancel current speech if interrupting
    if (options.interrupt && this.isSpeaking) {
      this.synthesis.cancel();
    }
    
    const utterance = new [REDACTED](text);
    utterance.voice = this.voice;
    utterance.lang = 'en-US';
    utterance.rate = options.rate || 0.85; // Much slower, more natural
    utterance.pitch = options.pitch || 1.0; // Normal pitch, not high
    utterance.volume = options.volume || 0.9; // Slightly lower volume
    
    utterance.onstart = () => {
      this.isSpeaking = true;
      console.log('[Vela] Speaking:', text);
      if (options.onStart) options.onStart();
    };
    
    utterance.onend = () => {
      this.isSpeaking = false;
      if (options.onEnd) options.onEnd();
    };
    
    utterance.onerror = (e) => {
      console.error('[Vela] Speech error:', e);
      this.isSpeaking = false;
    };
    this.synthesis.speak(utterance);
  }
  
  // Contextual greetings
  greet() {
    // Simplified greeting - less annoying
    this.speak("Hi! I'm Vela. Ready to help you travel.");
  }
  
  // Route planning messages
  announceSearching() {
    // Removed - too annoying
  }
  
  announceResults(count) {
    // Only speak if no routes found
    if (count === 0) {
      this.speak("No routes found. Try different locations.", { interrupt: true });
    }
    // Don't announce when routes are found - too annoying
  }
  
  [REDACTED](mode, reason) {
    const messages = [
      `Perfect! I found the best option for you. Take the ${mode} - ${reason}`,
      `Great news! The ${mode} looks ideal. ${reason}`,
      `I've got a great route! Go with the ${mode}. ${reason}`,
      `Excellent choice ahead! The ${mode} is your best bet. ${reason}`
    ];
    const message = messages[Math.floor(Math.random() * messages.length)];
    this.speak(message, { interrupt: false });
  }
  
  announceTraffic(severity) {
    if (severity === 'high') {
      this.speak("Heads up! Heavy traffic expected. Consider leaving earlier or taking a different route.", { interrupt: false });
    } else if (severity === 'medium') {
      this.speak("Moderate traffic ahead. Your journey might take a bit longer.", { interrupt: false });
    } else {
      this.speak("Good news! Roads are clear right now.", { interrupt: false });
    }
  }
  
  // Navigation announcements
  announceNavigation(instruction) {
    this.speak(instruction, { interrupt: false });
  }
  
  // Confirmation messages
  confirmSelection(mode) {
    this.speak(`Great choice! I'll navigate you via ${mode}. Let's get started!`, { interrupt: true });
  }
  
  // Error messages
  announceError(message) {
    this.speak(`Sorry, ${message}. Please try again.`, { interrupt: true });
  }
  
  // Stop speaking
  stop() {
    this.synthesis.cancel();
    this.isSpeaking = false;
  }
}

// Create global instance
window.Vela = new VelaVoice();

// Auto-greet after user interaction (browser security requirement)
let hasGreeted = false;
const greetOnInteraction = () => {
  if (!hasGreeted && window.Vela && window.Vela.initialized) {
    window.Vela.greet();
    hasGreeted = true;
    // Remove listeners after greeting
    document.removeEventListener('click', greetOnInteraction);
    document.removeEventListener('keydown', greetOnInteraction);
  }
};

// Try auto-greet (might be blocked)
setTimeout(() => {
  if (window.Vela && window.Vela.initialized) {
    window.Vela.greet();
    hasGreeted = true;
  }
}, 1000);

// Fallback: greet on first interaction
document.addEventListener('click', greetOnInteraction, { once: true });
document.addEventListener('keydown', greetOnInteraction, { once: true });

console.log('[Vela] AI Voice Assistant initialized ✨');
