// Global frontend API configuration for Velora
(function(){
  try {
    var loc = window.location;
    var origin = loc.origin || (loc.protocol + '//' + loc.host);
    // Common layouts:
    // 1) Docroot is backend/php/ -> API is origin
    // 2) XAMPP htdocs hosts project at /Velora/ -> API is origin + /Velora/backend/php
    // 3) Served from /backend/php/frontend/* -> API is one level up
    var path = loc.pathname || '/';
    var baseUrl = origin; // default
    if (/\/backend\/php\/frontend\//.test(path)) {
      baseUrl = origin + path.replace(/\/backend\/php\/frontend\/.*/, '/backend/php');
    } else if (/\/Velora\//i.test(path)) {
      // Guess project under /Velora/
      baseUrl = origin + '/Velora/backend/php';
    }
    window.__VELO_API__ = { baseUrl: baseUrl };
  } catch(e){ window.__VELO_API__ = { baseUrl: '' }; }
})();
