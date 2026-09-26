import { useEffect, useRef } from "react";
import * as THREE from "three";

/* =========================================================
   ASSETS
========================================================= */

const heroImageModules = import.meta.glob("../../assets/fotohero1*.*", {
  eager: true,
  import: "default",
});

function getHeroImage(fragment) {
  return (
    Object.entries(heroImageModules).find(([path]) =>
      path.includes(fragment),
    )?.[1] || ""
  );
}

const images = [
  getHeroImage("fotohero1 (1)"),
  getHeroImage("fotohero1 (2)"),
  getHeroImage("fotohero1 (3)"),
  getHeroImage("fotohero1 (4)"),
  getHeroImage("fotohero1 (5)"),
  getHeroImage("fotohero1 (6)"),
  getHeroImage("fotohero1 (7)"),
];

/* =========================================================
   DATOS DEMO
========================================================= */

const BASE_PROPERTIES = [
  {
    image: images[0],
    type: "Departamento",
    title: "Departamento 3 ambientes",
    price: "US$ 125.000",
    exchange: "Acepta permuta",
  },
  {
    image: images[1],
    type: "Casa",
    title: "Casa 4 ambientes",
    price: "US$ 210.000",
    exchange: "Permuta parcial",
  },
  {
    image: images[2],
    type: "PH",
    title: "PH con patio",
    price: "US$ 98.000",
    exchange: "Acepta permuta",
  },
  {
    image: images[3],
    type: "Departamento",
    title: "Departamento 2 ambientes",
    price: "US$ 145.000",
    exchange: "Permuta total",
  },
  {
    image: images[4],
    type: "Casa",
    title: "Casa con parque",
    price: "US$ 185.000",
    exchange: "Permuta + diferencia",
  },
  {
    image: images[5],
    type: "Departamento",
    title: "Departamento con vista",
    price: "US$ 165.000",
    exchange: "Acepta permuta",
  },
  {
    image: images[6],
    type: "Casa",
    title: "Casa moderna",
    price: "US$ 230.000",
    exchange: "Escucha propuestas",
  },
  {
    image: images[3],
    type: "Departamento",
    title: "Departamento céntrico",
    price: "US$ 135.000",
    exchange: "Permuta total",
  },
  {
    image: images[4],
    type: "Casa",
    title: "Casa con jardín",
    price: "US$ 195.000",
    exchange: "Permuta parcial",
  },
  {
    image: images[2],
    type: "PH",
    title: "PH 3 ambientes",
    price: "US$ 118.000",
    exchange: "Acepta permuta",
  },
  {
    image: images[3],
    type: "Departamento",
    title: "Departamento premium",
    price: "US$ 178.000",
    exchange: "Acepta permuta",
  },
  {
    image: images[5],
    type: "Casa",
    title: "Casa con quincho",
    price: "US$ 240.000",
    exchange: "Permuta + diferencia",
  },
  {
    image: images[0],
    type: "Departamento",
    title: "Departamento luminoso",
    price: "US$ 142.000",
    exchange: "Acepta permuta",
  },
  {
    image: images[6],
    type: "Casa",
    title: "Casa residencial",
    price: "US$ 260.000",
    exchange: "Escucha propuestas",
  },
];

/*
 * 24 slots para mantener el recorrido continuo.
 * 12 van hacia izquierda y 12 hacia derecha.
 */
const CARDS = Array.from({ length: 24 }, (_, index) => ({
  ...BASE_PROPERTIES[index % BASE_PROPERTIES.length],
  id: index + 1,
  textureIndex: index % BASE_PROPERTIES.length,
}));

/* =========================================================
   CONFIG
========================================================= */

const FIRE_INTERVAL = 900;
const FIRE_DURATION = 9.6;

const CAMERA_FOV = 45;
const CAMERA_Z = 5;

const CARD_ASPECT = 720 / 1040;

const DESKTOP_CARD_WIDTH = 0.75;
const MOBILE_CARD_WIDTH = 1.2;

