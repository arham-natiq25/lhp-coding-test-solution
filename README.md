# Event Visuals Coding Test

This project is a Laravel 13 and Vue 3 event browsing app built on top of the Laravel starter kit. The original codebase already had a realistic seeded events dataset and a basic events listing. The work here turns that starter into a more complete product experience for browsing events, registering interest, and sending event emails.

The assignment asked for two distinct event browsing pages, local images, human-readable locations, timezone-aware event times, filtering, attendee registration, confirmation emails, and reminder emails. The app now covers that full flow end to end.

## What this app does

The app lets you browse events in three ways:

- `/events` gives you a practical table-style listing with filters.
- `/events-visual-1` is a visual discovery page built as an image-first card grid.
- `/events-visual-2` is a date-grouped agenda view for scanning events as a schedule.

Each event now includes:

- title and description
- readable location
- local event time with timezone awareness
- at least two locally served images
- venue, organizer, category, capacity, and price when available

Each event also has a detail page where users can register interest and join the attendee list.

## Main features

### Two distinct event visuals

The two visual pages were intentionally designed to feel different:

- `Event Visual 1` focuses on browsing and discovery through large imagery and card-based summaries.
- `Event Visual 2` focuses on time and structure through agenda-style groupings and date sections.

They are backed by the same event data endpoint, but present the information in different ways.

### Local event images

Events did not originally have image support. That has been added through the presentation layer using local assets stored in `public/images/events`.

Each event is assigned two or more images based on event type, which keeps the solution lightweight and avoids bloating the seeded dataset.

### Human-readable locations

The dataset stores latitude and longitude, not addresses. The app now resolves those coordinates into readable labels such as `Toronto, Canada` or `San Jose, United States` using a deterministic local anchor list in [EventLocationResolver.php](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/app/Support/EventLocationResolver.php).

This approach avoids any dependency on external reverse-geocoding services and stays predictable for testing.

### Timezone-aware event times

Event times are displayed in the event's resolved local timezone. The detail page, event listings, and attendee emails now all share the same presentation logic, so the time shown in the UI matches the time shown in email.

### Filtering

Filtering is available on the main listing and both visual pages.

Supported filters:

- status
- from date
- to date
- location text

Location filtering supports:

- city searches like `Toronto`
- combined queries like `Toronto Canada`
- minor typos like `Toronto Candana`

### Attendees and emails

Users can register interest for an event from the detail page.

That flow includes:

- attendee list storage
- duplicate prevention per event
- confirmation email on registration
- reminder emails 3 days before the event
- reminder emails 24 hours before the event

Reminder emails are sent through an Artisan command and scheduled to run hourly.

## Tech stack

- PHP 8.3
- Laravel 13
- Vue 3
- Inertia.js
- Tailwind CSS
- Pest
- Pint

## Important routes

- `/`
- `/events`
- `/events/data`
- `/events/{event}`
- `/events-visual-1`
- `/events-visual-2`

## Project structure

Some of the key files for this implementation are:

- [EventController.php](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/app/Http/Controllers/EventController.php)
- [EventPresenter.php](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/app/Support/EventPresenter.php)
- [EventLocationResolver.php](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/app/Support/EventLocationResolver.php)
- [SendEventReminders.php](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/app/Console/Commands/SendEventReminders.php)
- [web.php](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/routes/web.php)
- [Index.vue](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/resources/js/pages/Events/Index.vue)
- [VisualOne.vue](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/resources/js/pages/Events/VisualOne.vue)
- [VisualTwo.vue](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/resources/js/pages/Events/VisualTwo.vue)
- [Show.vue](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/resources/js/pages/Events/Show.vue)
- [EventListingTest.php](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/tests/Feature/EventListingTest.php)

## Setup

### 1. Install dependencies

```bash
composer install
npm install
```

### 2. Create environment file

```bash
copy .env.example .env
php artisan key:generate
```

If you are on macOS or Linux, use:

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure the database

Set your database connection in `.env`, then run:

```bash
php artisan migrate
```

### 4. Seed the data

The seeder is designed for a large dataset. For local development it is much more comfortable to seed a smaller amount first.

Examples:

```bash
SEED_ROWS=500 php artisan migrate:fresh --seed
SEED_ROWS=5000 php artisan migrate:fresh --seed
```

If you want to exercise the app against a heavier dataset, you can increase `SEED_ROWS`.

### 5. Run the app

In one terminal:

```bash
php artisan serve
```

In another terminal:

```bash
npm run dev
```

Open:

- `http://127.0.0.1:8000/events`
- `http://127.0.0.1:8000/events-visual-1`
- `http://127.0.0.1:8000/events-visual-2`

## Email setup

To test confirmation and reminder emails locally, configure your mailer in `.env`.

For quick local inspection, you can use:

```env
MAIL_MAILER=log
```

If you want to test real email delivery in a sandbox like Mailtrap, set the corresponding `MAIL_*` credentials in `.env`.

## Reminder emails

Reminder emails are handled by this command:

```bash
php artisan events:send-reminders
```

The app schedules that command hourly in [bootstrap/app.php](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/bootstrap/app.php).

Reminder windows:

- `3_days`: more than 24 hours away and up to 72 hours away
- `24_hours`: from now up to 24 hours away

Each reminder type is only sent once per attendee.

## Running tests

Run the feature suite used for this implementation:

```bash
php artisan test tests/Feature/EventListingTest.php
```

Run the full test suite:

```bash
php artisan test
```

Run formatting checks:

```bash
vendor/bin/pint --test
```

Run frontend checks:

```bash
npm run lint:check
npm run format:check
npm run types:check
```

## Manual testing guide

A detailed manual testing checklist is available in [TESTING_GUIDE.txt](c:/Users/Admin/Downloads/lhp-coding-test-main/lhp-coding-test-main/TESTING_GUIDE.txt).

That guide covers:

- listing pages
- visual pages
- filters
- event detail page
- attendee registration
- confirmation emails
- reminder emails
- empty states
- edge cases such as typo-tolerant location filtering

## Implementation notes

A few decisions were made deliberately to keep the solution practical with a seeded dataset:

- Presentation logic lives in one place so the table, visual pages, detail page, and emails all stay in sync.
- Location resolution is local and deterministic instead of relying on a third-party API.
- Images are derived by event type rather than stored as separate rows for every seeded event.
- Filtering is applied before pagination, and database indexes were added for common filter fields.
- Raw payload data is kept off the listing endpoint but is still available on the detail page for inspection.

## Tradeoffs

- The placeholder images are local and satisfy the assignment, but they are still placeholders.
- Location resolution is approximate because it maps to the nearest known anchor city.
- Emails currently send synchronously. That is fine for the test and covered by tests, but queueing would be a better production choice.

## Assignment coverage

This implementation covers the requested brief in `CODING_TEST.md`:

- two different event visual pages
- title, description, location, date/time, and image support
- local images with two or more images per event
- readable locations from latitude and longitude
- timezone-aware event display
- filtering by date and location
- Tailwind-based UI
- restrained animations
- attendee registration
- confirmation emails
- 3-day and 24-hour reminder emails

## Final note

The goal here was to keep the solution grounded in the existing codebase while still making it feel like a thoughtful product instead of a thin test submission. The app now has a clearer presentation layer, better event browsing, a working attendee flow, and enough test coverage to make future changes safer.
