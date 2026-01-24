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
**A:** Yes, but not recommended. Use priority 999 with a constant check:
```php
if (defined('DISABLE_ZEROSPAM_NETWORK_MANAGEMENT') && DISABLE_ZEROSPAM_NETWORK_MANAGEMENT) {
    // Your override code
}
```

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

## Support

For questions or issues with extensibility:
1. Check filter priority (must be > 100)
2. Verify `is_multisite()` returns `true`
3. Test with all other plugins disabled
4. Check error logs for PHP warnings

## Examples Repository

Find more examples at:
```
wp-content/plugins/zero-spam/examples/network-settings-extensions/
```

---

**Version:** 5.6.0+  
**Last Updated:** 2026-01-24  
**Priority Range:** 200-500 (recommended for custom overrides)
