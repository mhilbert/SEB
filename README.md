# SEB

The ILIAS SEB plugin is a UIHook-Plugin for ILIAS that improves how ILIAS
integrates with the [Safe Exam Browser](http://safeexambrowser.org). It has the
following features:
* It reduces the ILIAS interface removing the main menu, the breadcrumb, the
menu in the upper right, the tab-menu, the link to the startpage through the
logo, the left and the right columns on the personal desktop and the right
column in the repository.
* It implements a template that is clearly different from a standard ILIAS
template.
* It allows to block users having certain roles from accessing the ILIAS
installation with any other browser, either through sitewide keys or object
specific keys. Object specific keys only allow access to the ILIAS object (test)
they are defined in. **Be aware that sitewide keys might pose a risk for your exam if using them in BYOD exams!**
* Object-specific keys can be deactivated (for backward compatibility).
* Object-specific keys can be forced. If this option is selected users that are
not assinged to any roles that are limited in the global configuration and do not
have 'write' permissions on the corresponding test are also forced to use a
correctly configured Safe Exam Browser and the test will be shown to them in the
SEB Kiosk Skin.
* It adds a menu to change the language without having to access the user
settings to the interface.
* It adds a tab for administrators to the test object (could be expanded to
other objects in the future) to add object specific keys.
* If "Prevent Simultaneous Logins" is activated a tab for session management in
the test object can be activated, allowing to delete sessions for specific test
users, to allow users back in if they are locked out due to a browser or
computer issue (exiting SEB without closing the session).
* From Version 2.5 on it works with the old header-method as well as with
SEB-Keys sent through the new Javascript based method.
* **This plugin resets the "Exam View"-Setting to false when a test is started.**
**This is needed to avoid colitions with some modifications done by the Exam View.**

This plugin is a completely refactored fork of the SEB plugin developed by
Stefan Schneider at the University of Marburg.

**Minimum ILIAS Version:**
9.0

**Maximum ILIAS Version:**
9.99

**Responsible Developer:**
Stephan Kergomard - office@kergomard.ch

**Supported Languages:**
German, English

### Update Information
**Please make sure to install all updates in the 2.6.\* version before updating**
**to this version. This version switches to a new machanism for creating**
**database entries and assumes that all database changes in previous versions**
**have been applied!**

### Quick Installation Guide
1. Copy the content of this folder in <ILIAS_directory>/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SEB
or clon this Github-Repo to <ILIAS_directory>/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/
2. Run `composer install --no-dev`  and `php setup/setup.php update` in the root folder of your installation.
3. Access ILIAS, go to the administration menu and select "Plugins".
4. Look for the SEB-Plugin in the table, press the "Actions" button and select "Update".
5. Press the "Actions" button and select "Configure" to choose your settings. Be
aware that you might be locking out users from your installation, so check the
bylines of the different options for the correct settings.
6. Press the "Actions" button and select "Activate" to activate the plugin.
7. If you want to use object specific keys and have activated the corresponding
option, users with write rights on a test can set the keys directly in the
corresponding tab in the test object. This key is only valid for this test, so a
direct static link to the test object (to be found in the info tab) MUST be set
in the SEB config file. It is possible to show an User Agreement after login.