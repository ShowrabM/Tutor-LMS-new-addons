# Tutor LMS Customization

Custom Tutor LMS components for course archive filtering, demo uploads, and testimonial management.

## Google Search Console dashboard link

Go to **Settings > GSC Dashboard** and paste your Google Search Console URL. Enable the option to show a one-click GSC shortcut in the WordPress dashboard widget and on the Tutor dashboard for allowed users.

You can also place the link manually with:

```text
[stm_gsc_dashboard_link]
```

## Popup & Leads dashboard (1.3.0)

Administrators can open **Tutor Dashboard → Popup & Leads** to see all popups,
active/paused counts and lead totals. Each popup can be created, edited,
previewed, duplicated, activated, paused or moved to trash independently.
Trashing a popup preserves its media and leads. Copies start paused.

Select a JPG/PNG/WebP/GIF banner and an optional PDF, DOC, DOCX, ODT or TXT
gift document from WordPress Media Library, or upload either from a device.
Media Library selection stays available after an asset is selected.
After successful form submission, visitors immediately receive a signed gift
download link on the confirmation screen. The link lasts 24 hours. This is
on-page delivery; no email is sent. WordPress Media Library files retain their
normal public-upload access if their original URL is known.

Every active popup has its own form, delay, frequency and priority. Higher
priorities display first, one popup at a time, with a short gap between offers.
New popups start paused. Email is required; name, phone and message are optional.
The **Captured Leads** tab filters by campaign and exports matching leads as CSV.
The visitor popup stays off dashboard pages. Banner images load when opened.
Preview works for drafts without recording leads or changing campaign status.

Testimonial cards now show a compact six-line preview with **Read more / Show
less** controls. The complete original testimonial remains saved and readable.

## Course and testimonial shortcodes

### Free courses (Elementor landing page)

```text
[stm_free_courses limit="6"]
```

Displays up to 24 published Tutor courses whose `_tutor_course_price_type` is
`free`, newest first. Default: six cards. Course access and enrollment continue
to be handled by Tutor and the site's membership configuration. This listing
does not change any course's price or permissions.

Course and testimonial styles/scripts are detected in nested Elementor
widgets as well as ordinary WordPress content. Keep Elementor and Elementor
Pro active for the landing page, and Tutor LMS with Tutor LMS Elementor Addons
for the existing course widgets.

The repaired landing page is in `templates/elementor/MyTennisCloud-Landing-2026-11-12.json`.
Update this plugin before importing that template. Import it into Elementor's
Saved Templates, replace the homepage's existing sections, then regenerate
Elementor CSS/data and clear the page cache. Do not insert it below the old
homepage, which would duplicate the layout. Saved testimonials use
`[tdl_testimonial_carousel limit="10" autoplay="false"]`; manage the entries
through the plugin's existing dashboard. Video popups in this template fetch
their MP4 source only when opened and release it on close.

### Course archive

Use this shortcode to show the custom Tutor LMS course listing with category filters:

```text
[stm_tutor_courses]
```

Optional attributes:

```text
[stm_tutor_courses posts_per_page="12" title="All Courses" include_categories="1,2,3" exclude_categories="4" show_all_tab="true"]
```

- `posts_per_page`: Number of courses to show. Use `-1` for all.
- `title`: Main heading for the archive.
- `include_categories`: Comma-separated Tutor LMS course category term IDs to allow.
- `exclude_categories`: Comma-separated Tutor LMS course category term IDs to hide.
- `show_all_tab`: `true` or `false`.

### Demo upload form

Use this shortcode to show the frontend demo submission form for allowed users:

```text
[tutor_demo_upload_form]
```

### Demo gallery

Use this shortcode to show the saved demo gallery:

```text
[tutor_demo_gallery]
```

Optional attributes:

```text
[tutor_demo_gallery posts_per_page="9"]
```

- `posts_per_page`: Number of demo items per page.

### Testimonial form

Use this shortcode to show the frontend testimonial form for allowed users:

```text
[tdl_testimonial_form]
```

### Testimonial carousel

Use this shortcode to show the testimonial slider:

```text
[tdl_testimonial_carousel]
```

Optional attributes:

```text
[tdl_testimonial_carousel limit="10" autoplay="true" speed="5000"]
```

- `limit`: Number of testimonials to load.
- `autoplay`: `true` or `false`.
- `speed`: Autoplay interval in milliseconds.

## Notes

- Demo and testimonial submission forms are restricted to logged-in users with supported roles in the plugin code.
- The course archive shortcode also loads its frontend assets automatically when used on a page.
