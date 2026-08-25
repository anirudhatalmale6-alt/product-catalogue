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

There is no price box on the product form, and that is deliberate.

The catalogue shows **no prices at all**. Every product displays "Price on
request", and a buyer asks for a quote by shortlisting items and sending them
over. Your own figures live somewhere else entirely — see
**[The internal price sheet](#the-internal-price-sheet)** below.

You can reword the label under **Settings → Label shown instead of a price** —
"Contact us for a quote", or whatever suits.

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

## The internal price sheet

**Admin → Price sheet.** This is the only screen in the whole site that holds
a figure, and nothing on the public catalogue reads it. That is not a setting
you could switch on by accident — the buyer-facing pages do not look at the
pricing table at all.

It is one big editable grid, one row per product, grouped by category:

| Column | What goes in it |
|---|---|
| **Price** | The number. Leave blank for anything not priced yet. |
| **Cur** | Three-letter code — CAD, USD, THB. Defaults to whatever is set under Settings. |
| **Per** | What the price is *per*: per kg, per 10kg carton, per 20ft FCL. |
| **MOQ** | Minimum order, in your own words — "1 x 20ft", "500 cartons". |
| **Incoterm** | FOB, CIF, EXW and so on. Typed in lower case, saved upper. |
| **Valid until** | When the quote expires. Rows past their date turn red. |
| **Supplier** | Who quoted it. Also searchable. |
| **Notes** | Anything else — "subject to crop", "add 3% for organic cert". |

Type into as many rows as you like and press **Save price sheet** once at the
bottom. Only the rows on screen are saved, so filtering to one category and
saving leaves every other product untouched.

Two things it will not let you do:

- **A price with no unit is refused.** "4.85" on an export line is ambiguous —
  per kilo and per carton are wildly different numbers, and the sheet is no
  use six months later if nobody can tell which was meant.
- **Clearing every box on a row deletes that row's pricing** rather than
  storing a set of blanks, so "not priced" is one state in the data instead of
  two that behave differently.

### Finding what still needs doing

- **Priced → Not priced yet** lists everything with no figure. The dashboard
  tile "No internal price" links straight here.
- **Expiring or expired** catches quotes at or past their valid-until date.
- The header line says how many of the total are priced.

### Getting a sheet out

**Export CSV** downloads whatever the current filter is showing — so you can
export one category, one origin, or the lot. It opens directly in Excel,
Numbers or Google Sheets.

The file is an internal working document. It carries your costs, suppliers and
margins; it is not something to forward to a buyer as it stands.

---

## Enquiries

**Admin → Enquiries.** When a buyer shortlists items on the catalogue and
sends them, the enquiry lands here with a reference like `DS-2608-0042`.

Each one shows what they asked for, how much of it, any notes they added, and
their contact and shipping details — destination port, preferred incoterm,
country. Alongside every line it shows **your internal price** for that
product, if you have entered one, so you can see immediately what is quotable
and what still needs a figure.

Two buttons at the top:

- **Open in price sheet** — jumps to the price sheet filtered to exactly the
  products on this enquiry. Fill in the missing ones and save.
- **Export quote sheet** — a CSV of the enquiry with your internal terms
  beside each line, for building the quote.

Set the **status** as you work — New, In progress, Quoted, Closed — and keep
notes in the internal notes box. Neither is ever shown to the buyer. The count
of new enquiries appears next to "Enquiries" in the sidebar and on the
dashboard.

### The notification email

Under **Settings → Enquiry notification email** you can put an address to be
emailed a copy of each enquiry. It is optional and it is a convenience, not
the record: enquiries are always saved here whether the email works or not.
Whether it arrives depends on the host having working outbound mail, which is
worth testing once after going live.

---

## Uploading photographs in bulk

**Admin → Products → Bulk image upload.** With nearly two hundred products,
attaching photographs one form at a time is a long evening. This takes a whole
folder at once and matches each file to a product by its filename.

It tries three things, in order:

1. the product's **SKU**, exactly
2. the product's **web address slug** (`fresh-young-coconut`)
3. the **product name** with spaces, punctuation and capitals ignored

Case does not matter, and a trailing copy number is ignored, so
`Dragon Fruit (2).jpg` and `dragon-fruit.JPG` both find the same product. The
page lists a sample of products still without a photograph and the exact
filename to use for each.

Every file you upload is accounted for in the report afterwards — attached,
skipped, or failed, each with the reason. Nothing is dropped silently.

**Replace is off by default.** A file matching a product that already has an
image is skipped rather than overwriting it, so you can run this repeatedly to
fill gaps without disturbing photographs you have already sorted. Tick
**Replace images on products that already have one** when you do want to
overwrite — that removes the old images for those products.

If the page times out on a very large batch, send them in smaller groups; most
hosts cap how much can be uploaded in one request.


---

## Settings

**Settings** changes site-wide values without touching any code:

- **Site name** and **tagline** — header, footer and page titles, currently
  "Disruptive Sourcing".
- **Default currency code and symbol** — used on new internal price sheet
  rows. Neither ever appears on the public site, because no public page prints
  a figure.
- **Products per page** — 4 to 60
- **Label shown instead of a price** — appears on every product. Defaults to
  "Price on request".
- **Contact email and phone** — optional. Fill either in and product pages
  gain a line telling visitors how to reach you directly. Leave both blank and
  the line disappears. The phone is currently set to the number in your
  signature — clear the field if you would rather it did not appear publicly.
- **Enquiry notification email** — optional; see
  **[Enquiries](#enquiries)** above.
- **Shortlist page introduction** — the line above the enquiry form.

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
  availability and brand, plus sorting. On a phone the sidebar collapses into
  a **Filters** button. There is no price filter, because there are no prices.
- **Search** — matches names, SKUs, brands, descriptions, origin names *and*
  specification values.
- **Product page** — image gallery, availability, origin, description, the
  specification table grouped into sections, and other items from the same
  category underneath.
- **Shortlist** — every product card and product page has an *Add to
  shortlist* button. The shortlist is kept in the visitor's own browser, so
  they can build one over several visits with no account and nothing is
  recorded on your side until they send it. The header shows a running count.
- **Enquiry** — on the shortlist page they add the volume they want and any
  notes per item, fill in their contact and destination details, and send. It
  arrives under **Admin → Enquiries** and they get a reference number.

Everything reflows for phones and tablets; there is no separate mobile site to
maintain.

One detail worth knowing: the shortlist stores only product *ids*, and the
names, photographs and availability are read back from the database each time
the page loads. A shortlist built weeks ago therefore shows current
information, and anything you have withdrawn from the catalogue drops off it
by itself.

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