const FIRE_TARGET = 1.65;

const GROUP_SCALE_START = 1.2;
const GROUP_SCALE_END = 0.5;
const GROUP_ZOOM_DURATION = 1500;

const REVEAL_DURATION = 1750;

const CYLINDRICAL_START = 1;
const CYLINDRICAL_END = 0.7;
const CYLINDRICAL_DURATION = 2000;

/*
 * Curvatura vertical adicional.
 *
 * Las cards centrales se levantan un poco más.
 * Ese lift va desapareciendo al acercarse a los extremos.
 */
const CENTER_LIFT_DESKTOP = 0.055;
const CENTER_LIFT_MOBILE = 0.04;
const CENTER_LIFT_POWER = 1.35;

/*
 * El cálculo principal centra matemáticamente el arco
 * entre título y bajada.
 *
 * Este valor permite desplazar ópticamente TODO el arco
 * un poco hacia abajo.
 *
 * MÁS ALTO = arco más abajo.
 * MÁS BAJO = arco más arriba.
 */
const STREAM_VISUAL_NUDGE_DESKTOP = 0.038;
const STREAM_VISUAL_NUDGE_MOBILE = 0.028;

/* =========================================================
   EASING
========================================================= */

function clamp01(value) {
  return Math.max(0, Math.min(1, value));
}

function smoothstep(edge0, edge1, x) {
  const t = clamp01((x - edge0) / (edge1 - edge0));

  return t * t * (3 - 2 * t);
}

function easeInQuad(value) {
  return value * value;
}

function power3InOut(value) {
  const t = clamp01(value);

  return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
}

function power4Out(value) {
  const t = clamp01(value);

  return 1 - Math.pow(1 - t, 4);
}

function lerp(start, end, progress) {
  return start + (end - start) * progress;
}

/* =========================================================
   POSICIONAMIENTO HERO
========================================================= */

function screenPxToWorldY(pixelY, viewportPxHeight, worldHeight) {
  return (0.5 - pixelY / viewportPxHeight) * worldHeight;
}

function getHeroAnchors(container) {
  const heroRoot =
    container.closest("[data-hero-root]") || container.parentElement;

  if (!heroRoot) {
    return {
      titleEl: null,
      subtitleEl: null,
    };
  }

  return {
    titleEl: heroRoot.querySelector("[data-hero-title]"),
    subtitleEl: heroRoot.querySelector("[data-hero-subtitle]"),
  };
}

/* =========================================================
   CANVAS CARD
========================================================= */

function roundRectPath(ctx, x, y, width, height, radius) {
  const r = Math.min(radius, width / 2, height / 2);

  ctx.beginPath();

  ctx.moveTo(x + r, y);
  ctx.lineTo(x + width - r, y);

  ctx.quadraticCurveTo(x + width, y, x + width, y + r);

  ctx.lineTo(x + width, y + height - r);

  ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);

  ctx.lineTo(x + r, y + height);

  ctx.quadraticCurveTo(x, y + height, x, y + height - r);

  ctx.lineTo(x, y + r);

  ctx.quadraticCurveTo(x, y, x + r, y);

  ctx.closePath();
}

function drawImageCover(ctx, image, x, y, width, height) {
  const imageRatio = image.width / image.height;

  const targetRatio = width / height;

  let sourceWidth = image.width;

  let sourceHeight = image.height;

  let sourceX = 0;
  let sourceY = 0;

  if (imageRatio > targetRatio) {
    sourceWidth = image.height * targetRatio;

    sourceX = (image.width - sourceWidth) / 2;
  } else {
    sourceHeight = image.width / targetRatio;

    sourceY = (image.height - sourceHeight) / 2;
  }

  ctx.drawImage(
    image,
    sourceX,
    sourceY,
    sourceWidth,
    sourceHeight,
    x,
    y,
    width,
    height,
  );
}

