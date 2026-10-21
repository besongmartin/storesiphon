"""StoreSiphon brand generator — the editable source of truth for every logo file.

Every mark is built from exact geometric primitives (rectangles, annular sectors, squares) and written as
plain filled <path> data: no fonts, no strokes, no filters, no scripts, no external references.

Run from the project root:   python brand/source/build_brand.py
Outputs go to brand/logo, brand/icons and brand/concepts (SVG). Rasters are rendered separately (see README).
"""
from __future__ import annotations

import math
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]  # brand/

# ---------------------------------------------------------------- palette (keep in sync with tokens/)
INK = "#0B1F2E"        # Ink 900 — primary text / dark surfaces
INK_700 = "#1C3A4F"
TEAL = "#0E9F8A"       # Siphon Teal 600 — brand primary on light
TEAL_300 = "#4FD8BF"   # Siphon Teal 300 — brand primary on dark
AMBER = "#F2A33A"      # Signal Amber — secondary accent (checkout, highlights)
PAPER = "#F6F8F7"      # light surface
WHITE = "#FFFFFF"


# ---------------------------------------------------------------- geometry helpers
def f(v: float) -> str:
    s = f"{v:.2f}".rstrip("0").rstrip(".")
    return "0" if s in ("-0", "") else s


def pt(cx, cy, r, deg):
    a = math.radians(deg)
    return cx + r * math.cos(a), cy + r * math.sin(a)


def rect(x, y, w, h):
    return f"M{f(x)} {f(y)}H{f(x + w)}V{f(y + h)}H{f(x)}Z"


def sector(cx, cy, R, r, a0, a1):
    """Annular sector from angle a0 to a1 (degrees, SVG coords: 0 = +x, 90 = down), a1 > a0."""
    large = 1 if (a1 - a0) > 180 else 0
    ox0, oy0 = pt(cx, cy, R, a0)
    ox1, oy1 = pt(cx, cy, R, a1)
    ix1, iy1 = pt(cx, cy, r, a1)
    ix0, iy0 = pt(cx, cy, r, a0)
    return (f"M{f(ox0)} {f(oy0)}A{f(R)} {f(R)} 0 {large} 1 {f(ox1)} {f(oy1)}"
            f"L{f(ix1)} {f(iy1)}A{f(r)} {f(r)} 0 {large} 0 {f(ix0)} {f(iy0)}Z")


def ring(cx, cy, R, r):
    return sector(cx, cy, R, r, 0, 180) + sector(cx, cy, R, r, 180, 360)


# ---------------------------------------------------------------- the StoreSiphon "S" (symbol)
def siphon_s(x0, top, H, t, split=True, gap_ratio=0.35):
    """Geometric S of height H and stroke t; top-left of its bounding box at (x0, top).

    Construction: centre-line radius rc = (H - t) / 4. Upper bowl = left half-ring, lower bowl = right
    half-ring, joined tangentially at the vertical centre. Flat bars close the top (to the right) and the
    bottom (to the left). With split=True the bottom bar detaches as one square "record" of size t×t:
    the product that has been drawn through the siphon.
    Returns (body_paths, record_paths, width).
    """
    rc = (H - t) / 4
    R, r = rc + t / 2, rc - t / 2
    cx = x0 + R
    c1 = top + R
    c2 = c1 + 2 * rc
    body = [
        sector(cx, c1, R, r, 90, 270),          # upper bowl (left half)
        rect(cx - 0.5, top, R + 0.5, t),        # top bar → right (overlap 0.5 to avoid seams)
        sector(cx, c2, R, r, -90, 90),          # lower bowl (right half)
    ]
    bottom_y = c2 + r
    record = []
    if split:
        gap = t * gap_ratio
        rec_x = cx - R                          # square aligned to the left edge of the upper bowl
        stub_x = rec_x + t + gap
        if stub_x < cx:                         # short flat stub only when there is room for one
            body.append(rect(stub_x, bottom_y, cx - stub_x + 0.5, t))
        record.append(rect(rec_x, bottom_y, t, t))
    else:
        body.append(rect(cx - R, bottom_y, R + 0.5, t))
    return body, record, 2 * R


