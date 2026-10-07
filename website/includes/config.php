<?php
// Site settings: edit these values before going live.
const SITE_NAME    = '[Your Name] Web Development';
const SITE_TAGLINE = 'Your Reliable PHP, Laravel & WordPress Development Partner';
const SITE_URL     = 'https://www.example.com';      // no trailing slash
const CONTACT_EMAIL = 'you@example.com';             // contact form messages are sent here
const MAIL_FROM     = 'no-reply@example.com';        // an address on your own domain (improves delivery)
const BOOKING_URL   = '';                            // Calendly / Cal.com link, e.g. https://calendly.com/yourname/30min
const LOCATION      = 'Hyderabad, India';
const CTA_TEXT      = 'Book a Free 30-Minute Consultation';

function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Main call-to-action link: external booking page if configured, otherwise the contact page.
function cta_url(): string { return BOOKING_URL !== '' ? BOOKING_URL : 'contact.php#book'; }
