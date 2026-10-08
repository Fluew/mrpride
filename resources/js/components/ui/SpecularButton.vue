<script setup lang="ts">
import { ref, computed, watchEffect, onMounted, onUnmounted } from 'vue';
import { Renderer, Program, Mesh, Triangle, Color } from 'ogl';
import { router } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

export interface SpecularButtonProps {
  size?: 'sm' | 'md' | 'lg' | 'default' | 'icon' | 'icon-sm' | 'icon-lg';
  variant?: 'primary' | 'brand' | 'brand-gold' | 'secondary' | 'outline' | 'destructive' | 'danger' | 'success' | 'ghost' | 'neutral' | 'default';
  radius?: number;
  tint?: string;
  tintOpacity?: number;
  blur?: number;
  textColor?: string;
  lineColor?: string;
  baseColor?: string;
  intensity?: number;
  shineSize?: number;
  shineFade?: number;
  thickness?: number;
  speed?: number;
  followMouse?: boolean;
  proximity?: number;
  autoAnimate?: boolean;
  disabled?: boolean;
  type?: 'button' | 'submit' | 'reset';
  as?: any;
  href?: string | { url: string; method?: string } | any;
  class?: any;
}

const props = withDefaults(defineProps<SpecularButtonProps>(), {
  size: 'md',
  radius: 12,
  blur: 0,
  intensity: 1,
  shineSize: 10,
  shineFade: 40,
  thickness: 1,
  speed: 0.35,
  followMouse: true,
  proximity: 250,
  autoAnimate: false,
  disabled: false,
  type: 'button',
  as: 'button',
});

const emit = defineEmits<{
  (e: 'click', event: MouseEvent): void;
}>();

// Variant presets tailored to MR-Pride application
const variantConfig = computed(() => {
  switch (props.variant) {
    case 'primary':
    case 'brand':
      return {
        tint: '#4b5320',
        tintOpacity: 1,
        textColor: '#ffffff',
        lineColor: '#ffffff',
        baseColor: '#3b4219',
        borderClass: 'border-transparent',
      };
    case 'brand-gold':
      return {
        tint: '#f8b803',
        tintOpacity: 1,
        textColor: '#111827',
        lineColor: '#ffffff',
        baseColor: '#b45309',
        borderClass: 'border-transparent',
      };
    case 'secondary':
    case 'outline':
      return {
        tint: '#ffffff',
        tintOpacity: 0.95,
        textColor: '#374151',
        lineColor: '#e5e7eb',
        baseColor: '#d1d5db',
        borderClass: 'border border-gray-200 dark:border-gray-700 shadow-xs',
      };
    case 'destructive':
    case 'danger':
      return {
        tint: '#dc2626',
        tintOpacity: 1,
        textColor: '#ffffff',
        lineColor: '#fca5a5',
        baseColor: '#991b1b',
        borderClass: 'border-transparent',
      };
    case 'success':
      return {
        tint: '#16a34a',
        tintOpacity: 1,
        textColor: '#ffffff',
        lineColor: '#86efac',
        baseColor: '#14532d',
        borderClass: 'border-transparent',
      };
    case 'ghost':
      return {
        tint: '#ffffff',
        tintOpacity: 0,
        textColor: 'inherit',
        lineColor: '#9ca3af',
        baseColor: '#e5e7eb',
        borderClass: 'border-transparent',
      };
    default:
      // Neutral default as specified
      return {
        tint: '#ffffff',
        tintOpacity: 0,
        textColor: '#f5f5f5',
        lineColor: '#ffffff',
        baseColor: '#525252',
        borderClass: 'border-transparent',
      };
  }
});

const resolvedTint = computed(() => props.tint ?? variantConfig.value.tint);
const resolvedTintOpacity = computed(() => props.tintOpacity ?? variantConfig.value.tintOpacity);
const resolvedTextColor = computed(() => props.textColor ?? variantConfig.value.textColor);
const resolvedLineColor = computed(() => props.lineColor ?? variantConfig.value.lineColor);
const resolvedBaseColor = computed(() => props.baseColor ?? variantConfig.value.baseColor);

