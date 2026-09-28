#!/usr/bin/env python3
"""
Renders the Disruptive Sourcing wordmark from the brand typeface.

    python3 tools/make_logo.py

Writes public/assets/img/ds-logo.png (for dark surfaces) and ds-logo-dark.png
(the same wordmark in black ink, for light ones).

Why a script rather than an exported image: the wordmark used to be a bitmap
keyed out of page 1 of the brand board PDF, which meant it was a heavy
distressed display face nobody could re-set, and any change - a colour, a
word, the spacing - needed the PDF again. This renders it from
public/assets/fonts/roboto-var.woff2, the same file the site loads for its
body text, instanced to weight 700. So the logo and the site are now
demonstrably the same typeface, and changing it is editing two lines here.

It renders at 2x the layout size. The CSS sizes the logo by height with
width:auto, so the extra pixels cost nothing in layout and buy a sharp mark on
a phone or a retina screen.
"""
import os
import sys

from PIL import Image, ImageDraw, ImageFont
from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(HERE)
FONT_SRC = os.path.join(ROOT, "public", "assets", "fonts", "roboto-var.woff2")
OUT_DIR = os.path.join(ROOT, "public", "assets", "img")

# Brand standards. Red carries the second word; on a dark surface the first
# word is Engineering White, on a light one it is Industrial Black.
WHITE = (245, 245, 245, 255)   # #F5F5F5
BLACK = (14, 14, 14, 255)      # #0E0E0E
RED = (200, 16, 46, 255)       # #C8102E

WORD_ONE = "DISRUPTIVE"
WORD_TWO = "SOURCING"

RENDER_PX = 126        # 2x the 63px the old mark used
TRACKING = 0.0         # extra space between letters, in pixels at RENDER_PX
PAD = 3


def bold_font(size: int) -> ImageFont.FreeTypeFont:
    """Roboto at weight 700, taken from the variable font the site ships."""
    tmp = os.path.join(HERE, ".roboto-bold-cache.ttf")
    if not os.path.exists(tmp):
        f = TTFont(FONT_SRC)
        f.flavor = None                       # woff2 -> plain ttf
        f.save(tmp + ".var")
        inst = instancer.instantiateVariableFont(TTFont(tmp + ".var"), {"wght": 700})
        inst.save(tmp)
        os.remove(tmp + ".var")
    return ImageFont.truetype(tmp, size)


def draw_word(draw, xy, text, font, fill):
    """Draws a word letter by letter so tracking can be controlled."""
    x, y = xy
    for ch in text:
        draw.text((x, y), ch, font=font, fill=fill)
        x += draw.textlength(ch, font=font) + TRACKING
    return x


def width_of(draw, text, font):
    return sum(draw.textlength(c, font=font) for c in text) + TRACKING * len(text)


def render(first_colour, path):
    font = bold_font(RENDER_PX)
    probe = ImageDraw.Draw(Image.new("RGBA", (10, 10)))

    w1 = width_of(probe, WORD_ONE, font)
    w2 = width_of(probe, WORD_TWO, font)

    # Cap height, measured rather than assumed: PIL's font metrics include
    # space for descenders these all-caps words never use, and trusting them
    # leaves the mark floating in a box a third taller than the letters.
    box = probe.textbbox((0, 0), WORD_ONE + WORD_TWO, font=font)
    top, bottom = box[1], box[3]

    img = Image.new("RGBA", (int(w1 + w2) + PAD * 2, bottom - top + PAD * 2), (0, 0, 0, 0))
    d = ImageDraw.Draw(img)
    x = draw_word(d, (PAD, PAD - top), WORD_ONE, font, first_colour)
    draw_word(d, (x, PAD - top), WORD_TWO, font, RED)

    img = img.crop(img.getbbox())           # trim to the ink
    img.save(path)
    return img.size


if __name__ == "__main__":
    if not os.path.exists(FONT_SRC):
        sys.exit("Cannot find " + FONT_SRC)
    light = render(WHITE, os.path.join(OUT_DIR, "ds-logo.png"))
    dark = render(BLACK, os.path.join(OUT_DIR, "ds-logo-dark.png"))
    print("ds-logo.png      %dx%d  (for dark surfaces)" % light)
    print("ds-logo-dark.png %dx%d  (for light surfaces)" % dark)
    print("aspect ratio %.2f : 1" % (light[0] / light[1]))
