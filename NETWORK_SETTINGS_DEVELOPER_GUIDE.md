# Network Settings - Developer Extensibility Guide

## Filter Priority Architecture

Zero Spam Network Settings uses a **well-defined priority system** that allows plugins and themes to extend or override behavior:

```
Priority 10:  Modules register their settings (WordPress default)
Priority 100: Network settings applies management layer
Priority 200+: YOUR custom code can override (recommended range: 200-500)
```

## Overriding Network Settings

### Example: Override a locked network setting

```php
/**
 * Override network-locked settings for specific conditions
 * Priority must be > 100 to run after network settings
 */
add_filter('zerospam_settings', function($settings) {
    // Example: Allow VIP users to have different comment protection
    if (current_user_can('vip_member')) {
        $settings['verify_comments']['value'] = 'disabled';
    }
    
    return $settings;
}, 200); // Priority 200 runs AFTER network settings (100)
```

### Example: Override based on site conditions

```php
/**
 * Disable comment protection for staging sites
 */
add_filter('zerospam_settings', function($settings) {
    if (wp_get_environment_type() === 'staging') {
        $settings['verify_comments']['value'] = 'disabled';
        $settings['log_blocked_comments']['value'] = 'disabled';
    }
    
    return $settings;
}, 250);
```

## Action Hooks for Monitoring

### Hook: `zerospam_network_setting_enforced`

Fires when a network administrator has **locked** a setting.

```php
/**
 * Log when network settings are enforced
 *
 * @param string $setting_key    The setting being locked
 * @param mixed  $network_value  The enforced value
 * @param mixed  $original_value The value before override
 * @param array  $network_config Full network configuration
 */
add_action('zerospam_network_setting_enforced', function($setting_key, $network_value, $original_value, $network_config) {
    error_log(sprintf(
        'Network enforced %s: %s (was: %s) by user %d',
        $setting_key,
        $network_value,
        $original_value,
        $network_config['updated_by']
    ));
}, 10, 4);
```

### Hook: `zerospam_network_setting_overridden`

Fires when a site admin has **overridden** an unlocked network default.

```php
/**
 * Track sites overriding network defaults
 *
 * @param string $setting_key   Setting with override
 * @param mixed  $site_value    Site's custom value
 * @param mixed  $network_value Network default value
 * @param int    $site_id       Site ID
 */
add_action('zerospam_network_setting_overridden', function($setting_key, $site_value, $network_value, $site_id) {
    // Send alert if critical settings are overridden
    if (in_array($setting_key, ['verify_comments', 'verify_registrations'])) {
        wp_mail(
            'security@example.com',
            'Zero Spam Override Alert',
            sprintf('Site %d overrode %s to %s', $site_id, $setting_key, $site_value)
        );
    }
}, 10, 4);
```

### Hook: `zerospam_network_setting_default`

Fires when a network default is being used (site has no override).

```php
/**
 * Monitor which sites use network defaults
 *
 * @param string $setting_key    Setting using network default
 * @param mixed  $network_value  Network default value
 * @param mixed  $plugin_default Plugin's default value
 */
add_action('zerospam_network_setting_default', function($setting_key, $network_value, $plugin_default) {
    // Analytics: track network default usage
}, 10, 3);
```

### Hook: `zerospam_network_settings_applied`

Fires AFTER all network hierarchy has been applied.

```php
/**
 * Post-process all settings after network management
 *
 * @param array $settings         Final settings array
 * @param array $network_settings Complete network configuration
 */
add_action('zerospam_network_settings_applied', function($settings, $network_settings) {
    // Audit: Compare final values vs network policy
    foreach ($network_settings['settings'] as $key => $config) {
        if (!empty($config['locked'])) {
            $final_value = $settings[$key]['value'] ?? null;
            if ($final_value !== $config['value']) {
                // Alert: A locked setting was overridden!
                error_log("WARNING: Locked setting {$key} was overridden!");
            }
        }
    }
}, 10, 2);
```

## Site Settings Page Hooks

On a network site, `Site_Admin_Overrides` (`includes/admin/class-siteadminoverrides.php`) uses these hooks, which the Zero Spam settings page (`core/admin/class-settings.php`) fires. You can use them too.

