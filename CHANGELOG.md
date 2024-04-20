# Changelog
All notable changes to this project will be documented in this file.

## [1.3.1 - 2024-04-21](https://github.com/iniznet/authcred/releases/tag/v1.3.1)

### Bug Fixes
* Fix post counting in Permalinks
* Fix styles in dark mode

## [1.3.0 - 2022-12-03](https://github.com/iniznet/authcred/releases/tag/v1.3.0)

### Enhancements
* Added table of contents shortcode, please see the wiki for more information.
* You're now able to set a `cost` argument/parameter in the mycred built-in `mycred_buy` shortcode to set your own custom cost.

## [1.2.8 - 2022-12-03](https://github.com/iniznet/authcred/releases/tag/v1.2.8)

### Bug Fixes
* Permalinks generation triggered and crash in admin screen
* `br` tag or newline between authcred buy button

## [1.2.7 - 2022-12-03](https://github.com/iniznet/authcred/releases/tag/v1.2.7)

### Bug Fixes
* Escape $/dollar sign in button label, to prevent it from being interpreted as a variable.

## [1.2.6 - 2022-12-02](https://github.com/iniznet/authcred/releases/tag/v1.2.6)

### Bug Fixes
* Fix locked post return post id `?p=123` as permalink rather normal one `/2022/12/a-locked-chapter-test/`

## [1.2.5 - 2022-12-01](https://github.com/iniznet/authcred/releases/tag/v1.2.5)

### Bug Fixes
* Fix user icon height & size

## [1.2.4 - 2022-12-01](https://github.com/iniznet/authcred/releases/tag/v1.2.4)

### Enhancements
* Change `mycred` tab to `top up` tab in settings

## [1.2.3 - 2022-12-01](https://github.com/iniznet/authcred/releases/tag/v1.2.3)

### Bug Fixes
* Fix input fields not filling the form space

## [1.2.2 - 2022-12-01](https://github.com/iniznet/authcred/releases/tag/v1.2.2)

### Bug Fixes
* Fix potential underline & color issues

* Change border of buy button from red to blue in hover
## [1.2.1 - 2022-12-01](https://github.com/iniznet/authcred/releases/tag/v1.2.1)

### Enhancements
* Change border of buy button from red to blue in hover

## [1.2.0 - 2022-12-01](https://github.com/iniznet/authcred/releases/tag/v1.2.0)

### Enhancements
* Change password shortcode & page
* Custom/dynamic amount top up shortcode & page, similary to [mycred_buy_form] shortcode
* Custom cost top up in [authcred-buy] shortcode, you can set the custom cost for specific point amount
* Change user fields library to CMB2, it's still disabled by default as it's still in development

## [1.1.0 - 2022-11-30](https://github.com/iniznet/authcred/releases/tag/v1.1.0)

### Enhancements
* Plugin updater
* Plugin settings page

## [1.0.0 - 2022-10-29](https://github.com/iniznet/authcred/releases/tag/v1.0.0)

### Initial Features

#### Shortcodes
* [authcred] shortcode, display login, register, and forgot password form
* [authcred-balance] shortcode, displays the current balance of the user
* [authcred-buy] shortcode, displays the buy point button, similary to [mycred_buy] shortcode
* [authcred-login] shortcode, return the login link of given post id
* [authcred-logout] shortcode, return the logout link and redirect user to given url after logout
* [authcred-user-icon] shortcode, displays the user icon, recommended to be use in the navigation menu

#### Templates
* Authentication system & pages: login, register, forgot password
