# Vue Bits components

Source: https://github.com/DavidHDev/vue-bits
Documentation: https://vue-bits.dev
Retrieved: 2026-10-07. Copyright David Haz; see LICENSE.md.

- SpotlightCard is adapted from src/content/Components/SpotlightCard/SpotlightCard.vue. Changes: olive/gold defaults, semantic content layering, keyboard focus within, reduced-motion support, and configurable padding via classes.
- FadeContent is adapted from src/content/Animations/FadeContent/FadeContent.vue. This project uses IntersectionObserver and the native Web Animations API instead of GSAP. It animates once, keeps content readable before observation, respects reduced motion, and cleans up on unmount. No dependency was added.

Keep motion on presentation cards and section introductions, not on data tables, dialogs, or critical form controls.
