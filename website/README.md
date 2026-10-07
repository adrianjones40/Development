# [Your Name] Web Development: PHP website

Responsive PHP + HTML/CSS website (no database, no framework). Requires PHP 8.0+.

## Pages
`index.php`, `services.php`, `monthly-support.php`, `case-studies.php`, `about.php`, `pricing.php`, `contact.php`

## Set up
1. Edit `includes/config.php`: your name, domain, `CONTACT_EMAIL`, `MAIL_FROM`, location and `BOOKING_URL` (Calendly / Cal.com link).
2. Add your photo at `assets/img/profile.jpg` and swap the placeholder in `about.php`.
3. Review the case studies in `case-studies.php` and add a "Result" line once you have verifiable outcomes.
4. Upload everything to your PHP host (Apache; `.htaccess` included). For Nginx, block `/includes/` in your server config.

## Test locally
```
php -S localhost:8000
```

## Notes
- The contact form uses PHP `mail()`. If mail doesn't arrive, ask your host for SMTP settings or use PHPMailer; use a `MAIL_FROM` address on your own domain.
- The form has CSRF protection and a honeypot field against spam.
- Colors and fonts are CSS variables at the top of `assets/css/style.css`.