const resolvedTag = computed(() => {
  if (props.as && props.as !== 'button') return props.as;
  if (props.href) return 'a';
  return 'button';
});

const resolvedHref = computed(() => {
  if (!props.href) return undefined;
  if (typeof props.href === 'object' && props.href !== null && 'url' in props.href) {
    return props.href.url;
  }
  return String(props.href);
});

const sizeClass = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'min-h-8 text-xs px-3 py-1.5';
    case 'lg':
      return 'min-h-12 text-base px-6 py-3 font-semibold';
    case 'icon':
      return 'size-9 p-0';
    case 'icon-sm':
      return 'size-8 p-0';
    case 'icon-lg':
      return 'size-10 p-0';
    case 'md':
    case 'default':
    default:
      return 'min-h-10 text-sm px-4 py-2 font-medium';
  }
});

const btnRef = ref<any>(null);
const fxRef = ref<HTMLSpanElement | null>(null);

const getDomElement = (): HTMLElement | null => {
  if (!btnRef.value) return null;
  if (btnRef.value instanceof HTMLElement) return btnRef.value;
  if (typeof btnRef.value === 'object' && '$el' in btnRef.value && btnRef.value.$el instanceof HTMLElement) {
    return btnRef.value.$el;
  }
  return null;
};

const PAD = 20;

const VERT = `#version 300 es
in vec2 position;
void main() {
  gl_Position = vec4(position, 0.0, 1.0);
}
`;

const FRAG = `#version 300 es
precision highp float;

uniform vec2 uCenter;
uniform vec2 uHalfSize;
uniform float uRadius;
uniform float uAngle;
uniform float uPx;
uniform vec3 uLineColor;
uniform vec3 uBaseColor;
uniform float uIntensity;
uniform float uShineSize;
uniform float uShineFade;
uniform float uThickness;
uniform float uBaseWidth;

out vec4 fragColor;

float sdRoundedRect(vec2 p, vec2 b, float r) {
  vec2 q = abs(p) - b + r;
  return length(max(q, 0.0)) + min(max(q.x, q.y), 0.0) - r;
}

float shapeSDF(vec2 p) { return sdRoundedRect(p, uHalfSize, uRadius); }

float gaussianLine(float d, float sigma) {
  float x = d / (sigma + 1e-6);
  float k = mix(1.0, 1.6, smoothstep(0.0, 1.5, x));
  return exp(-k * x * x);
}

void main() {
  vec2 p = gl_FragCoord.xy - uCenter;
  float d = shapeSDF(p);
  vec2 L = vec2(cos(uAngle), sin(uAngle));

  // Dark base stroke hugging the edge for thickness
  float base = (1.0 - smoothstep(0.0, uBaseWidth, abs(d))) * 0.45;

  // Symmetric specular shine
  vec2 nEll = normalize(p / (uHalfSize * uHalfSize) + 1e-6);
  float phi = acos(clamp(abs(dot(nEll, L)), 0.0, 1.0));
  float rim = 1.0 - smoothstep(uShineSize - uShineFade, uShineSize + uShineFade + 1e-4, phi);
  float line = gaussianLine(d, uThickness);
  float edgeClamp = 1.0 - smoothstep(0.5 * uPx, 3.0 * uPx, abs(d));
  float hi = line * rim * edgeClamp * uIntensity;

  vec3 col = uBaseColor * base + uLineColor * hi;
  float a = clamp(base + hi, 0.0, 1.0);
  fragColor = vec4(col, a);
}
`;

const propsState = ref({
  radius: props.radius,
  lineColor: resolvedLineColor.value,
  baseColor: resolvedBaseColor.value,
  intensity: props.intensity,
  shineSize: props.shineSize,
  shineFade: props.shineFade,
  thickness: props.thickness,
  speed: props.speed,
  followMouse: props.followMouse,
  proximity: props.proximity,
  autoAnimate: props.autoAnimate,
});

watchEffect(() => {
  propsState.value = {
    radius: props.radius,
    lineColor: resolvedLineColor.value,
    baseColor: resolvedBaseColor.value,
    intensity: props.intensity,
    shineSize: props.shineSize,
    shineFade: props.shineFade,
    thickness: props.thickness,
    speed: props.speed,
    followMouse: props.followMouse,
    proximity: props.proximity,
    autoAnimate: props.autoAnimate,
  };
});

