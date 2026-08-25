# Using the admin panel

Everything is at **yourdomain.com/admin**. Sign in with the username and
password created during setup.

---

## The two ways products are grouped

There are two independent groupings, and it is worth being clear about why.

**Categories** are product types: Fresh Fruit, Coconut Products, Medical
Products. A product belongs to exactly one.

**Origins** are countries: Canada, United States, Mexico, Thailand, Vietnam. A
product belongs to at most one.

They are kept separate rather than nested because origin cuts across every
type. A buyer wanting Thai frozen items can pick Thailand *and* Frozen and get
exactly that. If origin were a category you would need a Thailand branch
duplicated under all eight types, and re-filing anything would be a nightmare.

Both have their own page — `/category/coconut-products`, `/origin/thailand` —
so either is a link you can send someone.

### Categories

**Categories → + New category.**

- **Name** — what visitors see, e.g. "Coconut Products".
- **Description** — optional, shown under the heading on the category page.
- **URL slug** — leave blank and it is built from the name. Only fill it in if
  you want a specific address.
- **Sort order** — controls the order in the menu and the sidebar. Lower first.
  Leave gaps (10, 20, 30) so you can slot something in later without
  renumbering everything.
- **Parent category** — optional. Filtering a parent also shows the products in
  its children.
- **Standard specification headings** — see "Specification templates" below.

Deleting a category never deletes products. They stay in the catalogue and
become "Uncategorised" until you file them somewhere else.

### Origins

**Origins → + New origin.** Name, an optional two-letter country code (not
shown on the site — it is there for paperwork later), and a sort order.

Deleting an origin never deletes products either. They stay listed, with no
origin set.

The Origins page tells you how many live products have no origin yet, with a
link that lists them. At the moment that is **45 of 197** — everything the
supplier list did not account for, mostly the commodities, the medical lines
and the fruit that is not on the Thai sheet. Those were left blank rather than
guessed at. You can also reach the same list from the sidebar of the public
catalogue, under Origin → "Not specified".

---

## Adding a product

**Products → + New product.**

### Basics

| Field | Notes |
|---|---|
| Product name | Required. |
| SKU / part number | Optional, but it must be unique. It is searchable. |
| Brand | Optional. Every brand in use appears in the public "Brand" filter dropdown. |
| Short description | One line, shown under the name on the catalogue grid. Keep it under about 120 characters or it is trimmed with "…" on the cards. |
| Full description | Plain text on the product page. Blank lines become paragraphs. |
| URL slug | Leave blank; it is built from the name. |

### Technical specifications

This is the flexible part. Add as many rows as you like with **+ Add row**.

- **Group** — optional heading. Rows sharing a group are printed together
  under it: "Packing", "Logistics", "Compliance". Leave it blank on every
  row and you get one plain table instead.
- **Name** — e.g. *Shelf life*
- **Value** — e.g. *18 months*

Both name and value must be filled in or the row is ignored, so you can leave a
blank row lying around without it appearing on the site.

Specifications are searchable. Someone typing "N95" into the search box finds
every respirator whose specification says N95, even where those characters
appear nowhere in the name or description.

#### Specification templates

Typing the same twelve headings onto two hundred products would be miserable,
and you would end up with "Shelf life", "Shelf-life" and "Shelf Life" all in
use. So each category carries its standard headings.

Pick a category on the product form and the specification table fills with that
category's headings, blank and ready for values. Type the values, save, done.

- It only fires while the table is **empty**. If you have already typed
  something, changing the category leaves your rows alone.
- Headings with no value are ignored on save, so anything you do not have an
  answer for simply does not appear.
- Edit the list per category under **Categories → edit → Standard
  specification headings**: one per line, or `Group|Heading` to put it in a
  section.

The eight categories came pre-loaded with sensible headings — pack format, pack
sizes, minimum order, shelf life, storage, incoterms, certifications, HS code
and so on. Change them to suit; they are only suggestions.

**None of those headings have values yet.** Nothing was filled in on your
behalf: a shelf life or a certification is a commercial claim published under
your name, and inventing one would be worse than leaving it blank.

### Images

Click **Choose images**, or drag files onto the dashed box. Several at once is
fine. They upload when you press **Save product**.

Once saved, the images appear under **Current images**:

- The one marked **MAIN** is what shows on the catalogue grid. Use
  **Make main** on any other image to change it.
- **Remove** deletes both the row and the file from the server.
- The text box under each image is its **alt text** — a short description for
  screen readers and for when an image fails to load. It saves with the
  product, not on its own.

Accepted: JPG, PNG, GIF, WebP, up to 6 MB each. Anything larger than
1600×1600 px is scaled down automatically, so you can upload straight off a
camera without resizing first.

### Origin

A dropdown of the countries set up under **Origins**, plus "Not specified".
Whatever you pick drives the origin filter, the badge on the catalogue card and
the line on the product page.

### Price

- **Regular price** — **optional**. Leave it blank and the item shows
  "Price on request" instead of a figure. Every product currently in the
  catalogue is like this.
