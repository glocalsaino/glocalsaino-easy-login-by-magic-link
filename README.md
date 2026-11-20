# GlocalSaino Easy Login by Magic Link

WordPress plugin: passwordless login links that only work where they should. IP and device (browser) restrictions, a scanner-proof login page, a request-by-email shortcode, a registration checkbox and redirects by role.

- WordPress.org readme: [readme.txt](readme.txt)
- Plugin page: https://glocalsaino.com/easyloginbymagiclink/

## Release zip

Always build the distribution zip from git (honours `.gitattributes` export-ignore). Works from any folder:

```sh
git -C ~/proyectos/wordpress/glocalsaino-easy-login-by-magic-link archive --format=zip --prefix=glocalsaino-easy-login-by-magic-link/ -o ~/Desktop/glocalsaino-easy-login-by-magic-link-1.0.0.zip HEAD
```

## Translations

WordPress.org does not accept bundled translations. `languages/` ships only the `.pot`; the Spanish translation in `translations/` is kept out of the zip and is meant to be imported at translate.wordpress.org once the plugin is approved.
