<?php
/**
 * Which supplied photograph belongs to which product.
 *
 * The files arrived named `AdobeStock_1222409314.jpeg` and so on - stock
 * library ids, with nothing in the filename to match on. So every one of these
 * pairings was made by looking at the picture. They are written down here,
 * rather than done once by hand in the admin panel, so that the decision is
 * reviewable, correctable, and repeatable if the catalogue is ever rebuilt.
 *
 * Two of them are worth reading twice, because they are the ones a careless
 * eye gets wrong:
 *
 * - AdobeStock_2066460810 is LONGKONG, not longan. Pale yellow, smooth, oval,
 *   packed in a dense cluster on a woody stem.
 * - AdobeStock_292177061 is LONGAN. Tan, lightly speckled, round, cut open to
 *   a translucent white flesh and a shiny black seed.
 *
 * Both fruits are in the catalogue as separate products, so getting this the
 * wrong way round would have put the wrong picture on a trade listing AND left
 * Longkong on a placeholder.
 *
 * Format: 'filename' => 'product-slug'. Two files may share a slug; the first
 * becomes the main image and the rest become extra gallery shots.
 */

return [
    // --- Fresh fruit --------------------------------------------------------
    'AdobeStock_1533288857.jpeg' => 'sugar-apples',
    'AdobeStock_182851283.jpeg'  => 'roselle',
    'AdobeStock_1971242311.jpeg' => 'papayas',
    'AdobeStock_2018546664.jpeg' => 'durian',
    'AdobeStock_2051047211.jpeg' => 'jackfruit',
    'AdobeStock_2066460810.jpeg' => 'longkong',      // see note above
    'AdobeStock_292177061.jpeg'  => 'longan',        // see note above
    'AdobeStock_2077844503.jpeg' => 'lychees',
    'AdobeStock_2085565109.jpeg' => 'limes',
    'AdobeStock_210168359.jpeg'  => 'coconuts',
    'AdobeStock_2175770831.jpeg' => 'mangosteen',
    'AdobeStock_267226577.jpeg'  => 'rambutan',
    'AdobeStock_286110454.jpeg'  => 'strawberries',
    'AdobeStock_29221462.jpeg'   => 'pineapples',
    'AdobeStock_486895338.jpeg'  => 'sweet-tamarind',
    'AdobeStock_530007120.jpeg'  => 'bananas',
    'AdobeStock_638395988.jpeg'  => 'pomelos',
    'AdobeStock_78711493.jpeg'   => 'mangoes',

    // --- Vegetables, herbs and spices ---------------------------------------
    'AdobeStock_1222409314.jpeg' => 'yardlong-beans',
    'AdobeStock_1977512839.jpeg' => 'turmeric',
    'AdobeStock_1985448377.jpeg' => 'tomatoes',
    'AdobeStock_2161876048.jpeg' => 'chili-peppers',
    'AdobeStock_268054738.jpeg'  => 'asparagus',
    'AdobeStock_285954722.jpeg'  => 'baby-corn',
    'AdobeStock_300915220.jpeg'  => 'galangal',
    'AdobeStock_365411128.jpeg'  => 'okra',
    'AdobeStock_414067931.jpeg'  => 'lemongrass',
    'AdobeStock_414068257.jpeg'  => 'lemongrass',    // second view
    'AdobeStock_440989972.jpeg'  => 'banana-leaves',
    'AdobeStock_535268562.jpeg'  => 'ginger',
    'AdobeStock_551261540.jpeg'  => 'amaranth',
    'AdobeStock_555244854.jpeg'  => 'carrots',
    'AdobeStock_88391513.jpeg'   => 'shallots',
    'AdobeStock_9839157.jpeg'    => 'pumpkins',

    // AdobeStock_92489950.jpeg is a basket of mixed Thai aromatics - galangal,
    // lemongrass, lime leaves, chillies, garlic, shallots. It is not any single
    // product, so it is deliberately NOT assigned. Putting it on one of those
    // six would show a buyer five things they did not click on.
];
