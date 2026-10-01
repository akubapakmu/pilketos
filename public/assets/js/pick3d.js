/**
 * Tombol 3D "Pilih Paslon 0X" di penutup profil paslon (Stage 22).
 * Markup: app/Views/paslon/partials/profile.php ([data-px-pick]), gaya:
 * paslon.css (.px-pick). Dimuat sebagai modul hanya saat pemilih masih dapat
 * mencoblos; three.js r186 disimpan lokal (CSP script-src 'self').
 *
 * Adegan: tombol bulat (keycap) berwarna kertas di atas dudukan gelap,
 * tulisan "Pilih Paslon 0X" di permukaannya, dan tangan sarung tangan putih
 * bergaris tepi gelap yang berulang kali menekan tombol: tombol turun lalu
 * memantul, riak cahaya melebar dari tepinya. Seperti penutup lainnya: tanpa
 * warna aksen.
 *
 * - Hover/fokus: tangan menekan lebih cepat, adegan miring mengikuti pointer.
 * - Klik/Enter: satu tekanan cepat lalu pindah ke bilik suara (href tautan);
 *   Ctrl/Cmd/Shift/klik tengah dibiarkan ke browser (tab baru).
 * - Render berhenti saat tombol tidak terlihat atau tab tersembunyi; konteks
 *   WebGL dilepas saat halaman ditinggal (pagehide).
 * - prefers-reduced-motion: satu frame diam (jari di atas tombol).
 * - Tanpa WebGL / gagal dibuat: .is-3d tidak dipasang, tombol 2D tetap tampil.
 * Tautan <a> tetap elemen sebenarnya (nama aksesibel = teks tombol 2D).
 */
import * as THREE from '../vendor/three-0.186.1/three.module.js';

const INK = 0x15141a;
const PAPER = 0xf6f4ee;
const TRAVEL = 0.2; // jarak turun keycap saat ditekan
const PERIOD = 2.6; // detik per siklus tekan (diam)
const PERIOD_HOT = 1.45; // detik per siklus saat hover/fokus

const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ------------------------------------------------------------------ */
/* geometri                                                           */
/* ------------------------------------------------------------------ */

function roundedRect(width, depth, radius) {
  const shape = new THREE.Shape();
  const x = -width / 2;
  const y = -depth / 2;
  shape.moveTo(x + radius, y);
  shape.lineTo(x + width - radius, y);
  shape.quadraticCurveTo(x + width, y, x + width, y + radius);
  shape.lineTo(x + width, y + depth - radius);
  shape.quadraticCurveTo(x + width, y + depth, x + width - radius, y + depth);
  shape.lineTo(x + radius, y + depth);
  shape.quadraticCurveTo(x, y + depth, x, y + depth - radius);
  shape.lineTo(x, y + radius);
  shape.quadraticCurveTo(x, y, x + radius, y);
  return shape;
}

/** Lempeng bulat diekstrusi ke atas (+Y); alasnya di y = 0. */
function slab(width, depth, radius, height, bevel) {
  const geometry = new THREE.ExtrudeGeometry(roundedRect(width, depth, radius), {
    depth: height,
    bevelEnabled: true,
    bevelThickness: bevel,
    bevelSize: bevel,
    bevelSegments: 5,
    curveSegments: 12
  });
  geometry.rotateX(-Math.PI / 2);
  geometry.computeBoundingBox();
  geometry.translate(0, -geometry.boundingBox.min.y, 0);
  geometry.computeBoundingBox();
  return geometry;
}

/** Bingkai bulat datar (untuk riak cahaya). */
function frame(width, depth, radius, thickness) {
  const outer = roundedRect(width + thickness * 2, depth + thickness * 2, radius + thickness);
  outer.holes.push(roundedRect(width, depth, radius));
  const geometry = new THREE.ShapeGeometry(outer, 12);
  geometry.rotateX(-Math.PI / 2);
  return geometry;
}

/* ------------------------------------------------------------------ */
/* material                                                           */
/* ------------------------------------------------------------------ */

/** Garis tepi kartun: sisi belakang yang digembungkan sepanjang normal. */
function outlineMaterial(width) {
  const material = new THREE.MeshBasicMaterial({ color: INK, side: THREE.BackSide });
  material.onBeforeCompile = (shader) => {
    shader.vertexShader = shader.vertexShader.replace(
      '#include <begin_vertex>',
      '#include <begin_vertex>\n\ttransformed += normal * ' + width.toFixed(3) + ';'
    );
  };
  return material;
}

