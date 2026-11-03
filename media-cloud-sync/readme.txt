=== Media Cloud Sync ===
Author: Dudlewebs
Author URI: https://dudlewebs.com
Contributors: dudlewebs
Tags: sync, cloud, offload, media, aws
License: GPLv2 or later
Requires PHP: 8.1
Requires at least: 5.9
Tested up to: 7.1
Stable tag: 1.5.0

Offload media to cloud storage (S3, DigitalOcean, Google Cloud, Cloudflare R2, S3 compatible Services) and rewrite URLs for seamless file delivery.

== Description ==
Media Cloud Sync is an innovative plugin for WordPress that dramatically transforms how you interact with media and increases your website's performance. This plugin allows you to transfer your files, media, and images from a WordPress server to online cloud storage, such as Amazon S3, DigitalOcean Spaces, Google Cloud Storage, Cloudflare R2 and S3 Compatible Services. It also rewrites URLs to serve files from the same storage provider or another CDN provider.

You can sync both new media files as well as existing media from your WordPress Media Library to your configured cloud storage, making it easy to sync older medias to cloud storage.

== Installation ==
Installation of "Media Cloud Sync" can be done either by searching for "Media Cloud Sync" via the "Plugins > Add New" screen in your WordPress dashboard or by using the following steps:

1. Download the plugin via WordPress.org.
2. Upload the ZIP file through the ‘Plugins > Add New > Upload’ screen in your WordPress dashboard.
3. Activate the plugin through the ‘Plugins’ menu in WordPress.

== How to Manage Settings ==
To manage settings in the Media Cloud Sync plugin, follow these steps:

1. **Access the Plugin Menu**:
   - In your WordPress admin dashboard, look for the **Media Cloud Sync** menu item in the left menu bar. This menu provides access to all the settings and features of the plugin.

2. **Manage Settings**:
   - Click on the **Media Cloud Sync** menu to enter the settings area.
   - You will see two main sections: **Configure** and **Settings**.

   **a. Configure**:
   - In this section, you can set up the basic configurations for the plugin, including connecting your cloud storage account (e.g., Amazon S3, Google Cloud Storage, DigitalOcean Spaces, Cloudflare R2, S3 Compatible Services) and defining the default options for media offloading.
   - Follow the prompts to authenticate your cloud account and grant the necessary permissions.

   **b. Settings**:
   - The **Settings** section allows for more advanced customization options. Here, you can adjust how media files are uploaded and served from the cloud.
   - Make sure to save your changes after adjusting the settings to ensure they take effect.

3. **Review and Test**:
   - After configuring the settings, it’s advisable to test the plugin to ensure that your media files are being uploaded and served correctly from the cloud storage.
   - Upload a new media file and check if it appears in your cloud storage as expected.

== Basic Features ==
The Media Cloud Sync plugin significantly enhances your website's speed by offloading media to cloud servers. This approach allows your site to load more efficiently, as it reduces the number of server requests, ultimately resulting in faster page load times. Once media files—such as images, videos, PDFs, and ZIP files—are uploaded to the cloud, your server no longer needs to handle these files, freeing up resources.

Here are the key features of the Media Cloud Sync plugin:

🔹 Seamlessly sync your media to popular cloud storage solutions like Amazon S3, Google Cloud Storage, DigitalOcean Spaces, Cloudflare R2, or S3 Compatible Services.
🔹 **Sync existing media** from your WordPress Media Library to the cloud with a simple migration tool.
🔹 Automatically delete files from the server after they are uploaded to the cloud, optimizing storage use.  
🔹 Customize the base path for server storage to suit your organizational needs.  
🔹 Tailor the URL structure for your media files to enhance your site's SEO and user experience.  
🔹 Enable object versioning to prevent invalidations of your media files.  
🔹 Utilize a custom CDN for serving your media URLs, improving loading speeds and reliability.  
🔹 Enjoy built-in support for WooCommerce, ensuring smooth integration with your online store.  
🔹 Leverage compatibility with Advanced Custom Fields for enhanced flexibility.  
🔹 Benefit from RTL (Right to Left) support for multilingual websites.  
🔹 Access WPML string translation support for seamless multilingual content management.
🔹 Works on multisite networks, with each site set up independently.
🔹 Keep your storage keys out of the database with a wp-config.php constant.  
🔹 Optimize offloaded images with built-in Imagify support.  
🔹 Copy settings to another site with Import / Export, previewing every change first.  
🔹 Check storage, delivery, background jobs and memory in Site Health.  
🔹 Run and monitor bulk sync from the command line with WP-CLI (`wp mcs`).  

