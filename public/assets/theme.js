/**
 * EIDOLON PATHWAY — Accessible Adaptive Theme Controller
 * First-party external script compliant with strict Content Security Policy.
 * Zero inline scripts, zero external dependencies, zero FOUC.
 */
(function () {
  'use strict';

  function getSavedTheme() {
    try {
      return localStorage.getItem('pathway-theme') || 'system';
    } catch (e) {
      return 'system';
    }
  }

  function applyTheme(theme) {
    if (theme === 'light' || theme === 'dark') {
      document.documentElement.setAttribute('data-theme', theme);
    } else {
      document.documentElement.setAttribute('data-theme', 'system');
    }
  }

  // Execute synchronously in <head> to prevent Flash of Unstyled Content (FOUC)
  applyTheme(getSavedTheme());

  // Attach interactive button listeners once DOM is ready
  function initThemeSwitcher() {
    var current = getSavedTheme();
    var buttons = document.querySelectorAll('[data-theme-set]');
    if (!buttons || buttons.length === 0) return;

    buttons.forEach(function (btn) {
      var t = btn.getAttribute('data-theme-set');
      btn.setAttribute('aria-pressed', t === current ? 'true' : 'false');
      btn.addEventListener('click', function () {
        var selected = this.getAttribute('data-theme-set');
        try {
          localStorage.setItem('pathway-theme', selected);
        } catch (e) {}
        applyTheme(selected);
        buttons.forEach(function (b) {
          b.setAttribute('aria-pressed', b.getAttribute('data-theme-set') === selected ? 'true' : 'false');
        });
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeSwitcher);
  } else {
    initThemeSwitcher();
  }
})();
