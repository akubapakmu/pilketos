/**
 * Kartu avatar dasbor siswa & guru (Stage 26, Living Election Wall).
 * Markup: app/Views/partials/avatar_card.php ([data-avatar-card]), gaya:
 * avatar.css. Server: App\Controllers\AvatarController (JSON untuk fetch).
 *
 * - Acak avatar: pratinjau gaya + seed baru (crypto.getRandomValues), lalu
 *   "Pakai avatar ini" -> POST avatar/acak {style, seed}; "Batal" kembali.
 * - Unggah foto: file dibaca browser (createImageBitmap, orientasi EXIF),
 *   dialog potong: masker lingkaran, geser (pointer/sentuh), zoom 1-3x
 *   (penggeser, tombol, roda mouse, cubit dua jari), panah & +/- di keyboard
 *   -> kanvas 512 x 512 -> WebP 0,85 (cadangan JPEG 0,88) -> POST multipart
 *   dengan header X-CSRF-TOKEN. File asli maks. 15 MB (data-max-bytes).
 * - Hapus foto: POST avatar/hapus -> avatar bawaan.
 * - Kemiringan 3D halus (maks. 6deg) hanya untuk pointer halus; tidak ada
 *   gerak saat prefers-reduced-motion.
 * Tanpa JavaScript (atau bila modul avatar gagal dimuat: .is-fallback)
 * ketiga formulir tetap berfungsi (redirect + pesan). avatar-art.js dimuat
 * lewat import() dari URL ber-versi (data-art-module, asset_url) agar tidak
 * tertahan cache lama.
 * Data pemilih tidak disimpan di localStorage/sessionStorage.
 */

const OUTPUT = 512;
const ZOOM_MIN = 1;
const ZOOM_MAX = 3;
const TILT_MAX = 6;

const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

function csrfHeaders() {
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  const meta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
  if (meta) {
    headers['X-CSRF-TOKEN'] = meta.getAttribute('content');
  }
  return headers;
}

/** POST (FormData atau JSON). Resolve {status, data}; sesi habis -> login. */
function send(url, body) {
  const headers = csrfHeaders();
  let payload = body;
  if (!(body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body || {});
  }

  return fetch(url, { method: 'POST', headers: headers, credentials: 'same-origin', body: payload })
    .then((response) => response.json().catch(() => ({})).then((data) => {
      if (response.status === 401 && data && data.redirect) {
        window.location.assign(data.redirect);
      }
      return { status: response.status, data: data || {} };
    }));
}

/** Gambar dari File: createImageBitmap (orientasi EXIF), cadangan <img>. */
function loadImage(file) {
  if ('createImageBitmap' in window) {
    return window.createImageBitmap(file, { imageOrientation: 'from-image' })
      .catch(() => window.createImageBitmap(file));
  }

  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('decode'));
    };
    img.src = url;
  });
}

function canvasBlob(canvas) {
  return new Promise((resolve) => {
    canvas.toBlob((webp) => {
      if (webp && webp.type === 'image/webp') {
        resolve({ blob: webp, name: 'avatar.webp' });
        return;
      }
      canvas.toBlob((jpeg) => resolve(jpeg ? { blob: jpeg, name: 'avatar.jpg' } : null), 'image/jpeg', 0.88);
    }, 'image/webp', 0.85);
  });
}

/* ------------------------------------------------------------------ */
/* dialog potong                                                      */
/* ------------------------------------------------------------------ */