== Pro Features ==
[Media Cloud Sync Pro](https://dudlewebs.com/product/media-cloud-sync/) extends the free plugin with cloud-side management tools, faster bulk operations, and per-item control directly from the Media Library:

**Media Library and delivery**

🔹 **Media Library actions** — Sync to Cloud, Force Re-upload, Remove from Server, Restore from Cloud, Remove from Cloud, View on Cloud, Use Server URL / Cloud URL, Mark as Private / Public, and Purge CDN Cache, per item and in bulk, with a cloud status column and a Synced/Not Synced filter.
🔹 **Private Media** — mark individual items private so they're served only through short-lived signed URLs, directly from storage or through Amazon CloudFront or Bunny.net.
🔹 **Cache-Control duration** — set a custom Cache-Control max-age (or explicit no-cache) per storage/CDN choice for newly uploaded files.
🔹 **Include/Exclude Extensions** — control exactly which file types get uploaded to the bucket.
🔹 **File Manager** — browse your connected bucket, download as zip, restore to server, move, and delete files, with per-item and bulk actions.

**Bulk jobs**

🔹 **Ajax/Mixed sync methods** — run bulk jobs live in the browser (Ajax) or with automatic WP-Cron fallback (Mixed), instead of Cron only.
🔹 **Restore Media to Server** — bring offloaded media back from the cloud to your server.
🔹 **Remove Media from Server** — delete the local copies of synced media (all sizes, originals and edit backups) after verifying every file in the cloud.
🔹 **Remove Media from Cloud** — delete synced media from the bucket, with an optional restore-to-server step first.
🔹 **Move Media to New Path** — move already-synced media to a newly configured base path.
🔹 **Migrate Bucket to Bucket** — copy synced media to a new provider, bucket, or account, without re-uploading from your server.

**Tools**

🔹 **Import from Other Plugins** — take over media already offloaded by WP Offload Media, Media Cloud, Offload Media – Cloud Storage, WP-Stateless or Advanced Media Offloader, without uploading it again.
🔹 **Import from Bucket** — connect media to files you already copied to the bucket, uploading only what is missing.
🔹 **Repair Synced Media** — check synced media in the bucket and upload any files that are missing there.
🔹 **Update File Headers** — apply your Cache-Control setting and the right content type to files already in the bucket.
🔹 **Clean Up Unused Files** — find files in the bucket that no synced media uses, review them, then delete them.
🔹 **Clean Orphaned Records** — remove records of media items that no longer exist, without deleting any files.
🔹 **Export Media Inventory** — download a CSV of every media file: its server path, bucket key, URL and sync status.
🔹 **Change Storage Class** — move older media to a cheaper storage class, now or automatically, without changing its URLs (Amazon S3, Google Cloud Storage, Cloudflare R2).
🔹 **Bucket CORS** — let browsers load fonts, SVGs and video from your bucket on your site.
🔹 **CDN Cache Purge** — clear Amazon CloudFront, Bunny.net or Cloudflare caches when files are uploaded again: automatically, per item, or everything at once.
🔹 **Assets on CDN** — serve theme, plugin and WordPress CSS, JS, fonts and images from a CDN on visitor pages.
🔹 **WP-CLI** — run every Pro job with `wp mcs`, plus `restore`, `remove-local` and `purge-cdn` commands.

**Third-party compatibility**

🔹 WooCommerce and Easy Digital Downloads downloadable files, BuddyBoss Platform, WPML, Elementor, Divi, Enable Media Replace, EWWW Image Optimizer, Meta Slider, and Smush all stay in sync with cloud storage.
🔹 And more...

== Other Useful Links ==
🔹 [Official website](https://dudlewebs.com)  
🔹 [Documentation](https://dudlewebs.com/guideline/media-cloud-sync/)  
🔹 [Get Media Cloud Sync Pro](https://dudlewebs.com/product/media-cloud-sync/)

== Screenshots ==
1. Plugin dashboard page, showcasing the main options for configuring the Media Cloud Sync settings.  
2. Configure wizard welcome screen.  
3. Choosing the appropriate service (e.g., Amazon S3, Google Cloud Storage, DigitalOcean Spaces, Cloudflare R2, and S3 Compatible Services) to use.  
4. Configuration settings according to the chosen service.  
5. Choose your bucket to store the media; if the bucket is not created, create one.  
6. Validating the bucket and permissions.
7. Enable additional security configurations.
8. Configuration verified successfully; save the settings.  
9. Choose the appropriate media provider (CDN or native service URL).
10. Main settings page.
11. Plugin dashboard page after configuring the service.  
12. Media properties after uploading the media.  
13. Sync existing media to the cloud: choose how the job runs and follow its progress.  
14. Sync in progress, running in the background.  
15. Keep your storage keys out of the database with a wp-config.php constant.  
16. Copy settings to another site with Import / Export, previewing every change first.  

== Frequently Asked Questions ==

= How do I install the plugin? =
You can install the plugin from the WordPress plugin store or upload it manually.

= Is there a limit on the number of files I can sync? =
No, you can sync as many files as you need. Both newly uploaded media and existing media in your library can be synced.

= How do I sync my media files to the cloud? =
After activating the plugin, go to the configure page and follow the prompts to connect your cloud account. Then, navigate to settings and configure them according to your needs. Upload a media file in the Media Library, and you will see that the URL has been replaced by the cloud server URL.  
If you already have media files in your library, use the **Sync Media To Cloud** option to migrate them to the cloud.

= What is Existing Media Sync? =
Existing Media Sync is a feature that lets you migrate files that were uploaded to your WordPress Media Library before installing or configuring the plugin.  
Instead of manually re-uploading older files, you can run the Sync Media To Cloud tool from the plugin dashboard, and it will automatically upload them to your connected cloud storage.

= What cloud providers are supported? =
Currently, the plugin supports major cloud providers such as AWS S3, Google Cloud Storage, DigitalOcean Spaces, Cloudflare R2 and All S3 Compatible Services.

= Is WPML supported? =
The plugin's text can be translated with WPML. Keeping WPML's translated copies of media in sync with the cloud is a Pro feature.

= Is Multisite supported? =
Yes, the Media Cloud Sync plugin is compatible with Multisite and it will works as an individual plugin for all the sites under multisite.

= Can I use S3-compatible services? =
Yes, the Media Cloud Sync plugin supports S3-compatible services. You simply need to provide the correct configuration details. Should you encounter any issues, please contact us via our official website. We will investigate the matter and provide an appropriate resolution.

== External Services ==
This plugin integrates with third-party services to enhance its functionality. Below is an overview of the external services utilized, the data transmitted, and relevant legal documentation for your reference.

* **Google Cloud Storage**
  - **Description**: Connects to manage media files, allowing upload, download, and delete operations.
  - **Data Sent**: User authentication data, file metadata (name, size, MIME type), user location data (if explicitly provided).
  - **Legal Links**: [Terms of Service](https://policies.google.com/terms), [Privacy Policy](https://policies.google.com/privacy)

* **Amazon S3**
  - **Description**: Facilitates media file management, enabling seamless upload, download, and delete actions.
  - **Data Sent**: User authentication data, file metadata (name, size, MIME type).
  - **Legal Links**: [Terms of Service](https://aws.amazon.com/service-terms/), [Privacy Policy](https://aws.amazon.com/privacy/)

* **DigitalOcean Spaces**
  - **Description**: Manages media files efficiently, allowing file storage, retrieval, and deletion.
  - **Data Sent**: User authentication data, file metadata (name, size, MIME type).
  - **Legal Links**: [Terms of Service](https://www.digitalocean.com/legal/terms-of-service/), [Privacy Policy](https://www.digitalocean.com/legal/privacy-policy/)

* **Cloudflare R2**
  - **Description**: Provides efficient, low-cost object storage with S3-compatible APIs, enabling seamless upload, retrieval, and deletion of media files.
  - **Data Sent**: User authentication data, file metadata (name, size, MIME type).
  - **Legal Links**: [Terms of Service](https://www.cloudflare.com/terms/), [Privacy Policy](https://www.cloudflare.com/privacypolicy/)

* **S3 Compatible Services**
  - **Description**: Manages media files efficiently, allowing file storage, retrieval, and deletion.
  - **Data Sent**: User authentication data, file metadata (name, size, MIME type).

== Changelog ==
= 1.5.0 =
* Feature: Import / Export page: copy settings to another site as a JSON file, without secrets, previewing every change before it's applied.
* Feature: Site Health checks for storage, delivery, stalled jobs, WP-Cron and PHP memory, plus an Info section for support requests.
* Feature: WP-CLI commands (`wp mcs`) to run, monitor and control bulk jobs, sync single items, and read or change settings.
* Feature: PRO tools and settings appear as locked placeholders with a PRO badge.
* Tweak: Redesigned the admin: header, dashboard, Settings, setup wizard, Tools, License, File Manager (PRO), Bucket-to-Bucket form (PRO), buttons and loading screens.
* Tweak: The delivery provider is now the last setup step, with its custom domain on the same screen. Media is served from your server until one is chosen.
* Tweak: Bulk job screens show consistent counters, a message that fits each job, and a Download button for jobs that produce a file.
* Tweak: Admin notices can be dismissed, and the header shows both the free and PRO versions.
* Tweak: Admin screens are now defined server-side and can be extended with `wpmcs_ui_schema_*` filters. Update Media Cloud Sync PRO to 1.1.0 or later together with this version.
* Tweak: New hooks and storage methods for add-ons: file headers, storage classes, bucket CORS, `wpmcs_files_replaced` and `wpmcs_remove_server_files_after_item_update`.
* Tweak: Removed Edgio from the delivery providers: its network shut down in January 2025.
* Bug Fix: Security: the bucket policy applied by the setup wizard and by Private Media allowed anonymous writes and deletes. It now grants public read only and keeps your other statements; existing buckets are fixed automatically after updating.
* Bug Fix: Delivering from Cloudflare R2 without a public domain broke every image. Saving that setup is now blocked with an explanation.
* Bug Fix: With PRO installed but not licensed, the admin was locked to the License page instead of keeping free screens usable.
* Bug Fix: Running the storage and delivery checks together could falsely report a failure.
* Bug Fix: Moving files (PRO) dropped the public-read permission older uploads rely on.
* Bug Fix: Re-syncing an item dropped extra data such as PRO's "Use Server URL" choice.
* Bug Fix: Turning off "Restore files to the server" when removing media from the cloud (PRO) had no effect.
* Bug Fix: Messages showed HTML codes for quotes, the header showed v1.0.0 while loading, and Retry buttons could stay stuck loading.
* Bug Fix: Opening a running or paused job showed a spinner in its Errors list until the job finished.
= 1.4.3 =
* Bug Fix: With a persistent object cache (e.g. Redis), bulk sync stayed at 0 and Pause/Stop had no effect.
= 1.4.2 =
* Feature: Read-only stream-wrapper fallback lets plugins without a dedicated integration read offloaded files.
* Feature: Offload the HEIC source and animated-GIF files from WordPress 7.1's client-side media processing.
* Bug Fix: With "Remove from server" on, images using WordPress 7.1's client-side sub-sizes only synced the original.
* Bug Fix: Flipping or rotating an image 180° wasn't detected, so the bucket kept the old version.
* Bug Fix: A corrupted media count could crash the admin page.
* Bug Fix: Local media URLs in ACF oEmbed fields weren't rewritten.
= 1.4.1 =
* Feature: PRO: private media, a Remove from Cloud job, and a per-item Use Server URL / Use Cloud URL choice.
* Feature: Security step (Block All Public Access) in the Google Cloud Storage setup wizard.
* Feature: Extension points for add-ons: source types, cloud-delete veto, and job lifecycle hooks.
* Security Fix: A crafted path in a PRO downloadable-file field could escape the uploads directory.
* Bug Fix: WooCommerce, Customizer crop, image editor and Regenerate Thumbnails could leave restored files on the server or skip re-uploads.
* Bug Fix: A failed size re-upload could wipe that size's cloud reference.
* Bug Fix: Private media on Google Cloud Storage always failed to apply; it now uses Managed Folders.
* Bug Fix: Fixes for Cloudflare R2 restore, signed URLs, relative path resolution, URL-to-attachment lookups and orphaned tracking rows.
= 1.4.0 =
* Compatibility: WordPress 7.1
* Bug Fix: WooCommerce image regeneration left restored files on the server and could leave stale images in the cloud.
= 1.3.12 =
* Feature: PRO: File Manager, Ajax/Cron/Mixed sync methods, custom Cache-Control duration, and Include/Exclude Extensions.
* Feature: CDN provider presets for every storage service.
* Feature: New uploads get a Cache-Control header, 1 month by default.
* Feature: Storage groundwork for Bucket-to-Bucket Migration (PRO), including a "Use Existing" credential option.
* Tweak: Pro upgrade prompts link to the product page, and the deactivation survey is redesigned.
* Bug Fix: Security: SQL injection risk in media path and settings queries.
* Bug Fix: Sync reliability: slow Pause/Stop, duplicate uploads, skipped items, uncapped retries, leaked file handles, thumbnails marked failed too early, and a tick loop that stopped under Ajax/Mixed.
* Bug Fix: Connection and status checks across all providers, including false "Access Denied" results from CDN caching.
* Bug Fix: Media Library, Customizer, WooCommerce, Imagify and ACF issues, including a PHP 8.4 fatal and a missing capability check.
* Bug Fix: Settings, Configure and Dashboard pages getting stuck or crashing before a provider was configured.
* Bug Fix: Nested status data repaired by a database upgrade, and duplicate-key errors when moving items between buckets.
= 1.3.11 =
* Feature: wp-config.php credentials support (WPMCS_CONFIG).
* Feature: Connection status checks for storage and CDN.
* Bug Fix: Redis Object Cache and object cache compatibility.
* Bug Fix: Settings page and wp-config credential handling.
* Compatibility: WordPress 5.9+ and PHP 8.1+.
= 1.3.10 =
* Bug Fix: Version not supported issue on google SDK.
* Tweek: PHP compatibility restriction updated.
* Optimization: Lazy Loaded SDK.
* Compatibility: WordPress Version 7.0
= 1.3.9 =
* Compatibility: Updated AWS SDK.
* Compatibility: Updated Google Cloud SDK.
* Compatibility: Pro Version Licensing support.
= 1.3.8 =
* Bug Fix: Critical error when file type not supported.
* Bug Fix: Removed file type check from media library.
* Tweek: New filters to skip image size upload and skip a specific sizes
= 1.3.7 = 
* Bug Fix: WooCommerce updation of thumbnail size not updating in frontend from customizer.
* Bug Fix: Source set Generate function has original even if there is no original file in source set.
= 1.3.6 = 
* Compatibility: Imagify compatbility added.
* Bug Fix: Updated background runner.
* Bug Fix: Optimized database structure.
* Bug Fix: Optimized code base.
* Bug Fix: Added database fetching optimization.
* Bug Fix: Too many file open issue fixed.
= 1.3.5 = 
* Feature: New service Cloudflare R2
* Bug Fix: Background Runner Error warning fixed.
* Bug Fix: Removed Unwanted files from plugin
* Bug Fix: Removed warning from content.php
* Bug Fix: Limit Background Runner to maximum 20 media per CRON run
* Tweek: New filter for get media
* Compatibility: WordPress Version 6.9
= 1.3.4 = 
* Bug Fix: Caching plugins compatbility fixed.
* Bug Fix: Minor Bug Fixes
* Tweek: PRO version compatibility classes
* Tweek: Updated Logos and Banners
= 1.3.3 =
* Bug Fix: Sync Media To Cloud Page not working
= 1.3.2 =
* Bug Fix: Log file exist check added to avoid warning
* Bug Fix: Sync disabled when CRON is disabled
* Bug Fix: Edited Image files are now removed from server
* Bug Fix: Fixed Customizer fatal error
* Bug Fix: Background runner in consistency fixed
* Bug Fix: UI Breakage issues resolved
* Bug Fix: Fixed previous sttus checked date clearing issue
= 1.3.1 =
* Bug Fix: Plugin specific folder is not creating and avoid adding htaccess in uploads folder
= 1.3.0 =
* Feature: Existing Media Sync is added
* Feature: UI is updated for better UX
* Bug Fix: S3 Compatible Console link Updated
* Bug Fix: Customizer is breaking while plugin is active
= 1.2.13 =
* Bug Fix: Fixed Delete Error
* Bug Fix: Fixed S3 Compatible, Issue when no region is mentioned
* Bug Fix: S3 SDK Update
* Tweek: Code Structure Updated
* Tweek: Removed use of hook wp_generate_attachment_metadata
* Tweek: Avoided creating plugin additional folder. Since it is not required for newer versions
* Compatibility: Force Regenerate Thumbnails Added
= 1.2.12 = 
* Tweek: Plugin Structure Updation
* Compatibility: ACF Added
* Compatibility: Regenerate Thumbnails Added
= 1.2.11 = 
* Tweek: Google Cloud Config file saved as file before, and Currently avoided to used db itself
* Bug Fix: Minor UI bugs fixed
= 1.2.10 =
* Bug Fix: image URL replacement 
= 1.2.9 = 
* Bug Fix: Removed validation of CDN url
= 1.2.8 =
* Bug Fix: DigitalOcean configuration stuck on permission screen
= 1.2.7 =
* Bug Fix: Major Security Update.
* Bug Fix: Create bucket disabled in S3 Compatible service.
= 1.2.6 =
* Bug Fix: Label updation for S3 compatible services.
* Bug Fix: Verification issue fix for S3 compatible devices.
= 1.2.5 =
* Feature: New status check interface for read permissions
* Bug Fix: Configuration screen got stuck while no permissions
* Bug Fix: Google Cloud Bucket permission issue
* Bug Fix: Change security settings api fix
= 1.2.4 =
* Compatibility: Multisite Compatibility Added
* Bug Fix: Minor Bug Fixes on new UI
= 1.2.3 =
* Bug Fix: Minor Bug Fixes on new UI
= 1.2.2 =
* Bug Fix: Minor Bug Fixes on new UI
* Bug Fix: Existing issues fixing
* Bug Fix: global variable update
= 1.2.1 =
* Bug Fix: Error on creating digitalocean bucket
= 1.2.0 =
* Feature: Updated UI
* Feature: Compatibility for S3 compatible services
* Bug Fix: Bug fixes (Minor, Major)
= 1.1.1 =
* Compatibility: WordPress Version 6.8
= 1.1.0 =
* Feature: New Dashboard UI/UX 
* Bug Fix: Minor bug fixes
= 1.0.3 =
* Tweeks: Digital ocean configuration issue
= 1.0.2 =
* Tweeks: Digital ocean configuration issue
= 1.0.1 =
* Compatibility: WordPress Version 6.7
* Tweeks: Feedback form added.
* Donation Link Added
= 1.0.0 =
* Initial release.