- **Sale price** — leave blank if the item is not on sale. When filled in, the
  catalogue shows the sale price with the regular one struck through and a
  "Sale" badge on the card. It must be lower than the regular price, and it
  needs a regular price to be reduced from — a sale price on its own would
  strike a line through nothing.

A blank price is a real state, not a gap. For export lines the number usually
depends on volume and incoterm, so:

- The card and the product page say "Price on request". You can reword that
  under **Settings → Label for unpriced items** — "Contact us for a quote", or
  whatever suits.
- A price range filter **skips** unpriced items rather than treating them as
  free. Someone filtering $0–$100 does not get two hundred results.
- "Price low to high" puts the unpriced ones at the end, not the front.
- The price filter box does not appear at all while nothing is priced, because
  a range that can only return nothing is just a trap.

Price a handful of items and the filter appears on its own.

### Availability

| Status | Shows as |
|---|---|
| In stock | green |
| Low stock | amber |
| **Available to order** | blue — the default |
| Pre-order | blue |
| Out of stock | red |
| Discontinued | grey |

Everything loaded from your list is set to **Available to order**. That seemed
the honest default for sourcing lines — marking two hundred items "In stock"
would be a claim rather than a fact. Change any of them whenever you know
better.

A status nothing uses is hidden from the public filter, so the sidebar never
offers a tick box that can only return zero results.

**Quantity on hand** is optional. Fill it in and the product page adds
"(27 available)". Leave it blank if you do not count units — nothing breaks.

### Visibility

- **Show on the public catalogue** — untick to take a product off the site
  without deleting it. Its page returns "not found" while it is hidden.
- **Feature this product** — featured products come first under the default
  "Featured first" sort and get a badge.
- **Sort order** — a tie-breaker between featured products. Lower first.

---

## Editing and deleting

**Products** lists everything with a search box and filters by category and
availability. The **Images** column shows how many images each product has, in
amber when it has none — a quick way to spot gaps.

Every row has **View** (opens the public page in a new tab), **Edit** and
**Delete**. Deleting a product also removes its specifications and its image
files. There is no undo, so take a database backup before a big clear-out.

---

## Settings

**Settings** changes site-wide values without touching any code:

- **Site name** and **tagline** — header, footer and page titles. Currently
  "Disruptive Sourcing" — change it here if the catalogue should carry MIKN
  Consulting instead; nothing else needs touching.
- **Currency code and symbol** — the symbol goes in front of every price
- **Products per page** — 4 to 60
- **Label for unpriced items** — what appears where a price would be. Defaults
  to "Price on request".
- **Contact email and phone** — optional. Fill either in and product pages
  gain a line telling visitors how to enquire. Leave both blank and the line
  disappears. The phone is currently set to the number in your signature —
  clear the field if you would rather it did not appear publicly.

### The logo

The logo is a file, not a setting:

| File | Used for |
|---|---|
| `public/assets/img/ds-logo.png` | Header, footer and admin bar |
| `public/assets/img/ds-logo-dark.png` | The same wordmark in black ink, for any light background |
| `public/assets/img/favicon.png` | Browser tab |
| `public/assets/img/apple-touch-icon.png` | Home-screen icon on a phone |

Replace any of them with your own artwork and the site picks it up on the next
page load — no code change.

They were cut from the wordmark at the top of your brand board. That version is
a flat picture on a black background, so the black was keyed out to give a
transparent PNG that sits on any colour cleanly — which is what lets the same
file work on the black header and the black admin bar without a visible box
around it.

It is still a bitmap lifted from a PDF, though. If you have the original as an
**SVG, EPS or AI** file, send it over and I will swap it in: a vector stays
crisp on a high-resolution screen and at any size, where this one will start to
soften if it is ever used much larger than the header.

---

## Your password

**Password** changes it. You need your current one, and the new one must be at
least 10 characters. A short phrase you can actually remember beats a
scrambled word you will end up writing down.

If you are locked out after too many wrong attempts, wait fifteen minutes —
the lockout clears itself.

---

## What visitors see

- **Catalogue** — grid of products, with a sidebar to filter by type, origin,
  availability, brand and price range, plus sorting. On a phone the sidebar
  collapses into a **Filters** button.
- **Search** — matches names, SKUs, brands, descriptions, origin names *and*
  specification values.
- **Product page** — image gallery, price, availability, origin, description,
  the specification table grouped into sections, and other items from the same
  category underneath.

Everything reflows for phones and tablets; there is no separate mobile site to
maintain.

---

## The placeholder images

Every product currently shows a generated plate: the category, the product name
and the words "image to follow", on the brand black.

They are deliberately not fake photographs of produce. A convincing but
invented picture of a mango is worse than an obvious placeholder, because a
buyer might take it for the real thing.

To replace one, open the product and upload a photograph — the moment a product
has an uploaded image, its placeholder stops being used. There is no need to
delete anything first.

Send the photographs over whenever they are ready and I can bulk-load them if
that is easier than doing 197 by hand; the file names just need to say which
product each one belongs to.

---

## Housekeeping

**Backups.** The database holds the text; `public/uploads/` holds the images.
A backup needs both — a database dump on its own restores products with broken
image links.
