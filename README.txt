DRC PORTAL - deployment (cPanel)

1. Upload the CONTENTS of this folder to  public_html/drc/  (so index.php sits at authenticatorglobal.com/drc/index.php).
2. (New install) In cPanel > MySQL Databases, create (or reuse) a database + user. Open phpMyAdmin, select it, Import > sql/schema.sql.
   (Already installed the first version? Do NOT re-import schema.sql. Import sql/migration_v2.sql once instead.)
   All tables are prefixed drc_, so they can live next to your existing tables.
3. Edit includes/config.php: DB details, SITE_URL, ADMIN_EMAIL (gets "new registration" mails), MAIL_FROM (a real mailbox on your domain).
4. Open  https://authenticatorglobal.com/drc/admin/setup.php  once to create the first admin. It locks itself afterwards; delete the file if you like.
5. Admin sign in: /drc/admin/login.php . Customers sign in at /drc/ .

Admins: Admin panel > Admins > Add an admin. The new admin gets an email with an activation link (valid 48 hours) where they choose a password.
Every active admin also receives the "new registration" emails.

Troubleshooting: if a page shows "Something went wrong", note the reference code and check the cPanel Errors log. To see the real error on screen, add  define('APP_DEBUG', true);  to includes/config.php, then remove it again.
Keep your existing includes/config.php when you upload new files (do not overwrite it with the sample).

Flow: customer registers -> admin gets email with a review link -> admin clicks Activate -> customer gets "account activated" email.
Emails use PHP mail(). If they land in spam, add SPF/DKIM for the domain in cPanel > Email Deliverability.
Requires PHP 7.2+ (8.x recommended) with PDO MySQL.
