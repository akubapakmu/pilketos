/**
 * Tombol 3D "Pilih Paslon 0X" di penutup profil paslon (Stage 22).
 * Markup: app/Views/paslon/partials/profile.php ([data-px-pick]), gaya:
 * paslon.css (.px-pick). Dimuat sebagai modul hanya saat pemilih masih dapat
 * mencoblos; three.js r186 disimpan lokal (CSP script-src 'self').
 *
 * Stage 24: animasi "klik ala tombol Subscribe" menggantikan tangan yang
 * menekan. Adegan: tombol bulat (keycap) berwarna kertas di atas dudukan
 * gelap dengan tulisan "Pilih Paslon 0X" tepat di tengahnya. Kursor panah
 * putih bergaris tepi gelap meluncur masuk dari kanan bawah, berhenti di
 * sisi kanan tombol (tidak menutupi tulisan), lalu mengklik: kursor mengecil
 * sesaat, keycap turun dan memantul, cincin klik melebar dari titik klik,
 * garis "klik" memercik di ujung kursor, riak cahaya dari tepi tombol;
 * kursor lalu keluar dan siklus diulang. Kursor digambar di atas tombol
 * (lapisan kedua, seperti kursor layar), bayangannya jatuh di keycap.
 * Seperti penutup lainnya: tanpa warna aksen.
 *
 * - Pointer sungguhan di atas tombol / fokus keyboard: kursor tiruan
 *   menyingkir, tombol menyala dan adegan miring mengikuti pointer.
 * - Klik/Enter: tombol tertekan di titik klik lalu pindah ke bilik suara
 *   (href tautan); Ctrl/Cmd/Shift/klik tengah dibiarkan ke browser (tab baru).
 * - Render berhenti saat tombol tidak terlihat atau tab tersembunyi; konteks
 *   WebGL dilepas saat halaman ditinggal (pagehide).
 * - prefers-reduced-motion: satu frame diam (kursor di atas tombol).
 * - Tanpa WebGL / gagal dibuat: .is-3d tidak dipasang, tombol 2D tetap tampil.
 * Tautan <a> tetap elemen sebenarnya (nama aksesibel = teks tombol 2D).
 */
import * as THREE from '../vendor/three-0.186.1/three.module.js';

const INK = 0x15141a;
const PAPER = 0xf6f4ee;
const TRAVEL = 0.15; // jarak turun keycap saat diklik
const PERIOD = 3.2; // detik per siklus kursor: masuk, klik, diam, keluar
const CLICK = 0.4; // fase saat kursor mengklik
const STILL = 0.62; // fase frame diam (gerak dikurangi): kursor di atas tombol

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
/* material & tekstur                                                 */
/* ------------------------------------------------------------------ */

