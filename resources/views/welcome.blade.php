<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Uzumaki — Tarjetas digitales que conectan experiencias</title>
  <meta name="description"
    content="Crea tarjetas de lealtad digitales disponibles al instante en Apple Wallet y Google Wallet." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <style>
    :root {
      --color-background: #0774C3;
      --color-dark: #054686;
      --color-foreground: #FFFFFF;
      --color-cream: #CFD6D7;
      --color-cream-fg: #10161A;
      --color-accent: #64B3E3;
      --color-wa: #25D366;
      --color-wa-dark: #128C4A;
      --font-serif: 'Instrument Serif', 'DM Serif Display', Georgia, serif;
      --font-sans: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif;
    }

    *,
    *::before,
    *::after {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      font-family: var(--font-sans);
      background: var(--color-cream);
      color: var(--color-cream-fg);
      overflow-x: hidden;
    }

    @keyframes marquee-up {
      from {
        transform: translateY(0);
      }

      to {
        transform: translateY(-50%);
      }
    }

    @keyframes marquee-down {
      from {
        transform: translateY(-50%);
      }

      to {
        transform: translateY(0);
      }
    }

    @keyframes marquee-right {
      from {
        transform: translateX(-33.3334%);
      }

      to {
        transform: translateX(0);
      }
    }

    .marquee-up {
      animation: marquee-up 28s linear infinite;
    }

    .marquee-down {
      animation: marquee-down 22s linear infinite;
    }

    .reveal {
      opacity: 0;
      transform: translateY(24px);
      transition: opacity 700ms ease-out, transform 700ms ease-out;
    }

    .reveal.visible {
      opacity: 1;
      transform: translateY(0);
    }

    .serif {
      font-family: var(--font-serif);
    }

    /* ── NAV ── */
    .nav-pill {
      background: var(--color-cream);
      border-radius: 9999px;
      padding: 12px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: relative;
    }

    .nav-wordmark {
      font-family: var(--font-serif);
      font-size: 1.5rem;
      color: var(--color-cream-fg);
      letter-spacing: -0.03em;
      line-height: 1;
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
      user-select: none;
    }

    .nav-links {
      display: none;
      gap: 40px;
      align-items: center;
    }

    .nav-links a {
      font-size: 15px;
      font-weight: 500;
      color: var(--color-cream-fg);
      text-decoration: none;
      opacity: 0.7;
      transition: opacity 0.2s;
    }

    .nav-links a:hover {
      opacity: 1;
    }

    .nav-links a.active {
      opacity: 1;
      font-weight: 600;
    }

    .nav-cta {
      display: none;
      background: var(--color-cream-fg);
      color: var(--color-cream);
      border: none;
      border-radius: 9999px;
      padding: 10px 24px;
      font-family: var(--font-sans);
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
    }

    .nav-cta:hover {
      opacity: 0.85;
    }

    .mobile-toggle {
      background: none;
      border: none;
      cursor: pointer;
      color: var(--color-cream-fg);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .mobile-menu {
      display: none;
      flex-direction: column;
      gap: 4px;
      padding: 16px 24px 20px;
      background: var(--color-cream);
      border-radius: 24px;
      margin-top: 8px;
    }

    .mobile-menu.open {
      display: flex;
    }

    .mobile-menu a {
      font-size: 16px;
      font-weight: 500;
      color: var(--color-cream-fg);
      text-decoration: none;
      padding: 10px 0;
      border-bottom: 1px solid rgba(5, 70, 134, 0.08);
    }

    .mobile-menu a:last-of-type {
      border-bottom: none;
    }

    .mobile-menu-cta {
      margin-top: 12px;
      background: var(--color-cream-fg);
      color: var(--color-cream);
      border: none;
      border-radius: 9999px;
      padding: 13px 24px;
      font-family: var(--font-sans);
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
    }

    @media (min-width: 1024px) {
      .nav-links {
        display: flex;
      }

      .nav-cta {
        display: block;
      }

      .mobile-toggle {
        display: none;
      }

      .nav-wordmark {
        font-size: 1.75rem;
      }
    }

    /* ── HERO ── */
    .hero-card {
      background: var(--color-background);
      color: var(--color-foreground);
      border-radius: 28px;
      overflow: hidden;
    }

    @media (min-width: 1024px) {
      .hero-card {
        border-radius: 36px;
      }
    }

    .hero-grid {
      display: grid;
      grid-template-columns: 1fr;
    }

    @media (min-width: 1024px) {
      .hero-grid {
        grid-template-columns: minmax(480px, 540px) 1fr;
        min-height: 560px;
      }
    }

    .hero-copy {
      display: flex;
      align-items: center;
      padding: 48px 20px;
    }

    @media (min-width: 640px) {
      .hero-copy {
        padding: 56px 24px;
      }
    }

    @media (min-width: 1024px) {
      .hero-copy {
        padding: 80px 48px;
      }
    }

    .hero-copy-inner {
      max-width: 520px;
    }

    .trust-row {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 28px;
    }

    .avatar-stack {
      display: flex;
    }

    .avatar-ph {
      width: 36px;
      height: 36px;
      border-radius: 9999px;
      border: 2px solid var(--color-background);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 700;
      color: var(--color-foreground);
      margin-left: -8px;
      flex-shrink: 0;
    }

    .avatar-ph:first-child {
      margin-left: 0;
    }

    .trust-text {
      font-size: 13px;
      opacity: 0.85;
    }

    .hero-h1 {
      font-family: var(--font-serif);
      font-size: 44px;
      line-height: 1.05;
      letter-spacing: -0.03em;
      color: var(--color-foreground);
    }

    @media (min-width: 640px) {
      .hero-h1 {
        font-size: 60px;
      }
    }

    @media (min-width: 1024px) {
      .hero-h1 {
        font-size: 72px;
      }
    }

    @media (min-width: 1280px) {
      .hero-h1 {
        font-size: 80px;
        line-height: 0.98;
      }
    }

    .hero-sub {
      margin-top: 24px;
      font-size: 15px;
      line-height: 1.7;
      opacity: 0.85;
      max-width: 420px;
    }

    @media (min-width: 640px) {
      .hero-sub {
        font-size: 17px;
      }
    }

    .cta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 32px;
      align-items: center;
    }

    .btn-primary {
      display: flex;
      align-items: center;
      width: fit-content;
      background: var(--color-cream);
      color: var(--color-cream-fg);
      border: none;
      border-radius: 9999px;
      padding: 8px 8px 8px 24px;
      font-family: var(--font-sans);
      font-size: 15px;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      gap: 10px;
      transition: opacity 0.2s;
    }

    .btn-primary:hover {
      opacity: 0.92;
    }

    .btn-disc {
      width: 36px;
      height: 36px;
      border-radius: 9999px;
      background: var(--color-background);
      border: 1.5px solid oklch(0.98 0.005 90 / 0.25);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    /* ── MARQUEE ── */
    .marquee-col {
      position: relative;
      overflow: hidden;
      height: 360px;
    }

    @media (min-width: 640px) {
      .marquee-col {
        height: 440px;
      }
    }

    @media (min-width: 1024px) {
      .marquee-col {
        height: 660px;
      }
    }

    .marquee-inner {
      display: grid;
      grid-template-columns: 1fr;
      height: 100%;
      align-content: start;
      padding: 0 20px;
    }

    @media (min-width: 1024px) {
      .marquee-inner {
        padding: 0 20px 0 16px;
      }
    }

    .marquee-track {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .pass-card {
      position: relative;
      width: 100%;
      flex-shrink: 0;
      aspect-ratio: 17 / 20;
      overflow: hidden;
      border-radius: 24px;
    }

    @media (min-width: 1024px) {
      .pass-card {
        max-width: 600px;
        margin-right: auto;
      }
    }

    @media (max-width: 1023px) {
      .marquee-track {
        flex-direction: row;
        height: 100%;
        width: 1800px;
        gap: 0;
      }

      .pass-card {
        width: 300px;
        height: 100%;
        margin-right: -100px;
      }

      .marquee-down {
        animation-name: marquee-right;
        animation-duration: 9s;
      }
    }

    .pass-card-bg {
      position: absolute;
      inset: 0;
      border-radius: inherit;
    }

    .pass-card img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      border-radius: 24px;
    }

    .pass-badge {
      position: absolute;
      bottom: 12px;
      left: 12px;
      right: 12px;
      background: rgba(0, 0, 0, 0.35);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-radius: 9999px;
      padding: 8px 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 12px;
      color: white;
      font-weight: 500;
    }

    .pass-badge-left {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .lime-dot {
      width: 8px;
      height: 8px;
      border-radius: 9999px;
      background: var(--color-accent);
      flex-shrink: 0;
    }

    .marquee-fade-top,
    .marquee-fade-bottom {
      position: absolute;
      left: 0;
      right: 0;
      height: 112px;
      z-index: 10;
      pointer-events: none;
    }

    .marquee-fade-top {
      top: 0;
      background: linear-gradient(to bottom, var(--color-background), transparent);
    }

    .marquee-fade-bottom {
      bottom: 0;
      background: linear-gradient(to top, var(--color-background), transparent);
    }

    /* Pass designs */
    .pass-bg-1 {
      background: linear-gradient(135deg, #0a0f2e 0%, #1a1260 40%, #8B6914 100%);
    }

    .pass-bg-1::after {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse 60% 40% at 70% 20%, rgba(200, 160, 60, 0.3) 0%, transparent 70%), repeating-linear-gradient(0deg, transparent, transparent 28px, rgba(255, 255, 255, 0.03) 28px, rgba(255, 255, 255, 0.03) 29px);
    }

    .pass-bg-2 {
      background: linear-gradient(160deg, #0d0d0d 0%, #1a1008 60%, #4a3000 100%);
    }

    .pass-bg-2::after {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse 80% 30% at 50% 0%, rgba(220, 160, 40, 0.2) 0%, transparent 60%), linear-gradient(to bottom, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.02) 60px, transparent 61px);
    }

    .pass-bg-3 {
      background: linear-gradient(150deg, #1a2e1a 0%, #2d4a1e 50%, #e8ddb5 100%);
    }

    .pass-bg-4 {
      background: linear-gradient(135deg, #060c1a 0%, #0a2040 50%, #004d4d 100%);
    }

    .pass-bg-4::after {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse 70% 50% at 80% 50%, rgba(0, 220, 200, 0.2) 0%, transparent 70%);
    }

    .pass-bg-5 {
      background: linear-gradient(145deg, #2a0a10 0%, #4a1020 50%, #f0c0c0 100%);
    }

    .pass-bg-5::after {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse 80% 60% at 50% 80%, rgba(240, 192, 192, 0.3) 0%, transparent 60%);
    }

    .pass-bg-6 {
      background: linear-gradient(135deg, #3d1a10 0%, #c04a20 50%, #ffffff 100%);
    }

    .pass-label {
      position: absolute;
      top: 18px;
      left: 18px;
      font-size: 9px;
      letter-spacing: 0.15em;
      text-transform: uppercase;
      color: rgba(255, 255, 255, 0.5);
      font-family: var(--font-sans);
      font-weight: 500;
    }

    .pass-big-num {
      position: absolute;
      bottom: 52px;
      left: 18px;
      font-family: var(--font-serif);
      font-size: 48px;
      color: white;
      opacity: 0.9;
      line-height: 1;
    }

    .pass-barcode {
      position: absolute;
      bottom: 52px;
      left: 50%;
      transform: translateX(-50%);
      width: 70%;
      height: 20px;
      background: repeating-linear-gradient(90deg, white 0, white 2px, transparent 2px, transparent 5px);
      border-radius: 2px;
      opacity: 0.7;
    }

    /* ── SECTIONS ── */
    .section {
      padding: 80px 24px;
    }

    @media (min-width: 768px) {
      .section {
        padding: 96px 40px;
      }
    }

    .content-max {
      max-width: 1200px;
      margin: 0 auto;
    }

    .content-narrow {
      max-width: 760px;
      margin: 0 auto;
    }

    .eyebrow {
      font-size: 11px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--color-cream-fg);
      opacity: 0.55;
      font-weight: 600;
      margin-bottom: 16px;
    }

    .section-h2 {
      font-family: var(--font-serif);
      font-size: 36px;
      line-height: 1.1;
      letter-spacing: -0.02em;
      color: var(--color-cream-fg);
    }

    @media (min-width: 640px) {
      .section-h2 {
        font-size: 48px;
      }
    }

    /* ── LOGO STRIP ── */
    .logo-strip {
      padding: 48px 24px;
      text-align: center;
    }

    .logos-row {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 32px 48px;
      margin-top: 24px;
    }

    .logo-word {
      font-family: var(--font-serif);
      font-size: 20px;
      letter-spacing: -0.02em;
      color: var(--color-cream-fg);
      opacity: 0.4;
      transition: opacity 0.2s;
      cursor: default;
    }

    .logo-word:hover {
      opacity: 0.65;
    }

    /* ── PRODUCT SHOWCASE ── */
    .product-showcase {
      display: grid;
      grid-template-columns: 1fr;
      gap: 40px;
      margin-bottom: 64px;
    }

    @media (min-width: 1024px) {
      .product-showcase {
        grid-template-columns: 1fr 1fr;
        align-items: center;
        gap: 64px;
      }
    }

    .product-showcase-media {
      border-radius: 24px;
      overflow: hidden;
      aspect-ratio: 5 / 4;
    }

    .product-showcase-media img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .value-list {
      display: flex;
      flex-direction: column;
      gap: 28px;
      margin-top: 24px;
    }

    .value-item {
      display: flex;
      gap: 16px;
      align-items: flex-start;
    }

    .value-icon {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: var(--color-background);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .value-title {
      font-family: var(--font-serif);
      font-size: 20px;
      color: var(--color-cream-fg);
      margin-bottom: 4px;
      letter-spacing: -0.02em;
    }

    .value-desc {
      font-size: 14px;
      line-height: 1.7;
      color: var(--color-cream-fg);
      opacity: 0.7;
    }

    /* ── FEATURE CARDS ── */
    .feature-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
    }

    @media (min-width: 768px) {
      .feature-grid {
        grid-template-columns: repeat(3, 1fr);
      }
    }

    .feature-card {
      background: var(--color-background);
      color: var(--color-foreground);
      border-radius: 24px;
      padding: 32px;
    }

    .feature-icon {
      width: 44px;
      height: 44px;
      background: var(--color-dark);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 20px;
    }

    .feature-title {
      font-family: var(--font-serif);
      font-size: 22px;
      margin-bottom: 10px;
      letter-spacing: -0.02em;
    }

    .feature-desc {
      font-size: 14px;
      line-height: 1.7;
      opacity: 0.75;
    }

    /* Push highlight */
    .push-highlight {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(100, 179, 227, 0.15);
      border: 1px solid rgba(100, 179, 227, 0.3);
      border-radius: 9999px;
      padding: 4px 12px;
      font-size: 11px;
      font-weight: 600;
      color: var(--color-accent);
      margin-top: 14px;
      letter-spacing: 0.04em;
    }

    /* CTA de lealtad */
    .event-cta-card {
      background: var(--color-background);
      color: var(--color-foreground);
      border-radius: 24px;
      padding: 40px 32px;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      gap: 20px;
    }

    @media (min-width: 768px) {
      .event-cta-card {
        flex-direction: row;
        align-items: center;
        justify-content: space-between;
        gap: 32px;
      }

      .event-cta-card .wa-btn {
        flex-shrink: 0;
      }
    }

    .wa-btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: var(--color-wa);
      color: white;
      border: none;
      border-radius: 9999px;
      padding: 13px 28px;
      font-family: var(--font-sans);
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s;
      text-decoration: none;
    }

    .wa-btn:hover {
      background: var(--color-wa-dark);
    }

    .wa-btn svg {
      width: 20px;
      height: 20px;
      fill: white;
      flex-shrink: 0;
    }

    /* ── HOW IT WORKS ── */
    .hiw-panel {
      background: var(--color-background);
      border-radius: 36px;
      padding: 64px 40px;
      display: grid;
      grid-template-columns: 1fr;
      gap: 48px;
      overflow: visible;
    }

    @media (min-width: 1024px) {
      .hiw-panel {
        grid-template-columns: 1fr 1fr;
        align-items: center;
        padding: 80px 64px;
      }
    }

    .hiw-steps {
      display: flex;
      flex-direction: column;
      gap: 40px;
    }

    .hiw-step {
      display: flex;
      gap: 20px;
      align-items: flex-start;
    }

    .hiw-num {
      font-family: var(--font-serif);
      font-size: 48px;
      line-height: 1;
      color: var(--color-foreground);
      opacity: 0.35;
      flex-shrink: 0;
      width: 56px;
    }

    .hiw-step-title {
      font-family: var(--font-serif);
      font-size: 24px;
      color: var(--color-foreground);
      margin-bottom: 6px;
      letter-spacing: -0.02em;
    }

    .hiw-step-desc {
      font-size: 14px;
      line-height: 1.7;
      color: var(--color-foreground);
      opacity: 0.7;
    }

    .workspace-img {
      border-radius: 24px;
      overflow: visible;
      position: relative;
      background: transparent;
      aspect-ratio: 1672 / 941;
      width: 100%;
      z-index: 2;
    }

    .workspace-img img {
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      transform: scale(1.15);
      position: relative;
      z-index: 2;
    }

    /* ── STATS ── */
    .stats-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 40px;
      text-align: center;
    }

    @media (min-width: 768px) {
      .stats-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    .stat-num {
      font-family: var(--font-serif);
      font-size: 52px;
      letter-spacing: -0.04em;
      color: var(--color-cream-fg);
      line-height: 1;
    }

    @media (min-width: 768px) {
      .stat-num {
        font-size: 64px;
      }
    }

    .stat-label {
      font-size: 13px;
      color: var(--color-cream-fg);
      opacity: 0.6;
      margin-top: 6px;
      font-weight: 500;
    }

    /* ── TESTIMONIALS ── */
    .testimonial-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
      margin-top: 56px;
    }

    @media (min-width: 768px) {
      .testimonial-grid {
        grid-template-columns: 1fr 1fr;
      }
    }

    .testimonial-card {
      background: var(--color-background);
      color: var(--color-foreground);
      border-radius: 24px;
      padding: 36px 32px;
    }

    .quote-mark {
      font-family: var(--font-serif);
      font-size: 64px;
      line-height: 0.6;
      opacity: 0.25;
      margin-bottom: 16px;
      display: block;
    }

    .quote-text {
      font-size: 16px;
      line-height: 1.75;
      opacity: 0.9;
      margin-bottom: 28px;
      font-style: italic;
    }

    .quote-author {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .author-avatar {
      width: 44px;
      height: 44px;
      border-radius: 9999px;
      overflow: hidden;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 16px;
    }

    .author-name {
      font-weight: 600;
      font-size: 14px;
    }

    .author-role {
      font-size: 12px;
      opacity: 0.6;
      margin-top: 2px;
    }

    /* ── PRICING ── */
    .pricing-grid {
      display: grid;
      grid-template-columns: 1fr;
      gap: 20px;
      margin-top: 56px;
      align-items: start;
    }

    @media (min-width: 768px) {
      .pricing-grid {
        grid-template-columns: repeat(3, 1fr);
        align-items: stretch;
      }
    }

    .pricing-card {
      border-radius: 24px;
      padding: 36px 28px;
      border: 1px solid rgba(5, 70, 134, 0.15);
      background: var(--color-cream);
      position: relative;
    }

    .pricing-card.featured {
      background: var(--color-background);
      color: var(--color-foreground);
      border-color: transparent;
    }

    .popular-pill {
      display: inline-block;
      background: var(--color-accent);
      color: var(--color-dark);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      border-radius: 9999px;
      padding: 4px 12px;
      margin-bottom: 20px;
    }

    .plan-name {
      font-family: var(--font-serif);
      font-size: 24px;
      letter-spacing: -0.02em;
      margin-bottom: 6px;
    }

    .plan-desc {
      font-size: 13px;
      opacity: 0.6;
      margin-bottom: 20px;
    }

    .plan-price {
      font-family: var(--font-serif);
      font-size: 56px;
      letter-spacing: -0.04em;
      line-height: 1;
      margin-bottom: 4px;
    }

    .plan-per {
      font-size: 13px;
      opacity: 0.5;
      margin-bottom: 28px;
    }

    .plan-features {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 32px;
    }

    .plan-features li {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
      opacity: 0.85;
    }

    .check-icon {
      width: 18px;
      height: 18px;
      border-radius: 9999px;
      background: var(--color-accent);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .pricing-card.featured .check-icon {
      background: oklch(0.98 0.005 90 / 0.15);
    }

    .plan-btn {
      width: 100%;
      padding: 13px 24px;
      border-radius: 9999px;
      font-family: var(--font-sans);
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.2s;
      border: 1.5px solid rgba(5, 70, 134, 0.2);
      background: transparent;
      color: var(--color-cream-fg);
    }

    .pricing-card.featured .plan-btn {
      background: var(--color-cream);
      color: var(--color-cream-fg);
      border-color: transparent;
    }

    .plan-btn:hover {
      opacity: 0.8;
    }

    .billing-toggle-wrap {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      margin-top: 36px;
      background: var(--color-cream);
      border: 1px solid rgba(5, 70, 134, 0.12);
      border-radius: 9999px;
      padding: 4px;
      width: fit-content;
      margin-left: auto;
      margin-right: auto;
      box-shadow: 0 1px 4px rgba(5, 70, 134, 0.07);
    }

    .billing-btn {
      background: transparent;
      border: none;
      border-radius: 9999px;
      padding: 9px 22px;
      font-family: var(--font-sans);
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      color: var(--color-cream-fg);
      opacity: 0.5;
      transition: all 0.25s;
      display: flex;
      align-items: center;
      gap: 8px;
      white-space: nowrap;
    }

    .billing-btn.active {
      background: var(--color-cream-fg);
      color: var(--color-cream);
      opacity: 1;
    }

    .billing-btn.active .save-badge {
      background: var(--color-accent);
      color: var(--color-dark);
    }

    .save-badge {
      background: rgba(5, 70, 134, 0.1);
      color: var(--color-cream-fg);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      border-radius: 9999px;
      padding: 3px 9px;
      transition: all 0.25s;
    }

    /* ── ACCORDION ── */
    .accordion {
      display: flex;
      flex-direction: column;
      gap: 0;
      margin-top: 48px;
    }

    .accordion-item {
      border-bottom: 1px solid rgba(5, 70, 134, 0.12);
    }

    .accordion-trigger {
      width: 100%;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 20px 0;
      background: none;
      border: none;
      cursor: pointer;
      font-family: var(--font-sans);
      font-size: 16px;
      font-weight: 600;
      color: var(--color-cream-fg);
      text-align: left;
      gap: 16px;
    }

    .accordion-arrow {
      width: 24px;
      height: 24px;
      border-radius: 9999px;
      border: 1.5px solid rgba(5, 70, 134, 0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: transform 0.3s;
      color: var(--color-cream-fg);
    }

    .accordion-item.open .accordion-arrow {
      transform: rotate(180deg);
    }

    .accordion-body {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.35s ease;
    }

    .accordion-item.open .accordion-body {
      max-height: 200px;
    }

    .accordion-content {
      padding: 0 0 20px;
      font-size: 14px;
      line-height: 1.75;
      color: var(--color-cream-fg);
      opacity: 0.7;
    }

    /* ── CONTACTO WHATSAPP ── */
    .contact-section {
      padding: 80px 24px;
    }

    .contact-panel {
      background: var(--color-background);
      border-radius: 36px;
      padding: 72px 40px;
      display: grid;
      grid-template-columns: 1fr;
      gap: 48px;
      align-items: center;
    }

    @media (min-width: 1024px) {
      .contact-panel {
        grid-template-columns: 1fr 1fr;
        padding: 80px 64px;
      }
    }

    .contact-left h2 {
      font-family: var(--font-serif);
      font-size: 40px;
      line-height: 1.1;
      letter-spacing: -0.03em;
      color: var(--color-foreground);
      margin-bottom: 16px;
    }

    @media (min-width: 640px) {
      .contact-left h2 {
        font-size: 48px;
      }
    }

    .contact-left p {
      font-size: 15px;
      line-height: 1.75;
      color: var(--color-foreground);
      opacity: 0.7;
      margin-bottom: 32px;
      max-width: 420px;
    }

    .wa-big-btn {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      background: var(--color-wa);
      color: white;
      border: none;
      border-radius: 9999px;
      padding: 16px 32px;
      font-family: var(--font-sans);
      font-size: 17px;
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s;
      text-decoration: none;
    }

    .wa-big-btn:hover {
      background: var(--color-wa-dark);
    }

    .wa-big-btn svg {
      width: 24px;
      height: 24px;
      fill: white;
      flex-shrink: 0;
    }

    .contact-right {
      display: flex;
      flex-direction: column;
      gap: 20px;
    }

    .contact-card {
      background: var(--color-dark);
      border-radius: 20px;
      padding: 24px 28px;
      display: flex;
      align-items: flex-start;
      gap: 16px;
    }

    .contact-card-icon {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: var(--color-dark);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .contact-card-title {
      font-weight: 600;
      font-size: 15px;
      color: var(--color-foreground);
      margin-bottom: 4px;
    }

    .contact-card-desc {
      font-size: 13px;
      color: var(--color-foreground);
      opacity: 0.65;
      line-height: 1.6;
    }

    /* ── FOOTER ── */
    footer {
      padding: 64px 24px;
      border-top: 1px solid rgba(5, 70, 134, 0.12);
    }

    .footer-inner {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr;
      gap: 48px;
    }

    @media (min-width: 768px) {
      .footer-inner {
        grid-template-columns: 1fr auto;
        align-items: start;
      }
    }

    .footer-brand {
      font-family: var(--font-serif);
      font-size: 1.5rem;
      letter-spacing: -0.03em;
      color: var(--color-cream-fg);
      margin-bottom: 8px;
    }

    .footer-copy {
      font-size: 13px;
      color: var(--color-cream-fg);
      opacity: 0.45;
    }

    .footer-links {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 32px 48px;
    }

    @media (min-width: 640px) {
      .footer-links {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    .footer-col-title {
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--color-cream-fg);
      margin-bottom: 14px;
    }

    .footer-col a {
      display: block;
      font-size: 13px;
      color: var(--color-cream-fg);
      opacity: 0.6;
      text-decoration: none;
      margin-bottom: 8px;
      transition: opacity 0.2s;
    }

    .footer-col a:hover {
      opacity: 1;
    }

    .page-wrapper {
      max-width: 1560px;
      margin: 0 auto;
      padding: 16px;
    }

    @media (min-width: 640px) {
      .page-wrapper {
        padding: 20px 24px;
      }
    }

    @media (min-width: 1024px) {
      .page-wrapper {
        padding: 24px 40px;
      }
    }

    /* WA Icon SVG */
    .wa-icon {
      width: 20px;
      height: 20px;
      fill: white;
    }
  </style>
</head>

<body>

  <!-- ══════════════════════════════════
     NAV
══════════════════════════════════ -->
  <div class="page-wrapper">
    <nav>
      <div class="nav-pill">
        <div class="nav-wordmark">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
            stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
            <path d="M12 3
           C6.8 3 3 6.8 3 12
           C3 17.2 6.8 21 12 21
           C17.2 21 21 17.2 21 12
           C21 8.1 18.2 5.5 14.7 5.5
           C11.4 5.5 9 7.8 9 10.7
           C9 13.2 10.8 15 13 15
           C14.8 15 16 13.8 16 12.3
           C16 11.1 15.2 10.3 14.2 10.3" />
          </svg>
          Uzumaki
        </div>
        <div class="nav-links">
          <a href="#" class="active">Inicio</a>
          <a href="#producto">Producto</a>
          <a href="#como-funciona">Cómo funciona</a>
          <a href="#precios">Precios</a>
          <a href="#contacto">Contacto</a>
        </div>
        <button class="nav-cta"
          onclick="window.open('https://wa.me/521XXXXXXXXXX?text=Hola 👋,%20me%20interesan%20las%20tarjetas%20de%20lealtad%20para%20mi%20negocio,%20me%20dar%C3%ADas%20m%C3%A1s%20informaci%C3%B3n%3F','_blank')">Comenzar
          gratis</button>
        <button class="mobile-toggle" onclick="toggleMobile()">
          <span id="menuIcon" style="display:flex;align-items:center;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
              stroke-linecap="round">
              <line x1="3" y1="6" x2="21" y2="6" />
              <line x1="3" y1="12" x2="21" y2="12" />
              <line x1="3" y1="18" x2="21" y2="18" />
            </svg>
          </span>
          <span id="closeIcon" style="display:none;align-items:center;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
              stroke-linecap="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </span>
        </button>
      </div>
      <div class="mobile-menu" id="mobileMenu">
        <a href="#">Inicio</a>
        <a href="#producto">Producto</a>
        <a href="#como-funciona">Cómo funciona</a>
        <a href="#precios">Precios</a>
        <a href="#contacto">Contacto</a>
        <button class="mobile-menu-cta">Comenzar gratis</button>
      </div>
    </nav>

    <!-- ══════════════════════════════════
       HERO CARD
  ══════════════════════════════════ -->
    <div class="hero-card" style="margin-top:12px;">
      <div class="hero-grid">
        <!-- Copia -->
        <div class="hero-copy">
          <div class="hero-copy-inner">
            <div class="trust-row">
              <div class="avatar-stack">
                <div class="avatar-ph" style="background:oklch(0.42 0.12 220);">JK</div>
                <div class="avatar-ph" style="background:oklch(0.38 0.10 300);">ML</div>
                <div class="avatar-ph" style="background:oklch(0.40 0.11 250);">RB</div>
              </div>
              <span class="trust-text">Empresa oaxaqueña hecha para oaxaqueños</span>
            </div>
            <h1 class="hero-h1">Tarjetas de lealtad que conectan experiencias.</h1>
            <p class="hero-sub">
              Crea tarjetas de lealtad con tu marca — disponibles
              al instante en Apple Wallet y Google Wallet.
            </p>
            <div class="cta-row">
              <a href="#contacto" class="btn-primary">
                Comenzar gratis
                <span class="btn-disc">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                    stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-foreground)">
                    <line x1="5" y1="12" x2="19" y2="12" />
                    <polyline points="12 5 19 12 12 19" />
                  </svg>
                </span>
              </a>
            </div>
          </div>
        </div>

        <!-- Marquee -->
        <div class="marquee-col">
          <div class="marquee-fade-top"></div>
          <div class="marquee-fade-bottom"></div>
          <div class="marquee-inner" style="padding-top:40px;">
            <div style="overflow:hidden;height:100%;">
              <div class="marquee-track marquee-down">
                <!-- Set 1 -->
                <div class="pass-card">
                  <img src="{{ asset('images/img1.png') }}" alt="Ejemplo de tarjeta de lealtad en Apple Wallet" loading="lazy">
                </div>
                <div class="pass-card">
                  <img src="{{ asset('images/img2.png') }}" alt="Ejemplo de tarjeta de lealtad en Apple Wallet" loading="lazy">
                </div>
                <div class="pass-card">
                  <img src="{{ asset('images/img3.png') }}" alt="Ejemplo de tarjeta de lealtad en Apple Wallet" loading="lazy">
                </div>
                <!-- Set 2 (duplicado para loop continuo) -->
                <div class="pass-card">
                  <img src="{{ asset('images/img1.png') }}" alt="Ejemplo de tarjeta de lealtad en Apple Wallet" loading="lazy">
                </div>
                <div class="pass-card">
                  <img src="{{ asset('images/img2.png') }}" alt="Ejemplo de tarjeta de lealtad en Apple Wallet" loading="lazy">
                </div>
                <div class="pass-card">
                  <img src="{{ asset('images/img3.png') }}" alt="Ejemplo de tarjeta de lealtad en Apple Wallet" loading="lazy">
                </div>
                <!-- Set 3 (duplicado para loop continuo) -->
                <div class="pass-card">
                  <img src="{{ asset('images/img1.png') }}" alt="Ejemplo de tarjeta de lealtad en Apple Wallet" loading="lazy">
                </div>
                <div class="pass-card">
                  <img src="{{ asset('images/img2.png') }}" alt="Ejemplo de tarjeta de lealtad en Apple Wallet" loading="lazy">
                </div>
                <div class="pass-card">
                  <img src="{{ asset('images/img3.png') }}" alt="Ejemplo de tarjeta de lealtad en Apple Wallet" loading="lazy">
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div><!-- /page-wrapper -->


  <!-- ══════════════════════════════════
     1. LOGO STRIP
══════════════════════════════════ -->
  <!-- <div class="logo-strip reveal">
  <p class="eyebrow">Usado por equipos en</p>
  <div class="logos-row">
    <span class="logo-word">wavelength</span>
    <span class="logo-word">arcus</span>
    <span class="logo-word">nomad</span>
    <span class="logo-word">circuit</span>
    <span class="logo-word">solstice</span>
    <span class="logo-word">meridian</span>
  </div>
</div> -->


  <!-- ══════════════════════════════════
     2. PRODUCTO (TABS)
══════════════════════════════════ -->
  <section id="producto" class="section">
    <div class="content-max">
      <p class="eyebrow" style="text-align:center;">Producto</p>
      <div class="product-showcase reveal">
        <div class="product-showcase-media">
          <img src="{{ asset('images/publicidad1.png') }}" alt="Cliente mostrando su tarjeta de lealtad digital en una cafetería"
            loading="lazy">
        </div>
        <div>
          <h2 class="section-h2" style="margin-bottom:8px;">Cada visita cuenta.<br>Cada cliente regresa.</h2>
          <div class="value-list">
            <div class="value-item">
              <div class="value-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-foreground)">
                  <polygon
                    points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
                </svg>
              </div>
              <div>
                <div class="value-title">Fidelización y recompensas</div>
                <p class="value-desc">Cada compra suma sellos visibles al instante en el wallet de tu cliente. Ve su
                  progreso, siente el avance y vuelve por la recompensa — sin apps ni tarjetas físicas que se
                  pierden.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-foreground)">
                  <path
                    d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" />
                </svg>
              </div>
              <div>
                <div class="value-title">Felicitaciones y comunicación express</div>
                <p class="value-desc">Aprovecha el cumpleaños de tu cliente para enviarle una felicitación
                  acompañada de un beneficio, descuento o recompensa — directo a la pantalla de bloqueo, en el
                  momento justo.</p>
              </div>
            </div>
            <div class="value-item">
              <div class="value-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-foreground)">
                  <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" />
                  <circle cx="12" cy="10" r="3" />
                </svg>
              </div>
              <div>
                <div class="value-title">Notificaciones por cercanía</div>
                <p class="value-desc">Cuando un cliente pasa cerca de tu negocio, su tarjeta le envía un recordatorio
                  de que estás ahí — el empujón perfecto para convertir un trayecto cualquiera en una visita
                  espontánea.</p>
              </div>
            </div>
          </div>
        </div>
      </div>




    </div>
  </section>


  <!-- ══════════════════════════════════
     3. CÓMO FUNCIONA
══════════════════════════════════ -->
  <section id="como-funciona" class="section" style="padding-top:0;">

    <div class="content-max">
      <div class="reveal" style="text-align:center;">
        <p class="eyebrow" style="text-align:center;">Cómo funciona</p>
        <h2 class="section-h2" style="text-align:center;margin-bottom:32px;">Diseña. Distribuye. Fideliza.</h2>
      </div>
      <div class="hiw-panel reveal">
        <div class="hiw-steps">
          <div class="hiw-step">
            <div class="hiw-num">01</div>
            <div>
              <div class="hiw-step-title">Diseña</div>
              <p class="hiw-step-desc">Crea tu tarjeta personalizada que represente tu marca.</p>
            </div>
          </div>
          <div class="hiw-step">
            <div class="hiw-num">02</div>
            <div>
              <div class="hiw-step-title">Distribuye</div>
              <p class="hiw-step-desc">Tus clientes escanean un QR y agregan la tarjeta a su billetera digital para
                siempre tenerla a la mano.</p>
            </div>
          </div>
          <div class="hiw-step">
            <div class="hiw-num">03</div>
            <div>
              <div class="hiw-step-title">Actualiza</div>
              <p class="hiw-step-desc">Envía mensajes de marketing a tus clientes, y avísales cuando andan cerca de
                tu negocio para mejorar tus ventas.</p>
            </div>
          </div>
        </div>
        <div class="workspace-img">
          <img src="{{ asset('images/ejemplo1.png') }}" alt="Tarjeta de lealtad guardada en Apple Wallet" loading="lazy">
        </div>
      </div>
    </div>
  </section>


  <!-- ══════════════════════════════════
     4. ESTADÍSTICAS
══════════════════════════════════ -->
  <section class="section">
    <div class="content-max reveal">
      <div class="stats-grid">
        <div>
          <div class="stat-num">500+</div>
          <div class="stat-label">Negocios activos</div>
        </div>
        <div>
          <div class="stat-num">2M+</div>
          <div class="stat-label">Pases emitidos</div>
        </div>
        <div>
          <div class="stat-num">99.97%</div>
          <div class="stat-label">Tasa de entrega exitosa</div>
        </div>
        <div>
          <div class="stat-num">4.8/5</div>
          <div class="stat-label">Calificación promedio</div>
        </div>
      </div>
    </div>
  </section>


  <!-- ══════════════════════════════════
     5. TESTIMONIOS
══════════════════════════════════ -->
  <section class="section" style="padding-top:0;">
    <div class="content-max">
      <div class="reveal">
        <h2 class="section-h2">Negocios que lo usan lo recomiendan.</h2>
      </div>
      <div class="testimonial-grid">
        <div class="testimonial-card reveal" style="transition-delay:0.1s;">
          <span class="quote-mark">"</span>
          <p class="quote-text">Cambiamos las tarjetas físicas de puntos por Passify y nuestras redenciones se
            triplicaron en el primer mes. Los clientes realmente las usan.</p>
          <div class="quote-author">
            <div class="author-avatar" style="background:oklch(0.38 0.12 230);">CM</div>
            <div>
              <div class="author-name">Carlos M.</div>
              <div class="author-role">Director, Cadena de cafeterías</div>
            </div>
          </div>
        </div>
        <div class="testimonial-card reveal" style="transition-delay:0.2s;">
          <span class="quote-mark">"</span>
          <p class="quote-text">Las notificaciones push sin app son un game changer. Mandamos una promo y en 20 minutos
            ya teníamos gente en el local. Nunca habíamos tenido esa velocidad de respuesta.</p>
          <div class="quote-author">
            <div class="author-avatar" style="background:oklch(0.36 0.08 260);">JO</div>
            <div>
              <div class="author-name">Jorge O.</div>
              <div class="author-role">Gerente de operaciones, Restaurante</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>





  <!-- ══════════════════════════════════
     6. PRECIOS (solo lealtad)
══════════════════════════════════ -->
  <section id="precios" class="section">
    <div class="content-max">
      <div class="reveal" style="text-align:center;">
        <p class="eyebrow" style="text-align:center;">Precios</p>
        <h2 class="section-h2" style="text-align:center;">Planes para tu programa de lealtad.</h2>
        <p style="font-size:14px;opacity:0.55;margin-top:12px;text-align:center;">¿Necesitas algo a la medida? <a
            href="#contacto" style="color:var(--color-cream-fg);font-weight:600;">Contáctanos por WhatsApp</a> y te
          damos una propuesta.</p>
      </div>
      <!-- Toggle mensual / anual -->
      <div class="billing-toggle-wrap reveal" style="transition-delay:0.05s;">
        <button class="billing-btn active" id="btn-mensual" onclick="setBilling('mensual')">Mensual</button>
        <button class="billing-btn" id="btn-anual" onclick="setBilling('anual')">Anual <span class="save-badge">Ahorra
            20%</span></button>
      </div>
      <div class="pricing-grid">
        <!-- ── Básico ── -->
        <div class="pricing-card reveal" style="transition-delay:0.1s;">
          <div class="plan-name">Básico</div>
          <p class="plan-desc">Para negocios que ya tienen clientes.</p>
          <div class="plan-price" data-mensual="$299" data-anual="$240">$299</div>
          <div class="plan-per">MXN / mes</div>
          <ul class="plan-features">
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Clientes ilimitados
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>1 diseño de tarjeta de lealtad
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Sucursales ilimitadas
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Notificaciones personalizadas
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Soporte prioritario
            </li>
          </ul>
          <button class="plan-btn">Comenzar</button>
        </div>
        <!-- ── Pro ── -->
        <div class="pricing-card featured reveal" style="transition-delay:0.2s;">
          <div class="popular-pill">Más popular</div>
          <div class="plan-name">Pro</div>
          <p class="plan-desc" style="opacity:0.55;">Para negocios en crecimiento.</p>
          <div class="plan-price" data-mensual="$449" data-anual="$360">$449</div>
          <div class="plan-per" style="opacity:0.5;">MXN / mes</div>
          <ul class="plan-features">
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white"
                  stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Clientes ilimitados
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white"
                  stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>3 diseños de tarjeta de lealtad
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white"
                  stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Sucursales ilimitadas
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white"
                  stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Notificaciones personalizadas
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white"
                  stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Soporte prioritario
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white"
                  stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Analítica avanzada
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white"
                  stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>API y webhooks
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white"
                  stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Marca 100% personalizada
            </li>
          </ul>
          <button class="plan-btn">Elegir Pro</button>
        </div>
        <!-- ── Empresarial ── -->
        <div class="pricing-card reveal" style="transition-delay:0.3s;">
          <div class="plan-name">Empresarial</div>
          <p class="plan-desc">Para múltiples marcas o franquicias.</p>
          <div class="plan-price" data-mensual="$599" data-anual="$480">$599</div>
          <div class="plan-per">MXN / mes</div>
          <ul class="plan-features">
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Clientes ilimitados
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>7 diseños de tarjeta de lealtad
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Sucursales ilimitadas
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Notificaciones personalizadas
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Soporte prioritario
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Analítica avanzada
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>API y webhooks
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Marca 100% personalizada
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Account manager dedicado
            </li>
            <li>
              <div class="check-icon"><svg width="10" height="10" viewBox="0 0 24 24" fill="none"
                  stroke="var(--color-cream-fg)" stroke-width="3" stroke-linecap="round">
                  <polyline points="20 6 9 17 4 12" />
                </svg></div>Onboarding personalizado
            </li>
          </ul>
          <button class="plan-btn">Hablar con ventas</button>
        </div>
      </div>
    </div>
  </section>


  <!-- ══════════════════════════════════
     7. CASOS DE USO
══════════════════════════════════ -->
  <section class="section" style="padding-top:0;">
    <div class="content-narrow">
      <div class="reveal">
        <h2 class="section-h2">Hecho para cada tipo de negocio.</h2>
      </div>
      <div class="accordion reveal" style="transition-delay:0.1s;">
        <div class="accordion-item">
          <button class="accordion-trigger" onclick="toggleAccordion(this)">Restaurantes y cafeterías<div
              class="accordion-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2.5">
                <polyline points="6 9 12 15 18 9" />
              </svg></div></button>
          <div class="accordion-body">
            <div class="accordion-content">Reemplaza las tarjetas de papel con una tarjeta digital que vive en el wallet
              de tu cliente. Acumula visitas automáticamente, envía una notificación push cuando están cerca y activa
              una recompensa especial al décimo café.</div>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-trigger" onclick="toggleAccordion(this)">Tiendas y boutiques<div
              class="accordion-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2.5">
                <polyline points="6 9 12 15 18 9" />
              </svg></div></button>
          <div class="accordion-body">
            <div class="accordion-content">Recompensa a tus clientes frecuentes con puntos acumulables por compra.
              Notifica a tus mejores clientes cuando llegan productos nuevos o hay una venta especial — directo a su
              pantalla de bloqueo, sin spam de correo.</div>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-trigger" onclick="toggleAccordion(this)">Salones de belleza y spas<div
              class="accordion-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2.5">
                <polyline points="6 9 12 15 18 9" />
              </svg></div></button>
          <div class="accordion-body">
            <div class="accordion-content">Emite una tarjeta de sellos digital por servicio. Al llegar al corte o
              tratamiento número diez, el pase se actualiza solo y avisa al cliente que ya tiene su recompensa
              disponible.</div>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-trigger" onclick="toggleAccordion(this)">Gimnasios y estudios de fitness<div
              class="accordion-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2.5">
                <polyline points="6 9 12 15 18 9" />
              </svg></div></button>
          <div class="accordion-body">
            <div class="accordion-content">Premia la asistencia constante con niveles de lealtad (Silver, Gold, VIP)
              que se actualizan solos. Tus clientes ven su progreso en tiempo real, directo en su wallet.</div>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-trigger" onclick="toggleAccordion(this)">Tiendas en línea y delivery<div
              class="accordion-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2.5">
                <polyline points="6 9 12 15 18 9" />
              </svg></div></button>
          <div class="accordion-body">
            <div class="accordion-content">Suma una tarjeta de lealtad digital al proceso de compra sin fricción.
              Cuando el cliente sube de nivel o gana una recompensa, su pase se actualiza automáticamente — sin
              reemitir nada.</div>
          </div>
        </div>
        <div class="accordion-item">
          <button class="accordion-trigger" onclick="toggleAccordion(this)">Pop-ups y promociones temporales<div
              class="accordion-arrow"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2.5">
                <polyline points="6 9 12 15 18 9" />
              </svg></div></button>
          <div class="accordion-body">
            <div class="accordion-content">Lanza un cupón en minutos con límite de usos y fecha de expiración. Activa
              una notificación push el último día de la promo para crear urgencia y disparar el tráfico a tu local.
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!-- ══════════════════════════════════
     8. CONTACTO WHATSAPP
══════════════════════════════════ -->
  <section id="contacto" class="contact-section">
    <div class="content-max">
      <div class="contact-panel reveal">
        <!-- Izquierda -->
        <div class="contact-left">
          <h2>¿Tienes dudas? Escríbenos al momento.</h2>
          <p>Nuestro equipo responde por WhatsApp en menos de una hora en horario hábil. Sin formularios, sin esperas.
          </p>
          <a class="wa-big-btn"
            href="https://wa.me/529512474143?text=Hola 👋,%20me%20interesan%20las%20tarjetas%20de%20lealtad%20para%20mi%20negocio,%20me%20dar%C3%ADas%20m%C3%A1s%20informaci%C3%B3n%3F"
            target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"
              style="width:24px;height:24px;fill:white;flex-shrink:0;">
              <path
                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z" />
            </svg>
            Iniciar conversación
          </a>
        </div>

        <!-- Derecha: tarjetas de info -->
        <div class="contact-right">
          <div class="contact-card">
            <div class="contact-card-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-foreground)">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
            </div>
            <div>
              <div class="contact-card-title">Respuesta rápida</div>
              <div class="contact-card-desc">Atención de lunes a sábado de 9 am a 7 pm (hora del centro de México).
                Respondemos en menos de 1 hora.</div>
            </div>
          </div>
          <div class="contact-card">
            <div class="contact-card-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-foreground)">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                <path d="M16 3.13a4 4 0 0 1 0 7.75" />
              </svg>
            </div>
            <div>
              <div class="contact-card-title">Asesoría sin compromiso</div>
              <div class="contact-card-desc">Te ayudamos a entender qué plan se adapta mejor a tu negocio. Sin
                presiones, sin ventas agresivas.</div>
            </div>
          </div>
          <div class="contact-card">
            <div class="contact-card-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round" style="color:var(--color-foreground)">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" />
              </svg>
            </div>
            <div>
              <div class="contact-card-title">Onboarding guiado</div>
              <div class="contact-card-desc">Si decides empezar, te acompañamos en la configuración inicial para que tu
                primera tarjeta esté lista en el día.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!-- ══════════════════════════════════
     FOOTER
══════════════════════════════════ -->
  <footer>
    <div class="footer-inner">
      <div>
        <div class="footer-brand">Uzumaki</div>
        <div class="footer-copy">© 2026 Uzumaki, Inc. Todos los derechos reservados.</div>
      </div>
      <div class="footer-links">
        <div class="footer-col">
          <div class="footer-col-title">Producto</div>
          <a href="#producto">Lealtad</a>
          <a href="#como-funciona">Cómo funciona</a>
          <a href="#precios">Precios</a>
          <a href="#">Novedades</a>
        </div>
        <div class="footer-col">
          <div class="footer-col-title">Casos de uso</div>
          <a href="#">Restaurantes</a>
          <a href="#">Retail</a>
          <a href="#">Salones y spas</a>
          <a href="#">Gimnasios</a>
        </div>
        <div class="footer-col">
          <div class="footer-col-title">Recursos</div>
          <a href="#">Documentación</a>
          <a href="#">API</a>
          <a href="#">Blog</a>
          <a href="#">Estado</a>
        </div>
        <div class="footer-col">
          <div class="footer-col-title">Legal</div>
          <a href="#">Privacidad</a>
          <a href="#">Términos</a>
          <a href="#">Seguridad</a>
        </div>
      </div>
    </div>
  </footer>

  <script>
    function toggleMobile() {
      const menu = document.getElementById('mobileMenu');
      const menuIcon = document.getElementById('menuIcon');
      const closeIcon = document.getElementById('closeIcon');
      const isOpen = menu.classList.contains('open');
      menu.classList.toggle('open');
      menuIcon.style.display = isOpen ? 'flex' : 'none';
      closeIcon.style.display = isOpen ? 'none' : 'flex';
    }

    function setBilling(mode) {
      document.getElementById('btn-mensual').classList.toggle('active', mode === 'mensual');
      document.getElementById('btn-anual').classList.toggle('active', mode === 'anual');
      document.querySelectorAll('.plan-price[data-mensual]').forEach(el => {
        el.textContent = el.dataset[mode];
      });
    }

    function toggleAccordion(btn) {
      const item = btn.closest('.accordion-item');
      const wasOpen = item.classList.contains('open');
      document.querySelectorAll('.accordion-item.open').forEach(i => i.classList.remove('open'));
      if (!wasOpen) item.classList.add('open');
    }

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

    document.addEventListener('click', (e) => {
      const nav = document.querySelector('nav');
      if (!nav.contains(e.target)) {
        const menu = document.getElementById('mobileMenu');
        if (menu.classList.contains('open')) toggleMobile();
      }
    });
  </script>
</body>

</html>