function wrapText(ctx, text, maxWidth, maxLines = 2) {
  const words = text.split(" ");

  const lines = [];

  let line = "";

  for (const word of words) {
    const test = line ? `${line} ${word}` : word;

    if (ctx.measureText(test).width <= maxWidth) {
      line = test;
      continue;
    }

    if (line) {
      lines.push(line);
    }

    line = word;

    if (lines.length === maxLines - 1) {
      break;
    }
  }

  if (line && lines.length < maxLines) {
    lines.push(line);
  }

  return lines;
}

function loadImage(src) {
  return new Promise((resolve, reject) => {
    const image = new Image();

    image.decoding = "async";

    image.onload = () => resolve(image);

    image.onerror = reject;

    image.src = src;
  });
}

async function createCardTexture(property) {
  const WIDTH = 720;
  const HEIGHT = 1040;
  const RADIUS = 32;

  const canvas = document.createElement("canvas");

  canvas.width = WIDTH;
  canvas.height = HEIGHT;

  const ctx = canvas.getContext("2d");

  ctx.clearRect(0, 0, WIDTH, HEIGHT);

  const image = await loadImage(property.image);

  /* =====================================================
     CARD
  ====================================================== */

  ctx.save();

  roundRectPath(ctx, 2, 2, WIDTH - 4, HEIGHT - 4, RADIUS);

  ctx.clip();

  ctx.fillStyle = "#ffffff";

  ctx.fillRect(0, 0, WIDTH, HEIGHT);

  /* =====================================================
     FOTO
  ====================================================== */

  const IMAGE_HEIGHT = 735;

  drawImageCover(ctx, image, 0, 0, WIDTH, IMAGE_HEIGHT);

  /* =====================================================
     BADGE PERMUTA
  ====================================================== */

  ctx.font = '800 18px "Manrope", Arial, sans-serif';

  const badgeText = property.exchange.toUpperCase();

  const badgeTextWidth = ctx.measureText(badgeText).width;

  const badgeWidth = badgeTextWidth + 42;

  const badgeHeight = 44;

  ctx.fillStyle = "rgba(255,255,255,0.95)";

  roundRectPath(ctx, 28, 28, badgeWidth, badgeHeight, badgeHeight / 2);

  ctx.fill();

  ctx.fillStyle = "#0a192f";

  ctx.textBaseline = "middle";

  ctx.fillText(badgeText, 49, 28 + badgeHeight / 2 + 1);

  /* =====================================================
     INFO
  ====================================================== */

  ctx.textBaseline = "alphabetic";

  ctx.fillStyle = "#047857";

  ctx.font = '800 19px "Manrope", Arial, sans-serif';

  ctx.fillText(property.type.toUpperCase(), 38, 805);

  ctx.fillStyle = "#0f172a";

  ctx.font = '800 39px "Manrope", Arial, sans-serif';

  const titleLines = wrapText(ctx, property.title, WIDTH - 76, 2);

  let titleY = 860;

  for (const line of titleLines) {
    ctx.fillText(line, 38, titleY);

    titleY += 45;
  }

  ctx.fillStyle = "#0f172a";

  ctx.font = '800 43px "Manrope", Arial, sans-serif';

  ctx.fillText(property.price, 38, 995);

  ctx.restore();

  /* =====================================================
     BORDE
  ====================================================== */

  ctx.strokeStyle = "#dbe3ed";

  ctx.lineWidth = 3;

  roundRectPath(ctx, 2, 2, WIDTH - 4, HEIGHT - 4, RADIUS);

  ctx.stroke();

  /* =====================================================
     TEXTURE
  ====================================================== */

  const texture = new THREE.CanvasTexture(canvas);

  texture.colorSpace = THREE.SRGBColorSpace;

  texture.generateMipmaps = false;

  texture.minFilter = THREE.LinearFilter;

  texture.magFilter = THREE.LinearFilter;

  texture.needsUpdate = true;

  return texture;
}

/* =========================================================
   POSTPROCESS
========================================================= */