# ---------------------------------------------------------------- wordmark letterforms (geometric, custom)
class Type:
    """Constructed geometric letters sharing the symbol's logic: stroke s, flat terminals, square dots."""

    def __init__(self, s=20.0, xh=86.0, cap=118.0, asc=122.0, desc=34.0):
        self.s, self.xh, self.cap, self.asc, self.desc = s, xh, cap, asc, desc

    # every glyph returns (paths, accent_paths, advance_width); baseline at y=0, ink grows upwards (negative y)
    def S(self, x):
        body, rec, w = siphon_s(x, -self.cap, self.cap, self.s, split=False)
        return body, rec, w

    def o(self, x):
        o = self.s * 0.06                               # overshoot for round forms
        R = self.xh / 2 + o
        return [ring(x + R, -self.xh / 2, R, R - self.s)], [], 2 * R

    def e(self, x):
        o = self.s * 0.06
        R = self.xh / 2 + o
        r = R - self.s
        cx, cy = x + R, -self.xh / 2
        paths = [sector(cx, cy, R, r, 40, 360)]           # open at lower right; arc ends flat at the bar line
        bar = self.s * 0.9                                # horizontals slightly thinner (optical)
        # crossbar sits on the arc's end cut (bottom edge = cy), so no spur appears at the opening
        paths.append(rect(cx - r - 0.5, cy - bar, 2 * r + 1, bar))
        return paths, [], 2 * R

    def n(self, x, ascender=False):
        s = self.s
        w = self.xh * 0.86
        R = w / 2
        r = R - s
        cx, cy = x + R, -self.xh + R
        top_stem = -self.asc if ascender else -self.xh
        paths = [
            rect(x, top_stem, s, -top_stem),             # left stem
            sector(cx, cy, R, r, 180, 360),              # arch
            rect(x + w - s, cy - 0.5, s, -cy + 0.5),     # right stem
        ]
        return paths, [], w

    def h(self, x):
        return self.n(x, ascender=True)

    def p(self, x):
        o = self.s * 0.06
        R = self.xh / 2 + o
        paths = [ring(x + R, -self.xh / 2, R, R - self.s),
                 rect(x, -self.xh / 2, self.s, self.xh / 2 + self.desc)]
        return paths, [], 2 * R

    def r(self, x):
        s = self.s
        R = self.xh * 0.5
        rr = R - s
        cx, cy = x + R, -self.xh + R
        paths = [rect(x, -self.xh, s, self.xh),
                 sector(cx, cy, R, rr, 180, 270),
                 rect(cx - 0.5, -self.xh, s * 0.55, s)]  # short flat terminal
        return paths, [], R + s * 0.55

    def t(self, x):
        s = self.s
        stem_x = x + s * 0.55
        paths = [rect(stem_x, -self.asc * 0.94, s, self.asc * 0.94),
                 rect(x, -self.xh, s * 2.1, s * 0.9)]
        return paths, [], s * 2.1

    def i(self, x):
        s = self.s
        # the tittle is a square "record", the same element as the symbol's detached square
        return [rect(x, -self.xh, s, self.xh)], [rect(x, -self.asc, s, s)], s


WORD = [("S", 0), ("t", 9), ("o", 7), ("r", 9), ("e", 4), ("S", 11), ("i", 11), ("p", 11), ("h", 10), ("o", 10), ("n", 8)]
# second value = space before the glyph (optical spacing, tuned by eye on renders)


def build_wordmark(x0=0.0, baseline=0.0, s=20.0, scale=1.0, split_letters=None):
    T = Type(s=s)
    body, accent = [], []
    x = x0
    for ch, space in WORD:
        x += space * scale
        p, a, w = getattr(T, ch)(x)
        body += p
        accent += a
        x += w
    return body, accent, x - x0


def translate(paths, dx, dy):
    """Shift absolute path data (M/L/H/V/A/Z only) by dx, dy."""
    import re
    out = []
    for d in paths:
        tokens = re.findall(r"[MLHVAZ]|-?\d+(?:\.\d+)?", d)
        res, i, cmd = [], 0, None
        while i < len(tokens):
            tk = tokens[i]
            if tk.isalpha():
                cmd = tk
                res.append(tk)
                i += 1
                continue
            if cmd in ("M", "L"):
                res += [f(float(tokens[i]) + dx), f(float(tokens[i + 1]) + dy)]
                i += 2
            elif cmd == "H":
                res.append(f(float(tk) + dx)); i += 1
            elif cmd == "V":
                res.append(f(float(tk) + dy)); i += 1
            elif cmd == "A":
                a = tokens[i:i + 7]
                res += [a[0], a[1], a[2], a[3], a[4], f(float(a[5]) + dx), f(float(a[6]) + dy)]
                i += 7
            else:
                i += 1
        d2 = ""
        for tk in res:
            d2 += tk if tk.isalpha() else ((" " if d2 and not d2[-1].isalpha() else "") + tk)
        out.append(d2)
    return out


