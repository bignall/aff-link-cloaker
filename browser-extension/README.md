# Affiliate Link Cloaker – browser extension

Sends the JVZoo products you're approved to promote to your WordPress site as cloaked links, while you're signed in to JVZoo.

JVZoo's API is vendor-only: it can't list the products you promote or give you your affiliate links. Your approved products are only visible on jvzoo.com when you're signed in, so this extension reads them from the page in your own browser and sends them to the [Affiliate Link Cloaker](../readme.txt) plugin (0.3.0 or newer).

## Install

1. Download `aff-link-cloaker-extension-<version>.zip` from the [releases](https://github.com/bignall/aff-link-cloaker/releases) and unzip it.
2. In Chrome (or Edge, Brave, any Chromium browser), open `chrome://extensions`, turn on **Developer mode**, click **Load unpacked** and choose the unzipped folder.
3. Pin the extension to the toolbar.

## Connect it to WordPress

1. In WordPress, go to **Users → Profile → Application Passwords**, enter a name such as "Link Cloaker extension" and click **Add New Application Password**. Copy the password it shows. Application Passwords need an HTTPS site.
2. Open the extension's **Settings** (right-click the icon → Options) and enter your site address, WordPress user name and the Application Password.
3. Click **Save & test connection**. Chrome asks to let the extension access your site; allow it. You should see "Connected to … as …".

The password is stored only in this browser (`chrome.storage.local`). You can revoke it any time from your WordPress profile.

## Use it

1. Sign in to JVZoo and open **Affiliates → Approved Products**.
2. Click the extension, then **Scan this page**. Repeat on each page of the list; products collect in the extension until you send them.
3. Untick anything you don't want, fix names if needed, and click **Create cloaked links**.
4. Each result shows its cloaked URL with a Copy button. Products you already have are reported as "already there", never duplicated.

## How it finds your links

On the Approved Products page and on a product's Affiliate Information page, it reads JVZoo's own "Get Link" boxes, which already hold each product's name and your affiliate link.

If a page doesn't have those, it falls back to:

- Any of your affiliate links on the page (`https://www.jvzoo.com/c/<affiliate ID>/<product ID>`, or `jvz1.com`–`jvz9.com`), in links, text boxes, copy buttons or text.
- Products linked to their Affiliate Information page, for when links only appear after clicking "Get Link". The link is built from the product ID and your affiliate ID, which is taken from Settings or from your links on the page.

Links under someone else's affiliate ID are ignored, including JVZoo's own (affiliate ID 1), which appear in its "Product of the day" banner and on product pages.

If a scan finds nothing on your Approved Products page, JVZoo's page probably differs from what the extension expects. Save the page (File → Save Page As…) and share it so the extension can be adjusted.

## Permissions

- **jvzoo.com:** to read the Approved Products page when you click **Scan this page**. Nothing runs on other sites.
- **Your WordPress site:** requested when you save Settings, only for the address you enter.
- **storage:** your settings, and the list of products you've scanned in this browser session.

## Development

`bin/build-extension-zip.sh` builds the zip; the release workflow attaches it to each GitHub release.
