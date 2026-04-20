# Tutor LMS Customization

Custom Tutor LMS components for course archive filtering, demo uploads, and testimonial management.

## Shortcodes

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