# ---------------------------------------------------------------- SVG writer
def svg(view_w, view_h, layers, title, bg=None, bg_rx=0):
    """layers: list of (fill, [path data]). Safe, static SVG: no scripts, no external refs."""
    parts = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {f(view_w)} {f(view_h)}" '
             f'width="{f(view_w)}" height="{f(view_h)}" role="img" aria-label="{title}">',
             f"<title>{title}</title>"]
    if bg:
        parts.append(f'<rect width="{f(view_w)}" height="{f(view_h)}" rx="{f(bg_rx)}" fill="{bg}"/>')
    for fill, paths in layers:
        if paths:
            parts.append(f'<path fill="{fill}" d="{"".join(paths)}"/>')
    parts.append("</svg>")
    return "\n".join(parts) + "\n"


def write(rel, content):
    p = ROOT / rel
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(content, encoding="utf-8")
    return p


# ---------------------------------------------------------------- concepts (exploration, black)
def concepts():
    K = "#111111"
    # A — Siphon S: S whose tail detaches as a square record
    body, rec, w = siphon_s(0, 16, 224, 40)
    dx = (256 - w) / 2
    write("concepts/concept-a-siphon-s.svg", svg(256, 256, [(K, translate(body + rec, dx, 0))], "Concept A — Siphon S"))
    # B — Siphon arc: a monoline inverted-U tube over a rim, dropping a record
    t = 32
    arc = [sector(128, 112, 72, 40, 180, 360),
           rect(56, 111.5, t, 56.5),        # short intake leg
           rect(168, 111.5, t, 88.5),       # long outflow leg
           rect(24, 176, 96, 16)]           # source level / rim
    drop = [rect(168, 214, t, t)]
    write("concepts/concept-b-siphon-arc.svg", svg(256, 256, [(K, arc + drop)], "Concept B — Siphon arc"))
    # C — Catalog to rows: a 2×2 product grid whose last cell is drawn out into a data row
    g = [rect(24, 40, 72, 72), rect(112, 40, 72, 72), rect(24, 128, 72, 72),
         rect(112, 152, 120, 24), rect(112, 192, 88, 24), rect(112, 128, 120, 16)]
    write("concepts/concept-c-catalog-rows.svg", svg(256, 256, [(K, g)], "Concept C — Catalog to rows"))
    # lockup preview for A (wordmark constructed)
    wb, wa, ww = build_wordmark(s=20)
    sb, sr, sw = siphon_s(0, 16, 224, 40)
    scale_h = 256
    gapx = 56
    total = sw + gapx + ww
    lock = translate(sb + sr, 0, 0) + translate(wb + wa, sw + gapx, 186)
    write("concepts/concept-a-lockup.svg", svg(total, scale_h, [(K, lock)], "Concept A lockup"))


