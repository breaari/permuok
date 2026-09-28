import { useEffect, useRef } from "react";
import * as THREE from "three";

/* =========================================================
   ASSETS
========================================================= */

const heroImageModules = import.meta.glob("../../assets/fotohero1*.*", {
  eager: true,
  import: "default",
});

const images = Object.entries(heroImageModules)
  .map(([path, src]) => {
    const match = path.match(/fotohero1 \((\d+)\)/);

    return {
      number: match ? Number(match[1]) : 999,
      src,
    };
  })
  .sort((a, b) => a.number - b.number)
  .map((item) => item.src)
  .slice(0, 24);

/* =========================================================
   DATOS
========================================================= */

const PROPERTY_META = [
  {
    type: "Departamento",
    title: "Departamento 3 ambientes",
    price: "US$ 125.000",
    exchange: "Acepta permuta",
  },
  {
    type: "Casa",
    title: "Casa 4 ambientes",
    price: "US$ 210.000",
    exchange: "Permuta parcial",
  },
  {
    type: "PH",
    title: "PH con patio",
    price: "US$ 98.000",
    exchange: "Acepta permuta",
  },
  {
    type: "Departamento",
    title: "Departamento 2 ambientes",
    price: "US$ 145.000",
    exchange: "Permuta total",
  },
  {
    type: "Casa",
    title: "Casa con parque",
    price: "US$ 185.000",
    exchange: "Permuta + diferencia",
  },
  {
    type: "Departamento",
    title: "Departamento con vista",
    price: "US$ 165.000",
    exchange: "Acepta permuta",
  },
  {
    type: "Casa",
    title: "Casa moderna",
    price: "US$ 230.000",
    exchange: "Escucha propuestas",
  },
  {
    type: "Departamento",
    title: "Departamento céntrico",
    price: "US$ 135.000",
    exchange: "Permuta total",
  },
  {
    type: "Casa",
    title: "Casa con jardín",
    price: "US$ 195.000",
    exchange: "Permuta parcial",
  },
  {
    type: "PH",
    title: "PH 3 ambientes",
    price: "US$ 118.000",
    exchange: "Acepta permuta",
  },
  {
    type: "Departamento",
    title: "Departamento premium",
    price: "US$ 178.000",
    exchange: "Acepta permuta",
  },
  {
    type: "Casa",
    title: "Casa con quincho",
    price: "US$ 240.000",
    exchange: "Permuta + diferencia",
  },
  {
    type: "Departamento",
    title: "Departamento luminoso",
    price: "US$ 142.000",
    exchange: "Acepta permuta",
  },
  {
    type: "Casa",
    title: "Casa residencial",
    price: "US$ 260.000",
    exchange: "Escucha propuestas",
  },
];

const BASE_PROPERTIES = images.map((image, index) => ({
  ...PROPERTY_META[index % PROPERTY_META.length],
  image,
}));

const CARDS = BASE_PROPERTIES.map((property, index) => ({
  ...property,
  id: index + 1,
  textureIndex: index,
}));

/* =========================================================
   CONFIG
========================================================= */

/*
 * Timing original del efecto.
 *
 * 24 cards:
 * 12 izquierda
 * 12 derecha
 *
 * 900ms / 9.6s mantiene un flujo continuo
 * sin agotar el pool.
 */
const FIRE_INTERVAL_DESKTOP = 900;
const FIRE_DURATION_DESKTOP = 9.6;

/*
 * Mobile:
 * menos cards simultáneas.
 *
 * 6.4 / 1.1 ≈ 5.8 por lado
 * => unas 10/12 visibles en total,
 * en vez de más de 20.
 */
const FIRE_INTERVAL_MOBILE = 1000;
const FIRE_DURATION_MOBILE = 6.4;

const CAMERA_FOV = 45;
const CAMERA_Z = 5;

const CARD_ASPECT = 720 / 980;

/*
 * Tamaño base del efecto.
 *
 * Desktop y mobile tienen distinta escala,
 * igual que en la referencia.
 */
const DESKTOP_CARD_WIDTH = 0.75;
const MOBILE_CARD_WIDTH = 1.35;

/*
 * Destino horizontal.
 *
 * La card termina completamente fuera del viewport
 * antes de ser reciclada.
 */
const FIRE_TARGET_DESKTOP = 1;
const FIRE_TARGET_MOBILE = 0.92;