/** Peta cahaya lembut tanpa file luar: ruangan abu-abu dengan panel terang. */
function environment(renderer) {
  const room = new THREE.Scene();
  const box = new THREE.Mesh(new THREE.BoxGeometry(12, 8, 12), new THREE.MeshBasicMaterial({ color: 0x3a3a40, side: THREE.BackSide }));
  room.add(box);
  [[0, 3.9, 0, 8, 6, 5.5], [-5.9, 1.5, 1, 3, 4, 3], [5.9, 2, -2, 3, 3, 1.6]].forEach(([x, y, z, w, h, power]) => {
    const panel = new THREE.Mesh(new THREE.PlaneGeometry(w, h), new THREE.MeshBasicMaterial({ color: new THREE.Color(power, power, power), side: THREE.DoubleSide }));
    panel.position.set(x, y, z);
    panel.lookAt(0, 0, 0);
    room.add(panel);
  });
  const pmrem = new THREE.PMREMGenerator(renderer);
  const target = pmrem.fromScene(room, 0.04);
  pmrem.dispose();
  room.traverse((o) => {
    if (o.isMesh) {
      o.geometry.dispose();
      o.material.dispose();
    }
  });
  return target;
}

/** Tulisan tombol pada kanvas 2D (dipakai sebagai tekstur permukaan keycap). */
function labelTexture(text, maxAnisotropy) {
  const canvas = document.createElement('canvas');
  canvas.width = 1600;
  canvas.height = 440;
  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  texture.anisotropy = Math.min(8, maxAnisotropy);

  const draw = () => {
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    let size = canvas.height * 0.44;
    const font = (px) => '800 ' + px + 'px "Plus Jakarta Sans", system-ui, sans-serif';
    ctx.font = font(size);
    const width = ctx.measureText(text).width;
    const max = canvas.width * 0.94;
    if (width > max) {
      size *= max / width;
      ctx.font = font(size);
    }
    ctx.fillStyle = '#15141A';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(text, canvas.width / 2, canvas.height / 2 + size * 0.04);
    texture.needsUpdate = true;
  };

  draw();
  if (document.fonts && document.fonts.load) {
    document.fonts.load('800 100px "Plus Jakarta Sans"').then(draw, () => {});
  }
  return texture;
}

/* ------------------------------------------------------------------ */
/* tangan: sarung tangan kartun, telunjuk menunjuk ke bawah            */
/* ------------------------------------------------------------------ */

function buildHand() {
  const glove = new THREE.MeshStandardMaterial({ color: 0xfbfbfd, roughness: 0.48, metalness: 0, envMapIntensity: 0.9 });
  const stitch = new THREE.MeshBasicMaterial({ color: INK });
  const outline = outlineMaterial(0.028);
  const hand = new THREE.Group();

  const part = (geometry, x, y, z, rx = 0, ry = 0, rz = 0, material = glove, withOutline = true) => {
    const mesh = new THREE.Mesh(geometry, material);
    mesh.position.set(x, y, z);
    mesh.rotation.set(rx, ry, rz);
    mesh.castShadow = true;
    hand.add(mesh);
    if (withOutline) {
      const edge = new THREE.Mesh(geometry, outline);
      edge.position.copy(mesh.position);
      edge.rotation.copy(mesh.rotation);
      hand.add(edge);
    }
    return mesh;
  };

  // telapak (lempeng bulat tegak), punggung tangan menghadap kamera (+z)
  const palm = slab(0.96, 0.46, 0.2, 0.86, 0.1);
  part(palm, 0.04, 0.02, 0);
  // telunjuk lurus ke bawah; ujungnya di titik asal grup tangan (lihat pivot)
  part(new THREE.CapsuleGeometry(0.155, 0.92, 8, 16), -0.27, -0.5, 0.02);
  // tiga jari tertekuk: buku-buku jari di dasar telapak, mengarah ke dalam
  [[0.04, 0.155], [0.31, 0.15], [0.55, 0.13]].forEach(([x, r], i) => {
    part(new THREE.CapsuleGeometry(r, 0.26, 6, 14), x, 0.02 - i * 0.02, 0.1, Math.PI / 2 + 0.25, 0, 0);
  });
  // ibu jari dilipat ke depan telapak, sisi telunjuk
  part(new THREE.CapsuleGeometry(0.14, 0.42, 6, 14), -0.44, 0.38, 0.12, 0.35, 0, 0.75);
  // manset sarung tangan
  const cuff = new THREE.CylinderGeometry(0.56, 0.6, 0.3, 28, 1);
  cuff.scale(1, 1, 0.62);
  part(cuff, 0.06, 1.12, 0);
  // jahitan klasik di punggung sarung tangan
  [-0.18, 0.06, 0.3].forEach((x) => {
    part(new THREE.CapsuleGeometry(0.018, 0.34, 3, 6), x, 0.6, 0.335, 0, 0, 0, stitch, false);
  });

  // pivot di ujung telunjuk: grup luar diletakkan tepat di titik tekan
  const pivot = new THREE.Group();
  hand.position.set(0.27, 1.115, -0.02);
  pivot.add(hand);
  pivot.scale.setScalar(0.86);
  return pivot;
}