# ---------------------------------------------------------------- final system
def final():
    # Symbol on a 256 canvas, 16 px padding top/bottom, centred horizontally
    body, rec, w = siphon_s(0, 16, 224, 40)
    dx = (256 - w) / 2
    S_body, S_rec = translate(body, dx, 0), translate(rec, dx, 0)

    # Small-size cut (≤ 32 px): heavier stroke, bigger gap so the split survives the pixel grid
    sb2, sr2, w2 = siphon_s(0, 20, 216, 48, gap_ratio=0.5)
    dx2 = (256 - w2) / 2
    s_body, s_rec = translate(sb2, dx2, 0), translate(sr2, dx2, 0)

    # Wordmark: cap height 118 on a 256-high artboard
    wb, wa, ww = build_wordmark(s=20)
    pad = 8
    word_w = math.ceil(ww + 2 * pad)
    base = 186                      # baseline so caps sit optically centred (cap 118, descender 34)
    W_body, W_acc = translate(wb, pad, base), translate(wa, pad, base)

    # Horizontal lockup: symbol height = 1.0 × artboard; gap = 1 stroke-unit × 1.4
    sym_w = w
    gap = 56
    lock_w = math.ceil(sym_w + gap + ww)
    L_sym_b, L_sym_r = translate(body, 0, 0), translate(rec, 0, 0)
    L_w_b, L_w_a = translate(wb, sym_w + gap, base), translate(wa, sym_w + gap, base)

    # Stacked lockup: symbol 256 tall over the wordmark scaled to the same width logic
    stk_w = math.ceil(max(ww, 256) + 32)
    st_dx = (stk_w - 256) / 2
    st_sym_b, st_sym_r = translate(S_body, st_dx, 0), translate(S_rec, st_dx, 0)
    wdx = (stk_w - ww) / 2
    st_w_b, st_w_a = translate(wb, wdx, 256 + 40 + 118), translate(wa, wdx, 256 + 40 + 118)
    stk_h = 256 + 40 + 118 + 34 + 16

    variants = {
        # name: (symbol body, symbol record, word body, word accent, background)
        "color": (INK, TEAL, INK, TEAL, None),
        "color-on-dark": (WHITE, TEAL_300, WHITE, TEAL_300, None),
        "black": ("#000000", "#000000", "#000000", "#000000", None),
        "white": (WHITE, WHITE, WHITE, WHITE, None),
        "mono-ink": (INK, INK, INK, INK, None),
        "mono-teal": (TEAL, TEAL, TEAL, TEAL, None),
    }
    out = []
    for name, (sb_c, sr_c, wb_c, wa_c, _bg) in variants.items():
        out.append(write(f"logo/storesiphon-symbol-{name}.svg",
                         svg(256, 256, [(sb_c, S_body), (sr_c, S_rec)], "StoreSiphon")))
        out.append(write(f"logo/storesiphon-wordmark-{name}.svg",
                         svg(word_w, 256, [(wb_c, W_body), (wa_c, W_acc)], "StoreSiphon")))
        out.append(write(f"logo/storesiphon-horizontal-{name}.svg",
                         svg(lock_w, 256, [(sb_c, L_sym_b + L_w_b), (sr_c, L_sym_r), (wa_c, L_w_a)]
                             if sr_c != wa_c else [(sb_c, L_sym_b + L_w_b), (sr_c, L_sym_r + L_w_a)],
                             "StoreSiphon")))
        out.append(write(f"logo/storesiphon-stacked-{name}.svg",
                         svg(stk_w, stk_h, [(sb_c, st_sym_b + st_w_b), (sr_c, st_sym_r + st_w_a)], "StoreSiphon")))

    # Small-size symbol cut (for favicon/app icon sources)
    out.append(write("logo/storesiphon-symbol-small-color.svg",
                     svg(256, 256, [(INK, s_body), (TEAL, s_rec)], "StoreSiphon")))
    out.append(write("logo/storesiphon-symbol-small-black.svg",
                     svg(256, 256, [("#000000", s_body + s_rec)], "StoreSiphon")))

    # App icon + favicon: small cut, 64 % of the tile, on Ink with a teal record
    def tile(path_b, path_r, size=512, inset=0.64, rx_ratio=0.225, bg=INK, fg=WHITE, acc=TEAL_300):
        k = size * inset / 256
        off = size * (1 - inset) / 2
        def scale(paths):
            import re
            res = []
            for d in paths:
                def rep(m):
                    return f(float(m.group(0)) * k)
                # scale every number (radii, flags 0/1 are unaffected because 0*k=0 and 1*k≠1 — handled below)
                tokens = re.split(r"(A[^A-Z]*)", d)
                nd = ""
                for tk in tokens:
                    if tk.startswith("A"):
                        nums = tk[1:].replace(",", " ").split()
                        out_n = []
                        for j in range(0, len(nums), 7):
                            a = nums[j:j + 7]
                            out_n += [f(float(a[0]) * k), f(float(a[1]) * k), a[2], a[3], a[4],
                                      f(float(a[5]) * k), f(float(a[6]) * k)]
                        nd += "A" + " ".join(out_n)
                    else:
                        nd += re.sub(r"-?\d+(?:\.\d+)?", rep, tk)
                res.append(nd)
            return translate(res, off, off)
        return svg(size, size, [(fg, scale(path_b)), (acc, scale(path_r))], "StoreSiphon",
                   bg=bg, bg_rx=size * rx_ratio)

    out.append(write("icons/app-icon.svg", tile(s_body, s_rec)))
    out.append(write("icons/app-icon-light.svg", tile(s_body, s_rec, bg=PAPER, fg=INK, acc=TEAL)))
    out.append(write("icons/maskable-icon.svg", tile(s_body, s_rec, inset=0.52, rx_ratio=0)))
    out.append(write("icons/favicon.svg", tile(s_body, s_rec, size=64, inset=0.78, rx_ratio=0.22)))
    return out


if __name__ == "__main__":
    concepts()
    for p in final():
        print(p.relative_to(ROOT.parent))
