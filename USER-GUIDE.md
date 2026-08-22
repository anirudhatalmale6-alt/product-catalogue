# Using the admin panel

Everything is at **yourdomain.com/admin**. Sign in with the username and
password created during setup.

---

## Before you add anything: set up your categories

Categories are the product types the public filters use, so they come first.

**Categories → + New category.**

- **Name** — what visitors see, e.g. "Power Tools".
- **Description** — optional, shown under the heading on the category page.
- **URL slug** — leave blank and it is built from the name. Only fill it in if
  you want a specific address.
- **Sort order** — controls the order in the menu and the sidebar. Lower first.
  Leave gaps (10, 20, 30) so you can slot something in later without
  renumbering everything.
- **Parent category** — optional. Filtering a parent also shows the products in
  its children.

Deleting a category never deletes products. They stay in the catalogue and
become "Uncategorised" until you file them somewhere else.

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
  under it: "Motor", "Dimensions", "Certification". Leave it blank on every
  row and you get one plain table instead.
- **Name** — e.g. *Max torque*
- **Value** — e.g. *70 Nm*

Both name and value must be filled in or the row is ignored, so you can leave a
blank row lying around without it appearing on the site.

Specifications are searchable. Someone typing "CAT III" into the search box
finds the meter whose specification says CAT III, even though those words
appear nowhere in its name or description.

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

### Price

- **Regular price** — required.
- **Sale price** — leave blank if the item is not on sale. When filled in, the
  catalogue shows the sale price with the regular one struck through and a
  "Sale" badge on the card. It must be lower than the regular price.

### Availability

| Status | Shows as |
|---|---|
| In stock | green |
| Low stock | amber |
| Out of stock | red |
| Pre-order | blue |
| Discontinued | grey |

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

- **Site name** and **tagline** — header, footer and page titles
- **Currency code and symbol** — the symbol goes in front of every price
- **Products per page** — 4 to 60
- **Contact email and phone** — optional. Fill either in and product pages
  gain a line telling visitors how to enquire. Leave both blank and the line
  disappears.

---

## Your password

**Password** changes it. You need your current one, and the new one must be at
least 10 characters. A short phrase you can actually remember beats a
scrambled word you will end up writing down.

If you are locked out after too many wrong attempts, wait fifteen minutes —
the lockout clears itself.

---

## What visitors see

- **Catalogue** — grid of products, with a sidebar to filter by type,
  availability, brand and price range, plus sorting. On a phone the sidebar
  collapses into a **Filters** button.
- **Search** — matches names, SKUs, brands, descriptions *and* specification
  values.
- **Product page** — image gallery, price, availability, description, the
  specification table grouped into sections, and other items from the same
  category underneath.

Everything reflows for phones and tablets; there is no separate mobile site to
maintain.

---

## Housekeeping

**Removing the sample products.** Once your own products are in, delete the
demo ones from **Products** (or ask me and I will send you the two-line SQL to
clear them in one go). You can also delete the files in
`public/uploads/products/sample/`.

**Backups.** The database holds the text; `public/uploads/` holds the images.
A backup needs both — a database dump on its own restores products with broken
image links.
