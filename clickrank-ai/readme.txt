=== ClickRank - Ai SEO Automation ===
Contributors: clickrank
Tags: seo, ai, automation, SEO automation,wordpress SEP, title, meta description, taxonomy, categories, tags 
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 3.3.5
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Supercharge your WordPress SEO with ClickRank.ai. Automate title & meta descriptions, generate schema, optimize images, and more with the power of AI.

== Description ==

The **ClickRank.ai** plugin seamlessly integrates your WordPress site with the powerful ClickRank.ai SEO automation platform. Stop wasting time on manual SEO tasks and let our AI handle the heavy lifting, so you can focus on creating great content. Our platform analyzes your content and automatically generates and applies SEO best practices, helping you improve your search engine rankings and drive more organic traffic.

**Key Features:**

* **AI-Powered Title & Meta Optimization:** Automatically generate and apply SEO-friendly titles and meta descriptions for posts, pages, categories, tags, and custom taxonomies that are crafted to maximize click-through rates.
* **Complete WordPress Content Support:** Optimize all content types including posts, pages, categories, tags, custom post types, custom taxonomies, archive pages, and homepage.
* **Automatic Image SEO:** Generate descriptive alt text and title attributes for your images to improve accessibility and image search rankings.
* **Advanced Schema Markup:** Automatically generate and deploy structured data (JSON-LD) to help search engines understand your content and qualify for rich snippets.
* **Canonical URL Management:** Prevent duplicate content issues by automatically setting the optimal canonical URL for all your content types.
* **Automatic Link Titles:** Improve accessibility and SEO by automatically adding descriptive title attributes to links in your content.
* **Enhanced SEO Plugin Compatibility:** Full integration with Yoast SEO, RankMath, All In One SEO, and graceful fallbacks for other plugins.
* **Full Control & Revert:** You have full control over which automation modules are active. All changes can be reverted with a single click from the ClickRank.ai platform.
* **Secure & Transparent:** Communication is secured via your unique API key. All activities are logged within your WordPress admin for full transparency.

