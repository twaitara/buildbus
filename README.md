# Bus & Truck Body Build — interactive blueprint

A login-gated, editable blueprint the client reviews and annotates before we build the
production system. This folder is a **separate project** from the 912 finance console.

## Files
- `blueprint.php` — the whole page (login + editable blueprint + save endpoint).
- `secret.sample.php` — template for the login credentials.
- `data/` — created automatically; holds the client's saved input (gitignored).

## Setup (once, on the PHP host)
1. Copy `secret.sample.php` to `secret.php`.
2. Set the `user` and `pass` in `secret.php` — these are the credentials you give the client.
3. Make sure `data/` is writable by PHP (it's created automatically on first save).
4. Open `blueprint.php` in a browser. Requires PHP 7.4+ (Hostinger/cPanel already has it).

## How the client uses it
Sign in → review each step → edit anything wrong → mark "Looks right / Needs a change" →
correct the bus & truck section lists → write any extra notes → press **Save my changes**.

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
