/**
 * EIDOLON FUTUREGLASS — 3D Career Constellation & Micro-Interactions Engine
 * Locally bundled, zero external CDN, zero security dependencies.
 * Compliant with strict CSP (script-src 'self').
 * Features:
 *   - 3D Projected Career Constellation (Core, Skills, Evidence, Readiness, Opportunities)
 *   - Smooth pointer/touch tracking with inertia & dampening (lerp)
 *   - Interactive node inspection HUD & ripple shockwaves
 *   - 3D perspective card tilt with specular light reflection
 *   - Reduced-motion accessibility (@media prefers-reduced-motion)
 *   - Battery-friendly IntersectionObserver lifecycle management
 */
(function () {
  'use strict';

  // ── Reduced Motion Detection ──
  var prefersReducedMotion = false;
  if (window.matchMedia) {
    var motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
    prefersReducedMotion = motionQuery.matches;
    motionQuery.addEventListener('change', function (e) {
      prefersReducedMotion = e.matches;
    });
  }

  // ── 3D Mathematical Projection Engine ──
  function initConstellation(canvas) {
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    if (!ctx) return;

    var width = canvas.clientWidth || 600;
    var height = canvas.clientHeight || 380;
    var dpr = window.devicePixelRatio || 1;

    function resize() {
      var rect = canvas.getBoundingClientRect();
      width = rect.width || 600;
      height = rect.height || 380;
      canvas.width = Math.floor(width * dpr);
      canvas.height = Math.floor(height * dpr);
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    }
    resize();
    window.addEventListener('resize', resize);

    // Constellation Graph Definition
    var nodes = [
      {
        id: 'core',
        label: 'EIDOLON CORE',
        subtitle: 'Cryptographic Provenance Engine',
        metric: 'Zero Self-Attestation',
        x: 0, y: 0, z: 0,
        radius: 20,
        color: '#42E8C0',
        glow: 'rgba(66, 232, 192, 0.6)',
        type: 'core'
      },
      {
        id: 'evidence',
        label: 'GitHub Evidence',
        subtitle: 'Commit-Pinned AST Analysis',
        metric: '12 Lexical Patterns Pinned',
        x: 135, y: -65, z: 45,
        radius: 14,
        color: '#42E8C0',
        glow: 'rgba(66, 232, 192, 0.45)',
        type: 'evidence'
      },
      {
        id: 'skills',
        label: 'Verified Skills',
        subtitle: 'Deep Competency Graph',
        metric: '94% Benchmark Match',
        x: -145, y: -50, z: -30,
        radius: 13,
        color: '#6A9CFF',
        glow: 'rgba(106, 156, 255, 0.45)',
        type: 'skill'
      },
      {
        id: 'readiness',
        label: 'Career Readiness',
        subtitle: 'Multidimensional Telemetry',
        metric: 'Tier 1 Placement Ready',
        x: 115, y: 85, z: -50,
        radius: 13,
        color: '#38BDF8',
        glow: 'rgba(56, 189, 248, 0.45)',
        type: 'readiness'
      },
      {
        id: 'jobs',
        label: 'Employment Pipeline',
        subtitle: 'Direct Institutional Matches',
        metric: '18 Active Listings',
        x: -125, y: 95, z: 50,
        radius: 13,
        color: '#A78BFA',
        glow: 'rgba(167, 139, 250, 0.45)',
        type: 'jobs'
      },
      {
        id: 'college',
        label: 'Campus Verification',
        subtitle: 'Academic Partner Consent',
        metric: 'DSATM Consented',
        x: 0, y: -130, z: 20,
        radius: 11,
        color: '#34D399',
        glow: 'rgba(52, 211, 153, 0.45)',
        type: 'college'
      }
    ];

    // Connect nodes into an informative career architecture graph
    var edges = [
      ['core', 'evidence'],
      ['core', 'skills'],
      ['core', 'readiness'],
      ['core', 'jobs'],
      ['core', 'college'],
      ['evidence', 'readiness'],
      ['skills', 'readiness'],
      ['readiness', 'jobs'],
      ['college', 'skills'],
      ['college', 'evidence']
    ];

    // Background floating data particles
    var ambientParticles = [];
    var particleCount = 42;
    for (var p = 0; p < particleCount; p++) {
      var angle = Math.random() * Math.PI * 2;
      var dist = 60 + Math.random() * 180;
      var elevation = (Math.random() - 0.5) * 160;
      ambientParticles.push({
        x: Math.cos(angle) * dist,
        y: (Math.random() - 0.5) * 140,
        z: Math.sin(angle) * dist,
        radius: 1 + Math.random() * 2,
        speed: 0.002 + Math.random() * 0.004,
        angle: angle,
        dist: dist,
        color: Math.random() > 0.5 ? '#42E8C0' : '#6A9CFF',
        alpha: 0.2 + Math.random() * 0.5
      });
    }

    // Energy packets travelling along edges
    var packets = [];
    edges.forEach(function (edge, idx) {
      packets.push({
        from: edge[0],
        to: edge[1],
        progress: (idx * 0.15) % 1,
        speed: 0.006 + (idx % 3) * 0.002,
        color: idx % 2 === 0 ? '#42E8C0' : '#6A9CFF'
      });
    });

    // 3D Camera & Orientation State
    var rotX = -0.15;
    var rotY = 0.25;
    var targetRotX = -0.15;
    var targetRotY = 0.25;
    var isDragging = false;
    var lastMouseX = 0;
    var lastMouseY = 0;
    var hoveredNode = null;
    var activeRipples = [];
    var focalLength = 340;
    var isVisible = true;
    var animFrame = null;
    var basePulse = 0;

    // Viewport Intersection Observer for Battery Efficiency
    if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          isVisible = entry.isIntersecting;
          if (isVisible && !animFrame) {
            lastTime = performance.now();
            loop(lastTime);
          }
        });
      }, { threshold: 0.05 });
      observer.observe(canvas);
    }

    // Interactive pointer handling
    canvas.addEventListener('mousedown', function (e) {
      isDragging = true;
      lastMouseX = e.clientX;
      lastMouseY = e.clientY;
    });

    window.addEventListener('mouseup', function () {
      isDragging = false;
    });

    canvas.addEventListener('mousemove', function (e) {
      var rect = canvas.getBoundingClientRect();
      var mx = e.clientX - rect.left;
      var my = e.clientY - rect.top;

      if (isDragging) {
        var dx = e.clientX - lastMouseX;
        var dy = e.clientY - lastMouseY;
        targetRotY += dx * 0.008;
        targetRotX += dy * 0.008;
        lastMouseX = e.clientX;
        lastMouseY = e.clientY;
      } else {
        // Subtle parallax track
        var normX = (mx - width / 2) / (width / 2);
        var normY = (my - height / 2) / (height / 2);
        targetRotY = normX * 0.45;
        targetRotX = -normY * 0.35;
      }

      // Check node hover
      var found = null;
      for (var i = 0; i < nodes.length; i++) {
        var n = nodes[i];
        if (n.proj) {
          var dist = Math.hypot(mx - n.proj.x, my - n.proj.y);
          if (dist < (n.radius * n.proj.scale + 10)) {
            found = n;
            break;
          }
        }
      }
      hoveredNode = found;
      canvas.style.cursor = found ? 'pointer' : (isDragging ? 'grabbing' : 'grab');
    });

    canvas.addEventListener('click', function (e) {
      if (hoveredNode) {
        // Trigger luminous ripple shockwave from clicked node
        activeRipples.push({
          x: hoveredNode.proj.x,
          y: hoveredNode.proj.y,
          radius: 8,
          maxRadius: 100,
          color: hoveredNode.color,
          alpha: 1
        });
      }
    });

    // Touch Support for mobile viewports
    canvas.addEventListener('touchstart', function (e) {
      if (e.touches.length === 1) {
        isDragging = true;
        lastMouseX = e.touches[0].clientX;
        lastMouseY = e.touches[0].clientY;
      }
    }, { passive: true });

    canvas.addEventListener('touchmove', function (e) {
      if (isDragging && e.touches.length === 1) {
        var dx = e.touches[0].clientX - lastMouseX;
        var dy = e.touches[0].clientY - lastMouseY;
        targetRotY += dx * 0.01;
        targetRotX += dy * 0.01;
        lastMouseX = e.touches[0].clientX;
        lastMouseY = e.touches[0].clientY;
      }
    }, { passive: true });

    canvas.addEventListener('touchend', function () {
      isDragging = false;
    });

    // 3D Transform Projection Helper
    function project(x, y, z, cx, cy) {
      // Rotation around Y
      var cosY = Math.cos(rotY);
      var sinY = Math.sin(rotY);
      var x1 = x * cosY + z * sinY;
      var z1 = -x * sinY + z * cosY;

      // Rotation around X
      var cosX = Math.cos(rotX);
      var sinX = Math.sin(rotX);
      var y2 = y * cosX - z1 * sinX;
      var z2 = y * sinX + z1 * cosX;

      var depth = focalLength + z2;
      var scale = focalLength / Math.max(depth, 40);
      return {
        x: cx + x1 * scale,
        y: cy + y2 * scale,
        scale: scale,
        z: z2
      };
    }

    var lastTime = performance.now();

    function loop(now) {
      if (!isVisible) {
        animFrame = null;
        return;
      }

      var dt = (now - lastTime) / 1000;
      lastTime = now;

      // Smooth camera interpolation
      if (!prefersReducedMotion) {
        rotX += (targetRotX - rotX) * 0.08;
        rotY += (targetRotY - rotY) * 0.08;
        // Idle gentle rotation
        if (!isDragging) {
          targetRotY += 0.002;
        }
        basePulse += dt * 2.2;
      } else {
        rotX = -0.15;
        rotY = 0.25;
      }

      ctx.clearRect(0, 0, width, height);

      var cx = width / 2;
      var cy = height / 2;

      // Update & Draw Ambient Particles
      ctx.save();
      for (var p = 0; p < ambientParticles.length; p++) {
        var pt = ambientParticles[p];
        if (!prefersReducedMotion) {
          pt.angle += pt.speed;
          pt.x = Math.cos(pt.angle) * pt.dist;
          pt.z = Math.sin(pt.angle) * pt.dist;
        }
        var proj = project(pt.x, pt.y, pt.z, cx, cy);
        if (proj.scale > 0) {
          ctx.beginPath();
          ctx.arc(proj.x, proj.y, pt.radius * proj.scale, 0, Math.PI * 2);
          ctx.fillStyle = pt.color;
          ctx.globalAlpha = Math.min(1, Math.max(0.1, pt.alpha * proj.scale));
          ctx.fill();
        }
      }
      ctx.restore();

      // Project all main nodes
      var nodeMap = {};
      nodes.forEach(function (n) {
        n.proj = project(n.x, n.y, n.z, cx, cy);
        nodeMap[n.id] = n;
      });

      // Draw Edges (Filaments)
      ctx.save();
      edges.forEach(function (edge) {
        var nA = nodeMap[edge[0]];
        var nB = nodeMap[edge[1]];
        if (nA && nB && nA.proj && nB.proj) {
          var isEdgeActive = (hoveredNode && (hoveredNode.id === nA.id || hoveredNode.id === nB.id));
          var grad = ctx.createLinearGradient(nA.proj.x, nA.proj.y, nB.proj.x, nB.proj.y);
          grad.addColorStop(0, nA.color);
          grad.addColorStop(1, nB.color);

          ctx.beginPath();
          ctx.moveTo(nA.proj.x, nA.proj.y);
          ctx.lineTo(nB.proj.x, nB.proj.y);
          ctx.strokeStyle = grad;
          ctx.lineWidth = isEdgeActive ? 2.5 : 1.2;
          ctx.globalAlpha = isEdgeActive ? 0.85 : 0.28;
          ctx.stroke();
        }
      });
      ctx.restore();

      // Draw Moving Energy Packets
      if (!prefersReducedMotion) {
        ctx.save();
        packets.forEach(function (pkt) {
          pkt.progress += pkt.speed;
          if (pkt.progress > 1) pkt.progress = 0;

          var nA = nodeMap[pkt.from];
          var nB = nodeMap[pkt.to];
          if (nA && nB && nA.proj && nB.proj) {
            var px = nA.proj.x + (nB.proj.x - nA.proj.x) * pkt.progress;
            var py = nA.proj.y + (nB.proj.y - nA.proj.y) * pkt.progress;
            ctx.beginPath();
            ctx.arc(px, py, 2.5, 0, Math.PI * 2);
            ctx.fillStyle = pkt.color;
            ctx.shadowColor = pkt.color;
            ctx.shadowBlur = 8;
            ctx.globalAlpha = 0.9;
            ctx.fill();
          }
        });
        ctx.restore();
      }

      // Sort nodes by Z-depth for correct painter's rendering
      var sortedNodes = nodes.slice().sort(function (a, b) {
        return (a.proj ? a.proj.z : 0) - (b.proj ? b.proj.z : 0);
      });

      // Draw Nodes
      sortedNodes.forEach(function (n) {
        var p = n.proj;
        if (!p) return;

        var isHovered = (hoveredNode && hoveredNode.id === n.id);
        var r = n.radius * p.scale * (isHovered ? 1.3 : 1);

        ctx.save();

        // Outer Pulsing Corona for Core
        if (n.type === 'core') {
          var pulseR = r + Math.sin(basePulse) * 6;
          var ringGrad = ctx.createRadialGradient(p.x, p.y, r * 0.5, p.x, p.y, pulseR * 2.2);
          ringGrad.addColorStop(0, 'rgba(66, 232, 192, 0.45)');
          ringGrad.addColorStop(0.5, 'rgba(66, 232, 192, 0.15)');
          ringGrad.addColorStop(1, 'rgba(66, 232, 192, 0)');

          ctx.beginPath();
          ctx.arc(p.x, p.y, pulseR * 2.2, 0, Math.PI * 2);
          ctx.fillStyle = ringGrad;
          ctx.fill();

          // Orbital Rings around Core
          ctx.beginPath();
          ctx.ellipse(p.x, p.y, r * 1.8, r * 0.9, rotY * 0.8, 0, Math.PI * 2);
          ctx.strokeStyle = 'rgba(66, 232, 192, 0.4)';
          ctx.lineWidth = 1;
          ctx.stroke();
        }

        // Luminous Node Halo
        var haloGrad = ctx.createRadialGradient(p.x, p.y, r * 0.2, p.x, p.y, r * 1.8);
        haloGrad.addColorStop(0, n.color);
        haloGrad.addColorStop(0.6, n.glow);
        haloGrad.addColorStop(1, 'rgba(0, 0, 0, 0)');

        ctx.beginPath();
        ctx.arc(p.x, p.y, r * 1.8, 0, Math.PI * 2);
        ctx.fillStyle = haloGrad;
        ctx.fill();

        // Node Solid Sphere
        var bodyGrad = ctx.createRadialGradient(p.x - r * 0.3, p.y - r * 0.3, 1, p.x, p.y, r);
        bodyGrad.addColorStop(0, '#FFFFFF');
        bodyGrad.addColorStop(0.4, n.color);
        bodyGrad.addColorStop(1, '#060B16');

        ctx.beginPath();
        ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
        ctx.fillStyle = bodyGrad;
        ctx.fill();

        // Fine Luminous Border
        ctx.beginPath();
        ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
        ctx.strokeStyle = isHovered ? '#FFFFFF' : n.color;
        ctx.lineWidth = isHovered ? 2 : 1;
        ctx.stroke();

        // Node Label in 3D Space
        ctx.font = (isHovered ? 'bold 12px' : '11px') + ' -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.fillStyle = isHovered ? '#FFFFFF' : '#EBF6FF';
        ctx.textAlign = 'center';
        ctx.shadowColor = 'rgba(0,0,0,0.8)';
        ctx.shadowBlur = 6;
        ctx.fillText(n.label, p.x, p.y + r + 14);

        if (isHovered || n.type === 'core') {
          ctx.font = '10px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
          ctx.fillStyle = n.color;
          ctx.fillText(n.metric, p.x, p.y + r + 26);
        }

        ctx.restore();
      });

      // Render Active Ripple Shockwaves
      for (var rIdx = activeRipples.length - 1; rIdx >= 0; rIdx--) {
        var rip = activeRipples[rIdx];
        rip.radius += 3.5;
        rip.alpha -= 0.025;
        if (rip.alpha <= 0 || rip.radius > rip.maxRadius) {
          activeRipples.splice(rIdx, 1);
        } else {
          ctx.save();
          ctx.beginPath();
          ctx.arc(rip.x, rip.y, rip.radius, 0, Math.PI * 2);
          ctx.strokeStyle = rip.color;
          ctx.lineWidth = 2;
          ctx.globalAlpha = rip.alpha;
          ctx.stroke();
          ctx.restore();
        }
      }

      // Render HUD Inspection Tooltip if a node is hovered
      if (hoveredNode && hoveredNode.proj) {
        var hn = hoveredNode;
        var hp = hn.proj;
        var tipX = Math.min(width - 190, Math.max(10, hp.x + 18));
        var tipY = Math.min(height - 75, Math.max(10, hp.y - 45));

        ctx.save();
        ctx.fillStyle = 'rgba(21, 36, 66, 0.92)';
        ctx.strokeStyle = hn.color;
        ctx.lineWidth = 1.2;
        ctx.shadowColor = 'rgba(0, 0, 0, 0.6)';
        ctx.shadowBlur = 12;

        // Rounded HUD box
        var bw = 175, bh = 58, br = 6;
        ctx.beginPath();
        ctx.roundRect ? ctx.roundRect(tipX, tipY, bw, bh, br) : ctx.rect(tipX, tipY, bw, bh);
        ctx.fill();
        ctx.stroke();

        ctx.fillStyle = '#FFFFFF';
        ctx.font = 'bold 11px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.textAlign = 'left';
        ctx.fillText(hn.label, tipX + 10, tipY + 18);

        ctx.fillStyle = hn.color;
        ctx.font = '10px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.fillText(hn.metric, tipX + 10, tipY + 34);

        ctx.fillStyle = '#94A9C9';
        ctx.font = '9px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
        ctx.fillText(hn.subtitle, tipX + 10, tipY + 48);
        ctx.restore();
      }

      animFrame = requestAnimationFrame(loop);
    }

    animFrame = requestAnimationFrame(loop);
  }

  // ── 3D Card Tilt with Specular Reflection ──
  function init3DTilt() {
    if (prefersReducedMotion) return;
    var cards = document.querySelectorAll('.futureglass-tilt');
    cards.forEach(function (card) {
      card.addEventListener('mousemove', function (e) {
        var rect = card.getBoundingClientRect();
        var x = e.clientX - rect.left;
        var y = e.clientY - rect.top;
        var centerX = rect.width / 2;
        var centerY = rect.height / 2;

        var rotateX = ((y - centerY) / centerY) * -6;
        var rotateY = ((x - centerX) / centerX) * 6;

        card.style.transform = 'perspective(1000px) rotateX(' + rotateX.toFixed(2) + 'deg) rotateY(' + rotateY.toFixed(2) + 'deg) scale3d(1.015, 1.015, 1.015)';
        // Specular dynamic reflection gradient
        var glareX = (x / rect.width) * 100;
        var glareY = (y / rect.height) * 100;
        card.style.setProperty('--glare-x', glareX + '%');
        card.style.setProperty('--glare-y', glareY + '%');
      });

      card.addEventListener('mouseleave', function () {
        card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
        card.style.transition = 'transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1)';
      });

      card.addEventListener('mouseenter', function () {
        card.style.transition = 'none';
      });
    });
  }

  // ── Demo Helper Controls ──
  function initDemoHelpers() {
    var fillBtns = document.querySelectorAll('[data-demo-email]');
    fillBtns.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var email = btn.getAttribute('data-demo-email');
        var pass = btn.getAttribute('data-demo-pass');
        var emailInput = document.getElementById('email');
        var passInput = document.getElementById('password');
        if (emailInput && email) {
          emailInput.value = email;
          emailInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (passInput && pass) {
          passInput.value = pass;
          passInput.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    });
  }

  // ── Entrypoint ──
  function initFutureglass() {
    var canvas = document.getElementById('constellation-canvas');
    if (canvas) {
      initConstellation(canvas);
    }
    init3DTilt();
    initDemoHelpers();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFutureglass);
  } else {
    initFutureglass();
  }
})();
