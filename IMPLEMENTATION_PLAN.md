# Implementation Plan: SMTP Email & French/English Language Toggle

## Overview
Two major features to implement:
1. SMTP Email Configuration System (PHPMailer integration)
2. French/English Language Toggle for the entire project

---

## Feature 1: SMTP Email Configuration System

### Files to Create/Modify

#### 1. `includes/PHPMailer.php` - NEW FILE
**SMTP Class configuration:**
- PHPMailer autoloader or minimal SMTP class
- Configurable properties:
  - `SMTP_HOST` (default: 'localhost')
  - `SMTP_PORT` (default: 587)
  - `SMTP_USERNAME` (default: from MAIL_FROM config)
  - `SMTP_PASSWORD` (from config or env)
  - `SMTP_ENCRYPTION` ('tls' or 'ssl')
  - `SMTP_AUTH` (boolean)
- Class with `send()` method that uses PHPMailer or falls back to native mail()
- Proper exception handling

#### 2. `includes/SMTP.php` - NEW FILE
**Protocol Handler:**
- Initialize PHPMailer instance
- Configure from config.php values
- Methods: `setFrom()`, `setTo()`, `setSubject()`, `setBody()`
- `send()` method that returns bool
- Error reporting

#### 3. `includes/mail.php` - MODIFY EXISTING
**Updated SMTP wrapper:**
- Replace native `mail()` usage with PHPMailer-aware sender
- Keep `send_mail()` function signature compatible
- Add `send_mail_smtp()` as alternative
- Auto-detect based on config setting
- Backward compatibility: existing `send_mail()` calls should still work

### Configuration in `includes/config.php`
Add SMTP settings section:
```php
// ---- SMTP ----
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');       // Leave empty for no auth
define('SMTP_PASSWORD', '');       // Leave empty for no auth
define('SMTP_ENCRYPTION', 'tls'); // 'tls' or 'ssl'
define('SMTP_AUTH', true);
define('USE_SMTP', true);          // Set to false to use native mail()
```

### Integration with existing code
- `send_mail()` in `includes/bootstrap.php` should check `USE_SMTP` flag
- If `USE_SMTP` is true, use PHPMailer from `includes/mail.php`
- If false, use native `mail()` as before
- All existing code using `send_mail()` continues to work without changes

---

## Feature 2: French/English Language Toggle

### Files to Create

#### 1. `includes/lang-en.php` - NEW FILE
**English translations** (default):
```php
$lang = [
    'title'         => 'DRC Portal',
    'sign_in'       => 'Sign in',
    'register'      => 'Register',
    'logout'        => 'Logout',
    'admin'         => 'Admin',
    'customer'      => 'Customer',
    'activate'      => 'Activate',
    'deactivate'    => 'Deactivate',
    'error'         => 'Error',
    'success'       => 'Success',
    'save'          => 'Save',
    'cancel'        => 'Cancel',
    'search'        => 'Search',
    'no_records'    => 'No records found',
    'email_sent'    => 'Email sent',
    'email_failed'  => 'Email failed',
    'pending'       => 'Pending',
    'active'        => 'Active',
    'disabled'      => 'Disabled',
    // ... all user-facing strings
];
```

#### 2. `includes/lang-fr.php` - NEW FILE
**French translations**:
```php
$lang = [
    'title'         => 'Portail DRC',
    'sign_in'       => 'Se connecter',
    'register'      => 'S\'inscrire',
    'logout'        => 'Déconnexion',
    'admin'         => 'Admin',
    'customer'      => 'Client',
    'activate'      => 'Activer',
    'deactivate'    => 'Désactiver',
    'error'         => 'Erreur',
    'success'       => 'Succès',
    'save'          => 'Enregistrer',
    'cancel'        => 'Annuler',
    'search'        => 'Recherche',
    'no_records'    => 'Aucun enregistrement trouvé',
    'email_sent'    => 'Email envoyé',
    'email_failed'  => 'Échec de l\'envoi de l\'email',
    'pending'       => 'En attente',
    'active'        => 'Actif',
    'disabled'      => 'Désactivé',
    // ... all user-facing strings
];
```

