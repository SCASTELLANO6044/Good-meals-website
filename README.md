# Good Meals Website

Promotional website for **Good Meals**, a chef-prepared meal subscription service.  
The project combines a main landing page, individual meal pages, and a PHP endpoint for the contact form.

## Features

- Landing page with hero, plans, FAQ, and contact sections.
- Featured meal catalog loaded from `data.js`.
- Individual meal detail pages in `/meals`.
- Menu carousel with expanded image view.
- Contact form connected to `api/contact.php`.
- Entrance effects and animations that respect `prefers-reduced-motion`.

## Project Structure

- `index.html` - main page.
- `styles.css` - global styles.
- `app.js` - rendering, animations, and form logic.
- `captcha.js` - automatic Cloudflare Turnstile verification for the contact form.
- `data.js` - meal catalog and nutrition data.
- `meals/` - detail pages for each dish.
- `assets/` - images, logo, and visual assets.
- `api/contact.php` - contact form endpoint.
- `router.php` - PHP server router for local development.

## Requirements

- PHP 8 or newer.
- A modern browser.

No Node.js dependencies are required to run the site, although `package.json` includes convenience start scripts.

## Run Locally

1. Open a terminal in the project folder.
2. Run:

```bash
npm start
```

3. Open `http://localhost:4173`.

You can also use:

```bash
npm run serve
```

Both commands start PHP's built-in server using `router.php`.

## How It Works

- `index.html` loads `data.js` and `app.js`.
- `data.js` exposes the global `window.GOOD_MEALS` array.
- `app.js` renders the cards, fills the detail pages, and handles the form.
- `api/contact.php` validates submissions and responds with JSON.

## Contact Form

The form expects these fields:

- `name`
- `email`
- `phone`
- `message`
- `cf-turnstile-response` (a fresh Cloudflare Turnstile token)

The endpoint accepts `POST` requests with JSON or `application/x-www-form-urlencoded`, validates the data, and returns a JSON response with `ok`, `message`, and, if needed, `errors`.

### Automatic bot verification

The contact form uses Cloudflare Turnstile. Create a widget in the [Cloudflare dashboard](https://dash.cloudflare.com/?to=/:account/turnstile), select **Invisible** mode for verification without a checkbox, and allow each hostname serving the form (for example, `goodmeals.es`, `www.goodmeals.es`, and your hosting preview hostname, if used).

Add the widget's keys to the hosting environment or the root `.env` file:

```dotenv
TURNSTILE_SITE_KEY=your-public-site-key
TURNSTILE_SECRET_KEY=your-private-secret-key
```

Keep the secret key on the server. `GET /api/contact.php` returns only the public site key and uses `Cache-Control: no-store`. The browser obtains a new token on each submission. The server validates it with Cloudflare before sending either email, and checks the token's `contact` action and hostname against the request hostname (`HTTP_HOST`, falling back to `SERVER_NAME`). PHP needs cURL or HTTPS stream support (`allow_url_fopen` with OpenSSL) and outbound HTTPS access to `challenges.cloudflare.com`.

Missing keys, missing or invalid tokens, and verification service failures block submission. Configure both keys before deploying this change. The form includes a Turnstile privacy disclosure; also reference the [Turnstile Privacy Addendum](https://www.cloudflare.com/turnstile-privacy-policy/) in your site's privacy policy, as required for invisible widgets.

Cloudflare's invisible test site key `1x00000000000000000000BB` can exercise the browser widget. Its dummy server responses do not match this endpoint's production hostname/action checks, so use mocked Siteverify responses for successful server tests or a separate real widget registered to your development hostname. Never use test keys in production. See [Cloudflare's testing guide](https://developers.cloudflare.com/turnstile/troubleshooting/testing/).

The footer newsletter form currently has no submission endpoint or subscription storage. Turnstile protects the working contact endpoint; a newsletter backend would need its own server verification when implemented.

## Menu Content

Each meal has its own page in `meals/` and its associated image in `assets/meals/`.  
Nutrition details, ingredients, allergens, and heating instructions are managed from `data.js`.

## Notes

- The site is intended to be served through PHP so routes and the contact form work correctly.
- `router.php` blocks sensitive files such as `.env` and `package.json`.
- If you add or change meals, update `data.js` and the relevant `meals/` HTML files.