/*
 * Limitamos únicamente el ALTO final porque nuestras
 * cards son más verticales y tienen información debajo
 * de la foto.
 *
 * Esto evita el corte inferior sin alterar el motor.
 */
const MAX_CARD_VISUAL_HEIGHT_DESKTOP = 0.74;
const MAX_CARD_VISUAL_HEIGHT_MOBILE = 0.9;

/*
 * Zoom inicial del conjunto.
 */
const GROUP_SCALE_START = 1.2;
const GROUP_SCALE_END = 0.5;
const GROUP_ZOOM_DURATION = 1500;

/*
 * Aparición.
 */
const REVEAL_DURATION = 1750;

/*
 * Distorsión global.
 */
const CYLINDRICAL_START = 1;

const CYLINDRICAL_END_DESKTOP = 0.7;
const CYLINDRICAL_END_MOBILE = 0.97;

const CYLINDRICAL_DURATION = 2000;

/*
 * PermuOK:
 * ubicamos el eje de las cards dentro del espacio
 * disponible entre título y bajada.
 */
const ARC_POSITION_RATIO_DESKTOP = 0.46;
const ARC_POSITION_RATIO_MOBILE = 0.46;

/* =========================================================
   EASING
========================================================= */

function getCylindricalEnd(isDesktop) {
  return isDesktop ? CYLINDRICAL_END_DESKTOP : CYLINDRICAL_END_MOBILE;
}

function getFireInterval(isDesktop) {
  return isDesktop ? FIRE_INTERVAL_DESKTOP : FIRE_INTERVAL_MOBILE;
}

function getFireDuration(isDesktop) {
  return isDesktop ? FIRE_DURATION_DESKTOP : FIRE_DURATION_MOBILE;
}

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

/*
 * Equivalente a GSAP power3.inOut.
 *
 * Power3 = quartic.
 */
function power3InOut(value) {
  const t = clamp01(value);

  return t < 0.5 ? 8 * Math.pow(t, 4) : 1 - Math.pow(-2 * t + 2, 4) / 2;
}

/*
 * Equivalente a GSAP power4.out.
 *
 * Power4 = quintic.
 */
function power4Out(value) {
  const t = clamp01(value);

  return 1 - Math.pow(1 - t, 5);
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
  const HEIGHT = 980;
  const RADIUS = 32;

  const canvas = document.createElement("canvas");

  canvas.width = WIDTH;
  canvas.height = HEIGHT;

  const ctx = canvas.getContext("2d");

  ctx.clearRect(0, 0, WIDTH, HEIGHT);

  const image = await loadImage(property.image);

  /* Card */
  ctx.save();

  roundRectPath(ctx, 2, 2, WIDTH - 4, HEIGHT - 4, RADIUS);

  ctx.clip();

  ctx.fillStyle = "#ffffff";

  ctx.fillRect(0, 0, WIDTH, HEIGHT);

  /* Foto */

  const IMAGE_HEIGHT = 755;

  drawImageCover(ctx, image, 0, 0, WIDTH, IMAGE_HEIGHT);

  /* Badge */

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

  /* Info */

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

  /*
   * Precio más cerca del título.
   * titleY ya quedó justo debajo de la última línea.
   */
  const priceY = titleY + 22;

  ctx.fillStyle = "#0f172a";
  ctx.font = '800 43px "Manrope", Arial, sans-serif';

  ctx.fillText(property.price, 38, priceY);

  ctx.restore();

  /* Borde */

  ctx.strokeStyle = "#dbe3ed";

  ctx.lineWidth = 3;

  roundRectPath(ctx, 2, 2, WIDTH - 4, HEIGHT - 4, RADIUS);

  ctx.stroke();

  /* Texture */

  const texture = new THREE.CanvasTexture(canvas);

  texture.colorSpace = THREE.SRGBColorSpace;

  texture.generateMipmaps = false;

  texture.minFilter = THREE.LinearFilter;

  texture.magFilter = THREE.LinearFilter;

  texture.needsUpdate = true;

  return texture;
}

