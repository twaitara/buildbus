# Bus & Truck Body Build — interactive blueprint

A login-gated, editable blueprint the client reviews and annotates before we build the
production system. This folder is a **separate project** from the 912 finance console.

## Files
- `index.php` — redirects the bare URL to `blueprint.php`.
- `blueprint.php` — the whole app (animated login + editable blueprint + save endpoint + admin logs).
- `secret.sample.php` — template for the login users.
- `data/` — created automatically; holds saved input + logs (gitignored).

## Users & roles
Defined in `secret.php` (a list of users). Each has `role`:
- `admin` — edits the blueprint AND views the activity logs.
- `editor` — edits the blueprint and saves; no log access.

Defaults: `tito` (admin) and `ann` (editor).

## Setup (once, on the PHP host)
1. Copy `secret.sample.php` to `secret.php`.
2. Set each user's `pass` (and `name`/`role`) in `secret.php` — these are the logins you hand out.
3. Make sure `data/` is writable by PHP (created automatically on first write).
4. Open the URL. Requires PHP 7.4+ (Hostinger/cPanel already has it).

## How it's used
Sign in → review each step → edit anything wrong → mark "Looks right / Needs a change" →
correct the bus & truck section lists → write any extra notes → press **Save my changes**.

## Admin logs
Admins see a **View logs** button in the header (or visit `blueprint.php?action=logs`):
- Activity table — every sign-in, sign-out, save and failed login, with time, who, and IP.
- Saved versions — every saved blueprint, newest first, expandable to the full JSON.
Backed by `data/activity.jsonl` and `data/blueprint_history.jsonl`.

## Where the input lands (so the developer can read it)
Every save writes the full current state to:

    data/blueprint_input.json

and appends a timestamped copy to `data/blueprint_history.jsonl` (nothing is ever lost).
To review the client's feedback, just open `blueprint_input.json` — it is plain, readable
JSON: `{ fields: {...}, busStages: [...], truckStages: [...] }`. If the page is hosted
remotely, pull that file down (git/FTP) and read it.

## Notes
- `secret.php` and `data/` are gitignored — never commit credentials or client input.
- Saving requires a valid login session + CSRF token; the endpoint rejects anything else.
