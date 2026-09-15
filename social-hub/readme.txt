=== Social Hub ===
Contributors: videosai
Tags: facebook, telegram, social sharing, open graph, auto post
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-publish new posts to a Facebook Page and Telegram, add share buttons, and control how shared links look.

== Description ==

Social Hub connects your site to the social networks you actually use, without a
third party service in the middle: your site talks straight to the Facebook
Graph API and the Telegram Bot API.

* Publishes a post to the connected networks the first time it is published, on
  a delayed cron event so saving stays fast.
* Facebook Pages through Graph API v26.0, as a link post or a photo post built
  from the featured image.
* Telegram channels and groups through a bot.
* Message template with `{title}`, `{excerpt}`, `{url}`, `{site}`, `{author}`,
  `{tags}`, `{categories}` and `{date}` placeholders.
* Per-post controls in the editor: custom message, "do not share", and a
  "Share now" button that reposts on demand.
* Share buttons for readers: Facebook, X, WhatsApp, Telegram, LinkedIn, email
  and copy link, in a filled or outline style, with or without labels.
* Open Graph and Twitter Card tags, skipped automatically when an SEO plugin
  already prints them.
* Activity log with the exact API error behind every failed attempt.
* Fully translated into Hebrew, and RTL friendly.

== Installation ==

1. Upload the `social-hub` folder to `/wp-content/plugins/`, or install the ZIP
   from Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Open the Social Hub menu and connect a network.

= Facebook =

Automatic publishing works with Pages, not personal profiles. Create an app on
developers.facebook.com, grant `pages_manage_posts`, `pages_read_engagement` and
`pages_show_list`, pick your Page in the Graph API Explorer token dropdown to get
a Page access token, extend it to a long-lived token in the Access Token
Debugger, then paste the Page ID and token into the Facebook tab.

= Telegram =

Create a bot with @BotFather, add it as an administrator of your channel, then
enter the bot token and `@channelname` in the Telegram tab.

== Frequently Asked Questions ==

= Will editing an old post republish it? =

No. Only the first transition to the published status triggers a share. You can
always repost deliberately with the "Share now" button.

= Can I place the share buttons myself? =

Yes. Set the position to "Only where I place them" and use the
`[social_hub_share]` shortcode or `social_hub_share_buttons()` in a template.

= Does it conflict with my SEO plugin? =

No. When Yoast, Rank Math, AIOSEO, SEOPress, The SEO Framework or Slim SEO is
active, Social Hub leaves the Open Graph tags to them unless you tell it not to.

= Where are the access tokens stored? =

In the plugin option, in your database. Anyone with database access can read
them, so treat them like passwords and revoke them if the site is compromised.

== Changelog ==

= 1.0.0 =
* First release: Facebook Page and Telegram publishing, share buttons, Open
  Graph tags, activity log and Hebrew translation.