/* ------------------------------------------------------------------ */
/* satu tombol                                                        */
/* ------------------------------------------------------------------ */

function mount(link) {
  const stage = link.querySelector('.px-pick__stage');
  const canvas = link.querySelector('[data-px-pick-canvas]');
  const label = (link.querySelector('.px-pick__label') || link).textContent.trim();
  if (!stage || !canvas) {
    return null;
  }

  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true, powerPreference: 'high-performance' });
  } catch (e) {
    return null; // tanpa WebGL: tombol 2D tetap dipakai
  }

  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setClearColor(0x000000, 0);
  renderer.toneMapping = THREE.NeutralToneMapping;
  renderer.toneMappingExposure = 1.05;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFShadowMap;

  const scene = new THREE.Scene();
  const envTarget = environment(renderer);
  scene.environment = envTarget.texture;

  const camera = new THREE.PerspectiveCamera(30, 2, 0.1, 60);
  const lookAt = new THREE.Vector3(0.25, 1.75, 0);

  // cahaya: langit/tanah lembut, cahaya utama berbayang, cahaya tepi dari belakang
  scene.add(new THREE.HemisphereLight(0xffffff, 0x2a2930, 0.55));
  const key = new THREE.DirectionalLight(0xffffff, 2.1);
  key.position.set(-3.2, 7, 4.5);
  key.castShadow = true;
  key.shadow.mapSize.set(1024, 1024);
  key.shadow.radius = 4;
  key.shadow.bias = -0.0004;
  key.shadow.normalBias = 0.02;
  Object.assign(key.shadow.camera, { left: -4, right: 4, top: 4, bottom: -4, near: 1, far: 20 });
  scene.add(key);
  const rim = new THREE.DirectionalLight(0xffffff, 1.4);
  rim.position.set(3, 3.5, -6);
  scene.add(rim);

  // dudukan gelap + keycap kertas
  const button = new THREE.Group();
  scene.add(button);

  const base = new THREE.Mesh(
    slab(4.85, 1.9, 0.62, 0.22, 0.09),
    new THREE.MeshPhysicalMaterial({ color: 0x2a2930, roughness: 0.42, metalness: 0.15, clearcoat: 0.5, clearcoatRoughness: 0.3 })
  );
  base.receiveShadow = true;
  base.castShadow = true;
  button.add(base);
  const baseTop = base.geometry.boundingBox.max.y;

  const capGroup = new THREE.Group();
  capGroup.position.y = baseTop - 0.02;
  button.add(capGroup);

  const capMaterial = new THREE.MeshPhysicalMaterial({ color: PAPER, roughness: 0.32, clearcoat: 0.7, clearcoatRoughness: 0.22, emissive: 0xffffff, emissiveIntensity: 0 });
  const cap = new THREE.Mesh(slab(4.4, 1.46, 0.5, 0.34, 0.1), capMaterial);
  cap.castShadow = true;
  cap.receiveShadow = true;
  capGroup.add(cap);
  const capTop = cap.geometry.boundingBox.max.y;

  const text = new THREE.Mesh(
    new THREE.PlaneGeometry(3.3, 0.92),
    new THREE.MeshBasicMaterial({ map: labelTexture(label, renderer.capabilities.getMaxAnisotropy()), transparent: true, depthWrite: false, toneMapped: false })
  );
  text.rotation.x = -Math.PI / 2;
  text.position.set(-0.38, capTop + 0.004, 0.02);
  capGroup.add(text);

  // riak cahaya dari tepi keycap saat tertekan
  const ripple = new THREE.Mesh(
    frame(4.4, 1.46, 0.5, 0.07),
    new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0, depthWrite: false, toneMapped: false })
  );
  ripple.position.y = baseTop + 0.01;
  button.add(ripple);

  // bayangan lembut di bawah dudukan (menapakkan tombol di latar gelap)
  const contact = document.createElement('canvas');
  contact.width = contact.height = 128;
  const cctx = contact.getContext('2d');
  const grad = cctx.createRadialGradient(64, 64, 4, 64, 64, 64);
  grad.addColorStop(0, 'rgba(0,0,0,0.55)');
  grad.addColorStop(1, 'rgba(0,0,0,0)');
  cctx.fillStyle = grad;
  cctx.fillRect(0, 0, 128, 128);
  const shadowTex = new THREE.CanvasTexture(contact);
  const ground = new THREE.Mesh(new THREE.PlaneGeometry(7.4, 3.4), new THREE.MeshBasicMaterial({ map: shadowTex, transparent: true, depthWrite: false }));
  ground.rotation.x = -Math.PI / 2;
  ground.position.y = -0.01;
  scene.add(ground);

  // tangan
  const hand = buildHand();
  scene.add(hand);
  const press = new THREE.Vector3(1.48, 0, 0.1); // titik tekan di kanan tulisan

  /* ---------- keadaan animasi ---------- */

  const state = {
    phase: reduced ? 0.12 : 0,
    hot: false,
    go: false,
    capY: 0,
    capV: 0,
    rippleT: 1,
    pressed: false,
    tiltX: 0,
    tiltY: 0,
    aimX: 0,
    aimY: 0
  };

  /** Ketinggian ujung jari di atas permukaan keycap untuk fase 0..1. */
  function fingerHeight(p) {
    const hover = 0.62;
    if (p < 0.36) {
      return hover + Math.sin(p / 0.36 * Math.PI) * 0.08;
    }
    if (p < 0.5) {
      const t = (p - 0.36) / 0.14;
      return hover * (1 - t * t * t);
    }
    if (p < 0.58) {
      const t = (p - 0.5) / 0.08;
      return -TRAVEL * Math.sin(t * Math.PI / 2);
    }
    if (p < 0.78) {
      const t = (p - 0.58) / 0.2;
      return -TRAVEL + (hover + TRAVEL) * (1 - Math.pow(1 - t, 3));
    }
    return hover;
  }

  function layout() {
    const width = stage.clientWidth;
    const height = stage.clientHeight;
    if (!width || !height) {
      return;
    }
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    // layar sempit: kamera mundur agar tombol & tangan tetap utuh
    const fit = Math.pow(Math.max(1, 1.6 / camera.aspect), 0.85);
    camera.position.set(0.25 * fit, 4 + 1.2 * fit, 7.8 * fit);
    camera.updateProjectionMatrix();
  }

  let last = 0;
  let raf = 0;
  let visible = true;
  let shown = false;

  function frame3d(time) {
    raf = 0;
    const dt = last ? Math.min(0.05, (time - last) / 1000) : 0.016;
    last = time;

    if (!reduced) {
      const period = state.go ? 0.55 : (state.hot ? PERIOD_HOT : PERIOD);
      state.phase += dt / period;
      if (state.phase >= 1) {
        state.phase -= 1;
        state.pressed = false;
      }
    }

    // ujung jari & keycap: jari mendorong keycap, setelah lepas keycap memantul
    const tip = fingerHeight(state.phase);
    if (tip < state.capY) {
      state.capY = Math.max(tip, -TRAVEL);
      state.capV = 0;
      if (!state.pressed && state.capY < -TRAVEL * 0.5) {
        state.pressed = true;
        state.rippleT = 0;
      }
    } else {
      const acc = -260 * state.capY - 11 * state.capV;
      state.capV += acc * dt;
      state.capY += state.capV * dt;
    }
    capGroup.position.y = baseTop - 0.02 + state.capY;

    hand.position.set(press.x, baseTop - 0.02 + capTop + Math.max(tip, state.capY), press.z);
    const lean = Math.max(0, Math.min(1, (0.62 - tip) / 0.82));
    hand.rotation.set(-0.16 + lean * 0.12, -0.32, -0.36 + lean * 0.08);

    // riak
    if (state.rippleT < 1) {
      state.rippleT = Math.min(1, state.rippleT + dt / 0.7);
      const e = 1 - Math.pow(1 - state.rippleT, 3);
      ripple.scale.set(1 + e * 0.16, 1, 1 + e * 0.5);
      ripple.material.opacity = 0.75 * (1 - state.rippleT);
    } else {
      ripple.material.opacity = 0;
    }

    // sorot saat hover/fokus, adegan miring mengikuti pointer
    const glow = state.hot ? 0.07 : 0;
    capMaterial.emissiveIntensity += (glow - capMaterial.emissiveIntensity) * Math.min(1, dt * 8);
    state.tiltX += (state.aimX - state.tiltX) * Math.min(1, dt * 5);
    state.tiltY += (state.aimY - state.tiltY) * Math.min(1, dt * 5);
    scene.rotation.y = state.tiltX * 0.12;
    camera.lookAt(lookAt.x, lookAt.y - state.tiltY * 0.25, lookAt.z);

    renderer.render(scene, camera);

    if (!shown) {
      shown = true;
      link.classList.add('is-3d');
      layout();
      renderer.render(scene, camera);
    }

    if (!reduced && visible && !document.hidden) {
      raf = window.requestAnimationFrame(frame3d);
    }
  }

  function start() {
    if (!raf) {
      last = 0;
      raf = window.requestAnimationFrame(frame3d);
    }
  }

  function stop() {
    if (raf) {
      window.cancelAnimationFrame(raf);
      raf = 0;
    }
  }

  /* ---------- interaksi ---------- */

  const setHot = (hot) => {
    state.hot = hot;
    if (reduced) {
      start();
    }
  };

  link.addEventListener('pointerenter', () => setHot(true));
  link.addEventListener('pointerleave', () => {
    setHot(false);
    state.aimX = 0;
    state.aimY = 0;
  });
  link.addEventListener('focus', () => setHot(true));
  link.addEventListener('blur', () => setHot(false));
  link.addEventListener('pointermove', (event) => {
    const rect = stage.getBoundingClientRect();
    state.aimX = ((event.clientX - rect.left) / rect.width - 0.5) * 2;
    state.aimY = ((event.clientY - rect.top) / rect.height - 0.5) * 2;
  });

  link.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    event.preventDefault();
    const href = link.href;
    if (reduced) {
      window.location.assign(href);
      return;
    }
    // satu tekanan cepat (mulai dari jari turun), lalu pindah halaman
    state.go = true;
    state.phase = Math.max(state.phase, 0.36);
    if (state.phase > 0.6) {
      state.phase = 0.36;
      state.pressed = false;
    }
    start();
    window.setTimeout(() => window.location.assign(href), 380);
  });

  if ('IntersectionObserver' in window) {
    new IntersectionObserver((entries) => {
      visible = entries[entries.length - 1].isIntersecting;
      if (visible) {
        start();
      } else {
        stop();
      }
    }, { rootMargin: '120px 0px' }).observe(stage);
  }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stop();
    } else if (visible) {
      start();
    }
  });

  if ('ResizeObserver' in window) {
    new ResizeObserver(() => {
      layout();
      if (reduced || !raf) {
        renderer.render(scene, camera);
      }
    }).observe(stage);
  } else {
    window.addEventListener('resize', layout);
  }

  canvas.addEventListener('webglcontextlost', (event) => {
    event.preventDefault();
    stop();
    link.classList.remove('is-3d'); // kembali ke tombol 2D
  });

  layout();
  start();

  return {
    destroy() {
      stop();
      link.classList.remove('is-3d');
      scene.traverse((o) => {
        if (o.isMesh) {
          o.geometry.dispose();
          (Array.isArray(o.material) ? o.material : [o.material]).forEach((m) => {
            if (m.map) {
              m.map.dispose();
            }
            m.dispose();
          });
        }
      });
      envTarget.dispose();
      renderer.dispose();
      renderer.forceContextLoss();
    }
  };
}

/* ------------------------------------------------------------------ */
/* init                                                               */
/* ------------------------------------------------------------------ */

let mounted = [];

function init() {
  mounted = Array.prototype.map.call(document.querySelectorAll('[data-px-pick]'), mount).filter(Boolean);
}

init();

// Halaman ditinggal: lepas GPU. Kembali lewat tombol back (bfcache): pasang lagi.
window.addEventListener('pagehide', () => {
  mounted.forEach((m) => m.destroy());
  mounted = [];
});

window.addEventListener('pageshow', (event) => {
  if (event.persisted && mounted.length === 0) {
    init();
  }
});
