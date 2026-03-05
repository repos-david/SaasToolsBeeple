/* ================================================================
   brands.js – Per-client branding configuration
   ================================================================
   Add an entry to BRANDS[] for each client/tenant you want to brand.
   Each entry:
     match          – RegExp tested against the tenant HOSTNAME only
                      (e.g. /colruyt/i matches "colruyt.beepleapp.com")
     logo           – URL / data-URI for topbar logo (optional, defaults to icon.svg)
     accent         – primary accent colour, light mode
     accent2        – secondary/lighter accent, light mode
     accentSoft     – very light tint of accent, light mode
     darkAccent     – primary accent, dark mode  (falls back to accent)
     darkAccent2    – secondary accent, dark mode (falls back to accent2)
     darkAccentSoft – soft tint, dark mode        (falls back to accentSoft)
     words          – optional vocabulary overrides (all keys optional):
                        team        singular, capitalised  (default: "Team")
                        teams       plural,   capitalised  (default: "Teams")
                        enrolment   singular, capitalised  (default: "Enrolment")
                        enrolments  plural,   capitalised  (default: "Enrolments")

   After calling applyBrand(base) you can call t("team") etc. anywhere to
   get the brand-specific term. t() always falls back to the default word.
   ================================================================ */

const BRANDS = [
  {
    match:         /colruyt/i,
    accent:         "#E2001A",
    accent2:        "#FF5566",
    accentSoft:     "#FFF0F1",
    darkAccent:     "#FF4455",
    darkAccent2:    "#FF8899",
    darkAccentSoft: "#3A0009",
    // words: { team:"Shift", teams:"Shifts", enrolment:"Registration", enrolments:"Registrations" },
  },
  {
    match:         /saas/i, // test
    accent:         "#FF001A",
    accent2:        "#1155FF",
    accentSoft:     "#FFF0F1",
    darkAccent:     "#FF4455",
    darkAccent2:    "#FF8899",
    darkAccentSoft: "#3A0009",
    words: { team:"gig", teams:"Gigs", enrolment:"Registration", enrolments:"Registrations" },
  },
  // ── Add more clients below ──────────────────────────────────────
  // {
  //   match:         /acme/i,
  //   logo:          "https://acme.example.com/logo.png",
  //   accent:        "#0070f3",
  //   accent2:       "#60a5fa",
  //   accentSoft:    "#eff6ff",
  //   darkAccent:    "#3b9eff",
  //   darkAccent2:   "#93c5fd",
  //   darkAccentSoft:"#0c2340",
  //   words: { team:"Job", teams:"Jobs", enrolment:"Application", enrolments:"Applications" },
  // },
];

/* ---- Internal state ---- */
const _defaults = { team:"Team", teams:"Teams", enrolment:"Enrolment", enrolments:"Enrolments" };
let _brandWords = {};

/* ---- t(key) – return the brand word for a key, falling back to the default ---- */
function t(key) {
  return _brandWords[key] !== undefined ? _brandWords[key] : (_defaults[key] !== undefined ? _defaults[key] : key);
}

/* ---- applyBrand(base) – apply colours, logo and word map for this tenant ---- */
function applyBrand(base) {
  if (base === undefined) base = localStorage.getItem("bwBase") || "";
  let hostname = "";
  try { hostname = new URL(base).hostname.toLowerCase(); } catch {}

  const brand = BRANDS.find(b => b.match.test(hostname));

  // Update word map
  _brandWords = (brand && brand.words) || {};

  const r    = document.documentElement;
  const dark = r.getAttribute("data-theme") === "dark";

  // Reset previous brand colour overrides
  ["--accent","--accent2","--accentSoft"].forEach(p => r.style.removeProperty(p));

  if (brand) {
    const a  = (dark && brand.darkAccent)     || brand.accent;
    const a2 = (dark && brand.darkAccent2)    || brand.accent2;
    const as = (dark && brand.darkAccentSoft) || brand.accentSoft;
    if (a)  r.style.setProperty("--accent",     a);
    if (a2) r.style.setProperty("--accent2",    a2);
    if (as) r.style.setProperty("--accentSoft", as);
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta && a) meta.setAttribute("content", a);
  }

  // Logo (update any .brand img found in the page)
  const logo = (brand && brand.logo) || "icon.svg";
  document.querySelectorAll(".brand img").forEach(img => { img.src = logo; });
}
