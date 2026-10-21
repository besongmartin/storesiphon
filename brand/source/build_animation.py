"""StoreSiphon logo animation generator (SVG + CSS, no JavaScript).

Produces:
  brand/animation/storesiphon-symbol-animated.svg      intro: S draws along its tube, record slides out (≈1.1 s, plays once)
  brand/animation/storesiphon-horizontal-animated.svg  same intro, wordmark fades/slides in after the symbol
  brand/animation/storesiphon-loader.svg               looping "extraction in progress" indicator (records stream out)

The final frame of every intro equals the static logo. prefers-reduced-motion → static, no motion.
Run from the project root:   python brand/source/build_animation.py
"""
from __future__ import annotations

from build_brand import (INK, TEAL, TEAL_300, WHITE, ROOT, build_wordmark, f, siphon_s, translate, write)

# Symbol geometry on the 256 canvas (must match build_brand.final())
H, T, TOP = 224, 40, 16
body, rec, w = siphon_s(0, TOP, H, T)
DX = (256 - w) / 2
S_BODY, S_REC = translate(body, DX, 0), translate(rec, DX, 0)
rc = (H - T) / 4
R = rc + T / 2
CX = DX + R
C1 = TOP + R
C2 = C1 + 2 * rc
REC_X = CX - R
STUB_X = REC_X + T + T * 0.35
SLIDE = STUB_X - REC_X            # distance the record travels out of the tail

# centre-line of the tube, used as an animated mask (draw-on effect for filled geometry)
CENTRE = (f"M{f(CX + R + 2)} {f(TOP + T / 2)}H{f(CX)}"
          f"A{f(rc)} {f(rc)} 0 0 0 {f(CX)} {f(C1 + rc)}"
          f"A{f(rc)} {f(rc)} 0 0 1 {f(CX)} {f(C2 + rc)}"
          f"H{f(STUB_X - 2)}")
LEN = 400                          # ≥ path length (≈ 66 + 2·π·46 + 12 ≈ 367), used for dasharray

EASE = "cubic-bezier(.65,0,.35,1)"       # in-out for the draw
EASE_OUT = "cubic-bezier(.22,1,.36,1)"   # decisive settle for the record


def css(prefix, word=False, ink=INK, acc=TEAL):
    rules = f"""
  .{prefix}-draw {{ stroke-dasharray:{LEN}; stroke-dashoffset:{LEN}; animation:{prefix}-draw .7s {EASE} .05s forwards; }}
  .{prefix}-rec {{ opacity:0; transform:translateX({f(SLIDE)}px); animation:{prefix}-rec .42s {EASE_OUT} .62s forwards; }}
  @keyframes {prefix}-draw {{ to {{ stroke-dashoffset:0; }} }}
  @keyframes {prefix}-rec {{ 0% {{ opacity:0; transform:translateX({f(SLIDE)}px); }} 25% {{ opacity:1; }} 100% {{ opacity:1; transform:translateX(0); }} }}"""
    if word:
        rules += f"""
  .{prefix}-word {{ opacity:0; transform:translateX(-12px); animation:{prefix}-word .5s {EASE_OUT} .55s forwards; }}
  @keyframes {prefix}-word {{ to {{ opacity:1; transform:translateX(0); }} }}"""
    rules += f"""
  @media (prefers-reduced-motion: reduce) {{
    .{prefix}-draw {{ animation:none; stroke-dashoffset:0; }}
    .{prefix}-rec, .{prefix}-word {{ animation:none; opacity:1; transform:none; }}
  }}"""
    return f"<style>{rules}\n</style>"


def symbol_group(prefix, ink, acc):
    # record is painted first so it emerges from *inside* the tube's tail as it slides out
    return (f'<mask id="{prefix}-mask" maskUnits="userSpaceOnUse" x="0" y="0" width="256" height="256">'
            f'<path class="{prefix}-draw" d="{CENTRE}" fill="none" stroke="#fff" stroke-width="{f(T + 6)}" stroke-linejoin="round"/>'
            f"</mask>"
            f'<path class="{prefix}-rec" fill="{acc}" d="{"".join(S_REC)}"/>'
            f'<path fill="{ink}" mask="url(#{prefix}-mask)" d="{"".join(S_BODY)}"/>')


def animated_symbol(prefix="ssa", ink=INK, acc=TEAL):
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256" role="img" aria-label="StoreSiphon">'
            f"<title>StoreSiphon</title>{css(prefix, ink=ink, acc=acc)}{symbol_group(prefix, ink, acc)}</svg>\n")


def animated_horizontal(prefix="ssh", ink=INK, acc=TEAL):
    wb, wa, ww = build_wordmark(s=20)
    gap, base = 56, 186
    # the lockup in build_brand uses the untranslated symbol (x from 0); shift the symbol group by -DX
    W_b, W_a = translate(wb, w + gap, base), translate(wa, w + gap, base)
    import math
    lock_w = math.ceil(w + gap + ww)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {lock_w} 256" width="{lock_w}" height="256" role="img" aria-label="StoreSiphon">'
            f"<title>StoreSiphon</title>{css(prefix, word=True, ink=ink, acc=acc)}"
            f'<g transform="translate({f(-DX)} 0)">{symbol_group(prefix, ink, acc)}</g>'
            f'<g class="{prefix}-word"><path fill="{ink}" d="{"".join(W_b)}"/><path fill="{acc}" d="{"".join(W_a)}"/></g>'
            f"</svg>\n")


def loader(prefix="ssl", ink=INK, acc=TEAL):
    """Looping indicator for running extraction jobs: static S, records stream out of the tail."""
    dur = 1.6
    rules = f"""
  .{prefix}-r {{ opacity:0; animation:{prefix}-flow {dur}s {EASE_OUT} infinite; }}
  .{prefix}-r2 {{ animation-delay:{dur / 2}s; }}
  @keyframes {prefix}-flow {{
    0% {{ opacity:0; transform:translateX({f(SLIDE)}px); }}
    20% {{ opacity:1; }}
    60% {{ opacity:1; transform:translateX(0); }}
    100% {{ opacity:0; transform:translateX(-{f(T * 0.9)}px); }}
  }}
  @media (prefers-reduced-motion: reduce) {{
    .{prefix}-r {{ animation:none; opacity:1; transform:none; }} .{prefix}-r2 {{ display:none; }}
  }}"""
    rec_d = "".join(S_REC)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="-48 0 304 256" width="304" height="256" role="img" aria-label="Working">'
            f"<title>Working</title><style>{rules}\n</style>"
            f'<path fill="{ink}" d="{"".join(S_BODY)}"/>'
            f'<path class="{prefix}-r" fill="{acc}" d="{rec_d}"/>'
            f'<path class="{prefix}-r {prefix}-r2" fill="{acc}" d="{rec_d}"/></svg>\n')


if __name__ == "__main__":
    for rel, content in [
        ("animation/storesiphon-symbol-animated.svg", animated_symbol()),
        ("animation/storesiphon-symbol-animated-on-dark.svg", animated_symbol("ssd", WHITE, TEAL_300)),
        ("animation/storesiphon-horizontal-animated.svg", animated_horizontal()),
        ("animation/storesiphon-horizontal-animated-on-dark.svg", animated_horizontal("shd", WHITE, TEAL_300)),
        ("animation/storesiphon-loader.svg", loader()),
        ("animation/storesiphon-loader-on-dark.svg", loader("sld", WHITE, TEAL_300)),
    ]:
        print(write(rel, content).relative_to(ROOT.parent))
