// In-browser loader for the RankPro Vite/React/TS app: transpiles the project's
// .tsx sources with Babel and mounts <App /> into #rankpro-root.

const SOLO_MODULE = typeof window !== "undefined" ? window.__rankproModule : null;

// Una página de módulo suelto sólo necesita AgencyDashboard.tsx (no tiene imports locales).
const MANIFEST = SOLO_MODULE ? ["app/AgencyDashboard.tsx"] : [
  "app/App.tsx",
  "app/AgencyDashboard.tsx",
  "app/data.ts",
  "app/types.ts",
  "app/components/shared/index.tsx",
  "app/components/figma/ImageWithFallback.tsx",
  "app/pages/ITAutomationPage.tsx",
  "app/pages/AgencyOSPage.tsx",
  "app/pages/AgendarPage.tsx",
  "app/pages/AgendarAdminPage.tsx",
  "app/pages/agendaStore.ts",
  "app/pages/BlogPage.tsx",
  "app/pages/BlogPostPage.tsx",
  "app/pages/CasosDeExitoPage.tsx",
  "app/pages/ContactoPage.tsx",
  "app/pages/DPMSOdontologicoPage.tsx",
  "app/pages/DashboardPage.tsx",
  "app/pages/FAQPage.tsx",
  "app/pages/HomePage.tsx",
  "app/pages/LoginPage.tsx",
  "app/pages/NosotrosPage.tsx",
  "app/pages/PMSHoteleroPage.tsx",
  "app/pages/PrivacidadPage.tsx",
  "app/pages/ProductosPage.tsx",
  "app/pages/PromocionesPage.tsx",
  "app/pages/RegisterPage.tsx",
  "app/pages/ServiceAnalyticsPage.tsx",
  "app/pages/ServiceDetailPage.tsx",
  "app/pages/ServicePageSpeedPage.tsx",
  "app/pages/ServiceSEMPage.tsx",
  "app/pages/ServiceSEMConversionPage.tsx",
  "app/pages/ServiceSEOPage.tsx",
  "app/pages/ServiceSEOConversionPage.tsx",
  "app/pages/ServiceSocialPage.tsx",
  "app/pages/ServiceWebPage.tsx",
  "app/pages/ServiceWebConversionPage.tsx",
  "app/pages/ServiciosPage.tsx",
  "app/pages/TerminosPage.tsx",
];

// Una sola copia de React (builds UMD) para que librerías y app compartan el mismo React.
let React, ReactDOMClient;
const EXTERNALS = {};
const CDN = "https://cdn.jsdelivr.net/npm/";
async function loadDeps() {
  for (const u of [
    "react@18.3.1/umd/react.production.min.js",
    "react-dom@18.3.1/umd/react-dom.production.min.js",
    "prop-types@15.8.1/prop-types.min.js",
    "react-is@18.3.1/umd/react-is.production.min.js",
    "lucide@0.487.0/dist/umd/lucide.min.js",
    "recharts@2.15.3/umd/Recharts.js",
  ]) await loadScript(CDN + u);
  React = window.React;
  ReactDOMClient = window.ReactDOM;
  EXTERNALS.react = React;
  EXTERNALS["react-dom"] = ReactDOMClient;
  EXTERNALS["react-dom/client"] = ReactDOMClient;
  // lucide-react no trae build UMD usable: se construyen los componentes React a partir de los datos de iconos de lucide (vanilla).
  const LU = window.lucide;
  const iconCache = Object.create(null);
  const makeIcon = (node) => {
    const Icon = (props) => {
      const { size = 24, color = "currentColor", strokeWidth = 2, absoluteStrokeWidth, className = "", children, ...rest } = props || {};
      return React.createElement(
        "svg",
        { xmlns: "http://www.w3.org/2000/svg", width: size, height: size, viewBox: "0 0 24 24", fill: "none", stroke: color,
          strokeWidth: absoluteStrokeWidth ? (Number(strokeWidth) * 24) / Number(size) : strokeWidth,
          strokeLinecap: "round", strokeLinejoin: "round", className: ("lucide " + className).trim(), ...rest },
        node.map(([tag, attrs], i) => React.createElement(tag, { ...attrs, key: i })),
        children
      );
    };
    return Icon;
  };
  const lookup = (k) => {
    const names = [k, k.replace(/^Lucide/, ""), k.replace(/Icon$/, "")];
    for (const n of names) {
      const node = (LU.icons && LU.icons[n]) || LU[n];
      if (Array.isArray(node)) return node;
    }
    return null;
  };
  EXTERNALS["lucide-react"] = new Proxy({}, {
    get(t, k) {
      if (k === "__esModule") return true;
      if (typeof k !== "string") return undefined;
      if (k in iconCache) return iconCache[k];
      if (k === "icons") return LU.icons;
      const node = /^[A-Z]/.test(k) ? lookup(k) : null;
      if (node) return (iconCache[k] = makeIcon(node));
      if (/^[A-Z]/.test(k)) { (window.__missing = window.__missing || []).push("lucide:" + k); return (iconCache[k] = () => null); }
      return undefined;
    },
  });
  EXTERNALS.recharts = window.Recharts;
  window.__missing = [];
  const guard = (lib, tag) => new Proxy(lib, {
    get(t, k) {
      if (k in t) return t[k];
      if (typeof k === "string" && /^[A-Z]/.test(k)) {
        window.__missing.push(tag + ":" + k);
        const Stub = () => null;
        t[k] = Stub;
        return Stub;
      }
      return undefined;
    },
  });
  EXTERNALS.recharts = guard(EXTERNALS.recharts, "recharts");
}

