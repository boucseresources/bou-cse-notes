# BOU CSE Notes — profile images, logo and YouTube focus links

This ZIP includes the previous mobile/profile/roles/speed update. Merge all contents into your EXISTING local project, replacing matching files; retain other project files and include `.github`. Commit and push to `main` yourself. Wait for the existing GitHub Actions deployment to turn green, then refresh https://in.aloskill.com.

No commit or deployment was performed. Private config, database, storage and uploaded photos are not packaged. The existing deployment preserves them. No additional migration is required if the previous mobile update already ran; otherwise its included username/profile migration runs automatically.

## Changes

- Dashboard profile card displays your uploaded photo. Without a photo it shows your avatar initials, not the BOU logo. Upload/change/remove a photo in Settings. The header/account menu and Settings retain your photo.
- Login, registration/recovery and public brand areas use the original BOU CSE Notes logo from the repository.
- Browser favicon is supplied as PNG plus multi-size ICO; mobile home-screen icon uses the same logo. The old generic SVG favicon is no longer linked. Browser icon caches may take an additional reload to clear.
- YouTube video links in rendered course materials and Markdown notes get an additional **Watch without distraction** link, opening `https://www.yout-ube.com/watch?v=VIDEO_ID` in a new tab. Valid timestamps are retained. The original link is retained.
- Plain YouTube links in teacher announcements, attached notes and anonymous text entries also get watch actions.
- Study Tools → **YouTube Focus Link** accepts watch, youtu.be, Shorts, live and embed links. It supplies focus/YouTube links and a Copy focus link button. No sign-in to an additional service or API credentials are required for link generation.
- Focus links are generated locally; video viewing opens the external yout-ube.com service. Actual playback, availability and the viewing experience depend on that service. Code snippets are left unchanged.
- Features & Changelog documents this release.

## Validation

Four automated URL parsing tests cover video forms, timestamp handling, invalid links, lookalike hosts and safe link markup. Browser integration checks the login logo, PNG/ICO/mobile icons, dashboard/header photo rendering, course focus action, focus tool and mobile layout. Prior backend changes are unchanged; the previous mobile suite passed PHP 8.2/SQLite and 43 session transport regressions. This update has not been deployed or tested on live Hostinger/MySQL.
