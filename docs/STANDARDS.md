# Better Builds — Licensing, Gating & Entitlements Standard

> **This document is authoritative.**
> All Better Builds plugins (Base and Pro) must follow this standard.

---

## 1. Purpose

This document defines the **shared system** used across all Better Builds plugins for:

* Feature gating (Base vs Pro behavior)
* Pro enablement
* Licensing and entitlements (via Better Builds Core)
* Bundle-first pricing with future individual plugin support

This system ensures:

* Base plugins remain fully functional on WP.org
* Pro plugins add value cleanly
* Licensing logic is centralized and future-proof
* All plugins behave consistently

---

## 2. Core Principles

1. **Feature gating ≠ licensing**

   * Plugins only ask “is this feature enabled?”
   * Plugins never decide *why* it’s enabled.

2. **Better Builds Core is the license authority**

   * Plugins do not store or validate licenses themselves.
   * Core determines entitlements and exposes them via filters.

3. **Bundle-first, individual later**

   * One license unlocks all Pro plugins initially.
   * Architecture supports individual plugin licenses later without refactors.

4. **Base plugins never depend on Core**

   * Core is optional.
   * Base must function independently.

---

## 3. Terminology

| Term        | Meaning                                |
| ----------- | -------------------------------------- |
| Base Plugin | Free plugin (WP.org)                   |
| Pro Plugin  | Paid add-on enabling advanced features |
| Feature     | A specific gated capability            |
| Entitlement | Permission granted by a license        |
| Bundle      | License granting all entitlements      |

---

## 4. Shared Gating API (Required in Every Base Plugin)

Each Base plugin **must include** a shared helper file
(e.g. `inc/bb-features.php`).

### Plugin Slug

Each plugin defines:

```php
define( 'BB_PLUGIN_SLUG', 'bb-experiments' );
```

---

### 4.1 `bb_is_pro_enabled()`

Determines whether Pro functionality is active **for this plugin**.

```php
function bb_is_pro_enabled(): bool {
	return (bool) apply_filters(
		'bb_is_pro_enabled',
		false,
		BB_PLUGIN_SLUG
	);
}
```

* Defaults to `false`
* Pro plugins or Core may enable it
* Base code must never assume Pro is enabled

---

### 4.2 `bb_feature_enabled( string $feature_key )`

Determines whether a specific feature is enabled.

```php
function bb_feature_enabled( string $feature_key ): bool {
	if ( ! bb_is_pro_enabled() ) {
		return false;
	}

	return (bool) apply_filters(
		'bb_feature_enabled',
		false,
		BB_PLUGIN_SLUG,
		$feature_key
	);
}
```

* Used everywhere:

  * Admin UI
  * Block registration
  * REST routes
  * Runtime logic

---

### 4.3 Optional helper

```php
function bb_entitlements(): array {
	return (array) apply_filters(
		'bb_entitlements',
		[],
		BB_PLUGIN_SLUG
	);
}
```

---

## 5. Standard Feature Keys

All plugins must use **shared feature keys**.

### Common keys

* `integrations`
* `block_experiments`
* `multi_variant`
* `funnels`
* `revenue_goals`
* `segmentation`
* `guardrails`

### Plugin-specific keys (examples)

* `advanced_validation`
* `progressive_profiling`
* `revenue_attribution`
* `webhooks`
* `custom_fields`

Rules:

* `snake_case`
* lowercase
* stable once released

---

## 6. Enforcement Rules (Mandatory)

Every Pro feature must be gated in **all three layers**:

### 1. Admin UI

* Hide Pro-only controls unless enabled
* Show upgrade messaging instead

### 2. Registration Layer

* Blocks, REST routes, integrations, cron jobs **must not register** unless enabled

### 3. Runtime

* No Pro logic executes unless enabled
* Prevent silent performance regressions

---

## 7. Pro Plugin Behavior (No Licensing Yet)

During early stages, Pro plugins may enable features unconditionally:

```php
add_filter( 'bb_is_pro_enabled', function( $enabled, $slug ) {
	return $slug === 'bb-experiments';
}, 10, 2 );

add_filter( 'bb_feature_enabled', function( $enabled, $slug, $feature ) {
	if ( $slug !== 'bb-experiments' ) {
		return $enabled;
	}

	$features = [
		'block_experiments',
		'integrations',
		'multi_variant',
	];

	return in_array( $feature, $features, true );
}, 10, 3 );
```

No license checks belong here.

---

## 8. Better Builds Core — Licensing & Entitlements

### Responsibilities

Better Builds Core:

* Stores license key
* Validates license remotely
* Caches verification results
* Determines entitlements
* Exposes entitlements via filters

Plugins only consume filters.

---

### License Model (Initial)

**One bundle license**:

* `bb_bundle_all`
* Unlocks all Pro plugins and features

Later:

* Individual entitlements may be added per plugin
* Bundle remains authoritative

---

### Entitlement → Feature Mapping (Examples)

#### bb-experiments

| Feature             | Entitlement |
| ------------------- | ----------- |
| `block_experiments` | bundle      |
| `integrations`      | bundle      |
| `funnels`           | bundle      |
| `multi_variant`     | bundle      |
| `revenue_goals`     | bundle      |
| `segmentation`      | bundle      |
| `guardrails`        | bundle      |

#### bb-hubspot-forms

| Feature                 | Entitlement |
| ----------------------- | ----------- |
| `integrations`          | bundle      |
| `advanced_validation`   | bundle      |
| `progressive_profiling` | bundle      |
| `advanced_redirects`    | bundle      |

(Extend per plugin.)

---

## 9. Core Filter Contract (Authoritative)

Better Builds Core must implement:

```php
add_filter( 'bb_is_pro_enabled', function( $enabled, $slug ) {
	return bb_core_entitlement_exists( 'bb_bundle_all' )
		|| bb_core_entitlement_exists( "{$slug}_pro" );
}, 10, 2 );

add_filter( 'bb_feature_enabled', function( $enabled, $slug, $feature ) {
	return bb_core_feature_is_entitled( $slug, $feature );
}, 10, 3 );
```

---

## 10. Required File in Every Pro Plugin

Each Pro plugin repo must include:

**`PRO_GATING.md`**

```md
This Pro plugin enables features using the Better Builds
feature gating standard.

- No licensing logic lives here.
- Licensing is owned by Better Builds Core.
- Features are enabled via shared filters.
```

---

## 11. Summary (Non-Negotiable)

* All plugins use the same gating functions
* Licensing is centralized in Core
* Bundle-first, individual later
* Base never breaks
* Pro never leaks
* Cursor always has one source of truth