const loadScript = (src) =>
  new Promise((res, rej) => {
    const s = document.createElement("script");
    s.src = src;
    s.onload = res;
    s.onerror = () => rej(new Error("no cargó " + src));
    document.head.appendChild(s);
  });

function normalize(p) {
  const out = [];
  for (const part of p.split("/")) {
    if (part === "." || part === "") continue;
    if (part === "..") out.pop();
    else out.push(part);
  }
  return out.join("/");
}

function resolve(spec, from) {
  const base = from.split("/").slice(0, -1).join("/");
  const raw = normalize(base + "/" + spec);
  const tries = [raw, raw + ".tsx", raw + ".ts", raw + "/index.tsx", raw + "/index.ts"];
  for (const t of tries) if (MANIFEST.includes(t)) return t;
  throw new Error("no se resolvió " + spec + " desde " + from);
}

const modules = Object.create(null);

async function build() {
  await loadScript("https://cdn.jsdelivr.net/npm/@babel/standalone@7.26.4/babel.min.js");
  const sources = await Promise.all(
    MANIFEST.map(async (path) => {
      const res = await fetch(path + "?v=" + Date.now(), { cache: "no-store" });
      if (!res.ok) throw new Error("falta " + path);
      return [path, await res.text()];
    })
  );
  for (const [path, code] of sources) {
    const out = window.Babel.transform(code, {
      filename: path,
      presets: [
        [
          "typescript",
          { isTSX: true, allExtensions: true, allowDeclareFields: true, onlyRemoveTypeImports: false },
        ],
        ["react", { runtime: "classic" }],
      ],
      plugins: ["transform-modules-commonjs"],
      sourceType: "module",
      compact: false,
    }).code;
    modules[path] = { factory: new Function("require", "module", "exports", "React", out), exports: null };
  }
}

function requireFrom(from) {
  return (spec) => {
    if (EXTERNALS[spec]) return EXTERNALS[spec];
    if (!spec.startsWith(".")) throw new Error("dependencia no disponible: " + spec);
    const path = resolve(spec, from);
    const mod = modules[path];
    if (mod.exports) return mod.exports;
    mod.exports = {};
    const m = { exports: mod.exports };
    mod.factory(requireFrom(path), m, m.exports, React);
    mod.exports = m.exports;
    return m.exports;
  };
}

// Mount outside the Design Component's own React tree, otherwise its re-renders
// wipe the app's DOM.
function mountPoint() {
  let el = document.getElementById("rankpro-mount");
  if (!el) {
    el = document.createElement("div");
    el.id = "rankpro-mount";
    el.style.cssText = "position:relative;z-index:1;min-height:100vh;background:" + (SOLO_MODULE ? "transparent" : "#fff");
    document.body.appendChild(el);
  }
  return el;
}

try {
  if (window.__rankproMounted) throw { skip: true };
  window.__rankproMounted = true;
  await loadDeps();
  await build();
  document.body.classList.add("rp-ready");
  let node;
  if (SOLO_MODULE) {
    const { AgencyModule } = requireFrom("app/entry")("./AgencyDashboard");
    node = React.createElement(AgencyModule, { id: SOLO_MODULE });
  } else {
    node = React.createElement(requireFrom("app/entry")("./App").default);
  }
  class Boundary extends React.Component {
    constructor(p) { super(p); this.state = { err: null }; }
    static getDerivedStateFromError(err) { return { err }; }
    componentDidCatch(err) { console.error(err); }
    render() {
      if (this.state.err) {
        return React.createElement("pre", { style: { font: "13px/1.5 ui-monospace,monospace", padding: 24, color: "#b91c1c", whiteSpace: "pre-wrap" } },
          "Error al renderizar la app:\n" + String(this.state.err && this.state.err.stack ? this.state.err.stack : this.state.err));
      }
      return this.props.children;
    }
  }
  window.addEventListener("error", (e) => {
    const m = document.getElementById("rankpro-mount");
    if (m && !m.childNodes.length) m.textContent = "Error: " + e.message;
  });
  ReactDOMClient.createRoot(mountPoint()).render(React.createElement(Boundary, null, node));
} catch (err) {
  if (err && err.skip) {
    // ya montado
  } else {
  console.error(err);
  document.body.classList.remove("rp-ready");
  const el = document.getElementById("rankpro-root");
  if (el) {
    el.innerHTML =
      '<div style="font:14px/1.6 ui-monospace,monospace;padding:32px;color:#b91c1c">Error al montar la app:<br>' +
      String(err && err.message ? err.message : err) +
      "</div>";
  }
  }
}