let cleanUpGl: (() => void) | null = null;

onMounted(() => {
  const btn = getDomElement();
  const fx = fxRef.value;
  if (!btn || !fx) return;

  const dpr = Math.min(window.devicePixelRatio || 1, 2);
  let renderer: Renderer | null = null;
  try {
    renderer = new Renderer({ alpha: true, premultipliedAlpha: true, antialias: true, dpr });
  } catch {
    return; // Fallback gracefully without WebGL
  }

  const gl = renderer.gl;
  if (!gl) return;

  gl.clearColor(0, 0, 0, 0);
  gl.enable(gl.BLEND);
  gl.blendFunc(gl.ONE, gl.ONE_MINUS_SRC_ALPHA);

  const geometry = new Triangle(gl);
  if ((geometry.attributes as any).uv) delete (geometry.attributes as any).uv;

  const program = new Program(gl, {
    vertex: VERT,
    fragment: FRAG,
    uniforms: {
      uCenter: { value: [0, 0] },
      uHalfSize: { value: [1, 1] },
      uRadius: { value: 0 },
      uAngle: { value: 2.4 },
      uPx: { value: dpr },
      uLineColor: { value: [1, 1, 1] },
      uBaseColor: { value: [0.32, 0.32, 0.32] },
      uIntensity: { value: 1 },
      uShineSize: { value: 0.17 },
      uShineFade: { value: 0.7 },
      uThickness: { value: 1 },
      uBaseWidth: { value: dpr },
    },
  });

  const mesh = new Mesh(gl, { geometry, program });
  fx.appendChild(gl.canvas);

  const sizeRef = { w: 1, h: 1 };
  const resize = () => {
    const el = getDomElement();
    if (!el || !renderer) return;
    const rect = el.getBoundingClientRect();
    const w = rect.width;
    const h = rect.height;
    if (w <= 0 || h <= 0) return;
    sizeRef.w = w;
    sizeRef.h = h;
    renderer.setSize(w + PAD * 2, h + PAD * 2);
    program.uniforms.uCenter.value = [(PAD + w / 2) * dpr, (PAD + h / 2) * dpr];
    program.uniforms.uHalfSize.value = [(w / 2) * dpr, (h / 2) * dpr];
  };

  const ro = new ResizeObserver(resize);
  ro.observe(btn);
  resize();

  let pointerAngle: number | null = null;
  let proximityT = 0;

  const onPointerMove = (e: PointerEvent) => {
    const el = getDomElement();
    if (!el) return;
    const rect = el.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    const dx = Math.max(rect.left - e.clientX, 0, e.clientX - rect.right);
    const dy = Math.max(rect.top - e.clientY, 0, e.clientY - rect.bottom);
    const dist = Math.hypot(dx, dy);

    if (dist === 0) {
      const nx = (e.clientX - cx) / (rect.width / 2 || 1);
      const ny = (cy - e.clientY) / (rect.height / 2 || 1);
      pointerAngle = Math.atan2(2 / (rect.height || 1), -2 / (rect.width || 1)) + nx * 0.3 + ny * 0.15;
    } else {
      pointerAngle = Math.atan2(cy - e.clientY, e.clientX - cx);
    }
    const t = Math.max(0, 1 - dist / Math.max(propsState.value.proximity, 1));
    proximityT = t * t * (3 - 2 * t);
  };

  window.addEventListener('pointermove', onPointerMove, { passive: true });

  let angle = 2.4;
  let idleAngle = 2.4;
  let bright = 0;
  let last = performance.now();
  let raf = 0;

  const lineC = new Color();
  const baseC = new Color();

  const update = (now: number) => {
    raf = requestAnimationFrame(update);
    const dt = Math.min((now - last) / 1000, 0.05);
    last = now;
    const p = propsState.value;

    idleAngle += p.speed * dt;
    const steer = p.followMouse && pointerAngle != null && (!p.autoAnimate || proximityT > 0);
    const targetAngle = steer ? (pointerAngle as number) : idleAngle;

    let da = (targetAngle - angle) % (Math.PI * 2);
    if (da > Math.PI) da -= Math.PI * 2;
    if (da < -Math.PI) da += Math.PI * 2;
    angle += da * (steer ? 0.14 : 0.04);

    const targetBright = p.autoAnimate ? 1 : p.followMouse ? proximityT : 0;
    bright += (targetBright - bright) * 0.12;

    if (bright < 0.002 && !p.autoAnimate) {
      // Skip WebGL render pass when light is invisible to optimize battery and GPU
      return;
    }

    lineC.set(p.lineColor);
    baseC.set(p.baseColor);

    program.uniforms.uRadius.value = p.radius * dpr;
    program.uniforms.uAngle.value = angle;
    program.uniforms.uLineColor.value = [lineC.r, lineC.g, lineC.b];
    program.uniforms.uBaseColor.value = [baseC.r, baseC.g, baseC.b];
    program.uniforms.uIntensity.value = p.intensity * bright;
    program.uniforms.uShineSize.value = (p.shineSize * Math.PI) / 180;
    program.uniforms.uShineFade.value = (p.shineFade * Math.PI) / 180;
    program.uniforms.uThickness.value = p.thickness * dpr;

    renderer?.render({ scene: mesh });
  };

  raf = requestAnimationFrame(update);

  cleanUpGl = () => {
    cancelAnimationFrame(raf);
    ro.disconnect();
    window.removeEventListener('pointermove', onPointerMove);
    if (gl.canvas && gl.canvas.parentNode === fx) {
      fx.removeChild(gl.canvas);
    }
    gl.getExtension('WEBGL_lose_context')?.loseContext();
  };
});

