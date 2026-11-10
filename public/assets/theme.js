/**
 * EIDOLON PATHWAY — First-Party Interactive Controller
 * Handles theme switching, print actions, and confirmation dialogs.
 * Compliant with strict Content Security Policy (script-src 'self').
 * Zero inline scripts, zero external dependencies, zero FOUC.
 */
(function () {
  'use strict';

  // ── Theme Controller ──

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

  // ── DOM-Ready Initialization ──

  function initThemeAndControls() {
    // Theme switcher buttons
    var current = getSavedTheme();
    var buttons = document.querySelectorAll('[data-theme-set]');
    if (buttons && buttons.length > 0) {
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

    // Print action buttons
    var printButtons = document.querySelectorAll('[data-action="print"]');
    printButtons.forEach(function (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        window.print();
      });
    });

    // Confirmation dialogs for destructive form actions
    var confirmForms = document.querySelectorAll('[data-confirm]');
    confirmForms.forEach(function (form) {
      form.addEventListener('submit', function (e) {
        var message = this.getAttribute('data-confirm');
        if (!window.confirm(message)) {
          e.preventDefault();
        }
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initThemeAndControls);
  } else {
    initThemeAndControls();
  }
})();