| Hook | Type | Arguments | Fired |
|---|---|---|---|
| `zerospam_settings_page_top` | action | `string $subview` | At the top of every settings form view (not on Documentation, Import/Export or Error Log). |
| `zerospam_setting_field_before` | action | `string $setting_key, array $field` | Before each setting's description and control. |
| `zerospam_setting_field_after` | action | `string $setting_key, array $field` | After each setting's control. |
| `zerospam_setting_field_disabled` | filter | `bool $disabled, string $setting_key, array $field` | Before each control is rendered. Return `true` to render it disabled. |

`$field` holds the field arguments, including the resolved `value`. A disabled control gets the `disabled` attribute and an `aria-describedby` pointing to a visually hidden "Locked by the network administrator" explanation.

### What a site save stores

The settings form submits every field. On a real form submission (the posted `option_page` matches the option being saved), `Settings::settings_validation()` stores the unticked checkboxes and empty multi-selects of that module only as `false` and `[]`; any other write (imports, "Apply to All Sites", WP-CLI, REST) is stored as given.

Because `resolve_all_settings()` treats any key stored in a site's `zero-spam-{module}` option as a site override, every write to a network site's options is filtered (`Site_Admin_Overrides::filter_site_save()`, on `sanitize_option_zero-spam-{module}` at priority 20, registered on `init` on every network-site request, so it also covers `wp zerospam set` and the REST settings route):

- **Locked setting:** the submitted value is never stored. The site's previously stored value is kept unchanged, so an override made before the lock applies again if the setting is unlocked.
- **Unlocked setting, submitted value equals the network value:** the key is removed, so the site keeps following the network default. An unticked checkbox equals `disabled`, empty values equal each other, numbers equal their string form, and multi-select order is ignored.
- **Unlocked setting, different value:** stored as a site override.
- **Setting the network has not configured:** stored as on a single site.

`blocked_email_domains` lives in its own option, `zerospam_blocked_email_domains`; while it is locked, `pre_update_option_zerospam_blocked_email_domains` keeps the stored value whichever path writes it.

Writes made inside `switch_to_blog()` (for example "Apply to All Sites") are not filtered.

On a network site, a site-scope `PATCH zero-spam/v1/settings` that includes a locked key is refused as a whole with `403 rest_setting_locked` (`data.locked_keys` lists them) and writes nothing. A value equal to the network default succeeds and is reported as `changes[key].source = "network_default"` (no override stored); other changes report `source = "site"`. `wp zerospam set` and `wp zerospam update-blocked-domains` print a warning and store nothing for a locked key.

## Advanced Use Cases

### 1. Conditional Network Enforcement

```php
/**
 * Only enforce network settings during business hours
 */
add_filter('zerospam_settings', function($settings) {
    $current_hour = (int) date('G');
    
    // During business hours (9-5), use strict network settings
    if ($current_hour >= 9 && $current_hour <= 17) {
        return $settings; // Use network settings as-is
    }
    
    // After hours, be more lenient
    $settings['verify_comments']['value'] = 'disabled';
    $settings['log_blocked_comments']['value'] = 'disabled';
    
    return $settings;
}, 300); // High priority to override network settings
```

### 2. Site-Specific Exceptions

```php
/**
 * Allow specific sites to bypass network locks
 */
add_filter('zerospam_settings', function($settings) {
    $exempt_sites = [2, 5, 8]; // Site IDs that can ignore locks
    
    if (in_array(get_current_blog_id(), $exempt_sites)) {
        // For exempt sites, check if they had an override before
        foreach ($settings as $key => $config) {
            if (empty($config['module'])) {
                continue;
            }
            
            $module_settings = get_option("zero-spam-{$config['module']}", []);
            if (isset($module_settings[$key])) {
                // Restore their original override
                $settings[$key]['value'] = $module_settings[$key];
            }
        }
    }
    
    return $settings;
}, 500); // Very high priority
```

### 3. Compliance/Audit Logging

```php
/**
 * Log all network setting enforcements for compliance
 */
add_action('zerospam_network_setting_enforced', function($setting_key, $network_value, $original_value, $network_config) {
    global $wpdb;
    
    $wpdb->insert('compliance_audit_log', [
        'timestamp'    => current_time('mysql'),
        'site_id'      => get_current_blog_id(),
        'setting'      => $setting_key,
        'enforced_by'  => $network_config['updated_by'],
        'value'        => maybe_serialize($network_value),
        'action'       => 'network_enforced'
    ]);
}, 10, 4);
```