onUnmounted(() => {
  if (cleanUpGl) cleanUpGl();
});

const handleClick = (e: MouseEvent) => {
  if (props.disabled) {
    e.preventDefault();
    e.stopPropagation();
    return;
  }
  emit('click', e);

  if (props.href && !e.defaultPrevented && !e.metaKey && !e.ctrlKey && !e.shiftKey && e.button === 0) {
    const rawUrl = resolvedHref.value;
    if (rawUrl && !rawUrl.startsWith('http://') && !rawUrl.startsWith('https://') && !rawUrl.startsWith('//')) {
      if (rawUrl.startsWith('#')) {
        const el = document.querySelector(rawUrl);
        if (el) {
          e.preventDefault();
          el.scrollIntoView({ behavior: 'smooth' });
        }
      } else {
        e.preventDefault();
        router.visit(rawUrl);
      }
    }
  }
};
</script>

<template>
  <component
    :is="resolvedTag"
    ref="btnRef"
    :type="resolvedTag === 'button' ? type : undefined"
    :href="resolvedHref"
    :disabled="disabled"
    @click="handleClick"
    :class="cn(
      'specular-btn relative inline-flex items-center justify-center select-none font-medium transition-transform duration-150 active:scale-[0.97] disabled:pointer-events-none disabled:opacity-50 outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[#4b5320] dark:focus-visible:ring-[#f8b803]',
      sizeClass,
      variantConfig.borderClass,
      props.class
    )"
    :style="{
      '--sb-radius': `${radius}px`,
      '--sb-tint': resolvedTint,
      '--sb-tint-opacity': resolvedTintOpacity,
      '--sb-blur': `${blur}px`,
      '--sb-text-color': resolvedTextColor,
      color: resolvedTextColor,
      backgroundColor: resolvedTintOpacity === 0 ? 'transparent' : `color-mix(in srgb, ${resolvedTint} calc(${resolvedTintOpacity} * 100%), transparent)`,
      borderRadius: `${radius}px`,
      backdropFilter: blur > 0 ? `blur(${blur}px)` : undefined,
    }"
  >
    <span ref="fxRef" class="specular-btn__fx pointer-events-none absolute -inset-[20px] z-[1] overflow-visible" aria-hidden="true" />
    <span class="specular-btn__label relative z-[2] inline-flex w-full items-center justify-center gap-2">
      <slot />
    </span>
  </component>
</template>

<style scoped>
.specular-btn__fx :deep(canvas) {
  display: block;
  width: 100%;
  height: 100%;
}
</style>
