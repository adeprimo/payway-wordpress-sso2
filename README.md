# Tulo Payway SSO2 for Wordpress

This plugin integrates with the [SSO2 single sign on solution](https://docs.worldoftulo.com/payway/integration/sso/sso2/sso2/) for Tulo Payway. It provides basic login/logout functionality as well as session identification.

## Requirements

* Wordpress: Tested with 5.6
* PHP: Tested with php 7.4

## Installation

* Download the latest version of the plugin from github and unzip it in the `wp-content/plugins` directory.
* Activate the plugin through the "Plugins" menu in Wordpress.
* Configure the plugin.

## Configuration

After activating the plugin you will find the plugin configuration in WP-admin under `Settings/Tulo Payway SSO2`.

### Create API user

First of all you will need to create an API user in [Tulo Payway Admin](https://docs.worldoftulo.com/payway/). Go to Payway administration, security and API users. Press "New".

* Name, enter name for API user, can be anything but should represent what kind of user this is.
* Redirect URI, needs to point at the `landing.php` page in the plugin, look at the configuration page to get the link.
* Offline access, not needed
* SSO2 Client, **check this, important!!**
* Scopes, the user will need the following scopes: `/external/me/w`
* Origin urls, add urls that should be able to call use this API user, leave empty when developing.
* Save user.

When the API user has been created, you can see two new properties: `Client ID` and `Secret`. Copy these to the next step in configuration.

### Configuration details

Add `API Client id` and `API Secret` as specified for the API user created in the previous step.

* Organisation id - Enter the Payway organisation id
* Set restrictions for new pages/posts
* Define towards which Payway environment the plugin should operate.

### Configure restriction handling

There are three different text-boxes available, one will contain code (html/css/js) that will be shown to the user if the user is trying to access restricted content and the user is **not** logged in. The other text-box will contain code that will be shown to the user if the user is logged but **do not** have access to the subscription that is required to view the content.

#### Not logged in

To get started, enter the following piece of code to be shown when the user is not logged in:

```html
<p>This content is restricted for subscribers only. You are not logged in. Please login.</p>

<form class="js-tuloLogin is-hidden" action="/" method="POST"> <label for="email"> Email <input id="email" type="text"> </label> <label for="password"> Password <input id="password" type="password"> </label> <input type="submit" value="Login"/> </form>
```

#### Logged in but do not have access

To get started, enter the following piece of code to be shown when the user is logged in but do not have required subscription:

```html
Hi <b>[tulo_user_name]</b>!!<br/>
This content is restricted for subscribers only. <br/>
You are logged in but don't have a subscription that is needed to view this content.
```

#### Whitelist IP addresses

Here you can add IP addresses that should have access to the content regardless login- and subscription-status. Add them one IP-address on each row.

#### Available shortcodes to use in restriction handling

* [tulo_user_name]
* [tulo_user_email]


## Login and logout management

### Login

The javascript provided with this plugin automatically detects any login-forms that you create on the site as long as they are decorated with the class `js-tuloLogin`. If the form also has a class `is-hidden` it will make sure to show the form only when the user is not logged in and the html is visible.

If the form can be found and the user clicks the submit button, the javascript will post an authenticate call to the plugin which will authenticate the user with Tulo Payway SSO2.

### Logout

You will also need to add a logout button somewhere on the site, for example like this: 

```html
<button class="js-tuloLogout is-hidden">Logout</button>
```

The javascript will find the logout button and manage it's visibility depending on whether the user is logged in or not. If the user clicks the logout button a logout-request will be sent to Tulo Payway SSO2 terminating the session. This will also terminate all other sessions the user may have in SSO2, effectively logging out the user on all sites.

## API

### User information

If a user is authenticated with Tulo Payway SSO2 and logged into Wordpress session, you can access user information in a number of ways. The publicly available PHP functions that might be useful are located in `Tulo_Payway_Session` and can be used like this:

```php
 $session = new Tulo_Payway_Session();
 $session->is_logged_in();
 $session->get_user_name();
 $session->get_user_email();
 $session->get_user_active_products();
 $session->user_has_subscription();
```
If user is logged in, the list of active products is also available in `localStorage` with property name `tulo_products`.


## FAQ

### Where is "Remember me"?

Functionality where the user can mark a checkbox upon login to "remember" the session upon closing the browser is not supported in Tulo Payway SSO2 and not implemented in this plugin. 

* If a user logs in to Tulo Payway a session is established and the user is automatically logged in to any other websites (which supports SSO2) the user visits in the same browser.
* If the user closes the browser and then re-open the browser again to go back to any of the sites connected to Tulo Payway SSO2 it gets automatically a session in SSO2 and user-data is fetched.
* If the user in the same browser logs out on any of the websites connected to Tulo Payway SSO2 it will also be logged out from all websites.

## Troubleshooting

### Debugging

Add the following to `wp_config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false);
define( 'WP_DEBUG_LOG', true);
```

Debug output will then be written to `wp-content\debug.log`.