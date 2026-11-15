# GlocalSaino Easy Login by Magic Link

WordPress plugin: create magic links that log a user in with one click, without a password. Expiry, maximum uses and a redirect page per link.

- WordPress.org readme: [readme.txt](readme.txt)
- Plugin page: https://glocalsaino.com/easyloginbymagiclink/

## Release zip

Always build the distribution zip from git (honours `.gitattributes` export-ignore):

```sh
git archive --format=zip --prefix=glocalsaino-easy-login-by-magic-link/ -o ~/Desktop/glocalsaino-easy-login-by-magic-link-1.0.0.zip HEAD
```

## Translations

WordPress.org does not accept bundled translations. `languages/` ships only the `.pot`; the Spanish translation in `translations/` is kept out of the zip and is meant to be imported at translate.wordpress.org once the plugin is approved.