/** Garis tepi kartun: sisi belakang yang digembungkan sepanjang normal. */
function outlineMaterial(width) {
  const material = new THREE.MeshBasicMaterial({ color: INK, side: THREE.BackSide, transparent: true });
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

/** Tulisan tombol pada kanvas 2D (tekstur permukaan keycap), rata tengah. */
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
    let size = canvas.height * 0.46;
    const font = (px) => '800 ' + px + 'px "Plus Jakarta Sans", system-ui, sans-serif';
    ctx.font = font(size);
    const width = ctx.measureText(text).width;
    const max = canvas.width * 0.96;
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

/** Bayangan lembut bulat (kanvas), untuk dudukan dan kursor. */
function softShadow(strength) {
  const canvas = document.createElement('canvas');
  canvas.width = canvas.height = 128;
  const ctx = canvas.getContext('2d');
  const shade = ctx.createRadialGradient(64, 64, 4, 64, 64, 64);
  shade.addColorStop(0, 'rgba(0,0,0,' + strength + ')');
  shade.addColorStop(1, 'rgba(0,0,0,0)');
  ctx.fillStyle = shade;
  ctx.fillRect(0, 0, 128, 128);
  return new THREE.CanvasTexture(canvas);
}

/* ------------------------------------------------------------------ */
/* kursor: panah klasik, putih bergaris tepi gelap                     */
/* ------------------------------------------------------------------ */

/** Titik panah kursor (piksel, ujung di 0,0, sumbu y ke bawah). */
const ARROW = [[0, 0], [0, 17], [4.2, 13.2], [7, 19.6], [9.8, 18.4], [7.1, 12.1], [12.4, 12.1]];

function buildCursor() {
  const shape = new THREE.Shape();
  ARROW.forEach(([x, y], i) => {
    if (i === 0) {
      shape.moveTo(x, -y);
    } else {
      shape.lineTo(x, -y);
    }
  });
  shape.closePath();

  const geometry = new THREE.ExtrudeGeometry(shape, {
    depth: 1.2,
    bevelEnabled: true,
    bevelThickness: 0.45,
    bevelSize: 0.4,
    bevelSegments: 3,
    curveSegments: 1
  });
  geometry.translate(0, 0, -0.6);
  geometry.scale(0.046, 0.046, 0.046);

  const body = new THREE.Mesh(geometry, new THREE.MeshStandardMaterial({ color: 0xfbfbfd, roughness: 0.42, metalness: 0, envMapIntensity: 0.9, transparent: true }));
  const edge = new THREE.Mesh(geometry, outlineMaterial(0.04));
  body.renderOrder = 2;

  // tiga garis "klik" memercik dari ujung kursor (atas, kiri atas, kiri)
  const sparkMaterial = new THREE.MeshBasicMaterial({ color: INK, transparent: true, opacity: 0, depthTest: false, toneMapped: false });
  const sparks = new THREE.Group();
  [1.62, 2.3, 2.98].forEach((angle) => {
    const spark = new THREE.Mesh(new THREE.PlaneGeometry(0.22, 0.05), sparkMaterial);
    spark.rotation.z = angle;
    spark.userData.dir = new THREE.Vector2(Math.cos(angle), Math.sin(angle));
    sparks.add(spark);
  });

  const group = new THREE.Group();
  group.add(edge, body, sparks);
  return { group, body, edge, sparks, sparkMaterial };
}

/* ------------------------------------------------------------------ */
/* irama kursor                                                       */
/* ------------------------------------------------------------------ */

const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const clamp01 = (t) => Math.min(1, Math.max(0, t));
const span = (p, a, b) => clamp01((p - a) / (b - a));

/**
 * Pose kursor untuk fase 0..1: posisi relatif titik klik (x, y, z), skala,
 * dan opasitas. Masuk 0-0.3, mendekat 0.3-0.38, klik 0.38-0.5, diam,
 * keluar 0.76-1.
 */
function cursorPose(p, from, to) {
  if (p < 0.3) {
    const t = ease(span(p, 0, 0.3));
    return {
      x: from.x + (0 - from.x) * t,
      y: from.y + (0.16 - from.y) * t + Math.sin(t * Math.PI) * 0.18,
      z: from.z + (0 - from.z) * t,
      scale: 1,
      alpha: span(p, 0, 0.1)
    };
  }
  if (p < 0.38) {
    const t = ease(span(p, 0.3, 0.38));
    return { x: 0, y: 0.16 * (1 - t), z: 0, scale: 1, alpha: 1 };
  }
  if (p < 0.5) {
    const t = span(p, 0.38, 0.5);
    const squeeze = Math.sin(clamp01((t - 0.15) / 0.6) * Math.PI);
    return { x: 0, y: 0, z: 0, scale: 1 - squeeze * 0.16, alpha: 1 };
  }
  if (p < 0.76) {
    return { x: 0, y: 0, z: 0, scale: 1, alpha: 1 };
  }
  const t = ease(span(p, 0.76, 1));
  return {
    x: to.x * t,
    y: to.y * t,
    z: to.z * t,
    scale: 1,
    alpha: 1 - span(p, 0.86, 1)
  };
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
  renderer.autoClear = false;

  const scene = new THREE.Scene();
  const envTarget = environment(renderer);
  scene.environment = envTarget.texture;

  // lapisan kedua: kursor selalu di atas tombol, seperti kursor layar
  const overlay = new THREE.Scene();
  overlay.environment = envTarget.texture;

  const camera = new THREE.PerspectiveCamera(13, 2.4, 0.1, 80);
  const lookAt = new THREE.Vector3(0.1, 0.24, 0.12);

  // cahaya: langit/tanah lembut, cahaya utama berbayang, cahaya tepi dari belakang
  [scene, overlay].forEach((target, i) => {
    target.add(new THREE.HemisphereLight(0xffffff, 0x2a2930, 0.55));
    const key = new THREE.DirectionalLight(0xffffff, 2.1);
    key.position.set(-3.2, 7, 4.5);
    if (i === 0) {
      key.castShadow = true;
      key.shadow.mapSize.set(1024, 1024);
      key.shadow.radius = 4;
      key.shadow.bias = -0.0004;
      key.shadow.normalBias = 0.02;
      Object.assign(key.shadow.camera, { left: -4, right: 4, top: 4, bottom: -4, near: 1, far: 20 });
    }
    target.add(key);
    const rim = new THREE.DirectionalLight(0xffffff, 1.4);
    rim.position.set(3, 3.5, -6);
    target.add(rim);
  });

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

  // tulisan tepat di tengah permukaan keycap
  const text = new THREE.Mesh(
    new THREE.PlaneGeometry(3.4, 0.935),
    new THREE.MeshBasicMaterial({ map: labelTexture(label, renderer.capabilities.getMaxAnisotropy()), transparent: true, depthWrite: false, toneMapped: false })
  );
  text.rotation.x = -Math.PI / 2;
  text.position.set(0, capTop + 0.004, 0);
  capGroup.add(text);

  // titik klik kursor: kanan depan keycap, di bawah tulisan (tidak menutupinya)
  const target = new THREE.Vector3(1.3, 0, 0.5);

  // bayangan kursor di permukaan keycap
  const cursorShadow = new THREE.Mesh(
    new THREE.PlaneGeometry(0.9, 0.9),
    new THREE.MeshBasicMaterial({ map: softShadow(0.5), transparent: true, opacity: 0, depthWrite: false })
  );
  cursorShadow.rotation.x = -Math.PI / 2;
  cursorShadow.renderOrder = 1;
  capGroup.add(cursorShadow);

  // cincin klik di permukaan keycap
  const ring = new THREE.Mesh(
    new THREE.RingGeometry(0.075, 0.115, 48),
    new THREE.MeshBasicMaterial({ color: INK, transparent: true, opacity: 0, depthWrite: false, toneMapped: false })
  );
  ring.rotation.x = -Math.PI / 2;
  ring.renderOrder = 2;
  capGroup.add(ring);

  // riak cahaya dari tepi keycap saat tertekan
  const ripple = new THREE.Mesh(
    frame(4.4, 1.46, 0.5, 0.07),
    new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0, depthWrite: false, toneMapped: false })
  );
  ripple.position.y = baseTop + 0.01;
  button.add(ripple);

  // bayangan lembut di bawah dudukan (menapakkan tombol di latar gelap)
  const ground = new THREE.Mesh(new THREE.PlaneGeometry(7.4, 3.4), new THREE.MeshBasicMaterial({ map: softShadow(0.55), transparent: true, depthWrite: false }));
  ground.rotation.x = -Math.PI / 2;
  ground.position.y = -0.01;
  scene.add(ground);

  // kursor
  const cursor = buildCursor();
  overlay.add(cursor.group);
  const enterFrom = new THREE.Vector3(1.7, 0.75, 1.5); // relatif titik klik
  const exitTo = new THREE.Vector3(1.5, 0.65, 1.1);

  /* ---------- keadaan animasi ---------- */

  const state = {
    phase: reduced ? STILL : 0,
    hot: false,
    go: false,
    show: 1, // pudar kursor tiruan saat pointer sungguhan di atas tombol
    capY: 0,
    capV: 0,
    pressing: 0,
    ringT: 1,
    rippleT: 1,
    sparkT: 1,
    clicked: false,
    tiltX: 0,
    tiltY: 0,
    aimX: 0,
    aimY: 0
  };

  /** Keycap ditekan di titik (x, z) permukaannya selama `hold` detik. */
  function press(x, z, hold) {
    state.pressing = hold;
    state.ringT = 0;
    state.rippleT = 0;
    ring.position.set(x, capTop + 0.006, z);
  }

  function layout() {
    const width = stage.clientWidth;
    const height = stage.clientHeight;
    if (!width || !height) {
      return;
    }
    renderer.setSize(width, height, false);
    camera.aspect = width / height;
    // sudut pandang sempit (perspektif tulisan tidak berlebihan); layar
    // sempit: kamera mundur agar tombol & jalur kursor tetap utuh
    const fit = Math.pow(Math.max(1, 2.5 / camera.aspect), 0.9);
    camera.position.set(0.1, 9.9 * fit, 9.9 * fit);
    camera.updateProjectionMatrix();
  }

  let last = 0;
  let raf = 0;
  let visible = true;
  let shown = false;
  const tip = new THREE.Vector3();
  const UP = new THREE.Vector3(0, 1, 0);

  function draw() {
    renderer.clear();
    renderer.render(scene, camera);
    renderer.clearDepth();
    renderer.render(overlay, camera);
  }

  function frame3d(time) {
    raf = 0;
    const dt = last ? Math.min(0.05, (time - last) / 1000) : 0.016;
    last = time;

    // siklus kursor tiruan berhenti selama pointer sungguhan di atas tombol
    if (!reduced && !state.hot) {
      const before = state.phase;
      state.phase += dt / PERIOD;
      if (state.phase >= 1) {
        state.phase -= 1;
        state.clicked = false;
      }
      if (!state.clicked && before < CLICK && state.phase >= CLICK) {
        state.clicked = true;
        state.sparkT = 0;
        press(target.x, target.z, 0.1);
      }
    }
    state.show += ((state.hot && !reduced ? 0 : 1) - state.show) * Math.min(1, dt * 12);

    // keycap: turun saat ditekan, lalu memantul (pegas)
    if (state.pressing > 0) {
      state.pressing -= dt;
      state.capY += (-TRAVEL - state.capY) * Math.min(1, dt * 40);
      state.capV = 0;
    } else {
      const acc = -260 * state.capY - 11 * state.capV;
      state.capV += acc * dt;
      state.capY += state.capV * dt;
    }
    capGroup.position.y = baseTop - 0.02 + state.capY;

    // sorot saat hover/fokus, tombol miring mengikuti pointer
    const glow = state.hot ? 0.07 : 0;
    capMaterial.emissiveIntensity += (glow - capMaterial.emissiveIntensity) * Math.min(1, dt * 8);
    state.tiltX += (state.aimX - state.tiltX) * Math.min(1, dt * 5);
    state.tiltY += (state.aimY - state.tiltY) * Math.min(1, dt * 5);
    button.rotation.y = state.tiltX * 0.1;
    camera.lookAt(lookAt.x, lookAt.y - state.tiltY * 0.12, lookAt.z);
    camera.updateMatrixWorld();

    // kursor: pose relatif titik klik, ikut miring bersama tombol, menghadap kamera
    const pose = cursorPose(state.phase, enterFrom, exitTo);
    const alpha = pose.alpha * state.show;
    const surface = capGroup.position.y + capTop;
    tip.set(target.x + pose.x, surface + 0.02 + pose.y, target.z + pose.z).applyAxisAngle(UP, button.rotation.y);
    cursor.group.position.copy(tip);
    cursor.group.quaternion.copy(camera.quaternion);
    cursor.group.scale.setScalar(pose.scale);
    cursor.group.visible = alpha > 0.01;
    cursor.body.material.opacity = alpha;
    cursor.edge.material.opacity = alpha;

    // bayangan kursor di keycap: makin dekat permukaan makin tegas & kecil
    const lift = Math.max(0, pose.y);
    cursorShadow.position.set(target.x + pose.x + 0.32 + lift * 0.5, capTop + 0.005, target.z + pose.z + 0.36 + lift * 0.3);
    cursorShadow.scale.setScalar(0.8 + lift * 1.2);
    cursorShadow.material.opacity = alpha * 0.55 * Math.max(0, 1 - lift * 1.6);

    // garis "klik" memercik dari ujung kursor
    if (state.sparkT < 1) {
      state.sparkT = Math.min(1, state.sparkT + dt / 0.42);
      const e = 1 - Math.pow(1 - state.sparkT, 3);
      cursor.sparkMaterial.opacity = alpha * (1 - state.sparkT);
      cursor.sparks.children.forEach((spark) => {
        const reach = 0.2 + e * 0.16;
        spark.position.set(spark.userData.dir.x * reach, spark.userData.dir.y * reach, 0.05);
        spark.scale.set(0.6 + e * 0.6, 1, 1);
      });
    } else {
      cursor.sparkMaterial.opacity = 0;
    }

    // cincin klik di permukaan keycap
    if (state.ringT < 1) {
      state.ringT = Math.min(1, state.ringT + dt / 0.6);
      const e = 1 - Math.pow(1 - state.ringT, 3);
      ring.scale.set(1 + e * 4.2, 1 + e * 4.2, 1);
      ring.material.opacity = 0.42 * (1 - state.ringT);
    } else {
      ring.material.opacity = 0;
    }

    // riak cahaya tepi
    if (state.rippleT < 1) {
      state.rippleT = Math.min(1, state.rippleT + dt / 0.7);
      const e = 1 - Math.pow(1 - state.rippleT, 3);
      ripple.scale.set(1 + e * 0.12, 1, 1 + e * 0.4);
      ripple.material.opacity = 0.6 * (1 - state.rippleT);
    } else {
      ripple.material.opacity = 0;
    }

    draw();

    if (!shown) {
      shown = true;
      link.classList.add('is-3d');
      layout();
      draw();
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
    if (state.hot && !hot) {
      // pointer pergi: kursor tiruan masuk lagi dari awal siklus
      state.phase = 0;
      state.clicked = false;
    }
    state.hot = hot;
    if (reduced) {
      start();
    }
  };

  link.addEventListener('pointerenter', (event) => {
    if (event.pointerType !== 'touch') {
      setHot(true);
    }
  });
  link.addEventListener('pointerleave', () => {
    if (document.activeElement !== link) {
      setHot(false);
    }
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

  const raycaster = new THREE.Raycaster();
  const pointer = new THREE.Vector2();

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
    // tombol tertekan di titik yang diklik (Enter/ketuk tanpa koordinat: tengah)
    let x = 0;
    let z = 0;
    const rect = stage.getBoundingClientRect();
    if (event.detail > 0 && rect.width > 0) {
      pointer.set(((event.clientX - rect.left) / rect.width) * 2 - 1, -((event.clientY - rect.top) / rect.height) * 2 + 1);
      raycaster.setFromCamera(pointer, camera);
      const hit = raycaster.intersectObject(cap, false)[0];
      if (hit) {
        const local = capGroup.worldToLocal(hit.point.clone());
        x = local.x;
        z = local.z;
      }
    }
    state.go = true;
    press(x, z, 0.14);
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
        draw();
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
      [scene, overlay].forEach((target) => {
        target.traverse((o) => {
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
