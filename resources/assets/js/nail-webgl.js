/**
 * Paku coblos 3D — renderer WebGL mandiri (Stage 2).
 *
 * Sengaja tanpa Three.js: yang dirender hanya satu objek kecil (paku hasil
 * "lathe" + bayangan planar), jadi WebGL langsung (~14 KB, tanpa minify) jauh lebih ringan
 * untuk HP siswa daripada library 600 KB, dan tetap berjalan tanpa internet.
 *
 * Dimuat malas (lazy) oleh ballot.js hanya bila mode 3D dipilih. Renderer
 * tidak menyimpan state interaksi: ballot.js memanggil render(state) per frame
 * dan hanya selama paku bergerak (tidak ada render loop saat diam).
 *
 * Koordinat: kanvas kecil (CSS 300px) yang titik jangkarnya (anchor) berada
 * tepat di ujung paku. Kamera di atas
 * bidang kertas (z = 0) menghadap ke bawah; paku berdiri dari ujungnya ke arah
 * kamera dengan kemiringan & azimut tertentu. Bagian paku di bawah kertas
 * (z < 0) dibuang di fragment shader sehingga paku tampak menembus kertas.
 */
(function () {
  'use strict';

  var CANVAS_CSS = 300;
  // Posisi ujung paku di kanvas (0..1 dari kiri/atas). Kepala paku & bayangannya
  // mengarah ke kanan-atas / kanan-bawah, jadi ujung diletakkan kiri-bawah tengah.
  var ANCHOR_X = 0.36;
  var ANCHOR_Y = 0.6;
  var CAM_Z = 14;
  var FOV = 30 * Math.PI / 180;
  var NAIL_LENGTH = 4.0;
  var SEGMENTS = 32;
  var LIGHT = normalize([-0.45, 0.55, 1.0]);

  var VERTEX = [
    'attribute vec3 aPos;',
    'attribute vec3 aNormal;',
    'uniform mat4 uModel;',
    'uniform mat4 uViewProj;',
    'uniform mat3 uNormalMat;',
    // presisi harus sama dengan deklarasi di fragment shader (uniform bersama)
    'uniform mediump float uShadow;',
    'uniform vec3 uLight;',
    'uniform vec2 uJitter;',
    'varying vec3 vNormal;',
    'varying vec3 vWorld;',
    'void main() {',
    '  vec4 world = uModel * vec4(aPos, 1.0);',
    '  if (uShadow > 0.5) {',
    '    float z = max(world.z, 0.0);',
    '    world.xy -= uLight.xy * (z / uLight.z);',
    '    world.xy += uJitter;',
    '    world.z = 0.002;',
    '  }',
    '  vWorld = world.xyz;',
    '  vNormal = uNormalMat * aNormal;',
    '  gl_Position = uViewProj * world;',
    '}'
  ].join('\n');

  var FRAGMENT = [
    'precision mediump float;',
    'varying vec3 vNormal;',
    'varying vec3 vWorld;',
    'uniform vec3 uCamera;',
    'uniform vec3 uRim;',
    'uniform float uRimStrength;',
    'uniform float uAlpha;',
    'uniform float uShadow;',
    'uniform float uShadowAlpha;',
    'void main() {',
    '  if (uShadow > 0.5) {',
    '    gl_FragColor = vec4(0.08, 0.078, 0.10, 1.0) * uShadowAlpha;',
    '    return;',
    '  }',
    '  if (vWorld.z < 0.0) { discard; }',
    '  vec3 N = normalize(vNormal);',
    '  vec3 V = normalize(uCamera - vWorld);',
    '  if (dot(N, V) < 0.0) { N = -N; }',
    '  vec3 L1 = normalize(vec3(-0.5, 0.65, 0.85));',
    '  vec3 L2 = normalize(vec3(0.8, -0.35, 0.45));',
    '  float d1 = max(dot(N, L1), 0.0);',
    '  float d2 = max(dot(N, L2), 0.0);',
    '  float s1 = pow(max(dot(N, normalize(L1 + V)), 0.0), 64.0);',
    '  float s2 = pow(max(dot(N, normalize(L2 + V)), 0.0), 24.0);',
    '  vec3 R = reflect(-V, N);',
    // pantulan lingkungan sederhana: langit terang di atas, lantai gelap di bawah
    '  float env = smoothstep(-0.4, 0.8, R.y);',
    '  float fres = pow(1.0 - max(dot(N, V), 0.0), 3.0);',
    '  vec3 steel = vec3(0.66, 0.68, 0.71);',
    '  vec3 col = steel * (0.26 + 0.56 * d1 + 0.2 * d2)',
    '    + vec3(0.2 * env)',
    '    + vec3(0.9 * s1 + 0.3 * s2)',
    '    + uRim * fres * uRimStrength;',
    '  gl_FragColor = vec4(min(col, vec3(1.0)) * uAlpha, uAlpha);',
    '}'
  ].join('\n');

  /* ---------- matematika kecil ---------- */

  function normalize(v) {
    var len = Math.sqrt(v[0] * v[0] + v[1] * v[1] + v[2] * v[2]) || 1;
    return [v[0] / len, v[1] / len, v[2] / len];
  }

  function cross(a, b) {
    return [a[1] * b[2] - a[2] * b[1], a[2] * b[0] - a[0] * b[2], a[0] * b[1] - a[1] * b[0]];
  }

  // Matriks column-major (format WebGL).
  function multiply(a, b) {
    var out = new Float32Array(16);
    for (var col = 0; col < 4; col++) {
      for (var row = 0; row < 4; row++) {
        var sum = 0;
        for (var k = 0; k < 4; k++) {
          sum += a[k * 4 + row] * b[col * 4 + k];
        }
        out[col * 4 + row] = sum;
      }
    }
    return out;
  }

  function perspective(fov, aspect, near, far) {
    var f = 1 / Math.tan(fov / 2);
    var nf = 1 / (near - far);
    return new Float32Array([
      f / aspect, 0, 0, 0,
      0, f, 0, 0,
      0, 0, (far + near) * nf, -1,
      0, 0, 2 * far * near * nf, 0
    ]);
  }

  function translation(x, y, z) {
    return new Float32Array([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, x, y, z, 1]);
  }

  /**
   * Model matrix: sumbu Y lokal paku (ujung -> kepala) diarahkan ke arah
   * (tilt dari sumbu z, azimut di bidang layar), diputar "spin" pada sumbunya.
   */
  function modelMatrix(state) {
    var tilt = Math.max(0.03, state.tilt * Math.PI / 180);
    var az = state.az * Math.PI / 180;
    var axis = [Math.sin(tilt) * Math.cos(az), Math.sin(tilt) * Math.sin(az), Math.cos(tilt)];
    var xAxis = normalize([-axis[1], axis[0], 0]);
    var zAxis = cross(xAxis, axis);

    var c = Math.cos(state.spin || 0);
    var s = Math.sin(state.spin || 0);
    // putar basis X/Z di sekitar sumbu paku
    var x2 = [xAxis[0] * c - zAxis[0] * s, xAxis[1] * c - zAxis[1] * s, xAxis[2] * c - zAxis[2] * s];
    var z2 = [xAxis[0] * s + zAxis[0] * c, xAxis[1] * s + zAxis[1] * c, xAxis[2] * s + zAxis[2] * c];

    var k = state.scale;
    var rotation = new Float32Array([
      x2[0] * k, x2[1] * k, x2[2] * k, 0,
      axis[0] * k, axis[1] * k, axis[2] * k, 0,
      z2[0] * k, z2[1] * k, z2[2] * k, 0,
      0, 0, 0, 1
    ]);

    return {
      model: multiply(translation(0, 0, state.h), rotation),
      normal: new Float32Array([x2[0], x2[1], x2[2], axis[0], axis[1], axis[2], z2[0], z2[1], z2[2]])
    };
  }

  /* ---------- geometri paku (lathe) ---------- */

  function buildNail() {
    var L = NAIL_LENGTH;
    // profil [radius, tinggi] dari ujung ke kepala
    var profile = [
      [0.0, 0.0],
      [0.15, 0.62],
      [0.15, L - 0.36],
      [0.19, L - 0.27],
      [0.64, L - 0.17],
      [0.64, L - 0.04],
      [0.57, L],
      [0.0, L + 0.04]
    ];

    var positions = [];
    var normals = [];
    var indices = [];

    for (var p = 0; p < profile.length - 1; p++) {
      var r0 = profile[p][0];
      var y0 = profile[p][1];
      var r1 = profile[p + 1][0];
      var y1 = profile[p + 1][1];
      var dr = r1 - r0;
      var dy = y1 - y0;
      var len = Math.sqrt(dr * dr + dy * dy) || 1;
      var nr = dy / len;
      var ny = -dr / len;
      var base = positions.length / 3;

      for (var i = 0; i <= SEGMENTS; i++) {
        var theta = (i / SEGMENTS) * Math.PI * 2;
        var c = Math.cos(theta);
        var s = Math.sin(theta);
        positions.push(r0 * c, y0, r0 * s, r1 * c, y1, r1 * s);
        normals.push(nr * c, ny, nr * s, nr * c, ny, nr * s);
      }

      for (var j = 0; j < SEGMENTS; j++) {
        var a = base + j * 2;
        indices.push(a, a + 2, a + 1, a + 1, a + 2, a + 3);
      }
    }

    return {
      positions: new Float32Array(positions),
      normals: new Float32Array(normals),
      indices: new Uint16Array(indices)
    };
  }

  /* ---------- renderer ---------- */

  function compile(gl, type, source) {
    var shader = gl.createShader(type);
    gl.shaderSource(shader, source);
    gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
      var log = gl.getShaderInfoLog(shader);
      gl.deleteShader(shader);
      throw new Error('Shader: ' + log);
    }
    return shader;
  }

  function Renderer(canvas, gl) {
    this.canvas = canvas;
    this.gl = gl;
    this.lost = false;

    var vs = compile(gl, gl.VERTEX_SHADER, VERTEX);
    var fs = compile(gl, gl.FRAGMENT_SHADER, FRAGMENT);
    var program = gl.createProgram();
    gl.attachShader(program, vs);
    gl.attachShader(program, fs);
    gl.linkProgram(program);
    gl.deleteShader(vs);
    gl.deleteShader(fs);

    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
      throw new Error('Program: ' + gl.getProgramInfoLog(program));
    }

    this.program = program;
    this.loc = {
      aPos: gl.getAttribLocation(program, 'aPos'),
      aNormal: gl.getAttribLocation(program, 'aNormal')
    };
    ['uModel', 'uViewProj', 'uNormalMat', 'uShadow', 'uLight', 'uJitter', 'uCamera',
      'uRim', 'uRimStrength', 'uAlpha', 'uShadowAlpha'].forEach(function (name) {
      this.loc[name] = gl.getUniformLocation(program, name);
    }, this);

    var mesh = buildNail();
    this.count = mesh.indices.length;
    this.buffers = {
      pos: this.buffer(gl.ARRAY_BUFFER, mesh.positions),
      normal: this.buffer(gl.ARRAY_BUFFER, mesh.normals),
      index: this.buffer(gl.ELEMENT_ARRAY_BUFFER, mesh.indices)
    };

    var view = translation(0, 0, -CAM_Z);
    // Geser hasil proyeksi agar ujung paku jatuh di titik jangkar kanvas.
    var anchorShift = translation(ANCHOR_X * 2 - 1, 1 - ANCHOR_Y * 2, 0);
    this.viewProj = multiply(anchorShift, multiply(perspective(FOV, 1, 1, 50), view));

    var self = this;
    this.onLost = function (event) {
      event.preventDefault();
      self.lost = true;
    };
    canvas.addEventListener('webglcontextlost', this.onLost);

    this.resize();
  }

  Renderer.prototype.buffer = function (target, data) {
    var gl = this.gl;
    var buffer = gl.createBuffer();
    gl.bindBuffer(target, buffer);
    gl.bufferData(target, data, gl.STATIC_DRAW);
    return buffer;
  };

  Renderer.prototype.resize = function () {
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    var size = Math.round(CANVAS_CSS * dpr);
    // Cek lebar DAN tinggi: kanvas bawaan berukuran 300x150.
    if (this.canvas.width !== size || this.canvas.height !== size) {
      this.canvas.width = size;
      this.canvas.height = size;
    }
    this.gl.viewport(0, 0, size, size);
  };

  /**
   * @param {{h:number,tilt:number,az:number,spin:number,scale:number,alpha:number,rim:number[],rimStrength:number}} state
   */
  Renderer.prototype.render = function (state) {
    if (this.lost) {
      return false;
    }

    var gl = this.gl;
    var loc = this.loc;
    var matrices = modelMatrix(state);

    gl.clearColor(0, 0, 0, 0);
    gl.clear(gl.COLOR_BUFFER_BIT | gl.DEPTH_BUFFER_BIT);

    if (state.alpha <= 0.01) {
      return true;
    }

    gl.useProgram(this.program);
    gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.pos);
    gl.enableVertexAttribArray(loc.aPos);
    gl.vertexAttribPointer(loc.aPos, 3, gl.FLOAT, false, 0, 0);
    gl.bindBuffer(gl.ARRAY_BUFFER, this.buffers.normal);
    gl.enableVertexAttribArray(loc.aNormal);
    gl.vertexAttribPointer(loc.aNormal, 3, gl.FLOAT, false, 0, 0);
    gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, this.buffers.index);

    gl.uniformMatrix4fv(loc.uModel, false, matrices.model);
    gl.uniformMatrix4fv(loc.uViewProj, false, this.viewProj);
    gl.uniformMatrix3fv(loc.uNormalMat, false, matrices.normal);
    gl.uniform3fv(loc.uLight, LIGHT);
    gl.uniform3f(loc.uCamera, 0, 0, CAM_Z);
    gl.uniform3fv(loc.uRim, state.rim);
    gl.uniform1f(loc.uRimStrength, state.rimStrength);
    gl.uniform1f(loc.uAlpha, state.alpha);

    gl.enable(gl.BLEND);
    gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);
    gl.enable(gl.DEPTH_TEST);
    gl.depthFunc(gl.LESS);
    gl.disable(gl.CULL_FACE);

    // Bayangan lembut: beberapa lintasan bergeser sedikit. Depth dibersihkan
    // per lintasan agar segitiga yang bertumpuk tidak menggelapkan dua kali.
    var lift = Math.max(0, state.h);
    var spread = 0.035 + 0.06 * lift;
    var jitters = [[0, 0], [spread, 0], [-spread, 0], [0, spread], [0, -spread]];
    var shadowAlpha = (0.3 - 0.07 * Math.min(lift, 2)) * state.alpha / jitters.length;

    gl.uniform1f(loc.uShadow, 1);
    gl.uniform1f(loc.uShadowAlpha, shadowAlpha);
    for (var i = 0; i < jitters.length; i++) {
      gl.clear(gl.DEPTH_BUFFER_BIT);
      gl.uniform2f(loc.uJitter, jitters[i][0], jitters[i][1]);
      gl.drawElements(gl.TRIANGLES, this.count, gl.UNSIGNED_SHORT, 0);
    }

    gl.clear(gl.DEPTH_BUFFER_BIT);
    gl.uniform1f(loc.uShadow, 0);
    gl.drawElements(gl.TRIANGLES, this.count, gl.UNSIGNED_SHORT, 0);

    return !gl.isContextLost();
  };

  /**
   * Lepas semua resource GPU (dipanggil saat beralih ke mode ringan atau
   * halaman ditinggal). Stage 4: konteks WebGL ikut dilepas segera lewat
   * WEBGL_lose_context agar memori GPU HP kelas bawah tidak menunggu GC.
   */
  Renderer.prototype.destroy = function () {
    var gl = this.gl;
    this.canvas.removeEventListener('webglcontextlost', this.onLost);
    if (!gl.isContextLost()) {
      gl.deleteBuffer(this.buffers.pos);
      gl.deleteBuffer(this.buffers.normal);
      gl.deleteBuffer(this.buffers.index);
      gl.deleteProgram(this.program);
      var lose = gl.getExtension('WEBGL_lose_context');
      if (lose) {
        lose.loseContext();
      }
    }
    this.lost = true;
  };

  /**
   * Buat renderer, atau null bila WebGL tidak tersedia / lambat / gagal.
   * force = true: pengguna memilih 3D sendiri, jadi renderer software
   * (failIfMajorPerformanceCaveat) tetap diizinkan.
   */
  function create(canvas, force) {
    try {
      var attributes = {
        alpha: true,
        antialias: true,
        premultipliedAlpha: true,
        depth: true,
        stencil: false,
        powerPreference: 'low-power',
        failIfMajorPerformanceCaveat: !force
      };
      var gl = canvas.getContext('webgl', attributes) || canvas.getContext('experimental-webgl', attributes);
      if (!gl) {
        return null;
      }
      return new Renderer(canvas, gl);
    } catch (error) {
      if (window.console && console.warn) {
        console.warn('Paku 3D tidak tersedia, memakai mode ringan.', error);
      }
      return null;
    }
  }

  window.NailWebGL = {
    create: create,
    size: CANVAS_CSS,
    // titik ujung paku dalam piksel CSS dari kiri-atas kanvas
    anchor: { x: CANVAS_CSS * ANCHOR_X, y: CANVAS_CSS * ANCHOR_Y }
  };
})();
