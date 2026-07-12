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

The endpoint accepts `POST` requests with JSON or `application/x-www-form-urlencoded`, validates the data, and returns a JSON response with `ok`, `message`, and, if needed, `errors`.

## Menu Content

Each meal has its own page in `meals/` and its associated image in `assets/meals/`.  
Nutrition details, ingredients, allergens, and heating instructions are managed from `data.js`.

## Notes

- The site is intended to be served through PHP so routes and the contact form work correctly.
- `router.php` blocks sensitive files such as `.env` and `package.json`.
- If you add or change meals, update `data.js` and the relevant `meals/` HTML files.