/* =========================================================
   POST PROCESS
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

    /*
     * El centro casi no se modifica.
     * Cuanto más cerca del borde horizontal,
     * más se comprime el rango Y que muestreamos.
     *
     * Al volver a proyectarlo sobre toda la pantalla
     * produce la perspectiva/arco del conjunto.
     */
    float stretchedY =
      vUv.y * cylindricalFactor +
      (1.0 - cylindricalFactor) * 0.5;

    float xFactor =
      abs(0.5 - vUv.x) * 2.0;

    xFactor =
      pow(xFactor, 2.0);

    vec2 distortedUv =
      vUv;

    distortedUv.y =
      mix(
        vUv.y,
        stretchedY,
        xFactor
      );

    vec4 color =
      texture2D(
        tMap,
        distortedUv
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

    const cardsGroup = new THREE.Group();

    scene.add(cardsGroup);

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
       POST SCENE
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

    let targetArcWorldY = 0;

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
             * Igual que la referencia:
             * planos transparentes sin escribir
             * en depth buffer.
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
          maxScale: 1,
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

    /*
     * Mismo pre-distribute del motor de referencia.
     *
     * 900 / 9600 = 0.09375
     *
     * Esto coloca las cards desde el centro
     * hasta los extremos desde el primer frame.
     */
    function preDistributeCards(pool) {
      const fireInterval = getFireInterval(isDesktop);

      const fireDuration = getFireDuration(isDesktop);

      const step = fireInterval / (1000 * fireDuration);

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
       RESIZE
    ====================================================== */

    function resize() {
      const width = container.clientWidth;

      const height = container.clientHeight;

      if (!width || !height) {
        return;
      }

      isDesktop = width >= 1024;

      /*
       * En mobile reducimos apenas el DPR.
       * No cambia la geometría, pero ayuda
       * mucho a mantener los 60fps.
       */
      const maxPixelRatio = isDesktop ? 2 : 1.5;

      const pixelRatio = Math.min(window.devicePixelRatio || 1, maxPixelRatio);

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
         POSICIÓN DEL EJE
      ==================================================== */

      const { titleEl, subtitleEl } = getHeroAnchors(container);

      let targetArcPx = height / 2;

      if (titleEl && subtitleEl) {
        const containerRect = container.getBoundingClientRect();

        const titleRect = titleEl.getBoundingClientRect();

        const subtitleRect = subtitleEl.getBoundingClientRect();

        const titleBottomPx = titleRect.bottom - containerRect.top;

        const subtitleTopPx = subtitleRect.top - containerRect.top;

        const freeSpacePx = Math.max(0, subtitleTopPx - titleBottomPx);

        const ratio = isDesktop
          ? ARC_POSITION_RATIO_DESKTOP
          : ARC_POSITION_RATIO_MOBILE;

        targetArcPx = titleBottomPx + freeSpacePx * ratio;

        targetArcWorldY = screenPxToWorldY(
          targetArcPx,
          height,
          viewportWorldHeight,
        );
      } else {
        targetArcWorldY = 0;
      }

      /* ===================================================
         TAMAÑO BASE
      ==================================================== */

      const preferredCardWidth =
        (isDesktop ? DESKTOP_CARD_WIDTH : MOBILE_CARD_WIDTH) *
        viewportWorldWidth;

      /*
       * No existe desplazamiento Y individual.
       * Todas las cards nacen sobre el mismo eje.
       *
       * La curva visual la genera exclusivamente
       * el postprocesado.
       */
      const cardCenterPx = targetArcPx;

      /* ===================================================
         LÍMITE DE ALTURA

         Adaptación necesaria porque nuestras cards
         incluyen información inmobiliaria y son
         más verticales.
      ==================================================== */

      const bottomSafeSpacePx = isDesktop ? 28 : 18;

      const topSafeSpacePx = isDesktop ? 12 : 10;

      const shaderFactor = getCylindricalEnd(isDesktop);

      const maxHeightByBottomPx =
        height * (1 + shaderFactor) -
        2 * cardCenterPx -
        2 * shaderFactor * bottomSafeSpacePx;

      const maxHeightByTopPx =
        2 * cardCenterPx -
        height * (1 - shaderFactor) -
        2 * shaderFactor * topSafeSpacePx;

      const maxHeightByRatioPx =
        height *
        (isDesktop
          ? MAX_CARD_VISUAL_HEIGHT_DESKTOP
          : MAX_CARD_VISUAL_HEIGHT_MOBILE);

      const maxPreShaderCardHeightPx = Math.max(
        0,
        Math.min(maxHeightByBottomPx, maxHeightByTopPx, maxHeightByRatioPx),
      );
      /*
       * IMPORTANTE:
       * mantenemos el tamaño BASE real del efecto.
       *
       * No achicamos toda la geometría para hacer entrar
       * las cards de los extremos.
       */
      const cardWidth = preferredCardWidth;

      const cardHeight = cardWidth / CARD_ASPECT;

      /*
       * Convertimos la altura máxima permitida a mundo WebGL.
       */
      const maxVisualCardHeightWorld =
        (maxPreShaderCardHeightPx / height) * viewportWorldHeight;

      /*
       * Calculamos cuánto puede escalar como máximo esta card
       * cuando cardsGroup ya llegó a GROUP_SCALE_END.
       */
      const maxCardScale =
        maxVisualCardHeightWorld / (cardHeight * GROUP_SCALE_END);

      /* ===================================================
         APLICAR
      ==================================================== */

      cardStates.forEach((card) => {
        card.mesh.scale.set(cardWidth, cardHeight, 1);

        card.maxScale = Math.min(1, maxCardScale);

        const fireTarget = isDesktop ? FIRE_TARGET_DESKTOP : FIRE_TARGET_MOBILE;

        card.fireTargetX =
          (card.direction === "left" ? -1 : 1) *
          viewportWorldWidth *
          fireTarget;
      });
    }

    /* =====================================================
       OBSERVERS
    ====================================================== */

    resizeObserver = new ResizeObserver(resize);

    resizeObserver.observe(container);

    const { titleEl, subtitleEl } = getHeroAnchors(container);

    if (titleEl) {
      resizeObserver.observe(titleEl);
    }

    if (subtitleEl) {
      resizeObserver.observe(subtitleEl);
    }

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

      const fireDuration = getFireDuration(isDesktop);

      card.fireProgress += delta / (1000 * fireDuration);

      /*
       * Igual que el motor original:
       * cuando progress llega a 1 la card ya está
       * completamente fuera del viewport.
       */
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
         MOVIMIENTO

         ESTA ES LA PARTE CLAVE.

         No linealizamos.
         No agregamos offsets.
         No movemos Y.
      ==================================================== */

      let movement = smoothstep(0, 1, progress);

      movement = 0.5 * easeInQuad(movement) + 0.5 * movement;

      /* ===================================================
   ESCALA
==================================================== */

      const scaleStart = 0.2;
      const initialScaleWeight = 0.125;

      const baseScale =
        initialScaleWeight * smoothstep(0, 0.15, progress) +
        (1 - initialScaleWeight) * smoothstep(scaleStart, 1, progress);

      /*
       * Desktop queda EXACTAMENTE como está.
       *
       * Mobile:
       * - no nace microscópica
       * - los extremos no llegan a ser tan gigantes
       * - disminuye la diferencia centro/extremos
       */
      const MOBILE_MIN_SCALE = 0.1;
      const MOBILE_MAX_SCALE = 0.82;

      const scale = isDesktop
        ? baseScale
        : lerp(MOBILE_MIN_SCALE, MOBILE_MAX_SCALE, baseScale);

      const finalScale = scale * card.maxScale;
      /* ===================================================
   TRANSFORM
==================================================== */

      card.innerGroup.position.x = card.fireTargetX * movement;

      card.innerGroup.position.y = 0;

      card.innerGroup.scale.setScalar(finalScale);

      card.innerGroup.renderOrder = movement + finalScale;
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

        /*
         * El conjunto se mueve como una sola unidad.
         *
         * Nada de centerLift.
         */
        cardsGroup.position.y = targetArcWorldY;

        /* =================================================
           DISTORSIÓN GLOBAL
        ================================================== */

        const cylinderProgress = power4Out(elapsed / CYLINDRICAL_DURATION);

        const cylindricalEnd = getCylindricalEnd(isDesktop);

        postMaterial.uniforms.uCylindricalFactor.value = lerp(
          CYLINDRICAL_START,
          cylindricalEnd,
          cylinderProgress,
        );

        /* =================================================
           DISPAROS

           Cadencia continua.
           Sin waits ni acumuladores especiales.
        ================================================== */
        const fireInterval = getFireInterval(isDesktop);

        fireAccumulator += delta;

        while (fireAccumulator >= fireInterval) {
          fireAccumulator -= fireInterval;

          fireNextPair();
        }

        /*
         * Igual que la referencia:
         * primero se procesa el firing y después
         * se actualizan las entidades.
         */
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
    <div ref={containerRef} className="absolute inset-0 overflow-hidden">
      <canvas
        ref={canvasRef}
        className="pointer-events-none block h-full w-full"
        aria-hidden="true"
      />
    </div>
  );
}