function Cropper(dialog) {
  const stage = dialog.querySelector('[data-crop-stage]');
  const canvas = dialog.querySelector('[data-crop-canvas]');
  const range = dialog.querySelector('[data-crop-range]');
  const error = dialog.querySelector('[data-crop-error]');
  const saveButton = dialog.querySelector('[data-crop-save]');
  const ctx = canvas.getContext('2d');

  let image = null;
  let size = 0; // sisi stage (px CSS)
  let zoom = 1;
  let x = 0; // posisi kiri-atas gambar di stage (px CSS)
  let y = 0;
  let pointers = new Map();
  let pinch = null;
  let onSave = null;

  function natural() {
    return { w: image.naturalWidth || image.width, h: image.naturalHeight || image.height };
  }

  function scale() {
    const n = natural();
    return Math.max(size / n.w, size / n.h) * zoom;
  }

  function clamp() {
    const n = natural();
    const s = scale();
    x = Math.min(0, Math.max(size - n.w * s, x));
    y = Math.min(0, Math.max(size - n.h * s, y));
  }

  function draw() {
    if (!image || !size) {
      return;
    }
    const ratio = Math.min(window.devicePixelRatio || 1, 2);
    if (canvas.width !== Math.round(size * ratio)) {
      canvas.width = Math.round(size * ratio);
      canvas.height = Math.round(size * ratio);
    }
    const n = natural();
    const s = scale();
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, size, size);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(image, x, y, n.w * s, n.h * s);
  }

  /** Zoom dengan titik (px stage) tetap di tempat, bawaan: tengah. */
  function setZoom(value, cx, cy) {
    const next = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, value));
    const px = cx === undefined ? size / 2 : cx;
    const py = cy === undefined ? size / 2 : cy;
    const before = scale();
    zoom = next;
    const after = scale();
    x = px - (px - x) * (after / before);
    y = py - (py - y) * (after / before);
    clamp();
    range.value = String(zoom);
    draw();
  }

  function move(dx, dy) {
    x += dx;
    y += dy;
    clamp();
    draw();
  }

  function measure() {
    size = stage.clientWidth;
  }

  function point(event) {
    const rect = stage.getBoundingClientRect();
    return { x: event.clientX - rect.left, y: event.clientY - rect.top };
  }

  stage.addEventListener('pointerdown', (event) => {
    if (!image) {
      return;
    }
    stage.setPointerCapture(event.pointerId);
    pointers.set(event.pointerId, point(event));
    stage.classList.add('is-dragging');
    if (pointers.size === 2) {
      const [a, b] = Array.from(pointers.values());
      pinch = { distance: Math.hypot(a.x - b.x, a.y - b.y), zoom: zoom };
    }
  });

  stage.addEventListener('pointermove', (event) => {
    if (!pointers.has(event.pointerId)) {
      return;
    }
    const previous = pointers.get(event.pointerId);
    const current = point(event);
    pointers.set(event.pointerId, current);

    if (pinch && pointers.size === 2) {
      const [a, b] = Array.from(pointers.values());
      const distance = Math.hypot(a.x - b.x, a.y - b.y);
      setZoom(pinch.zoom * (distance / Math.max(1, pinch.distance)), (a.x + b.x) / 2, (a.y + b.y) / 2);
      return;
    }
    move(current.x - previous.x, current.y - previous.y);
  });

  function release(event) {
    pointers.delete(event.pointerId);
    if (pointers.size < 2) {
      pinch = null;
    }
    if (pointers.size === 0) {
      stage.classList.remove('is-dragging');
    }
  }

  stage.addEventListener('pointerup', release);
  stage.addEventListener('pointercancel', release);

  stage.addEventListener('wheel', (event) => {
    if (!image) {
      return;
    }
    event.preventDefault();
    const p = point(event);
    setZoom(zoom * (event.deltaY < 0 ? 1.08 : 1 / 1.08), p.x, p.y);
  }, { passive: false });

  stage.addEventListener('keydown', (event) => {
    const step = event.shiftKey ? 24 : 8;
    const keys = {
      ArrowLeft: () => move(step, 0),
      ArrowRight: () => move(-step, 0),
      ArrowUp: () => move(0, step),
      ArrowDown: () => move(0, -step),
      '+': () => setZoom(zoom + 0.1),
      '=': () => setZoom(zoom + 0.1),
      '-': () => setZoom(zoom - 0.1),
      _: () => setZoom(zoom - 0.1)
    };
    if (keys[event.key]) {
      event.preventDefault();
      keys[event.key]();
    }
  });

  range.addEventListener('input', () => setZoom(parseFloat(range.value) || 1));

  dialog.querySelectorAll('[data-crop-step]').forEach((button) => {
    button.addEventListener('click', () => setZoom(zoom + 0.25 * Number(button.getAttribute('data-crop-step'))));
  });

  dialog.querySelector('[data-crop-cancel]').addEventListener('click', () => dialog.close());

  dialog.addEventListener('close', () => {
    if (image && typeof image.close === 'function') {
      image.close();
    }
    image = null;
    pointers = new Map();
    pinch = null;
    saveButton.removeAttribute('aria-disabled');
  });

  saveButton.addEventListener('click', () => {
    if (!image || saveButton.getAttribute('aria-disabled') === 'true') {
      return;
    }
    const out = document.createElement('canvas');
    out.width = OUTPUT;
    out.height = OUTPUT;
    const k = OUTPUT / size;
    const n = natural();
    const s = scale();
    const octx = out.getContext('2d');
    octx.imageSmoothingQuality = 'high';
    octx.drawImage(image, x * k, y * k, n.w * s * k, n.h * s * k);

    saveButton.setAttribute('aria-disabled', 'true');
    canvasBlob(out).then((file) => {
      if (!file) {
        throw new Error('encode');
      }
      return onSave(file);
    }).then((ok) => {
      if (ok) {
        dialog.close();
      } else {
        saveButton.removeAttribute('aria-disabled');
      }
    }).catch(() => {
      showError('Foto gagal diproses. Coba foto lain.');
      saveButton.removeAttribute('aria-disabled');
    });
  });

  function showError(message) {
    error.textContent = message;
    error.hidden = !message;
  }

  return {
    open(source, save) {
      image = source;
      onSave = save;
      zoom = 1;
      range.value = '1';
      showError('');
      dialog.showModal();
      measure();
      const n = natural();
      const s = scale();
      x = (size - n.w * s) / 2;
      y = (size - n.h * s) / 2;
      draw();
      stage.focus({ preventScroll: true });
    },
    showError: showError,
    resize() {
      if (image && dialog.open) {
        const previous = size;
        measure();
        if (previous && size !== previous) {
          x *= size / previous;
          y *= size / previous;
          clamp();
        }
        draw();
      }
    }
  };
}