## Best Practices

### ✅ DO:

1. **Use priority > 100** when overriding network settings
2. **Use action hooks** for logging/monitoring (don't modify in actions)
3. **Check conditions** before overriding (user roles, site type, environment)
4. **Document your overrides** in code comments
5. **Test in multisite** before deploying

### ❌ DON'T:

1. **Don't use priority 999+** (blocks other plugins)
2. **Don't modify settings in action hooks** (use filters)
3. **Don't override without checking** `is_multisite()`
4. **Don't assume keys exist** (always check `isset()`)
5. **Don't hardcode site IDs** (use constants or options)

## Testing Your Extensions

```php
/**
 * Test helper: Display current setting sources
 */
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $settings = \ZeroSpam\Core\Settings::get_settings();
    $network_settings_manager = new \ZeroSpam\Includes\Network_Settings();
    
    echo '<div class="notice notice-info"><pre>';
    foreach ($settings as $key => $config) {
        if (empty($config['module'])) {
            continue;
        }
        
        $is_locked = $network_settings_manager->is_locked($key);
        $is_default = $network_settings_manager->is_using_default($key);
        $source = $is_locked ? 'LOCKED' : ($is_default ? 'NETWORK' : 'OVERRIDE');
        
        printf("%s: %s [%s]\n", $key, $config['value'], $source);
    }
    echo '</pre></div>';
});
```

## Common Questions

### Q: What happens if I use priority 50?
**A:** Your code runs BEFORE network settings (priority 100), so network settings will override your changes. Use priority > 100.

### Q: Can I completely disable network management?
**A:** There is no constant or setting for that. Network management is active whenever the plugin is network-activated on a multisite install. If one site must ignore network values, add a `zerospam_settings` filter after the network layer (priority above 100) that puts that site's own stored values back:
```php
add_filter('zerospam_settings', function($settings) {
    if (get_current_blog_id() !== 5) {
        return $settings;
    }
    foreach ($settings as $key => $setting) {
        $stored = get_option('zero-spam-' . $setting['module'], []);
        if (isset($stored[$key])) {
            $settings[$key]['value'] = $stored[$key];
        }
    }
    return $settings;
}, 200);
```
Locked values are still enforced on save (settings page, REST and WP-CLI), so this only affects what the site reads.

### Q: How do I override just for one site?
**A:** Check `get_current_blog_id()` in your filter:
```php
add_filter('zerospam_settings', function($settings) {
    if (get_current_blog_id() === 5) {
        // Override for site 5 only
    }
    return $settings;
}, 200);
```

### Q: Can I add my own network settings?
**A:** Yes! Use the same `zerospam_settings` filter at priority < 100:
```php
add_filter('zerospam_settings', function($settings) {
    $settings['my_custom_setting'] = [
        'title' => 'My Setting',
        'module' => 'custom',
        'type' => 'checkbox',
        'value' => get_option('my_custom_value', 'enabled')
    ];
    return $settings;
}, 10); // Before network management
```

---

## Storage, the settings schema and REST (6.0)

- **One store.** Network values live in the site option `zerospam_network_settings` (`settings` → key → `value`, `locked`, `updated_by`, `updated_at`). The REST route `zero-spam/v1/settings` used a second option, `zero-spam-network-zerospam`, before 6.0; the `v6_network_store` migration merges it once per network (store values win, merged keys are unlocked) and leaves the old option in place, unread and unwritten, so a downgrade to 5.7.x still finds it. It is removed in 6.1, together with the `zerospam_backup_5x` copy of the 5.x settings.
- **Precedence everywhere.** `Network_Settings::resolve_all_settings()`, `Settings_Resolver`, `Core\Settings_Schema` and the REST routes all apply: locked network value → site override → network default → (license only) `ZEROSPAM_LICENSE_KEY` → plugin default. REST reports the source as `locked`, `site`, `network`, `constant` or `default`.
- **Optional registry keys.** A setting added with `zerospam_settings` may carry `default`, `secret`, `lockable`, `area`, `group`, `depends_on` (`array( 'key' => …, 'value' => … )`) and `order`. `lockable => false` keeps it off the lock switches; every type but `html` is lockable by default. `secret => true` makes it write-only over REST: reads return `{ set, source, hint }`, and on a network site `{ set, source }` only when the value comes from the network or a constant.
- **Audit.** A `scope=network` PATCH of `zero-spam/v1/settings` keeps each key's lock and adds one `wpzerospam_network_audit` entry per changed key, as the acting user, with secrets masked.
- **Schema.** `GET zero-spam/v1/settings/schema` (site administrators) returns every section and setting with these keys plus `locked`, `source` and the value; `Settings_Schema::get()` gives the same in PHP, built once per request.

## The 6.0 network page, templates and network-wide blocks

- **Page.** With the 6.0 admin on, network administrators manage everything on `admin.php?page=zero-spam-network` (network admin), in eight tabs (`&tab=overview|settings|templates|audit|comparison|io|notifications|stats`). It uses the routes below; site administrators get 403 on all of them.
- **Settings tab.** Every setting a network can lock (any type but `html`, unless its registry entry says `lockable => false`) is listed, grouped as in the site admin. Values are saved with `PATCH zero-spam/v1/settings?scope=network` (locks are kept); locks with `POST network-settings/{key}/lock|unlock`. Locking a secret the network does not set locks it empty. An extension's settings appear automatically, under Integrations unless their entry sets `area`/`group`.
- **Templates.** Built-in templates use real setting keys (6.0.0). `POST network-settings/templates` takes `name`, `slug`, `description` and `settings` (values checked like `PATCH /settings`; secrets and help text are refused); `PUT network-settings/templates/{slug}` changes a custom template; `POST templates/{slug}/apply` takes `scope` (`network` or `sites`), `sites` (IDs; empty = every site, at most 1,000), `force` (replace values sites changed) and `lock`. Locked settings are never written to sites.
- **Audit.** `GET network-settings/audit?page=1&per_page=25` (filters `action_type`, `setting_key`, `user_id`, `site_id`, `after`, `before`) answers with `X-WP-Total`; each entry has a plain-language `summary`. Actions: `set`, `lock`, `unlock`, `apply`, `bulk`, `template`, `import`, `reset`, `block`, `unblock`, `migrate`. Record your own with `( new \ZeroSpam\Includes\Network_Settings_Manager() )->record( $action, $key, $old, $new )` (secrets are masked).
- **Network-wide blocks.** `GET|POST zero-spam/v1/network/blocks` and `DELETE network/blocks/{id}` (with an undo token) manage addresses blocked on every site (`{base_prefix}wpzerospam_network_blocked`). `Access::check_blocked()` applies them after the site's own blocks; the log reason is `blocked_network`. A site's IP Allowlist still lets an address in. Sites read them with `GET rules/network-blocks`. They are enforced by the PHP check only: they are never written to `.htaccess`, also on sites whose block method is `.htaccess` (the site's own blocks are still written there).
- **Apply to all sites.** `POST network-settings/apply-all` takes `mode` (and `force`, `sites`):
  - `follow` (since 6.0.0; what the 6.0 network page's "Apply to all sites" sends): each site drops its own values of the unlocked settings the network sets, so it follows the network value again, and every later change to it. Locked settings are not touched (a site's earlier value applies again if the setting is unlocked).
  - `all` (default), `locked_only`, `defaults_only` (unchanged from 5.x; what the legacy network settings page and `wp zerospam network-settings apply` use): copy the network values into each site's own settings (`all` skips values a site changed unless `force`; `locked_only` writes locked settings only; `defaults_only` writes settings a site does not store). A copied value is a site value: the site no longer follows later network changes to it.
  The legacy page's button keeps its 5.x behaviour until the 6.0 cutover.

## Support

For questions or issues with extensibility:
1. Check filter priority (must be > 100)
2. Verify `is_multisite()` returns `true`
3. Test with all other plugins disabled
4. Check error logs for PHP warnings

## More Examples

The examples above are the complete set shipped with the plugin. The in-admin documentation (*Settings → Zero Spam → Documentation*, "Network Settings" and "REST API" tabs) covers the network pages, locked settings and the REST responses, including the `rest_setting_locked` error and the `source` field.

---

**Version:** 5.7.0+  
**Last Updated:** 2026-01-24  
**Priority Range:** 200-500 (recommended for custom overrides)