This plugin acts as a connector. A **ClickRank.ai account is required** to use the automation features. Get started for free at [clickrank.ai](https://clickrank.ai/)!

== External Service Disclosure ==

This plugin connects to the ClickRank.ai platform, an external third-party service, to provide its core SEO automation features. To function, this plugin must send data to and receive data from the ClickRank.ai API.

* **Service Provider:** ClickRank.ai
* **Service Website:** [https://clickrank.ai/](https://clickrank.ai/)
* **Terms of Service:** [https://clickrank.ai/terms-of-service/](https://clickrank.ai/terms-of-service/)
* **Privacy Policy:** [https://clickrank.ai/privacy-policy/](https://clickrank.ai/privacy-policy/)

**Data Sent to the Service:**

The following data is sent to the ClickRank.ai API endpoints (`https://app.clickrank.ai/api/v2/`):
* **API Key Activation:** When you save your API key, the plugin sends your unique API key and a WordPress-generated webhook URL to the service. This is a one-time action to securely link your website and allow our platform to send SEO updates back to you.
* **Manual Data Sync:** When the "Sync Data" button is pressed, your website's home URL and API key are sent to request the latest set of optimizations from the ClickRank.ai platform.
* **Content for Analysis:** For the service to generate SEO optimizations, your post and page content (including text, titles, and URLs) is accessed and processed by the ClickRank.ai platform.

This data transmission is essential for the plugin's features to work. All communication is performed over a secure (HTTPS) connection.

== Installation ==

1.  Upload the `clickrank-ai` folder to the `/wp-content/plugins/` directory, or install the ZIP file directly through the WordPress plugins screen.
2.  Activate the plugin. You will be redirected to the Activation page.
3.  Go to the **ClickRank.ai -> Activation** tab and enter your API Key from your ClickRank.ai dashboard.
4.  Click "Save & Activate Key".
5.  Navigate to the **Settings** tab to configure which automation modules you'd like to enable.
6.  That's it! The plugin will now sync with the ClickRank.ai platform.

== Frequently Asked Questions ==

= Do I need a ClickRank.ai account to use this plugin? =

Yes. This plugin connects your WordPress site to your ClickRank.ai account. The AI processing and optimization management happens on our platform. You can sign up at [clickrank.ai](https://clickrank.ai/).

= Will this work with my existing SEO plugin? =

Yes. Our plugin is designed to work alongside other popular SEO plugins. When an optimization is sent from ClickRank.ai, our plugin will intelligently override the specific setting (e.g., the meta description) from your existing SEO plugin to ensure the AI-generated content is displayed. Other settings from your SEO plugin remain unaffected.

= Is my data safe? =

Absolutely. The plugin communicates with our API over a secure (HTTPS) connection, and all requests are authenticated with your unique API key. We only access the data necessary to perform the SEO optimizations you've enabled.

= Can I undo a change made by the AI? =

Yes. All changes can be reverted directly from your ClickRank.ai dashboard. This gives you full control and peace of mind.

= What happens if I uninstall the plugin? =

The `uninstall.php` script will clean up all plugin data, including your saved API key, all module settings, and the custom database table used for logging. Your site will revert to its previous state.

== Screenshots ==

1.  The clean and informative Dashboard tab, providing an at-a-glance overview.
2.  The powerful Settings tab, where you can configure your API key and toggle individual automation modules.
3.  The detailed Logs tab, showing a transparent history of all webhook activity from the ClickRank.ai platform.

== Changelog ==

= 3.3.5 =
* Major Enhancement: Fixed duplicate meta tags that were appearing on pages - now only ONE of each tag appears!
* Bugfix: Schema markup no longer duplicates - removed conflicts with Yoast SEO, RankMath, and All in One SEO.
* Bugfix: Meta descriptions now appear only once per page - eliminated duplicates from SEO plugins and themes.
* Bugfix: Canonical URLs no longer duplicate - only one canonical tag per page ensuring proper search engine indexing.
* Enhancement: Added complete Open Graph tags for better Facebook and social media sharing (og:title, og:description, og:url, og:type).
* Enhancement: Added complete Twitter Card support for improved Twitter sharing (twitter:title, twitter:description, twitter:card).
* Enhancement: Improved compatibility with all major SEO plugins - automatically removes their meta tags and replaces with ClickRank optimizations.
* Enhancement: Better URL-based optimization system - works correctly with translated pages and pagination.
* Performance: Pages now load faster with smaller HTML output due to removal of duplicate meta tags.
* SEO: Cleaner meta tags mean better search engine understanding and improved rankings.

= 3.3.3 =
* Enhancement: Improved database upgrade system.
* Bugfix: Minor fixes to URL-based SEO data storage.

= 3.3.2 =
* Bugfix: Enhanced homepage title handling to write directly to page source without JavaScript dependency.
* Enhancement: Unified PHP-based solution for both homepage title and meta description processing.
* Enhancement: Improved output buffering system to replace existing HTML tags with optimized content.

= 3.3.1 =
* Bugfix: Fixed duplicate meta descriptions appearing on homepage when both SEO plugin filters and direct injection were active.
* Enhancement: Improved homepage meta description handling with reliable PHP output buffering solution.
* Enhancement: Added comprehensive meta description cleanup to ensure only one description tag appears per page.

= 3.3.0 =
* Major Enhancement: Production-ready release with comprehensive bug fixes and enhanced SEO plugin compatibility.
* Bugfix: Completely resolved template variables (%%sitename%%, %sep%, etc.) displaying on fresh plugin installations.
* Bugfix: Fixed ampersand encoding issue where "&" characters were displaying as "&amp;" in titles and meta descriptions.
* Enhancement: Improved RankMath, Yoast, and All-in-One SEO plugin compatibility with multiple override approaches.
* Enhancement: Enhanced JavaScript title override system for persistent SEO plugins.
* Enhancement: Added comprehensive template variable detection to prevent raw templates from ever appearing.
* Security: Removed all test and backup files for clean production deployment.
* Performance: Optimized SEO override system to only activate when ClickRank AI has optimization data.

= 3.2.1 =
* Bugfix: Fixed template variables (%%sitename%%, %sep%, etc.) displaying on fresh plugin installations instead of proper processed titles.
* Bugfix: Fixed ampersand encoding issue where "&" characters were displaying as "&amp;" in titles and meta descriptions.
* Enhancement: Improved SEO plugin compatibility to prevent raw template variables from ever appearing to users.
* Enhancement: Enhanced override system to only activate when ClickRank AI has actual optimization data.

= 3.2.0 =
* Major Enhancement: Added comprehensive support for WordPress taxonomy pages including categories, tags, and custom taxonomies.
* Major Enhancement: Added support for taxonomy archive base pages (e.g., /category, /tag).
* Enhancement: Completely integrated SEO compatibility layer with all popular SEO plugins (Yoast, RankMath, All In One SEO).
* Enhancement: Unified SEO plugin detection and meta key handling across all content types.
* Enhancement: Enhanced webhook endpoint to handle all WordPress content types.
* Enhancement: Added revert functionality for all new content types.
* Bugfix: Fixed post URL resolution for date-based and complex permalink structures.
* Compatibility: Full support for custom post types and custom taxonomies.
* Enhancement: Added link title optimization support through sync data processing.

= 3.1.2 =
* Bugfix: Implemented definitive fixes for schema overrides and image alt text updates to ensure compatibility with other plugins and themes.
* Bugfix: Corrected and fully implemented revert functionality for all optimization types.
* Bugfix: Improved image lookup logic to correctly find images from resized thumbnail URLs.
* Enhancement: Upgraded the API structure to handle both image alt text and title updates.

= 3.1.1 =
* Security: Hardened escaping on all echoed variables to prevent potential XSS vulnerabilities.
* Bugfix: Correctly enqueued admin scripts and styles.
* Enhancement: Added full disclosure of external services.
* Enhancement: Removed specific plugin names from readme.

= 3.1.0 =
* Major Refactor: Complete rewrite for a more professional and scalable architecture.

== Upgrade Notice ==

= 3.3.5 =
Critical update! Fixes duplicate meta tags, schema, and canonical URLs that could hurt your SEO. This update ensures only one of each meta tag appears on your pages, improving search engine rankings and social media sharing. Highly recommended for all users!

= 3.2.0 =
Major update with complete WordPress taxonomy support! Now optimize categories, tags, custom taxonomies, and archive pages. Includes critical post URL resolution fixes and enhanced SEO plugin compatibility. Highly recommended for all users.

= 3.1.2 =
This is a critical update that resolves bugs related to schema and image optimization compatibility. It is highly recommended to upgrade to ensure all features work as expected.

= 3.1.1 =
This version includes important security and compliance updates required by the WordPress plugin review team.