const POST_VERTEX_SHADER = `
  varying vec2 vUv;

  void main() {
    vUv = uv;

    gl_Position =
      vec4(
        position.xy,
        0.0,
        1.0
      );
  }
`;

const POST_FRAGMENT_SHADER = `
  precision highp float;

  uniform sampler2D tMap;
  uniform float uCylindricalFactor;

  varying vec2 vUv;

  void main() {
    float cylindricalFactor =
      uCylindricalFactor;

    float stretchedY =
      vUv.y * cylindricalFactor
      + (1.0 - cylindricalFactor) * 0.5;

    float xFactor =
      abs(0.5 - vUv.x) * 2.0;

    xFactor =
      pow(xFactor, 2.0);

    vec2 uvCylindrical =
      vUv;

    uvCylindrical.y =
      mix(
        vUv.y,
        stretchedY,
        xFactor
      );

    vec4 color =
      texture2D(
        tMap,
        uvCylindrical
      );

    gl_FragColor =
      color;

    #include <colorspace_fragment>
  }
`;

/* =========================================================
   COMPONENTE
========================================================= */

export default function HeroCardsWebGL() {
  const containerRef = useRef(null);

  const canvasRef = useRef(null);

  useEffect(() => {
    const container = containerRef.current;

    const canvas = canvasRef.current;

    if (!container || !canvas) {
      return;
    }

    let destroyed = false;
    let rafId = null;

    let resizeObserver = null;
    let intersectionObserver = null;
    let alignTimeoutId = null;

    let isVisible = true;

    /* =====================================================
       RENDERER
    ====================================================== */

    const renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: true,
      stencil: false,
      depth: true,
      alpha: true,
      powerPreference: "high-performance",
    });

    renderer.outputColorSpace = THREE.SRGBColorSpace;

    renderer.setClearColor(new THREE.Color(0x000000), 0);

    /* =====================================================
       SCENE
    ====================================================== */

    const scene = new THREE.Scene();

    const root = new THREE.Group();

    scene.add(root);

    const cardsGroup = new THREE.Group();

    root.add(cardsGroup);

    /* =====================================================
       CAMERA
    ====================================================== */

    const camera = new THREE.PerspectiveCamera(CAMERA_FOV, 1, 0.1, 1000);

    camera.position.set(0, 0, CAMERA_Z);

    camera.lookAt(0, 0, 0);

    /* =====================================================
       RENDER TARGET
    ====================================================== */

    const gl = renderer.getContext();

    const target = new THREE.WebGLRenderTarget(1, 1, {
      type: renderer.capabilities.isWebGL2
        ? THREE.HalfFloatType
        : THREE.UnsignedByteType,

      minFilter: THREE.LinearFilter,

      magFilter: THREE.LinearFilter,

      depthBuffer: true,
      stencilBuffer: false,
    });

    target.texture.generateMipmaps = false;

    if (renderer.capabilities.isWebGL2) {
      const maxSamples = gl.getParameter(gl.MAX_SAMPLES) || 0;

      target.samples = Math.min(2, maxSamples);
    }

    /* =====================================================
       POST PROCESS
    ====================================================== */

    const postScene = new THREE.Scene();

    const postCamera = new THREE.OrthographicCamera(-1, 1, 1, -1, 0, 1);

    const postMaterial = new THREE.ShaderMaterial({
      vertexShader: POST_VERTEX_SHADER,

      fragmentShader: POST_FRAGMENT_SHADER,

      uniforms: {
        tMap: {
          value: target.texture,
        },

        uCylindricalFactor: {
          value: CYLINDRICAL_START,
        },
      },

      transparent: true,
      depthTest: false,
      depthWrite: false,
      toneMapped: false,
    });

    const postQuad = new THREE.Mesh(
      new THREE.PlaneGeometry(2, 2),
      postMaterial,
    );

    postScene.add(postQuad);

    /* =====================================================
       CARD ENGINE
    ====================================================== */

    const geometry = new THREE.PlaneGeometry(1, 1);

    const cardStates = [];

    const leftCards = [];
    const rightCards = [];

    let materials = [];
    let textures = [];

    let nextFireIndexLeft = 0;
    let nextFireIndexRight = 0;

    let fireAccumulator = 0;
    let revealFactor = 0;

    let viewportWorldWidth = 1;
    let viewportWorldHeight = 1;

    /*
     * Centro matemático entre título y bajada.
     */
    let targetCenterWorldY = 0;

    let isDesktop = true;

    /* =====================================================
       BUILD
    ====================================================== */

    async function buildCards() {
      if (document.fonts?.ready) {
        await document.fonts.ready;
      }

      textures = await Promise.all(BASE_PROPERTIES.map(createCardTexture));

      if (destroyed) {
        return;
      }

      materials = textures.map(
        (texture) =>
          new THREE.MeshBasicMaterial({
            map: texture,

            transparent: true,

            depthTest: true,

            /*
             * Las cards transparentes
             * no escriben profundidad.
             */
            depthWrite: false,

            alphaTest: 0.001,
          }),
      );

      CARDS.forEach((property, index) => {
        const material = materials[property.textureIndex];

        const mesh = new THREE.Mesh(geometry, material);

        const innerGroup = new THREE.Group();

        innerGroup.add(mesh);

        cardsGroup.add(innerGroup);

        const direction = index % 2 === 0 ? "left" : "right";

        const state = {
          property,
          mesh,
          innerGroup,
          direction,

          isFiring: false,
          fireProgress: 0,
          fireTargetX: 0,
        };

        innerGroup.visible = false;

        innerGroup.scale.set(0, 0, 0);

        cardStates.push(state);

        if (direction === "left") {
          leftCards.push(state);
        } else {
          rightCards.push(state);
        }
      });

      resize();

      /*
       * Recalculamos después de que termine
       * la animación inicial del H1.
       */
      alignTimeoutId = window.setTimeout(resize, 850);

      cardsGroup.scale.setScalar(GROUP_SCALE_START);

      preDistributeCards(leftCards);

      preDistributeCards(rightCards);

      startAnimation();
    }

    /* =====================================================
       FIRE
    ====================================================== */

    function fireCard(card, initialProgress = 0) {
      if (card.isFiring) {
        return false;
      }

      card.fireProgress = initialProgress;

      card.innerGroup.position.set(0, 0, 0);

      card.innerGroup.scale.set(0, 0, 0);

      card.innerGroup.visible = true;

      card.innerGroup.renderOrder = 0;

      card.isFiring = true;

      return true;
    }

    function preDistributeCards(pool) {
      const step = FIRE_INTERVAL / (1000 * FIRE_DURATION);

      for (let index = 0; index < pool.length; index += 1) {
        const progress = index * step;

        if (progress >= 1) {
          break;
        }

        fireCard(pool[index], progress);
      }
    }

    function fireNextInPool(pool, direction) {
      if (!pool.length) {
        return;
      }

      const isLeft = direction === "left";

      let current = isLeft ? nextFireIndexLeft : nextFireIndexRight;

      const count = pool.length;

      for (let attempt = 0; attempt < count; attempt += 1) {
        const index = (current + attempt) % count;

        const card = pool[index];

        if (!card.isFiring) {
          fireCard(card);

          if (isLeft) {
            nextFireIndexLeft = (index + 1) % count;
          } else {
            nextFireIndexRight = (index + 1) % count;
          }

          return;
        }
      }
    }

    function fireNextPair() {
      fireNextInPool(leftCards, "left");

      fireNextInPool(rightCards, "right");
    }

    /* =====================================================
       RESIZE / ALINEACIÓN
    ====================================================== */

    function resize() {
      const width = container.clientWidth;

      const height = container.clientHeight;

      if (!width || !height) {
        return;
      }

      isDesktop = width >= 1024;

      const pixelRatio = Math.min(window.devicePixelRatio || 1, 2);

      renderer.setPixelRatio(pixelRatio);

      renderer.setSize(width, height, false);

      target.setSize(
        Math.round(width * pixelRatio),
        Math.round(height * pixelRatio),
      );

      camera.aspect = width / height;

      camera.updateProjectionMatrix();

      viewportWorldHeight =
        2 *
        Math.tan(THREE.MathUtils.degToRad(camera.fov) / 2) *
        camera.position.z;

      viewportWorldWidth = viewportWorldHeight * camera.aspect;

      /* ===================================================
         DIMENSIONES
      ==================================================== */

      const cardWidth =
        (isDesktop ? DESKTOP_CARD_WIDTH : MOBILE_CARD_WIDTH) *
        viewportWorldWidth;

      const cardHeight = cardWidth / CARD_ASPECT;

      cardStates.forEach((card) => {
        card.mesh.scale.set(cardWidth, cardHeight, 1);

        card.fireTargetX =
          (card.direction === "left" ? -1 : 1) *
          viewportWorldWidth *
          FIRE_TARGET;
      });

      /* ===================================================
         CENTRO ENTRE TÍTULO Y BAJADA
      ==================================================== */

      const { titleEl, subtitleEl } = getHeroAnchors(container);

      if (titleEl && subtitleEl) {
        const containerRect = container.getBoundingClientRect();

        const titleRect = titleEl.getBoundingClientRect();

        const subtitleRect = subtitleEl.getBoundingClientRect();

        const titleBottomPx = titleRect.bottom - containerRect.top;

        const subtitleTopPx = subtitleRect.top - containerRect.top;

        const midpointPx = (titleBottomPx + subtitleTopPx) / 2;

        targetCenterWorldY = screenPxToWorldY(
          midpointPx,
          height,
          viewportWorldHeight,
        );
      } else {
        targetCenterWorldY = 0;
      }
    }

    resizeObserver = new ResizeObserver(resize);

    resizeObserver.observe(container);

    intersectionObserver = new IntersectionObserver(
      ([entry]) => {
        isVisible = entry.isIntersecting;
      },
      {
        threshold: 0.01,
      },
    );

    intersectionObserver.observe(container);

    /* =====================================================
       UPDATE CARD
    ====================================================== */

    function updateCard(card, delta) {
      if (!card.isFiring) {
        return;
      }

      card.fireProgress += delta / (1000 * FIRE_DURATION);

      if (card.fireProgress >= 1) {
        card.isFiring = false;

        card.innerGroup.visible = false;

        card.innerGroup.position.set(0, 0, 0);

        card.innerGroup.scale.set(0, 0, 0);

        card.innerGroup.renderOrder = 0;

        return;
      }

      const progress = card.fireProgress * revealFactor;

      /* ===================================================
         MOVIMIENTO HORIZONTAL
      ==================================================== */

      let movement = smoothstep(0, 1, progress);

      movement = 0.5 * easeInQuad(movement) + 0.5 * movement;

      /* ===================================================
         CRECIMIENTO
      ==================================================== */

      const scaleStart = isDesktop ? 0.2 : 0.3;

      const scale =
        0.125 * smoothstep(0, 0.15, progress) +
        0.875 * smoothstep(scaleStart, 1, progress);

      /* ===================================================
         X
      ==================================================== */

      card.innerGroup.position.x = card.fireTargetX * movement;

      /* ===================================================
         CURVATURA VERTICAL

         Centro = más lift.
         Extremo = prácticamente 0.
      ==================================================== */

      const centerFactor = 1 - movement;

      const centerLiftStrength = isDesktop
        ? CENTER_LIFT_DESKTOP
        : CENTER_LIFT_MOBILE;

      const centerLift =
        Math.pow(centerFactor, CENTER_LIFT_POWER) *
        viewportWorldHeight *
        centerLiftStrength;

      card.innerGroup.position.y = centerLift;

      /* ===================================================
         SCALE
      ==================================================== */

      card.innerGroup.scale.setScalar(scale);

      card.innerGroup.renderOrder = movement + scale;
    }

    /* =====================================================
       LOOP
    ====================================================== */

    let previousTime = 0;
    let startTime = 0;

    function startAnimation() {
      startTime = performance.now();

      previousTime = startTime;

      function frame(time) {
        if (destroyed) {
          return;
        }

        rafId = requestAnimationFrame(frame);

        if (document.hidden || !isVisible) {
          previousTime = time;

          return;
        }

        const rawDelta = time - previousTime;

        const delta = Math.min(rawDelta, 100);

        previousTime = time;

        const elapsed = time - startTime;

        /* =================================================
           REVEAL
        ================================================== */

        revealFactor = power3InOut(elapsed / REVEAL_DURATION);

        /* =================================================
           ZOOM GENERAL
        ================================================== */

        const zoomProgress = power3InOut(elapsed / GROUP_ZOOM_DURATION);

        const groupScale = lerp(
          GROUP_SCALE_START,
          GROUP_SCALE_END,
          zoomProgress,
        );

        cardsGroup.scale.setScalar(groupScale);

        /* =================================================
           POSICIÓN VERTICAL FINAL

           1. centro real título/bajada
           2. compensación por centerLift
           3. nudge óptico hacia abajo
        ================================================== */

        const centerLiftStrength = isDesktop
          ? CENTER_LIFT_DESKTOP
          : CENTER_LIFT_MOBILE;

        const centerLiftWorld =
          viewportWorldHeight * centerLiftStrength * groupScale;

        const visualNudge =
          viewportWorldHeight *
          (isDesktop
            ? STREAM_VISUAL_NUDGE_DESKTOP
            : STREAM_VISUAL_NUDGE_MOBILE);

        cardsGroup.position.y =
          targetCenterWorldY - centerLiftWorld - visualNudge;

        /* =================================================
           DISTORSIÓN CILÍNDRICA
        ================================================== */

        const cylinderProgress = power4Out(elapsed / CYLINDRICAL_DURATION);

        postMaterial.uniforms.uCylindricalFactor.value = lerp(
          CYLINDRICAL_START,
          CYLINDRICAL_END,
          cylinderProgress,
        );

        /* =================================================
           NUEVAS CARDS
        ================================================== */

        fireAccumulator += delta;

        while (fireAccumulator >= FIRE_INTERVAL) {
          fireAccumulator -= FIRE_INTERVAL;

          fireNextPair();
        }

        cardStates.forEach((card) => updateCard(card, delta));

        /* =================================================
           PASS 1
        ================================================== */

        renderer.setRenderTarget(target);

        renderer.setClearColor(0x000000, 0);

        renderer.clear();

        renderer.render(scene, camera);

        /* =================================================
           PASS 2
        ================================================== */

        renderer.setRenderTarget(null);

        renderer.setClearColor(0x000000, 0);

        renderer.clear();

        renderer.render(postScene, postCamera);
      }

      rafId = requestAnimationFrame(frame);
    }

    /* =====================================================
       INIT
    ====================================================== */

    buildCards();

    /* =====================================================
       CLEANUP
    ====================================================== */

    return () => {
      destroyed = true;

      if (rafId) {
        cancelAnimationFrame(rafId);
      }

      if (alignTimeoutId) {
        window.clearTimeout(alignTimeoutId);
      }

      resizeObserver?.disconnect();

      intersectionObserver?.disconnect();

      geometry.dispose();

      textures.forEach((texture) => texture.dispose());

      materials.forEach((material) => material.dispose());

      postQuad.geometry.dispose();

      postMaterial.dispose();

      target.dispose();

      renderer.dispose();
    };
  }, []);

  return (
    <div ref={containerRef} className="absolute inset-0">
      <canvas
        ref={canvasRef}
        className="pointer-events-none block h-full w-full"
        aria-hidden="true"
      />
    </div>
  );
}