/* ------------------------------------------------------------------ */
/* kartu avatar                                                       */
/* ------------------------------------------------------------------ */

function initCard(card, art) {
  const paint = art.paint;
  const randomAvatar = art.randomAvatar;
  const current = card.querySelector('[data-avatar-current]');
  const caption = card.querySelector('[data-avatar-caption]');
  const status = card.querySelector('[data-avatar-status]');
  const fileInput = card.querySelector('[data-avatar-file]');
  const randomForm = card.querySelector('[data-avatar-random]');
  const removeForm = card.querySelector('[data-avatar-remove]');
  const applyButton = card.querySelector('[data-avatar-apply]');
  const cancelButton = card.querySelector('[data-avatar-cancel]');
  const cropper = Cropper(card.querySelector('[data-avatar-crop]'));
  const maxBytes = Number(card.getAttribute('data-max-bytes')) || 15 * 1024 * 1024;
  const you = card.getAttribute('data-pronoun') === 'Anda' ? 'Anda' : 'kamu';

  // Avatar tersimpan (bukan pratinjau); diperbarui dari balasan server.
  let saved = {
    photo: current.classList.contains('avatar--photo') ? (current.querySelector('.avatar__img') || {}).src || null : null,
    style: current.getAttribute('data-avatar-style'),
    seed: current.getAttribute('data-avatar-seed'),
    custom: card.getAttribute('data-avatar-custom') === '1'
  };
  let preview = null;

  function say(message, isError) {
    status.textContent = message || '';
    status.classList.toggle('is-error', !!isError);
  }

  function busy(on) {
    card.classList.toggle('is-busy', on);
    card.setAttribute('aria-busy', on ? 'true' : 'false');
  }

  function captionFor(avatar) {
    if (avatar.photo) {
      return 'Foto ' + you;
    }
    return avatar.custom ? (you === 'Anda' ? 'Avatar pilihan Anda' : 'Avatar pilihanmu') : 'Avatar bawaan';
  }

  function setPreview(avatar) {
    preview = avatar;
    applyButton.hidden = !avatar;
    cancelButton.hidden = !avatar;
    current.classList.toggle('is-preview', !!avatar);
  }

  function show(avatar) {
    saved = avatar;
    paint(current, avatar);
    card.setAttribute('data-avatar-custom', avatar.custom ? '1' : '0');
    caption.textContent = captionFor(avatar);
    removeForm.hidden = !avatar.photo;
    setPreview(null);
  }

  function handle(result, fallback) {
    busy(false);
    if (result.status === 200 && result.data.avatar) {
      show(result.data.avatar);
      say(result.data.message || fallback);
      return true;
    }
    say(result.data.message || 'Avatar gagal disimpan. Coba lagi.', true);
    return false;
  }

  function failed() {
    busy(false);
    say('Koneksi terputus. Periksa jaringan lalu coba lagi.', true);
    return false;
  }

  // -- acak avatar -> pratinjau -> pakai / batal ----------------------
  // stopPropagation: penjaga submit ganda app.js tidak menandai formulir
  // ini (formulir tidak benar-benar dikirim, tombolnya dipakai berulang).
  randomForm.addEventListener('submit', (event) => {
    event.preventDefault();
    event.stopPropagation();
    const next = randomAvatar();
    paint(current, next);
    setPreview(next);
    caption.textContent = 'Pratinjau avatar baru';
    say('');
  });

  applyButton.addEventListener('click', () => {
    if (!preview) {
      return;
    }
    busy(true);
    send(randomForm.action, { style: preview.style, seed: preview.seed })
      .then((result) => handle(result, 'Avatar baru dipakai.'), failed);
  });

  cancelButton.addEventListener('click', () => {
    show(saved);
    say('');
  });

  // -- hapus foto -------------------------------------------------------
  removeForm.addEventListener('submit', (event) => {
    event.preventDefault();
    event.stopPropagation();
    busy(true);
    send(removeForm.action, {}).then((result) => handle(result, 'Foto dihapus.'), failed);
  });

  // -- unggah foto -> dialog potong --------------------------------------
  fileInput.addEventListener('change', () => {
    const file = fileInput.files && fileInput.files[0];
    fileInput.value = '';
    if (!file) {
      return;
    }
    if (file.size > maxBytes) {
      say('Foto terlalu besar (maksimal ' + Math.round(maxBytes / 1048576) + ' MB).', true);
      return;
    }
    say('Membuka foto...');
    loadImage(file).then((image) => {
      say('');
      cropper.open(image, (blob) => {
        const form = new FormData();
        form.append('avatar', blob.blob, blob.name);
        busy(true);
        return send(card.getAttribute('data-upload-url'), form).then((result) => {
          const ok = handle(result, 'Foto avatar disimpan.');
          if (!ok) {
            cropper.showError(result.data.message || 'Foto gagal disimpan.');
          }
          return ok;
        }, () => {
          failed();
          cropper.showError('Koneksi terputus. Coba lagi.');
          return false;
        });
      });
    }).catch(() => {
      say('Format foto ini belum dapat dibaca browser. Pilih foto JPG, PNG, atau WebP.', true);
    });
  });

  window.addEventListener('resize', () => cropper.resize());

  // -- kemiringan 3D halus (pointer halus saja) ------------------------
  const tilt = card.querySelector('[data-avatar-tilt]');
  if (tilt && finePointer && !reduced) {
    let frame = 0;
    tilt.addEventListener('pointermove', (event) => {
      const rect = tilt.getBoundingClientRect();
      const px = (event.clientX - rect.left) / rect.width - 0.5;
      const py = (event.clientY - rect.top) / rect.height - 0.5;
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        tilt.classList.add('is-tilting');
        tilt.style.setProperty('--tilt-x', (-py * 2 * TILT_MAX).toFixed(2) + 'deg');
        tilt.style.setProperty('--tilt-y', (px * 2 * TILT_MAX).toFixed(2) + 'deg');
      });
    });
    tilt.addEventListener('pointerleave', () => {
      cancelAnimationFrame(frame);
      tilt.classList.remove('is-tilting');
      tilt.style.setProperty('--tilt-x', '0deg');
      tilt.style.setProperty('--tilt-y', '0deg');
    });
  }
}

document.querySelectorAll('[data-avatar-card]').forEach((card) => {
  import(card.getAttribute('data-art-module')).then((art) => {
    initCard(card, art);
  }).catch(() => {
    // Modul avatar gagal dimuat: formulir biasa (tanpa JavaScript) dipakai.
    card.classList.add('is-fallback');
  });
});
