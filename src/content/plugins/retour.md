---
title: Redirects and broken links
plugin: retour
status: draft
---
When a page moves or is deleted, people following old links can end up on an error page. Redirects send them to the right place instead.

Redirects are managed in **Retour**, in the main menu on the left.

## Add a redirect

1. Go to **Retour** and choose **Redirects**.
2. Add a new redirect.
3. Enter the old address (the one that no longer works) and the new address you want to send people to.
4. Save.

## Find broken links

Retour keeps a list of addresses people tried to visit that don't exist. Check it from time to time. If an address appears often, add a redirect for it.

## Changing a page's address

If you change an entry's slug, its address changes. {% if autoRedirects %}Retour adds a redirect from the old address automatically, and you'll see it in the list of redirects.{% else %}Add a redirect from the old address to the new one.{% endif %}