#### 3. `includes/language.php` - NEW FILE
**Language manager:**
- Detect current language (session or cookie)
- Provide `t($key)` function for translations
- Load appropriate lang-*.php file
- Language selector HTML

### Language Implementation

#### Session-based language preference
- User selects language from dropdown
- Store in `$_SESSION['drc_language']` (defaults to 'en')
- Load `includes/lang-<lang>.php` on each page request

#### Integration with layout.php
- Add language selector to the sidebar/header
- Currently `html_head()` in `includes/bootstrap.php` sets `lang="en"` - make this dynamic
- Add `<select>` or language links in the sidebar

#### Wrapping user-facing text
Create a `t()` function that translates keys:
```php
function t($key, $args = []): string {
    $lang = $_SESSION['drc_language'] ?? 'en';
    $file = __DIR__ . '/includes/lang-' . $lang . '.php';
    $translations = require $file;
    $value = $translations[$key] ?? $key;
    // Simple arg replacement if needed
    foreach ($args as $k => $v) {
        $value = str_replace('{'.$k.'}', $v, $value);
    }
    return $value;
}
```

### Pages that need translation wrapping

All pages that output user-facing text need to use `t()` instead of hardcoded strings:

**Customer-facing pages:**
- `index.php` - "Sign in", "Register your lines & products", etc.
- `register.php` - "DRC Customer Registration", field labels, buttons
- `dashboard.php` - Setup checklist, status labels
- `lines.php` - "Add a line", "Your lines"
- `partners.php` - "Add an importer/manufacturer", "Your partners"
- `products.php` - "Register a product", "Registered products"

**Admin-facing pages:**
- `admin/index.php` - "Customers", toolbar labels
- `admin/login.php` - "Admin sign in"
- `admin/customer.php` - "Registration details", "Access", panel headings
- `admin/admins.php` - "Admins", "Add an admin"
- `admin/activate.php` - "Activate admin account"

### Language Selector Component
Add to `includes/layout.php` or `includes/bootstrap.php`:
```php
// After session start, add language selector
if (isset($_SESSION['drc_language'])) {
    $currentLang = $_SESSION['drc_language'];
} else {
    $currentLang = 'en'; // default
}
?>
<div class="language-selector">
    <a href="?lang=en" class="<?= $currentLang === 'en' ? 'active' : '' ?>">English</a>
    <a href="?lang=fr" class="<?= $currentLang === 'fr' ? 'active' : '' ?>">Français</a>
</div>
```

### URL-based language switching
- Add `?lang=en` or `?lang=fr` to any page URL
- PHP code checks for `?lang=` parameter at the top of each page
- Sets `$_SESSION['drc_language'] = $_GET['lang']`
- Redirects back to the same page with language parameter

### Making all text translatable
Replace hardcoded strings like:
- `html_head('Sign in')` → `html_head(t('sign_in'))`
- Button labels → `<?= t('submit_registration') ?>`
- Flash messages → already use variables, wrap the message key
- Table headers → `<?= t('company_name') ?>`
- Error/success messages → use translated keys

### Backward compatibility
- Default language is 'en' (English)
- If no session language set, defaults to English
- All existing text remains in English by default
- New French translations added alongside English
- No breaking changes - existing pages work exactly as before in English

---

## Implementation Priority

### Phase 1: SMTP System (foundational)
1. Create `includes/PHPMailer.php` - SMTP class
2. Create `includes/SMTP.php` - protocol handler  
3. Modify `includes/mail.php` - updated wrapper
4. Update `includes/config.php` - add SMTP settings
5. Test that emails still work

### Phase 2: Language System
1. Create `includes/lang-en.php` and `includes/lang-fr.php`
2. Create `includes/language.php` - language manager
3. Add language selector to layout
4. Wrap key user-facing text with `t()` calls
5. Update all pages to use translated strings
6. Test both languages across all pages

### Phase 3: Integration & Testing
1. Verify SMTP sends work (possibly with test SMTP server or mock)
2. Verify language toggle works across all pages
3. Ensure no broken links or missing translations
4. Document the configuration for the user